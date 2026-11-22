"""MISSING-PHASE narrowed to a phase's `**Files:**` line paths.

A phase with a `**Files:**` line is checked against the repo paths that open that
line. A phase without one keeps the mention scan over every backticked path in its
body. Every conformance test drives the real parser (`planfile.parse_phases`), so the
same tests also run against master's sources for the counterfactual row.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness import planfile
from harness.state import PhaseInfo, PlanInfo  # noqa: F401  (PhaseInfo kept for parity with sibling suites)


def _check(text, changed, tracked, notes=None):
    plan = PlanInfo(found=True, phases=planfile.parse_phases(text))
    return conformance.phase_omission_items(plan, changed, tracked_files=tracked, notes=notes)


# ---------------------------------------------------------------------------
# Characterization: phases with no `**Files:**` line keep today's mention scan.
# ---------------------------------------------------------------------------

_NO_FILES_PLAN = (
    "# Plan\n\n"
    "## Phase 1: Wire the gate\n\n"
    "Edit `bin/alpha` and read `bin/beta`.\n"
)


def test_no_files_line_mentioned_absent_path_still_holds():
    items = _check(_NO_FILES_PLAN, ["README.md"], ["bin/alpha", "bin/beta", "README.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")
    assert "(phase cites bin/alpha, bin/beta;" in items[0]
    assert "Files line only" not in items[0]


def test_no_files_line_any_mentioned_path_touched_clears():
    items = _check(_NO_FILES_PLAN, ["bin/beta"], ["bin/alpha", "bin/beta", "README.md"])
    assert items == []


def test_fp2_shape_no_files_line_no_diff_marker_exempt():
    text = (
        "# Plan\n\n"
        "## Phase 1: Set up the worktree and baseline\n\n"
        "**No diff:** setup only; it creates the worktree and runs the baseline tests.\n\n"
        "1. Read `.claude/rules/postplan-harness-tests.md` before running the suite.\n"
    )
    notes: list[str] = []
    items = _check(
        text,
        ["tools/postplan-harness/harness/gate_backtest_replay.py"],
        [".claude/rules/postplan-harness-tests.md",
         "tools/postplan-harness/harness/gate_backtest_replay.py"],
        notes=notes,
    )
    assert items == []
    assert any(n.startswith("NO-DIFF-PHASE: 1") for n in notes)
