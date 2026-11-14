"""Phase-omission tests for the heading-identifier diff-content clearance.

Each test is named for the mutation it kills. The fixtures are the shapes of the real
false MISSING-PHASE holds on PR #3923 (Phase 3, `SHELLCHECK_OPTS`) and PR #3928
(Phase 2, `stage_pytest`), plus the #3946 Phase 3 shape for the body-only negative.
"""
import inspect
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance, planfile
from harness.conformance import _heading_identifiers, check, phase_omission_items
from harness.state import PlanInfo

SHELLCHECK_3923 = (
    "## Phase 3: Extend bin/test-prefire-verify with SHELLCHECK_OPTS cases\n\n"
    "CI runs the harness through `.github/workflows/harness-legs.txt`; no edit there.\n")
TRACKED_3923 = [".github/workflows/harness-legs.txt"]
CHANGED_3923 = ["bin/lib/prefire-verify", "bin/test-prefire-verify"]
DIFF_3923 = ("diff --git a/bin/test-prefire-verify b/bin/test-prefire-verify\n"
             "--- a/bin/test-prefire-verify\n+++ b/bin/test-prefire-verify\n"
             "@@ -10,2 +10,3 @@\n run_gate() {\n"
             "+  SHELLCHECK_OPTS='--exclude=SC2034' run_case lint\n }\n")

PYTEST_3928 = (
    "## Phase 2: Add stage_pytest, the PREFIRE_PYTEST_CMD seam, and wire it into "
    "--stage and all\n\n`bin/post-plan-now` needs no edit.\n")
TRACKED_3928 = ["bin/post-plan-now"]
CHANGED_3928 = ["bin/lib/prefire-verify"]
DIFF_3928 = ("diff --git a/bin/lib/prefire-verify b/bin/lib/prefire-verify\n"
             "--- a/bin/lib/prefire-verify\n+++ b/bin/lib/prefire-verify\n"
             "@@ -40,0 +41,2 @@\n+stage_pytest() {\n+}\n")

BOXSCORE_PHASE_3 = """\
## Phase 3: Thin migration driver and local-DB apply

Copy the head of `ibl5/migrations/171_restore_2004_suns_heat_boxscore.php`. Create the driver `ibl5/migrations/NNN_restore_missing_box_score_team_rows.php` (example) with the number from `bin/next-migration`. Rows carry `game_date`.
"""
BOXSCORE_TRACKED = ["ibl5/migrations/171_restore_2004_suns_heat_boxscore.php",
                    "bin/next-migration"]
BOXSCORE_CLASS = "ibl5/classes/Boxscore/Legacy/MissingTeamRowRestore.php"


def _plan_with_phases(content: str) -> PlanInfo:
    return PlanInfo(found=True, has_matrix=True, phases=planfile.parse_phases(content))


def _run(content, changed, tracked, **kw):
    notes: list[str] = []
    items = phase_omission_items(_plan_with_phases(content), changed,
                                 tracked_files=tracked, notes=notes, **kw)
    return items, notes


def _diff_3923_with(body_lines: str) -> str:
    return ("diff --git a/bin/test-prefire-verify b/bin/test-prefire-verify\n"
            "--- a/bin/test-prefire-verify\n+++ b/bin/test-prefire-verify\n"
            "@@ -10,2 +10,3 @@\n" + body_lines)


def _assert_one_missing(items, number):
    assert len(items) == 1
    assert items[0].startswith(f"MISSING-PHASE: {number} ")


def test_shellcheck_3923_identifier_on_added_line_clears_phase():
    """Mutation caught: `if content_ident:` replaced with `if False:`."""
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=DIFF_3923)
    assert items == []


def test_pytest_stage_3928_function_name_on_added_line_clears_phase():
    """Mutation caught: `_IDENTIFIER_JOIN` set to the never-matching `(?!)`."""
    items, _ = _run(PYTEST_3928, CHANGED_3928, TRACKED_3928, diff_text=DIFF_3928)
    assert items == []


