"""Print conformance.check's `MISSING:` items for every matrix plan in a corpus.

Usage:
  python3 corpus_conformance_diff.py --harness-root <dir containing harness/> \
      [--plans-dir ~/claude-plans] [--changed a.php,b.css]

Run once per harness tree and diff the outputs; see the plan
conformance-no-change-matrix-row, Phase 5. Stdlib only.
"""
import argparse
import glob
import os
import sys


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--harness-root", required=True)
    ap.add_argument("--plans-dir", default=os.path.expanduser("~/claude-plans"))
    ap.add_argument("--changed", default="", help="comma-separated changed files; default empty")
    args = ap.parse_args()
    sys.path.insert(0, os.path.abspath(args.harness_root))
    from harness import conformance, planfile  # noqa: E402  (tree chosen by --harness-root)
    from harness.state import PlanInfo

    changed = [c for c in args.changed.split(",") if c]
    no_change_parser = getattr(planfile, "parse_no_change_test_paths", None)  # absent on the old tree
    for path in sorted(glob.glob(os.path.join(args.plans_dir, "*.md"))):
        with open(path, encoding="utf-8", errors="replace") as fh:
            content = fh.read()
        if not any(planfile._MATRIX_HEADER.match(ln) for ln in content.splitlines()):
            continue
        planned, manual = planfile.parse_matrix(content)
        info = PlanInfo(found=True, path=path, has_matrix=True,
                        planned_test_paths=planned, truly_manual_rows=manual)
        if no_change_parser is not None:
            info.no_change_test_paths = no_change_parser(content)
        items = [i for i in conformance.check(info, changed) if i.startswith("MISSING: ")]
        print(f"{os.path.basename(path)}\t{len(items)}\t{' ;; '.join(items)}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
