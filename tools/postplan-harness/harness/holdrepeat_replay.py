"""Replay the repeat-hold detector over a corpus of past post-plan runs.

Pure counting over `<out_dir>/live-*/result.json`; no record files are read or written. The
report says how many held runs a strict (full normalised hold set) match would have flagged
against the structural subset production uses. Fingerprints are not stored in result.json,
so the replay cannot count declines. Each structural repeat after a would-DM is the upper
bound of what the Phase 3 pre-flight could decline.
"""
from __future__ import annotations

import argparse
import glob
import json
import os
import re
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import holdrepeat

RUN_DIR_RE = re.compile(r"^live-(.+)-(\d{8}-\d{6})-(\d+)$")


def load_runs(out_dir: str) -> list[dict]:
    """One {slug, ts, armed, conditions} per readable live-*/result.json."""
    runs = []
    for path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        m = RUN_DIR_RE.match(os.path.basename(os.path.dirname(path)))
        if not m:
            continue
        try:
            with open(path, encoding="utf-8") as fh:
                arm = json.load(fh).get("arm")
        except (OSError, ValueError, AttributeError):
            continue
        if not isinstance(arm, dict):
            continue
        runs.append({"slug": m.group(1), "ts": m.group(2),
                     "armed": bool(arm.get("armed")),
                     "conditions": arm.get("conditions") or []})
    return runs


def _in_window(ts: str, since: str, until: str) -> bool:
    day = ts[:8]
    return (not since or day >= since) and (not until or day <= until)


def replay(runs: list[dict], since: str = "", until: str = "") -> dict:
    by_slug: dict[str, list[dict]] = {}
    for run in runs:
        by_slug.setdefault(run["slug"], []).append(run)

    held = strict = structural = would_dm = 0
    in_3plus = caught_3plus = 0
    for slug_runs in by_slug.values():
        slug_runs.sort(key=lambda r: r["ts"])
        window_count = sum(1 for r in slug_runs if _in_window(r["ts"], since, until))
        prev_set = None      # full hold set of the previous run if it was held, else None
        prev_key = None      # structural key of the open record, None when no record
        dm_done = False
        for run in slug_runs:
            counted = _in_window(run["ts"], since, until)
            conds = run["conditions"]
            is_held = not run["armed"]
            full = holdrepeat.hold_set(conds) if is_held else None
            key = holdrepeat.structural_key(conds) if is_held else ""

            is_strict = is_held and full == prev_set
            is_struct = is_held and key != "" and key == prev_key
            first_dm = is_struct and not dm_done

            if counted:
                held += is_held
                strict += is_strict
                structural += is_struct
                would_dm += first_dm
                if window_count >= 3:
                    in_3plus += 1
                    caught_3plus += is_struct

            prev_set = full
            if not is_held or key == "":
                prev_key, dm_done = None, False
            elif key != prev_key:
                prev_key, dm_done = key, False
            elif first_dm:
                dm_done = True

    return {"held_runs": held, "strict_repeats": strict,
            "structural_repeats": structural, "would_dm": would_dm,
            "repeat_runs_in_slugs_with_3plus": in_3plus,
            "caught_in_slugs_with_3plus": caught_3plus}


def _fid_str(value) -> str:
    return value if isinstance(value, str) else ""


