"""Readout for the closer A/B: per-arm cost, time to merge, human touches, reverts.

Logic only. `bin/postplan-ab-readout` is the thin wrapper. The population is the enrollment
file; the outcome log (`ab-outcomes.jsonl`, one line per run) is folded to one record per PR.

Human-touch rule (the measurement design): a commit, issue comment or review counts when it is
later than the PR's first post-plan run, falls inside NO run window, and its author is not a
bot. A run window is `[run_started, run_finished + 120 s]`. The harness and the closer act
through the operator's own `gh` and git identity, so only the windows tell a harness action
from a human one.
"""
from __future__ import annotations

import datetime as dt
import json
import os
import statistics
import subprocess
import sys

from harness import ab_arm
from harness.state import TerminalState

REPO = "a-jay85/IBL5"
WINDOW_PAD_S = 120
REVERT_DAYS = 14
BOT_LOGINS = ("github-actions", "copilot-pull-request-reviewer")
HARNESS_MARKERS = ("<!-- pr-ready-verdict", "<!-- postplan-gate-hold")
USAGE = ("usage: postplan-ab-readout [--state-dir <path>] [--rows] [--json] [--offline]\n")


# ── time + bot helpers ───────────────────────────────────────────────────────

def parse_ts(value) -> dt.datetime | None:
    if not isinstance(value, str) or not value:
        return None
    try:
        t = dt.datetime.fromisoformat(value.replace("Z", "+00:00"))
    except ValueError:
        return None
    return t if t.tzinfo else t.replace(tzinfo=dt.timezone.utc)


def is_bot(login) -> bool:
    login = login or ""
    return login.endswith("[bot]") or login in BOT_LOGINS


# ── fold ─────────────────────────────────────────────────────────────────────

def read_outcome_rows(state_dir: str) -> list[dict]:
    rows = []
    try:
        with open(os.path.join(state_dir, ab_arm.OUTCOME_BASENAME), encoding="utf-8",
                  errors="replace") as fh:
            for raw in fh:
                raw = raw.strip()
                if not raw:
                    continue
                try:
                    row = json.loads(raw)
                except ValueError:
                    continue
                if isinstance(row, dict) and row.get("schema") == ab_arm.OUTCOME_SCHEMA:
                    rows.append(row)
    except OSError:
        pass
    return rows


def fold_rows(enrolled: dict, rows: list[dict]) -> list[dict]:
    """One record per enrolled slug. Rows for other slugs are ignored."""
    by_slug: dict[str, list[dict]] = {}
    for row in rows:
        slug = row.get("slug")
        if slug in enrolled:
            by_slug.setdefault(slug, []).append(row)
    out = []
    for slug in sorted(enrolled):
        entry = enrolled[slug] if isinstance(enrolled[slug], dict) else {}
        mine = by_slug.get(slug, [])
        starts = [(parse_ts(r.get("run_started")), r) for r in mine]
        starts = [(t, r) for t, r in starts if t]
        windows = []
        for r in mine:
            a, b = parse_ts(r.get("run_started")), parse_ts(r.get("run_finished"))
            if a and b:
                windows.append((a, b + dt.timedelta(seconds=WINDOW_PAD_S)))
        finished = [(parse_ts(r.get("run_finished")), r) for r in mine]
        finished = [(t, r) for t, r in finished if t]
        prs = [r.get("pr") for r in mine if r.get("pr") is not None]
        pr = prs[-1] if prs else entry.get("pr")
        out.append({
            "slug": slug, "arm": entry.get("arm"), "pr": pr, "runs": len(mine),
            "cost_usd": round(sum(float(r.get("llm_cost_usd") or 0) for r in mine), 4),
            "first_start": min((t for t, _ in starts), default=None),
            "windows": windows,
            "last_terminal": (max(finished, key=lambda p: p[0])[1].get("terminal")
                              if finished else None),
        })
    return out


# ── human touches ────────────────────────────────────────────────────────────

def _in_window(ts: dt.datetime, windows) -> bool:
    return any(a <= ts <= b for a, b in windows)


def _counts(ts, first_start, windows) -> bool:
    return bool(ts and first_start and ts > first_start and not _in_window(ts, windows))


