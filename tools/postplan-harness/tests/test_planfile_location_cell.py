"""Matrix token choice: `_planned_token` prefers the rightmost valid cell.

Each test is named for the mutation it kills. The planning gate (whole-cell Test type
plus a valid test path in any cell, backlog#1501) decides whether a row plans; this file pins which token it plans.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.conformance import check
from harness.planfile import (
    _planned_token,
    parse_matrix,
    parse_no_change_test_paths,
    parse_run_only_test_paths,
)
from harness.state import PlanInfo

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")

SEED = "ibl5/tests/e2e/fixtures/ci-seed.sql"
SNAP = "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots"
PHP = "tests/Foo/BarTest.php"
OTHER_PHP = "tests/Old/SeedTest.php"

# record-holders-bullets-logo-abbr row 8: seed cited as evidence, baseline is the planned token.
VR_SEED_ROW = (f"| 8 | RecordHolders screenshots stay byte-identical because the CI seed (`{SEED}`) "
               f"has no team 27 rows | Visual-regression | post-impl | `{SNAP}` (no-change) |\n")
VR_SEED_ROW_UNMARKED = VR_SEED_ROW.replace(" (no-change)", "").replace("| 8 |", "| 9 |")
DESC_ONLY_ROW = f"| 1 | `{PHP}` covers the cap math | PHPUnit | post-impl | see row 1 |\n"
LOCATION_COMMAND_ROW = (f"| 2 | `{PHP}` covers the cap math | PHPUnit | post-impl | "
                        "`cd ibl5 && composer test` |\n")
TRAILING_NOTES_ROW = (f"| 3 | regression of `{OTHER_PHP}` | PHPUnit | post-impl | `{PHP}` | "
                      "`testBarStillPasses` |\n")
METHOD_SUFFIX_ROW = (f"| 4 | seed at `{SEED}` gives 6 rows | PHPUnit | post-impl | "
                     f"`{PHP}::testSixRows` |\n")
RUN_ONLY_SEED_ROW = (f"| 5 | seed at `{SEED}` stays valid | PHPUnit | post-impl | "
                     f"`{PHP}` (run-only) |\n")
BARE_FIRST_ROW = f"| 6 | `testFoo` keeps passing | PHPUnit | post-impl | `{PHP}` |\n"
DUP_TOKEN_MARKER_ON_DESC_ROW = (f"| 7 | `{SNAP}` (no-change) stays byte-identical | "
                                f"Visual-regression | post-impl | `{SNAP}` |\n")


def _plan(body: str) -> PlanInfo:
    """Build a PlanInfo the way locate_plan does, from matrix text alone."""
    planned, manual, _ = parse_matrix(body)
    return PlanInfo(found=True, has_matrix=True, planned_test_paths=planned,
                    truly_manual_rows=manual,
                    no_change_test_paths=parse_no_change_test_paths(body),
                    run_only_test_paths=parse_run_only_test_paths(body))


def test_seed_in_description_plans_only_the_location_token():
    # Reverting _planned_token to the joined-row first match plans [SEED] here.
    body = HDR + VR_SEED_ROW
    assert parse_matrix(body)[0] == [SNAP]
    assert parse_no_change_test_paths(body) == [SNAP]


def test_seed_in_description_emits_no_missing_end_to_end():
    body = HDR + VR_SEED_ROW
    assert check(_plan(body), ["ibl5/modules/RecordHolders/RecordHoldersRenderer.php"]) == []


def test_description_only_path_is_still_planned():
    assert parse_matrix(HDR + DESC_ONLY_ROW)[0] == [PHP]


def test_location_cell_command_falls_back_to_description_path():
    body = HDR + LOCATION_COMMAND_ROW
    assert parse_matrix(body)[0] == [PHP]
    cells = [c.strip() for c in LOCATION_COMMAND_ROW.strip().strip("|").split("|")]
    assert _planned_token(cells) == PHP


def test_trailing_notes_cell_does_not_hide_the_location_token():
    assert parse_matrix(HDR + TRAILING_NOTES_ROW)[0] == [PHP]


def test_method_suffix_token_is_planned_whole_and_resolves_by_file():
    body = HDR + METHOD_SUFFIX_ROW
    assert parse_matrix(body)[0] == [f"{PHP}::testSixRows"]
    assert check(_plan(body), [PHP]) == []


def test_run_only_marker_on_location_token_exempts_it():
    body = HDR + RUN_ONLY_SEED_ROW
    assert parse_run_only_test_paths(body) == [PHP]
    assert parse_matrix(body)[0] == [PHP]
    assert check(_plan(body), ["ibl5/classes/Foo.php"], tracked_files=[PHP]) == []


def test_unmarked_row_sharing_the_token_still_wins():
    body = HDR + VR_SEED_ROW + VR_SEED_ROW_UNMARKED
    assert parse_no_change_test_paths(body) == []
    items = check(_plan(body), [])
    assert len(items) == 1
    assert items[0].startswith("MISSING: " + SNAP)
    assert not any("ci-seed" in item for item in items)


def test_bare_method_first_row_plans_location_path():
    cells = ["6", "`testFoo` keeps passing", "PHPUnit", "post-impl", f"`{PHP}`"]
    assert _planned_token(cells) == PHP
    assert parse_matrix(HDR + BARE_FIRST_ROW)[0] == [PHP]


def test_marker_after_description_copy_of_the_same_token_still_exempts():
    body = HDR + DUP_TOKEN_MARKER_ON_DESC_ROW
    assert parse_no_change_test_paths(body) == [SNAP]
    assert check(_plan(body), []) == []


def test_three_parsers_agree_on_the_chosen_token():
    vr = HDR + VR_SEED_ROW
    assert parse_matrix(vr)[0] == [SNAP]
    assert parse_no_change_test_paths(vr) == [SNAP]
    assert parse_run_only_test_paths(vr) == []
    ro = HDR + RUN_ONLY_SEED_ROW
    assert parse_matrix(ro)[0] == [PHP]
    assert parse_run_only_test_paths(ro) == [PHP]
    assert parse_no_change_test_paths(ro) == []
