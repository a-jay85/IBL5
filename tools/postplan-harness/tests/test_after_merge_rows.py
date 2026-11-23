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


def test_what_cell_post_merge_text_stays_manual():
    row = (
        "| 5 | confirm post-merge: the importer reads the file"
        " | Truly-manual | post-impl | n/a |\n"
    )
    _, manual, post_merge = parse_matrix(HDR + row)
    assert [r.number for r in manual] == ["5"]
    assert post_merge == []


def test_post_merge_row_outside_typed_table_yields_no_row():
    other = (
        "\n## Notes\n\n| # | Item | Type | Timing | Where |\n|---|---|---|---|---|\n"
        "| 5 | operator checks the nightly job | Truly-manual | post-impl"
        " | post-merge: launchd scheduling on the live host only fires after deploy |\n"
    )
    _, manual, post_merge = parse_matrix(HDR + PLAIN + other)
    assert [r.number for r in manual] == ["29"]
    assert post_merge == []


# --- Writer side: plan `## After merge` section -> tagged PR-body rows (ADR-0198) -------

import subprocess  # noqa: E402

from harness.classify import render_after_merge_block  # noqa: E402
from harness.manual_rows import ManualRow  # noqa: E402
from harness.planfile import parse_after_merge_section  # noqa: E402

REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__)))))


def _row(num, text, tag=""):
    return ManualRow(number=num, text=text, raw="", post_merge_reason="plan After merge section", tag=tag)


def _steps(tmp_path, *names):
    d = tmp_path / "steps"
    d.mkdir(exist_ok=True)
    for n in names:
        (d / n).write_text("#!/bin/bash\n")
    return str(d)


def test_render_after_merge_tagged_row(tmp_path):
    block = render_after_merge_block([_row("1", "run the step", "auto:mac ok-step")],
                                     steps_dir=_steps(tmp_path, "ok-step"))
    assert "- **Row 1** [auto:mac ok-step] — run the step" in block
    assert "bin/post-merge-run" in block


def test_render_after_merge_untagged_unchanged():
    rows = [_row("28", "set the secret")]
    block = render_after_merge_block(rows)
    assert "- **Row 28** — set the secret" in block
    assert "[" not in block.split("## After merge", 1)[1].split("-->")[0].replace("<!--", "")
    assert "bin/post-merge-run" not in block
    assert render_after_merge(rows) == "- **Row 28** — set the secret"


def test_missing_step_fails_closed_to_manual(tmp_path):
    block = render_after_merge_block([_row("1", "run it", "auto:mac ghost")],
                                     steps_dir=_steps(tmp_path, "other"))
    assert "- **Row 1** [manual] — run it" in block
    assert "auto:mac" not in block


def test_auto_ci_rewritten_to_manual(tmp_path):
    block = render_after_merge_block([_row("1", "run it", "auto:ci some-check")],
                                     steps_dir=_steps(tmp_path, "some-check"))
    assert "- **Row 1** [manual] — run it" in block
    assert "auto:ci" not in block


def test_rendered_rows_parse_in_bash(tmp_path):
    block = render_after_merge_block(
        [_row("1", "run it", "auto:mac ok-step"), _row("2", "do it by hand")],
        steps_dir=_steps(tmp_path, "ok-step"))
    f = tmp_path / "body.md"
    f.write_text(block + "\n")
    script = ('source bin/lib/post-merge-rows.sh; pmr_rows "$1" | while IFS= read -r l; do '
              'pmr_parse_row "$l"; echo "$PMR_KIND $PMR_NAME $PMR_VALID $PMR_TEXT"; done')
    out = subprocess.run(["bash", "-c", script, "_", str(f)], cwd=REPO_ROOT,
                         capture_output=True, text=True, check=True).stdout.splitlines()
    # PMR_TEXT keeps the em-dash separator, so a render that changes it no longer matches.
    assert out == ["auto:mac ok-step 1 — run it", "manual  1 — do it by hand"]


def test_after_merge_section_skips_fences():
    content = ("## After merge\n\n- **Row 1** [manual] — real\n\n```\n"
               "- **Row 2** [manual] — fenced\n```\n")
    rows = parse_after_merge_section(content)
    assert [r.number for r in rows] == ["1"]


def test_after_merge_section_link_text_not_tag():
    rows = parse_after_merge_section(
        "## After merge\n\n- **Row 1** [see the runbook](https://example.com) — open it\n"
        "- **Row 2** [auto:mac ok-step] — run it\n")
    assert rows[0].tag == ""
    assert rows[0].text.startswith("[see the runbook](https://example.com)")
    assert rows[1].tag == "auto:mac ok-step"
    assert rows[1].text == "run it"


def test_after_merge_section_stops_at_next_heading():
    rows = parse_after_merge_section(
        "## After merge\n\n- **Row 1** [manual] — in\n\n## Other\n\n- **Row 2** [manual] — out\n")
    assert [r.number for r in rows] == ["1"]


# --- Parser parity: planfile.py and bin/lib/post-merge-rows.sh read the same rows ------
# bin/post-merge-run calls pmr_rows, so the bash side below covers it too.

_PARITY_BODIES = {
    "tilde-fence": ("~~~\n## After merge\n- **Row 3** [auto:mac foo] — x\n~~~\n"
                    "## After merge\n- **Row 4** [manual] — real\n"),
    "info-string-inside-backticks": ("## After merge\n```\n- **Row 1** [manual] — fenced\n"
                                     "```python\n- **Row 2** [manual] — still fenced\n```\n"
                                     "- **Row 3** [manual] — real\n"),
    "nested-4-backtick": ("## After merge\n````\n```\n- **Row 1** [manual] — fenced\n```\n"
                          "- **Row 2** [manual] — fenced\n````\n- **Row 3** [manual] — real\n"),
    "tilde-closes-only-tilde": ("## After merge\n~~~\n```\n- **Row 1** [manual] — fenced\n~~~\n"
                                "- **Row 2** [manual] — real\n"),
    "end-anchor": ("## After merge\n- **Row 1** [manual] — in\n<!-- after-merge:end -->\n"
                   "- **Row 2** [manual] — after the anchor\n"
                   "## After merge\n- **Row 3** [manual] — second section after anchor\n"),
    "next-heading-closes": ("## After merge\n- **Row 1** [manual] — in\n## Other\n"
                            "- **Row 2** [manual] — out\n## After merge\n- **Row 3** [manual] — in again\n"),
    "back-to-back-headings": ("## After merge\n- **Row 1** [manual] — in\n## After merge\n"
                              "- **Row 2** [manual] — sed closed the range here\n"),
    "blockquote-fence": "## After merge\n> ```\n- **Row 1** [manual] — real\n> ```\n",
    "checkbox-and-link": ("## After merge\n- [ ] **Row 1** [manual] — box\n"
                          "- **Row 2** [see](https://example.com) — link\n"),
}


@pytest.mark.parametrize("name", sorted(_PARITY_BODIES))
def test_after_merge_parsers_agree(tmp_path, name):
    body = _PARITY_BODIES[name]
    f = tmp_path / "body.md"
    f.write_text(body)
    bash_rows = subprocess.run(
        ["bash", "-c", '. bin/lib/post-merge-rows.sh; pmr_rows "$1"', "_", str(f)],
        cwd=REPO_ROOT, capture_output=True, text=True, check=True).stdout.splitlines()
    py_rows = [r.raw for r in parse_after_merge_section(body)]
    assert py_rows == bash_rows
    if name == "tilde-fence":
        assert py_rows == ["- **Row 4** [manual] — real"]
