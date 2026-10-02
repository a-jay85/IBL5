"""Replay conformance.phase_omission_items over the `out/live-*` run corpus.

Replay mode runs one harness tree over every replayable run and writes JSON:
  python3 replay_phase_omission.py --harness-root <dir containing harness/> \
      [--out-dir <harness-root>/out] [--repo-root <harness-root>/../..] --json PATH

Compare mode classifies every MISSING-PHASE item that disappeared between a BEFORE
run (master's harness) and an AFTER run (the worktree's harness):
  python3 replay_phase_omission.py --compare BEFORE.json AFTER.json

A disappearance is class 1 when AFTER carries an `UNCHECKABLE-PHASE: N` note for the
phase, class 2 when it carries `NO-DIFF-PHASE: N`, and a REGRESSION otherwise.
Exit 0 when there are no regressions and nothing appeared, else 2. Stdlib only.
"""
import argparse
import glob
import json
import os
import re
import sys
from collections import Counter

_ITEM_NUM_RE = re.compile(r"^MISSING-PHASE:\s*(\d+)\b")
_AUDIT_PREFIX = "phase2 residual-phase: "


def _load(harness_root: str):
    sys.path.insert(0, os.path.abspath(harness_root))
    from harness import conformance, planfile  # noqa: E402  (tree chosen by --harness-root)
    return conformance, planfile


def _audit_items(run_dir: str) -> set[str]:
    path = os.path.join(run_dir, "audit.log")
    out: set[str] = set()
    try:
        with open(path, encoding="utf-8", errors="replace") as fh:
            for ln in fh:
                idx = ln.find(_AUDIT_PREFIX + "MISSING-PHASE:")
                if idx >= 0:
                    out.add(ln[idx + len(_AUDIT_PREFIX):].strip())
    except OSError:
        pass
    return out


def _run_check(conformance, plan, files, tracked):
    notes: list[str] = []
    try:
        items = conformance.phase_omission_items(plan, files, tracked_files=tracked, notes=notes)
    except TypeError:
        # master's signature has neither kwarg; this is what lets one script drive both trees
        notes = []
        items = conformance.phase_omission_items(plan, files)
    return items, notes


def replay(out_dir: str, harness_root: str, repo_root: str, tracked=None) -> dict:
    """Replay every `live-*/result.json` under out_dir with the harness at harness_root."""
    conformance, planfile = _load(harness_root)
    if tracked is None:
        lookup = getattr(conformance, "_tracked_files", None)
        tracked = lookup(repo_root) if lookup is not None else None
    runs: dict[str, dict] = {}
    skipped: Counter = Counter()
    for result_path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        run_dir = os.path.dirname(result_path)
        name = os.path.basename(run_dir)
        try:
            with open(result_path, encoding="utf-8") as fh:
                result = json.load(fh)
        except (OSError, ValueError):
            skipped["no-files"] += 1
            continue
        plan_meta = result.get("plan")
        plan_path = plan_meta.get("path") if isinstance(plan_meta, dict) else None
        if not plan_path:
            skipped["plan-null"] += 1
            continue
        if not os.path.isfile(plan_path):
            skipped["plan-missing"] += 1
            continue
        files = (result.get("classification") or {}).get("files")
        if files is None:
            skipped["no-files"] += 1
            continue
        slug = result.get("slug") or name[len("live-"):]
        plan = planfile.locate_plan(slug, explicit_path=plan_path)
        if not plan.found:
            skipped["plan-unparsed"] += 1
            continue
        items, notes = _run_check(conformance, plan, files, tracked)
        audit = _audit_items(run_dir)
        runs[name] = {"slug": slug, "items": items, "notes": notes,
                      "audit_items": sorted(audit), "audit_match": set(items) == audit}
    return {"runs": runs, "skipped": dict(skipped), "replayable": len(runs)}


def _key(name: str, item: str) -> tuple[str, str]:
    """(run, phase number). The hold line's sample list narrows after the change, so the
    item text is not a stable identity; the phase number is."""
    m = _ITEM_NUM_RE.match(item)
    return (name, m.group(1) if m else item)


