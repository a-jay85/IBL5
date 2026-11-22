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


# ---------------------------------------------------------------------------
# Parser: planfile._files_line_paths reads the leading path list of a Files line.
# ---------------------------------------------------------------------------

def _files(text):
    return planfile._files_line_paths(text.splitlines())


def test_files_line_inline_multi_path_separators():
    text = "**Files:** `bin/a`, `bin/b` and `bin/c` & `bin/d` + `bin/e`; `bin/f`"
    assert _files(text) == ["bin/a", "bin/b", "bin/c", "bin/d", "bin/e", "bin/f"]


def test_files_line_bare_paths_without_backticks():
    text = "**Files:** bin/foo and tools/postplan-harness/harness/state.py"
    assert _files(text) == ["bin/foo", "tools/postplan-harness/harness/state.py"]


def test_files_line_bullet_form_takes_leading_path_only():
    text = (
        "**Files:**\n"
        "- `bin/a` — NEW (adds `bin/z`)\n"
        "- `bin/b` (modify)\n"
        "\n"
        "- `bin/later`\n"
    )
    assert _files(text) == ["bin/a", "bin/b"]


def test_files_line_wrapped_continuation_after_separator():
    assert _files("**Files:** `bin/a`, `bin/b`,\n`bin/c`.\n") == ["bin/a", "bin/b", "bin/c"]
    assert _files("**Files:** `bin/a`\n`bin/c` is next\n") == ["bin/a"]


_FP1_FILES_LINE = (
    "**Files:** `bin/run-shell-harnesses` and `bin/lib/harness-select.sh`. The names "
    "`bin/test-burndown` and `bin/test-burndown-loop` below are fixture entries in the "
    "self-test, not files this phase edits."
)


def test_files_line_stops_at_trailing_prose():
    assert _files(_FP1_FILES_LINE) == ["bin/run-shell-harnesses", "bin/lib/harness-select.sh"]


def test_non_files_labels_keep_mention_scan():
    for text in ("**File:** `bin/a`",
                 "**Files.** `bin/a`",
                 "**Files touched:** `bin/a`",
                 "See the **Files:** line `bin/a`"):
        assert _files(text) == [], text


def test_files_line_normalisation_reuses_evidence_rules():
    text = (
        "**Files:** `tools/postplan-harness/harness/conformance.py:410`, "
        "`tools/postplan-harness/tests/test_conformance_nondiff.py::test_no_evidence_phase_skipped`, "
        "`bin/*.sh`, `bin/gone` (example)"
    )
    assert _files(text) == [
        "tools/postplan-harness/harness/conformance.py",
        "tools/postplan-harness/tests/test_conformance_nondiff.py",
    ]
