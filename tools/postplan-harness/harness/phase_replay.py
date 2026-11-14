"""Replay phase_omission_items against a plan file and a saved unified diff.

Usage: python3 -m harness.phase_replay <plan.md> <diff-file>
Exit 0: no MISSING-PHASE item. Exit 1: one or more (printed). Exit 2: usage error, an
unreadable or empty plan, or a diff with no file headers (fail closed: input it could not
read never reports zero missing).
"""
import re
import sys

from harness import planfile
from harness.conformance import phase_omission_items
from harness.state import PlanInfo

_DIFF_GIT = re.compile(r"^diff --git a/\S+ b/(\S+)$", re.M)


def changed_files_from_diff(diff_text: str) -> list[str]:
    return sorted(set(_DIFF_GIT.findall(diff_text)))


def main(argv: list[str] | None = None) -> int:
    args = sys.argv[1:] if argv is None else argv
    if len(args) != 2:
        print("usage: python3 -m harness.phase_replay <plan.md> <diff-file>", file=sys.stderr)
        return 2
    try:
        with open(args[0], encoding="utf-8") as fh:
            plan_text = fh.read()
        with open(args[1], encoding="utf-8") as fh:
            diff_text = fh.read()
    except (OSError, UnicodeDecodeError) as exc:
        print(f"phase_replay: cannot read input: {exc}", file=sys.stderr)
        return 2
    changed = changed_files_from_diff(diff_text)
    if not plan_text.strip() or not changed:
        print("phase_replay: empty plan, or diff without file headers", file=sys.stderr)
        return 2
    notes: list[str] = []
    plan = PlanInfo(found=True, has_matrix=True, phases=planfile.parse_phases(plan_text))
    items = phase_omission_items(plan, changed, notes=notes, diff_text=diff_text)
    for n in notes:
        print(f"note: {n}")
    for i in items:
        print(i)
    print(f"MISSING-PHASE items: {len(items)}")
    return 1 if items else 0


if __name__ == "__main__":
    sys.exit(main())
