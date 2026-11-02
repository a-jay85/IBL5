#!/usr/bin/env python3
"""Phase 5.5 fidelity rerun report.

Reads `out/live-*/audit.log` and `result.json` and prints, for a pre window and a post
window, how many full Opus reviews each PR cost. Read-only, standard library only, and
independent of `harness/`, so it runs under `python3 -I`.

    fidelity_rerun_report.py [--out-dir DIR] [--pre-window START..END]
                             [--post-window START..END] [--json]

Windows are half-open `[START, END)` ISO 8601 instants with a trailing `Z`. An empty END
(`2026-10-27T18:33:33Z..`) means now.
"""
from __future__ import annotations

import argparse
import json
import re
import sys
from collections import Counter
from datetime import datetime, timezone
from pathlib import Path

DEFAULT_PRE = "2026-10-13T00:00:00Z..2026-10-27T18:33:33Z"
DEFAULT_POST = "2026-10-27T18:33:33Z.."
ISO_Z = "%Y-%m-%dT%H:%M:%SZ"

LINE = re.compile(r"^\[\d{2}:\d{2}:\d{2}\] phase5\.5 fidelity: (?P<rest>.*)$")
DECLINED = re.compile(
    r"^review: full \(carry-forward declined: (?P<reason>[a-z0-9-]+)\)"
    r"(?: diff-id=(?P<diff>[0-9a-f]{40}|-) own-id=(?P<own>[0-9a-f]{40}|-)"
    r" own-arm=(?P<arm>[a-z0-9-]+))?$")
CARRIED = re.compile(r"^review: carried forward \(patch-id [0-9a-f]{12}\)(?P<tail>.*)$")
DELTA = re.compile(r"^verdict=\S+ .*\breview_kind=delta\b")
FIRED_BY = re.compile(r"^\[\d{2}:\d{2}:\d{2}\] postplan: fired-by=(?P<label>[a-z][a-z0-9-]{0,39})$")
STAMP = re.compile(r"-(\d{8})-(\d{6})-\d+$")

BUCKETS = ("1", "2", "3", "4", "5-9", "10+")


class UsageError(Exception):
    pass


def parse_bound(text: str, what: str) -> datetime:
    try:
        return datetime.strptime(text, ISO_Z).replace(tzinfo=timezone.utc)
    except ValueError:
        raise UsageError(f"{what}: {text!r} is not an ISO 8601 instant ending in Z") from None


def parse_window(spec: str, name: str, now: datetime) -> tuple[datetime, datetime]:
    if ".." not in spec:
        raise UsageError(f"{name}: expected START..END, got {spec!r}")
    start_s, _, end_s = spec.partition("..")
    start = parse_bound(start_s, f"{name} start")
    end = parse_bound(end_s, f"{name} end") if end_s else now
    if not start < end:
        raise UsageError(f"{name}: start {start_s} is not before end {end_s or 'now'}")
    return start, end


def run_time(dirname: str) -> datetime | None:
    m = STAMP.search(dirname)
    if not m:
        return None
    try:
        local = datetime.strptime(m.group(1) + m.group(2), "%Y%m%d%H%M%S")
    except ValueError:
        return None
    return local.astimezone(timezone.utc)


def classify(log_text: str) -> dict:
    """Kind, decline reason, own-arm and fire source for one audit.log."""
    carried = delta = declined = None
    fired_by = None
    for raw in log_text.splitlines():
        fb = FIRED_BY.match(raw)
        if fb and fired_by is None:
            fired_by = fb.group("label")
        m = LINE.match(raw)
        if not m:
            continue
        rest = m.group("rest")
        if carried is None and (c := CARRIED.match(rest)):
            carried = c
        elif delta is None and DELTA.match(rest):
            delta = True
        elif declined is None and (d := DECLINED.search(rest)):
            declined = d
    reason = arm = None
    if declined:
        reason = declined.group("reason")
        arm = declined.group("arm") or "not-logged"
    if carried:
        tail = carried.group("tail")
        if tail.startswith(" (own-change match"):
            kind = "carried-own-change"
        elif tail.startswith(" (conflict-only delta)"):
            kind = "carried-conflict-only"
        else:
            kind = "carried"
        reason = None
    elif delta:
        kind = "delta"
    elif declined:
        kind = "full"
    else:
        kind = "no-fidelity-line"
    return {"kind": kind, "reason": reason, "arm": arm, "audit_fired_by": fired_by}


def bucket(n: int) -> str:
    if n >= 10:
        return "10+"
    if n >= 5:
        return "5-9"
    return str(n)


