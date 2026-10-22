"""Before/after replay of condition (3) MISSING / MISSING-FILE over the `out/live-*` corpus.

  python3 replay_missing_file.py --before-root <harness dir> --after-root <harness dir> \
      [--out-dir <before-root>/out] [--slugs a,b] [--json PATH] \
      [--expect-cleared SUBSTR]... [--expect-kept SUBSTR]...

BEFORE and AFTER are each COMPUTED by running that tree's harness.conformance.check over the
run's classification.files; the stored result.json `unresolved_conformance` is never read (it
is stale against renumber tiers that landed later). classification.files is B-side only
(classify.py files_from_diff), so deleted and rename-source paths are absent from both sides.

Two harness trees cannot both be `import harness` in one interpreter, so each side runs in a
child process (this script with --worker). Exit 1 when any run has a regression or an
--expect-* substring matched nothing, 2 on a bad flag, else 0. Stdlib only.
"""
import argparse
import glob
import json
import os
import subprocess
import sys

_PREFIXES = ("MISSING:", "MISSING-FILE:")
_NOTE = "note: files = classification.files (B-side only: deleted and rename-source paths absent)"


def worker(harness_root: str) -> int:
    """stdin: {"plan_path","slug","files"}; stdout: {"items":[...]} or {"skip":"<reason>"}."""
    sys.path.insert(0, os.path.abspath(harness_root))
    from harness import conformance, planfile
    req = json.load(sys.stdin)
    plan = planfile.locate_plan(req["slug"], explicit_path=req["plan_path"])
    if not plan.found:
        json.dump({"skip": "plan-unparsed"}, sys.stdout)
        return 0
    items = [i for i in conformance.check(plan, req["files"]) if i.startswith(_PREFIXES)]
    json.dump({"items": items}, sys.stdout)
    return 0


def side_items(harness_root: str, plan_path: str, slug: str, files: list[str]) -> list[str] | None:
    proc = subprocess.run(
        [sys.executable, "-I", os.path.abspath(__file__), "--worker", harness_root],
        input=json.dumps({"plan_path": plan_path, "slug": slug, "files": files}),
        capture_output=True, text=True, check=True)
    out = json.loads(proc.stdout)
    return None if "skip" in out else out["items"]


def compare_run(before: list[str], after: list[str]) -> dict:
    b, a = set(before), set(after)
    return {"cleared": sorted(b - a), "kept": sorted(b & a), "regressions": sorted(a - b)}


def default_out_dir(before_root: str) -> str:
    return os.path.join(os.path.abspath(before_root), "out")


def parse_args(argv):
    ap = argparse.ArgumentParser(description=__doc__)
    ap.add_argument("--before-root")
    ap.add_argument("--after-root")
    ap.add_argument("--out-dir", help="default <before-root>/out")
    ap.add_argument("--slugs", help="comma list; only runs whose result slug is listed")
    ap.add_argument("--json", help="write the full result dict here")
    ap.add_argument("--expect-cleared", action="append", default=[], metavar="SUBSTR")
    ap.add_argument("--expect-kept", action="append", default=[], metavar="SUBSTR")
    ap.add_argument("--worker", metavar="HARNESS_ROOT", help=argparse.SUPPRESS)
    ns = ap.parse_args(argv)
    if ns.worker:
        return ns
    if not ns.before_root or not ns.after_root:
        ap.error("--before-root and --after-root are required")
    if ns.out_dir is None:
        ns.out_dir = default_out_dir(ns.before_root)
    return ns


def replay(before_root: str, after_root: str, out_dir: str, slugs: set[str] | None = None) -> dict:
    runs: dict[str, dict] = {}
    skipped: dict[str, int] = {}

    def skip(reason: str) -> None:
        skipped[reason] = skipped.get(reason, 0) + 1

    for result_path in sorted(glob.glob(os.path.join(out_dir, "live-*", "result.json"))):
        name = os.path.basename(os.path.dirname(result_path))
        try:
            with open(result_path, encoding="utf-8") as fh:
                result = json.load(fh)
        except (OSError, ValueError):
            skip("no-files")
            continue
        plan_meta = result.get("plan")
        plan_path = plan_meta.get("path") if isinstance(plan_meta, dict) else None
        if not plan_path:
            skip("plan-null")
            continue
        if not os.path.isfile(plan_path):
            skip("plan-missing")
            continue
        files = (result.get("classification") or {}).get("files")
        if files is None:
            skip("no-files")
            continue
        slug = result.get("slug") or name[len("live-"):]
        if slugs is not None and slug not in slugs:
            skip("slug-filtered")
            continue
        before = side_items(before_root, plan_path, slug, files)
        after = side_items(after_root, plan_path, slug, files)
        if before is None or after is None:
            skip("plan-unparsed")
            continue
        runs[name] = {"slug": slug, "pr_number": result.get("pr_number"),
                      "before": before, "after": after, **compare_run(before, after)}
    return {"runs": runs, "skipped": skipped}


def render(res: dict, expect_cleared: list[str], expect_kept: list[str]) -> tuple[str, bool]:
    """(text, ok). ok is False on any regression or unmatched expectation."""
    lines = [_NOTE]
    cleared_all: list[str] = []
    kept_all: list[str] = []
    regressions = 0
    for name, run in res["runs"].items():
        lines.append(f"== {name} ({run['slug']}, PR #{run['pr_number']})")
        for kind, key in (("CLEARED", "cleared"), ("KEPT", "kept"), ("REGRESSION", "regressions")):
            for item in run[key]:
                lines.append(f"  {kind} {item}")
        cleared_all += run["cleared"]
        kept_all += run["kept"]
        regressions += len(run["regressions"])
    ok = regressions == 0
    for kind, wanted, pool in (("cleared", expect_cleared, cleared_all),
                               ("kept", expect_kept, kept_all)):
        for sub in wanted:
            if not any(sub in i for i in pool):
                lines.append(f"EXPECT-FAILED: {kind} '{sub}' matched nothing")
                ok = False
    lines.append(f"Summary: runs={len(res['runs'])} cleared={len(cleared_all)} "
                 f"kept={len(kept_all)} regressions={regressions}")
    return "\n".join(lines) + "\n", ok


def main(argv=None) -> int:
    ns = parse_args(sys.argv[1:] if argv is None else argv)
    if ns.worker:
        return worker(ns.worker)
    slugs = {s for s in ns.slugs.split(",") if s} if ns.slugs else None
    res = replay(ns.before_root, ns.after_root, ns.out_dir, slugs)
    text, ok = render(res, ns.expect_cleared, ns.expect_kept)
    sys.stdout.write(text)
    if ns.json:
        with open(ns.json, "w", encoding="utf-8") as fh:
            json.dump(res, fh, indent=2)
    return 0 if ok else 1


if __name__ == "__main__":
    sys.exit(main())