def test_content_matched_note_names_identifier():
    """Mutation caught: the `CONTENT-MATCHED-PHASE:` note prefix renamed."""
    _, notes = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=DIFF_3923)
    matched = [n for n in notes if n.startswith("CONTENT-MATCHED-PHASE: 3 ")]
    assert len(matched) == 1
    assert "SHELLCHECK_OPTS" in matched[0]


def test_identifier_only_on_removed_line_still_missing():
    """Mutation caught: `_added_lines` also keeps `-` lines."""
    diff = _diff_3923_with(" run_gate() {\n-  SHELLCHECK_OPTS='x' run_case lint\n+  run_case lint\n }\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_identifier_only_on_context_line_still_missing():
    """Mutation caught: `_added_lines` keeps every non-header line."""
    diff = _diff_3923_with("   SHELLCHECK_OPTS='x' run_case lint\n+  run_case other\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_identifier_in_file_header_line_still_missing():
    """Mutation caught: the `+++ ` exclusion dropped from `_added_lines`."""
    diff = ("diff --git a/lib/SHELLCHECK_OPTS b/lib/SHELLCHECK_OPTS\n"
            "--- a/lib/SHELLCHECK_OPTS\n+++ b/lib/SHELLCHECK_OPTS\n"
            "@@ -1,1 +1,2 @@\n context\n+unrelated line\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_identifier_substring_still_missing():
    """Mutation caught: the token-boundary lookarounds dropped from the identifier pattern."""
    diff = _diff_3923_with("+SHELLCHECK_OPTS_EXTRA=1\n+MY_SHELLCHECK_OPTS=1\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_identifier_absent_from_diff_still_missing():
    """Mutation caught: `if content_ident:` replaced with `if True:`."""
    diff = _diff_3923_with("+echo unrelated\n+run_case lint\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_diff_text_none_still_missing():
    """Mutation caught: `content_ident` initialised to `"MUTANT"`."""
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=None)
    _assert_one_missing(items, 3)


def test_diff_text_empty_still_missing():
    """Mutation caught: `content_ident` initialised to `"MUTANT"`."""
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text="")
    _assert_one_missing(items, 3)


def test_added_lines_error_still_missing(monkeypatch):
    """Mutation caught: `content_ident = "MUTANT"` in the except branch."""
    def _boom(_diff):
        raise RuntimeError("diff unreadable")
    monkeypatch.setattr(conformance, "_clearing_added_lines", lambda *_a: _boom(None))
    items, notes = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=DIFF_3923)
    _assert_one_missing(items, 3)
    errors = [n for n in notes if n.startswith("CONTENT-MATCH-ERROR: 3 ")]
    assert len(errors) == 1
    assert "RuntimeError" in errors[0]


def test_other_phase_file_does_not_clear_phase():
    """Mutation caught: other-phase files not excluded from the clearing lines."""
    content = ("## Phase 2: Handle SHELLCHECK_OPTS in `bin/lib/prefire-verify`\n\nBody.\n\n"
               + SHELLCHECK_3923)
    diff = ("diff --git a/bin/lib/prefire-verify b/bin/lib/prefire-verify\n"
            "--- a/bin/lib/prefire-verify\n+++ b/bin/lib/prefire-verify\n"
            "@@ -1,1 +1,2 @@\n context\n+SHELLCHECK_OPTS=x\n")
    items, _ = _run(content, ["bin/lib/prefire-verify"], TRACKED_3923, diff_text=diff)
    assert [i for i in items if i.startswith("MISSING-PHASE: 3 ")]


def test_doc_only_identifier_does_not_clear_phase():
    """Mutation caught: prose/fixture files not excluded from the clearing lines."""
    diff = ("diff --git a/docs/notes.md b/docs/notes.md\n"
            "--- a/docs/notes.md\n+++ b/docs/notes.md\n"
            "@@ -1,1 +1,2 @@\n context\n+Uses SHELLCHECK_OPTS here\n")
    items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_generic_heading_words_do_not_clear():
    """Mutation caught: `_IDENTIFIER_JOIN` set to `.` (any character)."""
    content = ("## Phase 3: Extend README GitHub cases\n\n"
               "CI runs the harness through `.github/workflows/harness-legs.txt`; no edit there.\n")
    diff = _diff_3923_with("+Extend README GitHub cases\n")
    items, _ = _run(content, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_short_underscore_word_does_not_clear():
    """Mutation caught: `_IDENTIFIER_SHAPE` floor lowered from `{5,}` to `{1,}`."""
    content = ("## Phase 3: Wire a_b_c\n\n"
               "CI runs the harness through `.github/workflows/harness-legs.txt`; no edit there.\n")
    diff = _diff_3923_with("+a_b_c=1\n")
    items, _ = _run(content, CHANGED_3923, TRACKED_3923, diff_text=diff)
    _assert_one_missing(items, 3)


def test_boxscore_3946_body_only_identifier_does_not_clear():
    """Mutation caught: `_IDENTIFIER_JOIN` set to `.` (any character)."""
    diff = (f"diff --git a/{BOXSCORE_CLASS} b/{BOXSCORE_CLASS}\n"
            f"--- a/{BOXSCORE_CLASS}\n+++ b/{BOXSCORE_CLASS}\n"
            "@@ -1,0 +1,1 @@\n"
            "+    $row['game_date'] = $date; // migration driver helper\n")
    items, _ = _run(BOXSCORE_PHASE_3, [BOXSCORE_CLASS], BOXSCORE_TRACKED, diff_text=diff)
    _assert_one_missing(items, 3)


def test_default_call_matches_explicit_none():
    """Mutation caught: the `diff_text` keyword default changed to `"+SHELLCHECK_OPTS"`.

    A headerless default is dropped by `_added_lines_by_file`, so the call alone cannot
    tell it from None; the signature check pins the default itself."""
    assert inspect.signature(phase_omission_items).parameters["diff_text"].default is None
    notes: list[str] = []
    default_items = phase_omission_items(_plan_with_phases(SHELLCHECK_3923), CHANGED_3923,
                                         tracked_files=TRACKED_3923, notes=notes)
    none_items, _ = _run(SHELLCHECK_3923, CHANGED_3923, TRACKED_3923, diff_text=None)
    _assert_one_missing(default_items, 3)
    assert default_items == none_items


def test_check_forwards_diff_body_to_phase_matcher():
    """Mutation caught: `check()` passes `diff_text=None` instead of its `diff_body`."""
    plan = _plan_with_phases(SHELLCHECK_3923)
    with_diff = check(plan, CHANGED_3923, diff_body=DIFF_3923, tracked_files=TRACKED_3923)
    without = check(plan, CHANGED_3923, tracked_files=TRACKED_3923)
    assert [i for i in with_diff if i.startswith("MISSING-PHASE:")] == []
    phase_items = [i for i in without if i.startswith("MISSING-PHASE:")]
    assert len(phase_items) == 1
    assert phase_items[0].startswith("MISSING-PHASE: 3 ")


def test_heading_identifiers_accepts_decorated_forms():
    """Mutation caught: `if tok.endswith("()"):` replaced with `if False:`."""
    assert _heading_identifiers(["stage_pytest()", "$PREFIRE_PYTEST_CMD", "SHELLCHECK_OPTS"]) == [
        "stage_pytest", "PREFIRE_PYTEST_CMD", "SHELLCHECK_OPTS"]


def test_heading_identifiers_rejects_near_misses():
    """Mutation caught: `_IDENTIFIER_SHAPE` set to `^.{6,}$`."""
    assert _heading_identifiers([
        "cases", "HOME", "README", "GitHub", "phaseOmission", "a_b_c", "_touched",
        "local-DB", "2026_11_14", "--stage"]) == []