def summarize(runs: list[dict]) -> dict:
    per_pr = Counter(r["pr"] for r in runs)
    kinds = Counter(r["kind"] if not r["reason"] else f"{r['kind']}/{r['reason']}"
                    for r in runs)
    full = sum(1 for r in runs if r["kind"] == "full")
    delta = sum(1 for r in runs if r["kind"] == "delta")
    hist = Counter(bucket(n) for n in per_pr.values())
    return {
        "runs": len(runs),
        "prs": len(per_pr),
        "reviewer_calls": full + delta,
        "full_reviews": full,
        "full_per_pr": round(full / len(per_pr), 2) if per_pr else 0.0,
        "histogram": {b: hist[b] for b in BUCKETS if hist[b]},
        "kinds": dict(kinds.most_common()),
        "own_arm": dict(Counter(r["arm"] for r in runs
                                if r["kind"] == "full" and r["reason"] == "diff-changed")
                        .most_common()),
        "fired_by": dict(Counter(r["fired_by"] for r in runs).most_common()),
    }


def collect(out_dir: Path, windows: dict[str, tuple[datetime, datetime]]):
    runs: dict[str, list[dict]] = {name: [] for name in windows}
    skipped: Counter = Counter()
    for d in sorted(out_dir.glob("live-*")):
        if not d.is_dir():
            continue
        when = run_time(d.name)
        if when is None:
            skipped["unstamped-dir-name"] += 1
            continue
        home = next((n for n, (lo, hi) in windows.items() if lo <= when < hi), None)
        if home is None:
            continue
        result_p, audit_p = d / "result.json", d / "audit.log"
        if not result_p.is_file():
            skipped["no-result-json"] += 1
            continue
        if not audit_p.is_file():
            skipped["no-audit-log"] += 1
            continue
        try:
            result = json.loads(result_p.read_text(encoding="utf-8", errors="replace"))
        except (OSError, ValueError):
            skipped["unreadable-result-json"] += 1
            continue
        pr = result.get("pr_number") if isinstance(result, dict) else None
        if isinstance(pr, bool) or not isinstance(pr, int):
            skipped["no-integer-pr-number"] += 1
            continue
        try:
            info = classify(audit_p.read_text(encoding="utf-8", errors="replace"))
        except OSError:
            skipped["unreadable-audit-log"] += 1
            continue
        fired = result.get("fired_by")
        if not isinstance(fired, str) or not fired:
            fired = info["audit_fired_by"] or "unknown"
        runs[home].append({"pr": pr, "fired_by": fired, **info})
    return runs, skipped


def render(name: str, s: dict) -> str:
    out = [f"== {name} ==",
           f"runs: {s['runs']}  distinct PRs: {s['prs']}  "
           f"reviewer calls: {s['reviewer_calls']}  full reviews: {s['full_reviews']}",
           f"full reviews per PR: {s['full_per_pr']:.2f}",
           "runs per PR:"]
    out += [f"  {b}: {s['histogram'][b]}" for b in s["histogram"]] or ["  (none)"]
    out.append("kind/reason:")
    out += [f"  {k}: {v}" for k, v in s["kinds"].items()] or ["  (none)"]
    out.append("own-arm (full, diff-changed):")
    out += [f"  {k}: {v}" for k, v in s["own_arm"].items()] or ["  (none)"]
    out.append("fired-by:")
    out += [f"  {k}: {v}" for k, v in s["fired_by"].items()] or ["  (none)"]
    return "\n".join(out)


def main(argv: list[str] | None = None) -> int:
    default_out = Path(__file__).resolve().parent.parent / "out"
    ap = argparse.ArgumentParser(
        description="Report Phase 5.5 full-review counts per PR from harness run logs.",
        allow_abbrev=False)
    ap.add_argument("--out-dir", type=Path, default=default_out)
    ap.add_argument("--pre-window", default=DEFAULT_PRE, metavar="START..END")
    ap.add_argument("--post-window", default=DEFAULT_POST, metavar="START..END")
    ap.add_argument("--json", action="store_true")
    args = ap.parse_args(argv)
    now = datetime.now(timezone.utc)
    try:
        pre = parse_window(args.pre_window, "--pre-window", now)
        post = parse_window(args.post_window, "--post-window", now)
        if pre[0] < post[1] and post[0] < pre[1]:
            raise UsageError("--pre-window and --post-window overlap")
        if not args.out_dir.is_dir():
            raise UsageError(f"--out-dir {args.out_dir} is not a directory")
    except UsageError as e:
        print(f"error: {e}", file=sys.stderr)
        return 2
    runs, skipped = collect(args.out_dir, {"pre": pre, "post": post})
    report = {name: summarize(runs[name]) for name in ("pre", "post")}
    report["skipped"] = dict(skipped.most_common())
    if args.json:
        print(json.dumps(report, indent=2))
        return 0
    for name in ("pre", "post"):
        print(render(name, report[name]))
        print()
    print("skipped:")
    for k, v in report["skipped"].items():
        print(f"  {k}: {v}")
    if not report["skipped"]:
        print("  (none)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