def _harness_body(body) -> bool:
    body = body or ""
    return any(m in body for m in HARNESS_MARKERS)


def count_human_touches(pr: dict, first_start, windows) -> int:
    n = 0
    for c in pr.get("commits") or []:
        # The conflict autofix (`postplan-fix --headless`) commits a master merge under the
        # operator's identity outside any run window. It is automation, not a human touch.
        if (c.get("messageHeadline") or "").startswith("Merge "):
            continue
        logins = [a.get("login") for a in (c.get("authors") or []) if isinstance(a, dict)]
        human = (not logins) or any(not is_bot(lg) for lg in logins)
        if human and _counts(parse_ts(c.get("committedDate")), first_start, windows):
            n += 1
    for c in pr.get("comments") or []:
        login = (c.get("author") or {}).get("login")
        if is_bot(login) or _harness_body(c.get("body")):
            continue
        if _counts(parse_ts(c.get("createdAt")), first_start, windows):
            n += 1
    for r in pr.get("reviews") or []:
        login = (r.get("author") or {}).get("login")
        if is_bot(login) or _harness_body(r.get("body")):
            continue
        if _counts(parse_ts(r.get("submittedAt")), first_start, windows):
            n += 1
    return n


# ── per-PR enrichment ────────────────────────────────────────────────────────

def enrich(rec: dict, *, fetch_pr, git_log, now: dt.datetime) -> dict:
    rec = dict(rec)
    rec.update(merged=None, time_to_merge_h=None, human_touches=None,
               armed_without_human=None, revert=None)
    pr_no = rec.get("pr")
    data = fetch_pr(pr_no) if pr_no else None
    if not isinstance(data, dict):
        return rec
    merged_at = parse_ts(data.get("mergedAt")) if data.get("state") == "MERGED" else None
    rec["merged"] = bool(merged_at)
    rec["human_touches"] = count_human_touches(data, rec["first_start"], rec["windows"])
    if not merged_at:
        return rec
    if rec["first_start"]:
        rec["time_to_merge_h"] = round(
            (merged_at - rec["first_start"]).total_seconds() / 3600, 3)
    rec["armed_without_human"] = (rec["last_terminal"] == TerminalState.SHIPPED_ARMED.value
                                  and rec["human_touches"] == 0)
    until = merged_at + dt.timedelta(days=REVERT_DAYS)
    needles = [((data.get("mergeCommit") or {}).get("oid") or ""),
               f"Reverts {REPO}#{pr_no}"]
    hits = git_log(merged_at.isoformat(), until.isoformat(), [n for n in needles if n])
    if hits:
        rec["revert"] = "reverted"
    elif now < until:
        rec["revert"] = "pending"
    else:
        rec["revert"] = "clean"
    return rec


# ── summary ──────────────────────────────────────────────────────────────────

def _med(values):
    return round(statistics.median(values), 4) if values else None


def summarize(records: list[dict], *, offline: bool) -> dict:
    out = {}
    for arm in ab_arm.ARMS:
        recs = [r for r in records if r.get("arm") == arm]
        costs = [r["cost_usd"] for r in recs]
        s = {"n": len(recs), "cost_median_usd": _med(costs),
             "cost_total_usd": round(sum(costs), 4)}
        if offline:
            for k in ("merged", "unmerged", "time_to_merge_median_h", "human_touches_median",
                      "human_touches_total", "armed_without_human", "armed_without_human_rate",
                      "reverted", "pending", "clean"):
                s[k] = "n/a"
        else:
            merged = [r for r in recs if r["merged"]]
            touches = [r["human_touches"] for r in recs if r["human_touches"] is not None]
            awh = sum(1 for r in recs if r["armed_without_human"])
            s.update(
                merged=len(merged), unmerged=len(recs) - len(merged),
                time_to_merge_median_h=_med([r["time_to_merge_h"] for r in merged
                                             if r["time_to_merge_h"] is not None]),
                human_touches_median=_med(touches), human_touches_total=sum(touches),
                armed_without_human=awh,
                armed_without_human_rate=round(awh / len(recs), 4) if recs else None,
                reverted=sum(1 for r in recs if r["revert"] == "reverted"),
                pending=sum(1 for r in recs if r["revert"] == "pending"),
                clean=sum(1 for r in recs if r["revert"] == "clean"))
        out[arm] = s
    return out