def compare(before: dict, after: dict) -> dict:
    """Classify every held phase that disappeared between two replay JSONs."""
    class1: list[str] = []
    class2: list[str] = []
    regressions: list[str] = []
    appeared: list[str] = []
    before_counts: Counter = Counter()
    before_held: dict[tuple[str, str], str] = {}
    after_held: dict[tuple[str, str], str] = {}
    for name, b in before["runs"].items():
        for it in b["items"]:
            before_counts[it] += 1
            before_held[_key(name, it)] = it
    for name, a in after["runs"].items():
        for it in a["items"]:
            after_held[_key(name, it)] = it
    for (name, num), it in sorted(before_held.items()):
        if name not in after["runs"] or (name, num) in after_held:
            continue
        notes = after["runs"][name]["notes"]
        if any(n.startswith(f"UNCHECKABLE-PHASE: {num} ") for n in notes):
            class1.append(it)
        elif any(n.startswith(f"NO-DIFF-PHASE: {num} ") for n in notes):
            class2.append(it)
        else:
            regressions.append(it)
    for (name, num), it in sorted(after_held.items()):
        if name in before["runs"] and (name, num) not in before_held:
            appeared.append(it)
    still = {before_held[k] for k in before_held if k in after_held}
    top = [(it, n, "still-held" if it in still else "cleared")
           for it, n in before_counts.most_common(10)]
    audit_mismatch = sum(1 for r in before["runs"].values() if not r["audit_match"])
    return {"class1": class1, "class2": class2, "regressions": regressions,
            "appeared": appeared, "top": top, "audit_mismatch": audit_mismatch,
            "before_total": sum(before_counts.values()), "after_total": len(after_held),
            "replayable": before["replayable"], "skipped": before["skipped"],
            "exit_code": 0 if not regressions and not appeared else 2}


def render(res: dict) -> str:
    out = [f"replayable runs: {res['replayable']}",
           f"skipped: {json.dumps(res['skipped'], sort_keys=True)}",
           f"BEFORE items: {res['before_total']}",
           f"AFTER items: {res['after_total']}",
           f"audit mismatches (BEFORE vs audit.log): {res['audit_mismatch']}",
           f"class-1 uncheckable: {len(res['class1'])}",
           f"class-2 no-diff: {len(res['class2'])}",
           f"REGRESSION: {len(res['regressions'])}"]
    out.extend(f"  {it}" for it in res["regressions"])
    out.append(f"appeared: {len(res['appeared'])}")
    out.extend(f"  {it}" for it in res["appeared"])
    out.append("top 10 repeated BEFORE items:")
    out.extend(f"  {n:>4}  {tag:<10}  {it}" for it, n, tag in res["top"])
    return "\n".join(out)


def main() -> int:
    here = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    ap = argparse.ArgumentParser()
    ap.add_argument("--compare", nargs=2, metavar=("BEFORE", "AFTER"))
    ap.add_argument("--harness-root", default=here)
    ap.add_argument("--out-dir")
    ap.add_argument("--repo-root")
    ap.add_argument("--json")
    args = ap.parse_args()
    if args.compare:
        with open(args.compare[0], encoding="utf-8") as fh:
            before = json.load(fh)
        with open(args.compare[1], encoding="utf-8") as fh:
            after = json.load(fh)
        res = compare(before, after)
        print(render(res))
        return res["exit_code"]
    if not args.json:
        ap.error("--json is required in replay mode")
    harness_root = os.path.abspath(args.harness_root)
    out_dir = args.out_dir or os.path.join(harness_root, "out")
    repo_root = args.repo_root or os.path.abspath(os.path.join(harness_root, "..", ".."))
    data = replay(out_dir, harness_root, repo_root)
    with open(args.json, "w", encoding="utf-8") as fh:
        json.dump(data, fh)
    print(f"replayable: {data['replayable']} skipped: {json.dumps(data['skipped'], sort_keys=True)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
