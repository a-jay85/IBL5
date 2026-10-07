"""Replay conformance.phase_omission_items over the `out/live-*` run corpus.

Replay mode runs one harness tree over every replayable run and writes JSON:
  python3 replay_phase_omission.py --harness-root <dir containing harness/> \
      [--out-dir <harness-root>/out] [--repo-root <harness-root>/../..] \
      [--methods RUN[,RUN...]|all] [--diff-cache DIR] --json PATH

--methods also replays MISSING-METHOD for those runs, with the diff body from
`gh pr diff <pr_number>`. It needs a logged-in gh; the diff cache (default
<out-dir>/.pr-diffs) sits under the gitignored out/ tree.

Compare mode classifies every MISSING-PHASE item that disappeared between a BEFORE
run (master's harness) and an AFTER run (the worktree's harness):
  python3 replay_phase_omission.py --compare BEFORE.json AFTER.json

A disappearance is class 1 when AFTER carries an `UNCHECKABLE-PHASE: N` note for the
phase, class 2 when it carries `NO-DIFF-PHASE: N`, class 3 when it carries
`HEADING-NAMED-PHASE: N`, class 4 when it carries `NON-REPO-CITATION: N`, and a
REGRESSION otherwise. MISSING-METHOD flips are reported, never part of the exit code.
Exit 0 when there are no regressions and nothing appeared, else 2. Stdlib only.
"""
import argparse
import glob
import json
import os
import re
import subprocess
import sys
from collections import Counter

_ITEM_NUM_RE = re.compile(r"^MISSING-PHASE:\s*(\d+)\b")
_AUDIT_PREFIX = "phase2 residual-phase: "
_METHOD_PREFIX = "MISSING-METHOD:"
_DIFF_CACHE_DIRNAME = ".pr-diffs"


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


def _gh_pr_diff(pr_number: int, cache_dir: str) -> str:
    """Diff body for a PR via `gh pr diff <n>`, cached as <cache_dir>/<n>.diff.

    The cache lives under the gitignored out/ tree. A non-zero gh exit raises
    RuntimeError with the first stderr line so the caller records it per run."""
    os.makedirs(cache_dir, exist_ok=True)
    path = os.path.join(cache_dir, f"{pr_number}.diff")
    if os.path.isfile(path) and os.path.getsize(path) > 0:
        with open(path, encoding="utf-8", errors="replace") as fh:
            return fh.read()
    proc = subprocess.run(["gh", "pr", "diff", str(pr_number)],
                          capture_output=True, text=True, check=False)
    if proc.returncode != 0:
        first = (proc.stderr.strip().splitlines() or ["no stderr"])[0][:160]
        raise RuntimeError(f"gh pr diff {pr_number} exit {proc.returncode}: {first}")
    with open(path, "w", encoding="utf-8") as fh:
        fh.write(proc.stdout)
    return proc.stdout


def _run_check(conformance, plan, files, tracked):
    notes: list[str] = []
    try:
        items = conformance.phase_omission_items(plan, files, tracked_files=tracked, notes=notes)
    except TypeError:
        # master's signature has neither kwarg; this is what lets one script drive both trees
        notes = []
        items = conformance.phase_omission_items(plan, files)
    return items, notes


def replay(out_dir: str, harness_root: str, repo_root: str, tracked=None,
           methods=None, diff_fetch=None) -> dict:
    """Replay every `live-*/result.json` under out_dir with the harness at harness_root.

    `methods` is None, "all", or a set of run names whose MISSING-METHOD items are also
    replayed against `diff_fetch(pr_number)` (default: `gh pr diff`, cached under out_dir)."""
    conformance, planfile = _load(harness_root)
    if diff_fetch is None:
        def diff_fetch(pr):
            return _gh_pr_diff(pr, os.path.join(out_dir, _DIFF_CACHE_DIRNAME))
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
        if methods is not None and (methods == "all" or name in methods):
            pr = result.get("pr_number")
            if not pr:
                runs[name]["method_items"] = None
                skipped["methods-no-pr"] += 1
                continue
            try:
                diff_body = diff_fetch(int(pr))
            except Exception as e:  # recorded per run; one bad PR never aborts the corpus
                runs[name]["method_items"] = None
                runs[name]["method_error"] = str(e)[:200]
                skipped["methods-no-diff"] += 1
                continue
            all_items = conformance.check(plan, files, diff_body=diff_body, tracked_files=tracked)
            runs[name]["method_items"] = [it for it in all_items if it.startswith(_METHOD_PREFIX)]
            runs[name]["method_source"] = f"gh pr diff {pr}"
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
    class3: list[str] = []
    class4: list[str] = []
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
        elif any(n.startswith(f"HEADING-NAMED-PHASE: {num} ") for n in notes):
            class3.append(it)
        elif any(n.startswith(f"NON-REPO-CITATION: {num} ") for n in notes):
            class4.append(it)
        else:
            regressions.append(it)
    for (name, num), it in sorted(after_held.items()):
        if name in before["runs"] and (name, num) not in before_held:
            appeared.append(it)
    method_before = {(n, it) for n, r in before["runs"].items()
                     for it in (r.get("method_items") or [])}
    method_after = {(n, it) for n, r in after["runs"].items()
                    for it in (r.get("method_items") or [])}
    still = {before_held[k] for k in before_held if k in after_held}
    top = [(it, n, "still-held" if it in still else "cleared")
           for it, n in before_counts.most_common(10)]
    audit_mismatch = sum(1 for r in before["runs"].values() if not r["audit_match"])
    return {"class1": class1, "class2": class2, "class3": class3, "class4": class4,
            "regressions": regressions, "appeared": appeared,
            "method_cleared": [f"{n}: {it}" for n, it in sorted(method_before - method_after)],
            "method_appeared": [f"{n}: {it}" for n, it in sorted(method_after - method_before)],
            "method_still": [f"{n}: {it}" for n, it in sorted(method_before & method_after)], "top": top, "audit_mismatch": audit_mismatch,
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
           f"class-3 heading-named: {len(res['class3'])}",
           f"class-4 non-repo-citation: {len(res['class4'])}",
           f"REGRESSION: {len(res['regressions'])}"]
    out.extend(f"  {it}" for it in res["regressions"])
    out.append(f"appeared: {len(res['appeared'])}")
    out.extend(f"  {it}" for it in res["appeared"])
    for label, key in (("methods cleared", "method_cleared"),
                       ("methods appeared", "method_appeared"),
                       ("methods still-held", "method_still")):
        out.append(f"{label}: {len(res[key])}")
        out.extend(f"  {it}" for it in res[key])
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
    ap.add_argument("--methods", metavar="RUN[,RUN...]|all",
                    help="also replay MISSING-METHOD for these runs, diff from gh pr diff <pr_number>")
    ap.add_argument("--diff-cache", help=f"default <out-dir>/{_DIFF_CACHE_DIRNAME}")
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
    methods = None if not args.methods else (
        "all" if args.methods == "all" else set(args.methods.split(",")))
    cache_dir = args.diff_cache or os.path.join(out_dir, _DIFF_CACHE_DIRNAME)
    data = replay(out_dir, harness_root, repo_root, methods=methods,
                  diff_fetch=lambda pr: _gh_pr_diff(pr, cache_dir))
    with open(args.json, "w", encoding="utf-8") as fh:
        json.dump(data, fh)
    print(f"replayable: {data['replayable']} skipped: {json.dumps(data['skipped'], sort_keys=True)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
