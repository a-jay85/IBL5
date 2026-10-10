"""Byte parity between the shell and Python manual-confirmation renderers.

Both engines write the manual-confirmation block for the same plan: the harness
through `classify.render_manual_confirmation(classify.manual_confirmation_text(...))`
and the `/post-plan` skill by pasting `hold_manual_confirmation_block` stdout.
This file pins them byte-identical on the Decision path.
"""
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.classify import (decision_paragraphs, manual_confirmation_text,
                              render_manual_confirmation)
from harness.planfile import parse_hold_justification

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(
    os.path.dirname(os.path.abspath(__file__)))))
HOLD_CHECK_LIB = os.path.join(REPO_ROOT, "bin", "lib", "hold-check.sh")

_HEAD = "# Parity Plan\n\n## Automouse Hold Justification\n\n"
_TAIL = "\n## Verification Matrix\n\n| # | What |\n|---|---|\n| 1 | x |\n"

_HOLD_SECTIONS = {
    "four_part": (
        "**Category:** intrinsic taste hold.\n\n"
        "**Decision:** Merge if you're OK with about 7% more PRs being held.\n\n"
        "**Discharged by matrix rows:** 3, 4.\n\n"
        "Rows 3 and 4 cover the observable part.\n"
    ),
    "two_blocks": (
        "**Decision:** Decision A.\n\n"
        "Some prose in between.\n\n"
        "**Decision:** Decision B.\n"
    ),
    "two_line_decision": (
        "**Decision:** Merge if you're OK with the new copy\n"
        "and the longer label.\n\n"
        "Prose after.\n"
    ),
    "indented_decision": (
        "  **Decision:** Merge if you're OK with the indented form.\n\n"
        "Prose after.\n"
    ),
    "trailing_ws": (
        "**Decision:** Merge if you're OK with trailing spaces.  \n"
    ),
    "fenced_example": (
        "Example of the form:\n\n"
        "```\n**Decision:** fake example inside a fence.\n```\n\n"
        "**Decision:** The real decision.\n"
    ),
    "pr_2887_shape": (
        "**Category:** intrinsic — a self-gating change to the merge-gate "
        "machinery itself (the Phase 2 and Phase 7 lost-work proof that decides "
        "whether a rebased branch may be pushed and armed), and an executable-gate "
        "weakening: inputs the numstat proof rejected (gained work, master edits to "
        "the same file, absorbed hunks) now pass.\n\n"
        "**Decision:** The human accepts that \"every significant branch line is "
        "present in the post-rebase tree, every significant deletion is still "
        "absent, every touched file is still present or still deleted, and "
        "anything ambiguous blocks\" is a sufficient definition of lost work for "
        "this proof to stand in for the old byte-level numstat equality, given the "
        "backtest table in the ADR.\n\n"
        "**Discharged by matrix rows:** 6-19 (the full behavior table, including "
        "the four negative shapes: dropped hunk, dropped file, dropped deletion, "
        "empty post diff), 20-21 (§5.9g and §5.9b), 22 (existing harness "
        "consumers), 25-26 (backtest table and its placement in the ADR), 3-5 "
        "(verdict tokens and the conjunctive gate expression unchanged).\n\n"
        "The judgment is irreducible because it is about the invariant's adequacy, "
        "which no test can assert.\n"
    ),
}

FIXTURE_IDS = list(_HOLD_SECTIONS)


def _plan_text(fixture_id: str) -> str:
    return _HEAD + _HOLD_SECTIONS[fixture_id] + _TAIL


def _shell(func: str, plan_path) -> subprocess.CompletedProcess:
    return subprocess.run(
        ["bash", "-c", 'source "$1"; "$2" "$3"', "_",
         HOLD_CHECK_LIB, func, str(plan_path)],
        capture_output=True, text=True, check=False,
    )


@pytest.mark.parametrize("fixture_id", FIXTURE_IDS)
def test_shell_block_matches_python_render(fixture_id, tmp_path):
    """Shell block stdout == Python render + one newline.

    Mutation caught: dropping the leading-whitespace trim in the shell
    (indented_decision diverges); restoring the bold header in either engine;
    changing `printf '>\\n'` to `printf '> \\n'`.
    """
    plan = tmp_path / "plan.md"
    text = _plan_text(fixture_id)
    plan.write_text(text)
    proc = _shell("hold_manual_confirmation_block", plan)
    want = render_manual_confirmation(
        manual_confirmation_text(parse_hold_justification(text))) + "\n"
    assert proc.returncode == 0
    assert proc.stdout == want


@pytest.mark.parametrize("fixture_id", FIXTURE_IDS)
def test_shell_decision_paragraphs_match_python(fixture_id, tmp_path):
    """Shell Decision paragraphs (minus the final newline) == Python's.

    `parse_hold_justification` strips the whole section, so the outer edges
    differ for an indented first line or a trailing-space last line; both
    sides are compared stripped. The block-level test above is exact.

    Mutation caught: deleting the separator `printf '\\n'` in the shell
    (two_blocks diverges).
    """
    plan = tmp_path / "plan.md"
    text = _plan_text(fixture_id)
    plan.write_text(text)
    proc = _shell("hold_decision_paragraphs", plan)
    assert proc.returncode == 0
    assert proc.stdout.strip() == decision_paragraphs(
        parse_hold_justification(text)).strip()


def test_shell_block_absent_without_decision(tmp_path):
    """No Decision line: shell prints nothing and returns 1; Python falls back
    to the full prose (the fallback lives in the caller).

    Mutation caught: emitting the block with an empty body; `return 0`.
    """
    plan = tmp_path / "plan.md"
    text = _HEAD + "**Category:** taste.\n\nSome prose about the taste call.\n" + _TAIL
    plan.write_text(text)
    proc = _shell("hold_manual_confirmation_block", plan)
    assert proc.returncode == 1
    assert proc.stdout == ""
    justification = parse_hold_justification(text)
    assert manual_confirmation_text(justification) == justification
    assert "Some prose about the taste call." in justification


def test_shell_block_absent_without_hold_section(tmp_path):
    """No hold section: shell prints nothing and returns 1.

    Mutation caught: dropping the `in_section` guard in hold_decision_paragraphs.
    """
    plan = tmp_path / "plan.md"
    plan.write_text("# Plan\n\n**Decision:** stray line outside any hold section.\n" + _TAIL)
    proc = _shell("hold_manual_confirmation_block", plan)
    assert proc.returncode == 1
    assert proc.stdout == ""