def build_readout(state_dir: str, *, fetch_pr, git_log, now: dt.datetime,
                  offline: bool = False) -> dict:
    enrolled = ab_arm.read_enrollment(state_dir)
    records = fold_rows(enrolled, read_outcome_rows(state_dir))
    if not offline:
        records = [enrich(r, fetch_pr=fetch_pr, git_log=git_log, now=now) for r in records]
    else:
        for r in records:
            r.update(merged=None, time_to_merge_h=None, human_touches=None,
                     armed_without_human=None, revert=None)
    return {"enrolled": len(enrolled), "records": records,
            "summary": summarize(records, offline=offline)}


# ── live seams + CLI ─────────────────────────────────────────────────────────

def repo_root() -> str:
    return os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(
        os.path.abspath(__file__)))))


def live_fetch_pr(pr: int):
    try:
        proc = subprocess.run(
            ["gh", "pr", "view", str(pr), "-R", REPO, "--json",
             "state,mergedAt,mergeCommit,title,commits,comments,reviews"],
            capture_output=True, text=True, timeout=60)
        return json.loads(proc.stdout) if proc.returncode == 0 else None
    except (OSError, ValueError, subprocess.SubprocessError):
        return None


def live_git_log(since: str, until: str, needles: list[str]) -> list[str]:
    argv = ["git", "-C", repo_root(), "log", "origin/master", f"--since={since}",
            f"--until={until}", "-F"]
    for n in needles:
        argv.append(f"--grep={n}")
    argv.append("--format=%H")
    try:
        proc = subprocess.run(argv, capture_output=True, text=True, timeout=60)
    except (OSError, subprocess.SubprocessError):
        return []
    return [ln for ln in proc.stdout.splitlines() if ln.strip()] if proc.returncode == 0 else []


def _jsonable(rec: dict) -> dict:
    out = dict(rec)
    out["first_start"] = rec["first_start"].isoformat() if rec.get("first_start") else None
    out["windows"] = [[a.isoformat(), b.isoformat()] for a, b in rec.get("windows", [])]
    return out


def parse_args(argv):
    opts = {"state_dir": None, "rows": False, "json": False, "offline": False}
    i = 0
    while i < len(argv):
        a = argv[i]
        if a == "--state-dir":
            if i + 1 >= len(argv) or argv[i + 1].startswith("--"):
                return None
            opts["state_dir"] = argv[i + 1]
            i += 1
        elif a == "--rows":
            opts["rows"] = True
        elif a == "--json":
            opts["json"] = True
        elif a == "--offline":
            opts["offline"] = True
        else:
            return None  # unknown flag
        i += 1
    return opts


def main(argv=None) -> int:
    opts = parse_args(list(sys.argv[1:] if argv is None else argv))
    if opts is None:
        sys.stderr.write(USAGE)
        return 2
    state_dir = opts["state_dir"] or os.path.join(
        repo_root(), "tools", "postplan-harness", "out", "state")
    if not ab_arm.read_enrollment(state_dir):
        print("no enrolled PRs")
        return 0
    result = build_readout(state_dir, fetch_pr=live_fetch_pr, git_log=live_git_log,
                           now=dt.datetime.now(dt.timezone.utc), offline=opts["offline"])
    if opts["rows"]:
        for rec in result["records"]:
            print(json.dumps(_jsonable(rec), sort_keys=True))
        return 0
    if opts["json"]:
        print(json.dumps(result["summary"], indent=2, sort_keys=True))
        return 0
    for arm, s in result["summary"].items():
        print(f"{arm}: n={s['n']} merged={s['merged']} unmerged={s['unmerged']} "
              f"$/PR median={s['cost_median_usd']} total={s['cost_total_usd']} "
              f"ttm_h median={s['time_to_merge_median_h']} "
              f"touches median={s['human_touches_median']} total={s['human_touches_total']} "
              f"armed-without-human={s['armed_without_human']} "
              f"rate={s['armed_without_human_rate']} "
              f"reverts={s['reverted']}/{s['pending']}/{s['clean']} (reverted/pending/clean)")
    return 0


if __name__ == "__main__":
    sys.exit(main())
