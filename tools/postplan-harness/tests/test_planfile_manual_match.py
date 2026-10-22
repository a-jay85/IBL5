"""parse_matrix manual-row match is limited to typed tables with a whole label cell.

Pins a-jay85/IBL5-backlog#1490. The old parser matched any unfenced table row
mentioning the label, which counted 128 rows over the local plan corpus. 28 of
those were phantoms: prose-table rows and CLI-executable matrix rows that only
mention the words. The fix keeps the 100 real rows.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.planfile import parse_matrix

_HDR = (
    "## Verification Matrix\n\n"
    "| # | What to verify | Test type | Timing | Test file / location |\n"
    "|---|---|---|---|---|\n"
)


def test_case_table_outside_matrix_yields_no_manual_row():
    # Live phantom: discord-plan-review-1-digest.md `| Case | Assertion |` table
    # (mention only), plus a whole `Truly-manual` cell in a `| # | Item | Type |`
    # table that has neither a What-to-verify nor a Test-type header cell.
    content = (
        "## Notes\n\n| Case | Assertion |\n|---|---|\n"
        "| **NEG** foreign table | a comparison table elsewhere in the plan with a "
        "`Truly-manual` cell and no *what to verify* header → **absent** from stdout |\n"
        "\n| # | Item | Type | Timing | Where |\n|---|---|---|---|---|\n"
        "| 5 | operator checks the nightly job | Truly-manual | post-impl | n/a |\n"
    )
    _, manual, post_merge = parse_matrix(content)
    assert manual == [] and post_merge == []


def test_cli_executable_matrix_row_mentioning_truly_manual_yields_none():
    # Live phantom: trim-plan-hold-criteria-lore.md matrix row 24.
    content = _HDR + (
        "| 24 | `Truly-manual` still present in SKILL.md after all edits | CLI-executable "
        "| post-impl | `grep -qF 'Truly-manual' .claude/skills/plan/SKILL.md && echo PRESENT` |\n"
    )
    _, manual, post_merge = parse_matrix(content)
    assert manual == [] and post_merge == []


def test_bold_truly_manual_type_cell_still_yields_row():
    content = _HDR + (
        "| 3 | does the grid look right | **Truly-manual** | post-impl "
        "| manual (reviewer walkthrough) |\n"
    )
    _, manual, _ = parse_matrix(content)
    assert [r.number for r in manual] == ["3"]


def test_shifted_column_matrix_still_yields_row():
    # An escaped pipe in the What cell pushes the type cell from index 2 to 3.
    content = _HDR + (
        "| 7 | does the a \\| b toggle feel right | Truly-manual | post-impl "
        "| manual (reviewer walkthrough) |\n"
    )
    _, manual, _ = parse_matrix(content)
    assert [r.number for r in manual] == ["7"]


def test_typed_header_without_what_to_verify_keeps_row_and_skips_post_merge():
    content = (
        "## Matrix\n\n| # | Item | Test type | Timing | Where |\n|---|---|---|---|---|\n"
        "| 9 | operator checks the nightly job | Truly-manual | post-impl "
        "| post-merge: launchd scheduling on the live host only fires after deploy |\n"
    )
    _, manual, post_merge = parse_matrix(content)
    assert [r.number for r in manual] == ["9"]
    assert post_merge == []
