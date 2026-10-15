"""Parse split and normalizer flag for `post-merge:` Truly-manual rows.

A tagged row leaves truly_manual_rows (the Phase 6.5 condition (1) window) and
lands in post_merge_rows, which render under ## After merge as plain bullets.
"""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.manual_rows import main, render_after_merge, row_from_cells
from harness.planfile import parse_matrix

HDR = (
    "## Verification Matrix\n\n"
    "| # | What to verify | Test type | Timing | Test file / location |\n"
    "|---|---|---|---|---|\n"
)
TAGGED = (
    "| 28 | the operator reads the first nightly queue listing the morning after"
    " | Truly-manual | post-impl"
    " | Post-merge: launchd scheduling on the live host only fires after deploy |\n"
)
PLAIN = (
    "| 29 | the exported CSV opens in Excel without a repair prompt"
    " | Truly-manual | post-impl | n/a |\n"
)


def _plan(tmp_path, body):
    p = tmp_path / "plan.md"
    p.write_text(body)
    return str(p)


def test_parse_matrix_splits_tagged_row():
    _, manual, post_merge = parse_matrix(HDR + TAGGED + PLAIN)
    assert len(manual) == 1
    assert len(post_merge) == 1
    assert post_merge[0].number == "28"
    assert post_merge[0].post_merge_reason == (
        "launchd scheduling on the live host only fires after deploy"
    )
    assert manual[0].number == "29"


def test_untagged_truly_manual_row_stays_manual():
    _, manual, post_merge = parse_matrix(HDR + PLAIN)
    assert [r.number for r in manual] == ["29"]
    assert post_merge == []
    assert manual[0].post_merge_reason == ""


def test_tagged_row_inside_fence_is_ignored():
    body = HDR + PLAIN + "\n```markdown\n" + HDR + TAGGED + "```\n"
    _, manual, post_merge = parse_matrix(body)
    assert [r.number for r in manual] == ["29"]
    assert post_merge == []


def test_render_after_merge_has_no_checkbox():
    row = row_from_cells(
        [c.strip() for c in TAGGED.strip().strip("|").split("|")], 1,
    )
    out = render_after_merge([row])
    assert "- [ ]" not in out
    assert out.startswith("- **Row ")
    assert "launchd scheduling" in out


def test_normalize_default_excludes_tagged_rows(tmp_path, capsys):
    assert main([_plan(tmp_path, HDR + TAGGED + PLAIN)]) == 0
    out = capsys.readouterr().out
    assert "**Row 29**" in out
    assert "Row 28" not in out


def test_normalize_after_merge_flag_prints_only_tagged_rows(tmp_path, capsys):
    assert main(["--after-merge", _plan(tmp_path, HDR + TAGGED + PLAIN)]) == 0
    out = capsys.readouterr().out
    assert out.strip().splitlines() == [
        line for line in out.strip().splitlines() if line.startswith("- **Row 28**")
    ]
    assert "Row 29" not in out
    assert "[ ]" not in out


def test_normalize_after_merge_without_tagged_rows_prints_nothing(tmp_path, capsys):
    assert main(["--after-merge", _plan(tmp_path, HDR + PLAIN)]) == 0
    assert capsys.readouterr().out == ""


def test_normalize_unknown_flag_exits_2(tmp_path, capsys):
    assert main(["--bogus", _plan(tmp_path, HDR + PLAIN)]) == 2
    assert "unknown flag" in capsys.readouterr().err
