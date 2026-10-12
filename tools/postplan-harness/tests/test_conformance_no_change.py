"""`(no-change)` matrix marker: parser and conformance behaviour (backlog#1222).

Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import check
from harness.planfile import parse_matrix, parse_no_change_test_paths
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
SNAP = "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots"
VR_MARKED = f"| 1 | baselines unchanged | Visual-regression | post-impl | `{SNAP}` (no-change) |\n"
VR_UNMARKED = f"| 1 | baselines unchanged | Visual-regression | post-impl | `{SNAP}` |\n"
PHP_ROW = "| 2 | cap math | PHPUnit | post-impl | `tests/Trade/TradeValidatorTest.php` |\n"


def _plan(body: str) -> PlanInfo:
    """Build a PlanInfo the way locate_plan does, from matrix text alone."""
    planned, manual = parse_matrix(body)
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=planned,
                    truly_manual_rows=manual,
                    no_change_test_paths=parse_no_change_test_paths(body))


# --- Parser tests (matrix rows 1 to 8) ---

def test_marked_vr_row_is_exempt():
    body = HDR + VR_MARKED
    assert parse_no_change_test_paths(body) == [SNAP]
    assert parse_matrix(body)[0] == [SNAP]


def test_unmarked_vr_row_is_not_exempt():
    assert parse_no_change_test_paths(HDR + VR_UNMARKED) == []


def test_marker_on_phpunit_row_is_ignored():
    for kind, tok in (("PHPUnit", "tests/Foo/BarTest.php"),
                      ("API-test", "tests/Api/FooTest.php"),
                      ("E2E", "tests/e2e/foo.spec.ts")):
        body = HDR + f"| 1 | x | {kind} | post-impl | `{tok}` (no-change) |\n"
        assert parse_no_change_test_paths(body) == []
        assert parse_matrix(body)[0] == [tok]


def test_description_mentioning_visual_regression_does_not_qualify():
    body = HDR + "| 1 | visual regression spec passes | E2E | post-impl | `x/y.spec.ts` (no-change) |\n"
    assert parse_no_change_test_paths(body) == []


def test_token_shared_with_unmarked_row_stays_planned():
    body = HDR + VR_MARKED + VR_UNMARKED.replace("| 1 |", "| 3 |")
    assert parse_no_change_test_paths(body) == []
    body = HDR + VR_MARKED + PHP_ROW.replace("tests/Trade/TradeValidatorTest.php", SNAP)
    assert parse_no_change_test_paths(body) == []


def test_marker_inside_fence_is_not_honored():
    fenced = "\n```markdown\n" + HDR + VR_MARKED + "```\n"
    assert parse_no_change_test_paths(HDR + VR_UNMARKED + fenced) == []
    only_fenced = "intro\n" + fenced
    assert parse_no_change_test_paths(only_fenced) == []
    assert parse_matrix(only_fenced)[0] == []


def test_marker_must_follow_the_rows_own_token():
    body = HDR + ("| 1 | x | Visual-regression | post-impl | "
                  "`a/b.spec.ts` and `c/d.spec.ts-snapshots` (no-change) |\n")
    assert parse_no_change_test_paths(body) == []


def test_marker_is_case_insensitive_and_whitespace_tolerant():
    body = HDR + f"| 1 | x | Visual-regression | post-impl | `{SNAP}`   (No-Change) |\n"
    assert parse_no_change_test_paths(body) == [SNAP]


# --- Conformance tests (matrix rows 9 to 14) ---

def test_check_clears_marked_vr_row_with_css_only_diff():
    assert check(_plan(HDR + VR_MARKED), ["ibl5/design/tables.css"]) == []


def test_check_still_reports_unmarked_planned_test():
    items = check(_plan(HDR + VR_UNMARKED), ["ibl5/design/tables.css"])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + SNAP)


def test_marked_row_does_not_silence_other_rows():
    items = check(_plan(HDR + VR_MARKED + PHP_ROW), [])
    assert items == ["MISSING: tests/Trade/TradeValidatorTest.php (matrix planned a test the diff never wrote)"]


def test_marker_on_non_vr_row_still_missing():
    body = HDR + "| 1 | x | PHPUnit | post-impl | `tests/Foo/BarTest.php` (no-change) |\n"
    items = check(_plan(body), [])
    assert len(items) == 1
    assert items[0].startswith("MISSING: tests/Foo/BarTest.php")


def test_shared_token_with_unmarked_row_stays_missing():
    body = HDR + VR_MARKED + VR_UNMARKED.replace("| 1 |", "| 3 |")
    items = check(_plan(body), [])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + SNAP)


def test_default_plan_info_has_no_exemptions():
    assert PlanInfo().no_change_test_paths == []
    items = check(PlanInfo(found=True, has_matrix=True, planned_test_paths=[SNAP]), [])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + SNAP)
