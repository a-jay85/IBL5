"""Replay the PR #3511 condition (3) decline against the real plan and the pinned diff.

  python3 replay_reference_only.py [--plan PATH] [--range BASE...HEAD] [--target PATH]
                                   [--without-fix]

Runs harness.conformance.check over the plan `~/claude-plans/projected-draft-order-notes-flake.md`
and PR #3511's diff (head pinned to the sha the decline was computed on; the branch tip has since
moved). Prints every MISSING item and every REFERENCE-ONLY note, then CLEARED (exit 0) when no
`MISSING: <target>` item remains or KEPT (exit 1) when one does. --without-fix empties the two
reference-only verdicts after locate_plan so the pre-fix result shows from the same tree.

Exit 2 with a `SKIP:` line when a machine-local input (the plan file, the pinned sha) is absent.
Read-only; stdlib only.
"""
import argparse
import os
import subprocess
import sys

_HARNESS_ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
_REPO_ROOT = os.path.dirname(os.path.dirname(_HARNESS_ROOT))
_GIT_TIMEOUT = 20


def parse_args(argv):
    ap = argparse.ArgumentParser(description=__doc__.split("\n\n")[0])
    ap.add_argument("--plan", default=os.path.expanduser("~/claude-plans/projected-draft-order-notes-flake.md"))
    ap.add_argument("--range", default="origin/master...4997840249")
    ap.add_argument("--target", default=".github/workflows/e2e-tests.yml")
    ap.add_argument("--without-fix", action="store_true")
    return ap.parse_args(argv)


def _git(*args):
    return subprocess.run(["git", "-C", _REPO_ROOT, *args], capture_output=True, text=True,
                          timeout=_GIT_TIMEOUT)


def main(argv=None) -> int:
    args = parse_args(argv)
    plan_path = args.plan
    if not os.path.isfile(plan_path):
        print(f"SKIP: plan file not found: {plan_path} (machine-local; the trimmed fixture in "
              "tests/test_conformance_reference_only.py is the durable proof)")
        return 2
    sides = args.range.split("...")
    if len(sides) != 2 or not all(sides):
        print("SKIP: --range must be <base>...<head>")
        return 2
    base, head = sides
    try:
        for side in (head, base):
            if _git("cat-file", "-e", f"{side}^{{commit}}").returncode != 0:
                print(f"SKIP: head {side} not in this clone (git fetch origin <sha> or pass --range)")
                return 2
        diff = _git("diff", "--name-only", args.range)
        files = [f for f in diff.stdout.split("\n") if f.strip()] if diff.returncode == 0 else []
        if not files:
            print(f"SKIP: empty diff for {args.range}")
            return 2
        tree = _git("ls-tree", "-r", "--name-only", base)
        if tree.returncode != 0:
            print("SKIP: cannot list base tree")
            return 2
    except subprocess.TimeoutExpired:
        print("SKIP: git timed out")
        return 2
    tracked = [f for f in tree.stdout.split("\n") if f.strip()]

    sys.path.insert(0, _HARNESS_ROOT)
    from harness.conformance import check
    from harness.planfile import locate_plan

    slug = os.path.splitext(os.path.basename(plan_path))[0]
    plan = locate_plan(slug, explicit_path=plan_path)
    if not plan.found:
        print(f"SKIP: locate_plan did not load {plan_path}")
        return 2
    if args.without_fix:
        plan.reference_only_test_paths = {}
        plan.reference_only_refusals = {}

    notes: list[str] = []
    items = check(plan, files, tracked_files=tracked, notes=notes)
    for line in [i for i in items if i.startswith("MISSING")] + \
            [n for n in notes if n.startswith("REFERENCE-ONLY")]:
        print(f"  {line}")
    if any(i.startswith(f"MISSING: {args.target} ") for i in items):
        print(f"KEPT: MISSING: {args.target} still reported for {args.range}")
        return 1
    print(f"CLEARED: no MISSING: item names {args.target} for {args.range}")
    return 0


if __name__ == "__main__":
    sys.exit(main())
