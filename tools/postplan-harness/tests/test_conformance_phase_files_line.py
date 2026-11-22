"""MISSING-PHASE narrowed to a phase's `**Files:**` line paths.

A phase with a `**Files:**` line is checked against the repo paths that open that
line. A phase without one keeps the mention scan over every backticked path in its
body. Every conformance test drives the real parser (`planfile.parse_phases`), so the
same tests also run against master's sources for the counterfactual row.
"""
import os
import re
import subprocess
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness import planfile
from harness.state import PlanInfo


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


# ---------------------------------------------------------------------------
# Conformance: a Files line narrows MISSING-PHASE to its own paths.
# ---------------------------------------------------------------------------

def test_files_line_target_missing_holds_despite_touched_body_path():
    text = (
        "# Plan\n\n"
        "## Phase 1: Wire the gate\n\n"
        "**Files:** `bin/alpha`\n\n"
        "Read `bin/beta` for the pattern.\n"
    )
    items = _check(text, ["bin/beta"], ["bin/alpha", "bin/beta"])
    assert len(items) == 1
    assert "(Files line only; phase cites bin/alpha;" in items[0]


def test_heading_cited_path_ignored_when_files_line_present():
    text = (
        "# Plan\n\n"
        "## Phase 1: Edit `bin/head`\n\n"
        "**Files:** `bin/alpha`\n"
    )
    items = _check(text, ["bin/head"], ["bin/alpha", "bin/head"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")


def test_bare_files_line_path_touched_clears():
    text = (
        "# Plan\n\n"
        "## Phase 1: Wire the gate\n\n"
        "**Files:** bin/alpha\n\n"
        "Read `bin/beta` for the pattern.\n"
    )
    assert _check(text, ["bin/alpha"], ["bin/alpha", "bin/beta"]) == []


def test_bare_only_files_line_phase_is_checked():
    text = (
        "# Plan\n\n"
        "## Phase 1: Wire the gate\n\n"
        "**Files:** bin/alpha\n\n"
        "Make the change described above.\n"
    )
    items = _check(text, ["README.md"], ["bin/alpha", "README.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1")
    assert "bin/alpha" in items[0]


def test_files_line_prose_only_falls_back_to_mention_scan():
    text = (
        "# Plan\n\n"
        "## Phase 1: Wire the gate\n\n"
        "**Files:** None of these files change; see `bin/other` for context.\n\n"
        "Edit `bin/real`.\n"
    )
    items = _check(text, ["README.md"], ["bin/other", "bin/real", "README.md"])
    assert len(items) == 1
    assert "(phase cites bin/other, bin/real;" in items[0]
    assert "Files line only" not in items[0]


def test_files_line_non_repo_paths_fall_back_to_mention_scan():
    text = (
        "# Plan\n\n"
        "## Phase 1: Wire the gate\n\n"
        "**Files:** `~/claude-plans/x.md`\n\n"
        "Edit `bin/real`.\n"
    )
    notes: list[str] = []
    items = _check(text, ["README.md"], ["bin/real", "README.md"], notes=notes)
    assert len(items) == 1
    assert "bin/real" in items[0]
    assert "Files line only" not in items[0]
    assert any(n.startswith("FILES-LINE-FALLBACK: 1") for n in notes)


def test_files_line_phase_keeps_existing_exemptions():
    text = (
        "# Plan\n\n"
        "## Phase 1: Close the backlog issue [phases: S]\n\n"
        "**Files:** `bin/alpha`\n\n"
        "## Phase 2: Record the baseline\n\n"
        "**No diff:** records the baseline only, no edits.\n\n"
        "**Files:** `bin/alpha`\n"
    )
    notes: list[str] = []
    items = _check(text, ["README.md"], ["bin/alpha", "README.md"], notes=notes)
    assert items == []
    assert any(n.startswith("NO-DIFF-PHASE: 2") for n in notes)


# ---------------------------------------------------------------------------
# Replays of the two reported false-positive shapes.
# ---------------------------------------------------------------------------

_FP1_PLAN = (
    "# Plan\n\n"
    "## Phase 5: Add per-leg selected-list filtering to bin/run-shell-harnesses\n\n"
    + _FP1_FILES_LINE + "\n\n"
    "1. Add `bin/test-burndown` and `bin/test-burndown-loop` to the self-test fixture list.\n"
)
_FP1_TRACKED = ["bin/run-shell-harnesses", "bin/lib/harness-select.sh",
                "bin/test-burndown", "bin/test-burndown-loop", "README.md"]


def test_fp1_shape_item_names_only_files_line_paths():
    items = _check(_FP1_PLAN, ["README.md"], _FP1_TRACKED)
    assert len(items) == 1
    assert ("(Files line only; phase cites bin/run-shell-harnesses, "
            "bin/lib/harness-select.sh;") in items[0]
    assert "bin/test-burndown" not in items[0]


def test_fp1_shape_files_target_touched_clears():
    assert _check(_FP1_PLAN, ["bin/run-shell-harnesses"], _FP1_TRACKED) == []


def test_reference_only_body_citation_with_files_target_touched_clears():
    text = (
        "# Plan\n\n"
        "## Phase 1: Fix the replay\n\n"
        "**Files:** `tools/postplan-harness/harness/gate_backtest_replay.py`\n\n"
        "3. Record the baseline with per-file runs (`.claude/rules/postplan-harness-tests.md`).\n"
    )
    changed = ["tools/postplan-harness/harness/gate_backtest_replay.py"]
    tracked = changed + [".claude/rules/postplan-harness-tests.md"]
    assert _check(text, changed, tracked) == []


# ---------------------------------------------------------------------------
# The real `--phases` CLI, run the way bin/lib/prefire-verify runs it.
# ---------------------------------------------------------------------------

_HARNESS_HOME = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))

_CLI_PLAN = (
    "---\nauto_merge: false\n---\n\n"
    "# Files-line CLI plan\n\n"
    "## Phase 1: Edit the alpha gate\n\n"
    "**Files:** `bin/alpha`\n\n"
    "1. Copy the pattern from `bin/beta`.\n"
)


def _run_git(repo, *args):
    subprocess.run(["git", "-c", "user.email=t@example.com",
                    "-c", "user.name=Files Line Test", *args],
                   cwd=repo, check=True, capture_output=True, text=True)


def _make_repo(tmp_path, edit):
    """Base commit tracks bin/alpha and bin/beta; HEAD commits a change to `edit` only."""
    repo = tmp_path / "repo"
    (repo / "bin").mkdir(parents=True)
    (repo / "bin" / "alpha").write_text("alpha\n")
    (repo / "bin" / "beta").write_text("beta\n")
    _run_git(repo, "init", "-q", ".")
    _run_git(repo, "add", "bin/alpha", "bin/beta")
    _run_git(repo, "commit", "-qm", "seed")
    _run_git(repo, "update-ref", "refs/remotes/origin/master", "HEAD")
    (repo / "bin" / edit).write_text(f"{edit}\nedited\n")
    _run_git(repo, "add", f"bin/{edit}")
    _run_git(repo, "commit", "-qm", f"edit {edit}")
    return repo


def _write_plan(tmp_path):
    from harness.planfile import locate_plan
    plan_path = tmp_path / "plans" / "files-line.md"
    plan_path.parent.mkdir(parents=True)
    plan_path.write_text(_CLI_PLAN)
    parsed = locate_plan(slug=None, plans_dir=None, explicit_path=str(plan_path))
    assert parsed.found and len(parsed.phases) == 1, "fixture plan did not parse"
    return plan_path


def _run_phases_cli(plan, repo):
    env = dict(os.environ, PYTHONDONTWRITEBYTECODE="1")
    return subprocess.run([sys.executable, "-m", "harness.conformance", "--phases",
                           str(plan), str(repo)],
                          cwd=_HARNESS_HOME, env=env, capture_output=True, text=True)


def test_phases_cli_files_line_target_missing_exits_1(tmp_path):
    repo = _make_repo(tmp_path, "beta")
    proc = _run_phases_cli(_write_plan(tmp_path), repo)
    assert proc.returncode == 1, proc.stdout + proc.stderr
    lines = [ln for ln in proc.stdout.splitlines() if re.match(r"^MISSING-PHASE: 1 ", ln)]
    assert len(lines) == 1, proc.stdout
    assert "Files line only" in lines[0]


def test_phases_cli_files_line_target_touched_exits_0(tmp_path):
    repo = _make_repo(tmp_path, "alpha")
    proc = _run_phases_cli(_write_plan(tmp_path), repo)
    assert proc.returncode == 0, proc.stdout + proc.stderr
    assert "MISSING-PHASE:" not in proc.stdout
