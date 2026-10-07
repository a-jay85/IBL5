"""Matrix Test-type matching: `_planned_token` reads a WHOLE label cell.

Each test is named for the mutation it kills. test_conformance_no_change.py also
calls `_planned_token` indirectly and must stay green with the cells signature.
"""
import os
import re
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import planfile
from harness.planfile import parse_matrix, parse_no_change_test_paths

HDR = ("| # | What to verify | Test type | Timing | Test file / location |\n"
       "|---|---|---|---|---|\n")
TYPE_HDR = ("| # | What to verify | Type | Timing | Where |\n"
            "|---|---|---|---|---|\n")

# bug-pipeline-e2e-guard-tests row 10: the location cell ends in `bin/test-bug-pipeline-e2e`,
# whose trailing `e2e` the old whole-row `\bE2E\b` search read as an E2E row.
ROW10 = (
    "| 10 | Harness never writes the shared `/tmp/bug-pipeline-test-env.sh` or "
    "`/tmp/bug-pipeline-test-stubs/`. Mutation: point `bpe_env` at "
    "`/tmp/bug-pipeline-test-env.sh` ⇒ the listing changes ⇒ row fails "
    "| CLI-executable | post-impl | `b=$(ls -ld /tmp/bug-pipeline-test-env.sh "
    "/tmp/bug-pipeline-test-stubs 2>&1); bin/test-bug-pipeline-e2e >/dev/null; "
    "a=$(ls -ld /tmp/bug-pipeline-test-env.sh /tmp/bug-pipeline-test-stubs 2>&1); "
    "[ \"$b\" = \"$a\" ] # (wired in this PR)` |\n")

# draft-year-filter-24h-timestamp.md row 20 shape: CLI row whose prose names a real PHPUnit file.
CLI_PHPUNIT_PROSE = (
    "| 20 | Making `$clock` required breaks `new DraftController(...)` in "
    "`ibl5/tests/Draft/DraftControllerTest.php` | CLI-executable | post-impl | "
    "`vendor/bin/phpunit tests/Draft/` |\n")

# A cell that STARTS with the label but continues as prose (corpus: `E2E failures ...`).
PROSE_CELL_E2E = (
    "| 3 | E2E failures stay red | CLI-executable | post-impl | "
    "`ibl5/tests/e2e/smoke/home.spec.ts` |\n")

SNAP = "ibl5/tests/e2e/smoke/visual-regression.spec.ts-snapshots"
PHP_PATH = "tests/Trade/TradeValidatorTest.php"
E2E_PATH = "ibl5/tests/e2e/security/trade-anon.spec.ts"

PHP_ROW = f"| 1 | cap math | PHPUnit | post-impl | `{PHP_PATH}` |\n"
API_ROW = "| 2 | 403 on foreign team | API-test | post-impl | `ibl5/tests/Api/RouterTest.php` |\n"
# The description names the runner script WITHOUT backticks so the spec stays the first
# backticked test-ish token; the fixture isolates the type-cell question.
E2E_ROW = ("| 3 | anon POST is bounced; also exercised by bin/test-trade-e2e | E2E | post-impl | "
           f"`{E2E_PATH}` |\n")
VR_ROW = f"| 4 | baseline unchanged | Visual-regression | post-impl | `{SNAP}` |\n"
BOLD_ROW = "| 5 | cap math | **PHPUnit** | post-impl | `tests/Trade/BoldTest.php` |\n"
# `\|\|` in the description splits into extra cells, so the label sits at index 3+, not 2.
SHIFTED_ROW = ("| 6 | handles `a \\|\\| b` | PHPUnit | post-impl | "
               "`tests/Trade/ShiftedTest.php` |\n")
VR_NO_CHANGE = f"| 7 | baselines unchanged | Visual-regression | post-impl | `{SNAP}` (no-change) |\n"


# --- Red on the old whole-row match ---

def test_row_10_cli_row_containing_e2e_plans_nothing():
    assert parse_matrix(HDR + ROW10)[0] == []


def test_cli_row_whose_prose_names_a_phpunit_file_plans_nothing():
    assert parse_matrix(HDR + CLI_PHPUNIT_PROSE)[0] == []


def test_cell_that_starts_with_label_then_prose_plans_nothing():
    assert parse_matrix(HDR + PROSE_CELL_E2E)[0] == []


def test_no_change_parser_agrees_on_row_10():
    body = HDR + ROW10 + VR_NO_CHANGE
    assert parse_no_change_test_paths(body) == [SNAP]
    assert parse_matrix(body)[0] == [SNAP]


# --- Characterization: real test rows keep planning ---

def test_phpunit_label_cell_plans_path():
    assert parse_matrix(HDR + PHP_ROW)[0] == [PHP_PATH]


def test_api_test_label_cell_plans_path():
    assert parse_matrix(HDR + API_ROW)[0] == ["ibl5/tests/Api/RouterTest.php"]


def test_e2e_row_with_e2e_script_in_description_still_plans_spec():
    assert parse_matrix(HDR + E2E_ROW)[0] == [E2E_PATH]


def test_visual_regression_label_cell_plans_path():
    assert parse_matrix(HDR + VR_ROW)[0] == [SNAP]


def test_bold_type_cell_plans_path():
    """A bold label cell (`**…**`) plans: `*` must stay in the `_norm_cell` strip set."""
    assert parse_matrix(HDR + BOLD_ROW)[0] == ["tests/Trade/BoldTest.php"]


def test_shifted_column_row_plans_path():
    assert parse_matrix(HDR + SHIFTED_ROW)[0] == ["tests/Trade/ShiftedTest.php"]


def test_type_header_matrix_plans_path():
    assert parse_matrix(TYPE_HDR + PHP_ROW)[0] == [PHP_PATH]


@pytest.mark.parametrize("label", [
    "PHPUnit (database)",
    "PHPUnit DB-integration",
    "PHPUnit-analogue (pytest)",
    "PHPUnit (#[Group('database')])",
    "E2E + Visual-regression",
    "E2E / CLI-executable",
    "PHPUnit — stub repo",
])
def test_label_with_corpus_suffix_plans_path(label):
    row = f"| 9 | x | {label} | post-impl | `tests/Trade/SuffixTest.php` |\n"
    assert parse_matrix(HDR + row)[0] == ["tests/Trade/SuffixTest.php"]


def test_no_change_parser_agrees_with_matrix_on_real_rows():
    body = HDR + PHP_ROW + E2E_ROW + VR_NO_CHANGE
    no_change = parse_no_change_test_paths(body)
    planned = parse_matrix(body)[0]
    assert set(no_change) <= set(planned)
    assert SNAP in no_change
    assert SNAP in planned


# --- Mutation proof without editing production source ---

def test_whole_row_mutant_replans_row_10_phantom(monkeypatch):
    """Reverting to the whole-row type match brings the row-10 phantom back.

    Proves test_row_10_cli_row_containing_e2e_plans_nothing is sensitive to the
    exact mutation this change removes.
    """
    old = re.compile(r"\b(PHPUnit|API.?test|E2E|Visual.?regression)\b", re.I)
    monkeypatch.setattr(planfile, "_has_planning_type_cell",
                        lambda cells: bool(old.search(" | ".join(cells))))
    assert parse_matrix(HDR + ROW10)[0] == ["/tmp/bug-pipeline-test-env.sh"]
