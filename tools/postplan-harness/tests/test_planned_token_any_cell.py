"""Any-cell planned-token gate (backlog#1501). Each test is named for the behavior it pins."""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import check
from harness.planfile import (
    parse_matrix,
    parse_no_change_test_paths,
    parse_run_only_test_paths,
)
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
PHP = "tests/Foo/BarTest.php"
BARE_ROW = f"| 1 | `testFoo` rejects over-cap | PHPUnit | post-impl | `{PHP}` |\n"
RUN_ONLY_ROW = f"| 1 | `testFoo` rejects over-cap | PHPUnit | post-impl | `{PHP}` (run-only) |\n"


def _plan(body: str) -> PlanInfo:
    """Build a PlanInfo the way locate_plan does, from matrix text alone."""
    planned, manual, _ = parse_matrix(body)
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=planned,
                    truly_manual_rows=manual,
                    no_change_test_paths=parse_no_change_test_paths(body),
                    run_only_test_paths=parse_run_only_test_paths(body))


def test_bare_method_first_row_plans_location_path():
    assert parse_matrix(HDR + BARE_ROW)[0] == [PHP]


def test_command_first_row_plans_location_path():
    row = f"| 1 | `vendor/bin/phpunit --filter testFoo` passes | PHPUnit | post-impl | `{PHP}` |\n"
    assert parse_matrix(HDR + row)[0] == [PHP]


def test_every_backticked_token_in_cell_is_tried():
    row = f"| 1 | cap math | PHPUnit | post-impl | `testFoo` in `{PHP}` |\n"
    assert parse_matrix(HDR + row)[0] == [PHP]


def test_rightmost_valid_cell_wins():
    row = "| 1 | `tests/Old/OldTest.php` stays green | PHPUnit | post-impl | `tests/New/NewTest.php` |\n"
    assert parse_matrix(HDR + row)[0] == ["tests/New/NewTest.php"]


def test_row_with_no_valid_path_plans_nothing():
    row = "| 1 | `testFoo` and `npm test` | PHPUnit | post-impl | `FooTest::testBar` |\n"
    assert parse_matrix(HDR + row)[0] == []


def test_cli_executable_row_with_path_plans_nothing():
    row = f"| 1 | `testFoo` | CLI-executable | post-impl | `{PHP}` |\n"
    assert parse_matrix(HDR + row)[0] == []


def test_run_only_marker_on_location_token_exempts_bare_method_row():
    assert parse_run_only_test_paths(HDR + RUN_ONLY_ROW) == [PHP]


def test_conformance_missing_for_unwritten_location_test():
    items = check(_plan(HDR + BARE_ROW), ["ibl5/classes/Foo.php"])
    assert any(i.startswith("MISSING: " + PHP) for i in items), items


def test_conformance_run_only_bare_method_row_passes_when_tracked():
    items = check(_plan(HDR + RUN_ONLY_ROW), [], tracked_files=[PHP])
    assert not any(i.startswith("MISSING:") for i in items), items
