import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.manual_rows import ManualRow, _assert_no_sentinel, render_rows, row_from_cells
from harness.planfile import parse_matrix

FIXTURE = os.path.join(os.path.dirname(__file__), "fixtures", "manual-matrix-plan.md")
REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))


def test_five_column_house_row_drops_classification_and_timing():
    # 5-col: # | what | test-type | timing | location
    cells = ["3", "UI looks correct", "Truly-manual", "post-impl", "Dashboard"]
    row = row_from_cells(cells, 3)
    rendered = render_rows([row])
    assert rendered == "- [ ] **Row 3** — UI looks correct — Dashboard"
    assert "Truly-manual" not in rendered
    assert "post-impl" not in rendered


def test_legacy_three_column_row_uses_ordinal():
    cells = ["looks right", "Truly-manual", "eyeball the dashboard"]
    row = row_from_cells(cells, 2)
    rendered = render_rows([row])
    assert rendered.startswith("- [ ] **Row 2** — ")
    assert "Truly-manual" not in rendered


def test_all_empty_nontext_cells_fallback_to_raw():
    cells = ["1", "", "-", "n/a"]
    row = row_from_cells(cells, 1)
    assert row.text == row.raw


def test_em_dash_in_cell_survives():
    cells = ["5", "Check — em dash works", "Truly-manual", "post-impl", "UI"]
    row = row_from_cells(cells, 5)
    rendered = render_rows([row])
    assert "em dash" in rendered


def test_empty_rows_yields_empty_string():
    assert render_rows([]) == ""


def test_fixture_yields_exactly_two_rows():
    with open(FIXTURE) as fh:
        content = fh.read()
    _, manual_raw = parse_matrix(content)
    rows = list(manual_raw)
    assert len(rows) == 2
    rendered = render_rows(rows)
    lines = [l for l in rendered.splitlines() if l.strip()]
    assert len(lines) == 2
    assert all(l.startswith("- [ ] **Row ") for l in lines)


def test_assert_no_sentinel_raises():
    with pytest.raises(ValueError, match="sentinel"):
        _assert_no_sentinel("No manual testing needed\n")


def test_cli_parity_with_in_process():
    with open(FIXTURE) as fh:
        content = fh.read()
    _, manual_raw = parse_matrix(content)
    rows = list(manual_raw)
    expected = render_rows(rows)

    script = os.path.join(REPO_ROOT, "bin", "normalize-manual-testing")
    result = subprocess.run(
        [script, FIXTURE],
        capture_output=True, text=True, check=True,
    )
    assert result.stdout.rstrip("\n") == expected