def load_owner_only_runs(out_dir: str, since: str = "20261110") -> list[dict]:
    """Runs for the owner-only replay: one dict per live-*/result.json in the window.

    Only slugs with 2 or more runs are kept. `fp` pairs plan_hash and diff_id, the replay's
    stand-in for plan+diff, since result.json stores no fingerprint. `owner_refire` marks a
    run that carries an owner-approval entry, the stand-in for a `--state-changed` re-fire.
    """
    runs = []
    for path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        m = RUN_DIR_RE.match(os.path.basename(os.path.dirname(path)))
        if not m or not _in_window(m.group(2), since, ""):
            continue
        try:
            with open(path, encoding="utf-8") as fh:
                data = json.load(fh)
            arm = data.get("arm")
            fid = data.get("fidelity") or {}
            if not isinstance(arm, dict) or not isinstance(fid, dict):
                continue
            plan_hash, diff_id = _fid_str(fid.get("plan_hash")), _fid_str(fid.get("diff_id"))
            owner_scope = [{"title": e.get("title"), "source": e.get("source")}
                           for e in fid.get("kept_owner_scope") or [] if isinstance(e, dict)]
            runs.append({
                "slug": m.group(1), "ts": m.group(2), "armed": bool(arm.get("armed")),
                "terminal": data.get("terminal"),
                "conditions": [{"number": c.get("number"), "name": c.get("name"),
                                "reason": c.get("reason") or "", "blocked": bool(c.get("blocked"))}
                               for c in arm.get("conditions") or [] if isinstance(c, dict)],
                "fp": f"{plan_hash}:{diff_id}" if plan_hash and diff_id else "",
                "owner_refire": any(e["source"] == "owner-approval" for e in owner_scope),
                "fidelity": {
                    "review_items": [{"verdict": i.get("verdict"), "blocking": i.get("blocking") or []}
                                     for i in fid.get("review_items") or [] if isinstance(i, dict)],
                    "kept_unplanned": [t for t in fid.get("kept_unplanned") or []
                                       if isinstance(t, str)],
                    "kept_owner_scope": owner_scope,
                    "rounds": [{"outcome": r.get("outcome")}
                               for r in fid.get("rounds") or [] if isinstance(r, dict)],
                },
            })
        except (OSError, ValueError, AttributeError, TypeError):
            continue
    counts: dict[str, int] = {}
    for run in runs:
        counts[run["slug"]] = counts.get(run["slug"], 0) + 1
    return [r for r in runs if counts[r["slug"]] >= 2]


def replay_owner_only(runs: list[dict]) -> dict:
    """Count what the owner-only decline and the body-only skip would have done to past runs.

    `declined_then_armed` and `skipped_then_cleared` must stay 0: each one is a run the new
    rules would have stopped although the old path went on to arm or clear it.
    """
    import tempfile

    by_slug: dict[str, list[dict]] = {}
    for run in runs:
        by_slug.setdefault(run["slug"], []).append(run)
    out = {"runs": len(runs), "declines": 0, "declined_then_armed": 0,
           "body_only_skips": 0, "skipped_then_cleared": 0}
    with tempfile.TemporaryDirectory() as sd:
        for slug, slug_runs in by_slug.items():
            slug_runs.sort(key=lambda r: r["ts"])
            for run in slug_runs:
                fid = run.get("fidelity") or {}
                fp = run.get("fp") or ""
                if (not run.get("owner_refire")
                        and holdrepeat.owner_only_decline(sd, slug, fp)):
                    out["declines"] += 1
                    out["declined_then_armed"] += bool(run["armed"])
                obs = holdrepeat.observe(sd, slug, armed=bool(run["armed"]),
                                         conditions=run["conditions"], fingerprint=fp, pr=None,
                                         now=run["ts"], fidelity=fid)
                if getattr(obs, "action", "") == "repeat-dm":
                    holdrepeat.mark_dm_sent(sd, slug, obs.key)
                items = fid.get("review_items") or []
                for i, rnd in enumerate(fid.get("rounds") or []):
                    if rnd.get("outcome") != "body-only" or i >= len(items):
                        continue
                    if holdrepeat.kept_scope_only(items[i].get("blocking"),
                                                  fid.get("kept_unplanned")):
                        out["body_only_skips"] += 1
                        nxt = items[i + 1].get("verdict") if i + 1 < len(items) else None
                        out["skipped_then_cleared"] += (nxt is not None and nxt != "NOT READY")
    return out


def main(argv=None) -> int:
    default_out = os.path.join(os.path.dirname(os.path.dirname(os.path.abspath(__file__))), "out")
    ap = argparse.ArgumentParser(prog="harness.holdrepeat_replay")
    ap.add_argument("--out-dir", default=default_out)
    ap.add_argument("--since", default="", help="YYYYMMDD; earlier runs still prime state")
    ap.add_argument("--until", default="", help="YYYYMMDD")
    args = ap.parse_args(argv)
    print(json.dumps(replay(load_runs(args.out_dir), args.since, args.until), indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
