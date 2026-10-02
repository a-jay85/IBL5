"""linkify_refs, digest_rows_for_display, and the merge-digest body block."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.classify import (MERGE_DIGEST_BEGIN, MERGE_DIGEST_END, render_merge_digest,
                              upsert_merge_digest)
from harness.fidelity import LABELS, digest_rows_for_display, linkify_refs

B = "https://github.com/a-jay85/IBL5-backlog/issues/"
P = "https://github.com/a-jay85/IBL5/pull/"


@pytest.mark.parametrize("text", [
    "backlog#1169", "Backlog#1211", "IBL5-backlog#1244", "a-jay85/IBL5-backlog#1213",
])
def test_backlog_forms(text):
    n = text.split("#")[1]
    assert linkify_refs(f"see {text}.") == f"see [{text}](<{B}{n}>)."


def test_backticked_backlog_ref_unwrapped():
    assert linkify_refs("`a-jay85/IBL5-backlog#1213`") == \
        f"[a-jay85/IBL5-backlog#1213](<{B}1213>)"


def test_pr_refs():
    assert linkify_refs("PR #2559 and #2561") == \
        f"PR [#2559](<{P}2559>) and [#2561](<{P}2561>)"


@pytest.mark.parametrize("text", [
    "#1 seed", "#3 overall", "#12 thing", "x#1234", "a/#1234", "&#1234;", "[#1234",
    "-#1234", "##1234", "plain text",
])
def test_untouched(text):
    assert linkify_refs(text) == text


def test_three_digit_pr_ref_links():
    assert linkify_refs("#123") == f"[#123](<{P}123>)"


def test_refs_inside_existing_links_untouched():
    t = "[PR #1234](https://x.test/#1234) and [backlog#99](https://x.test/backlog#1234)"
    assert linkify_refs(t) == t


def test_refs_inside_other_code_spans_untouched():
    t = "run `foo #1234` and `backlog#1234 now`"
    assert linkify_refs(t) == t


def test_label_preserved_and_idempotent():
    line = ("**Watch:** backlog#1169, `a-jay85/IBL5-backlog#1213`, PR #2559, #2561, "
            "`git log #1234`, [x](http://y/#1234), #1 seed")
    once = linkify_refs(line)
    assert once.startswith("**Watch:** [backlog#1169]")
    assert "`git log #1234`" in once and "#1 seed" in once
    assert linkify_refs(once) == once


def test_rows_for_display_adds_remediation_suffix():
    rows = [f"{lbl} x" for lbl in LABELS]
    out = digest_rows_for_display(rows, {"remediation_sha": "abc1234"})
    assert out[4].endswith("(post-plan remediation: abc1234)")
    assert out[:4] == rows[:4]
    assert digest_rows_for_display(rows, {}) == rows


ROWS = [f"{lbl} v" for lbl in LABELS]


def test_render_merge_digest_shape():
    block = render_merge_digest(ROWS)
    assert block.startswith(MERGE_DIGEST_BEGIN + "\n## Merge digest\n\n**What changed:** v\n\n")
    assert block.endswith("**Machine-authored fixes:** v\n" + MERGE_DIGEST_END)


def test_upsert_prepends_on_fresh_body():
    block = render_merge_digest(ROWS)
    assert upsert_merge_digest("\n\n## Summary\nx\n", block) == block + "\n\n## Summary\nx\n"


def test_upsert_replaces_in_place():
    first = upsert_merge_digest("## Summary\nx\n", render_merge_digest(ROWS))
    new = render_merge_digest([r + "2" for r in ROWS])
    second = upsert_merge_digest(first, new)
    assert second == new + "\n\n## Summary\nx\n"
    assert second.count(MERGE_DIGEST_BEGIN) == 1


@pytest.mark.parametrize("body", [
    MERGE_DIGEST_BEGIN + "\nhand text\n",
    "text\n" + MERGE_DIGEST_END,
    MERGE_DIGEST_END + "\nmid\n" + MERGE_DIGEST_BEGIN,
])
def test_upsert_orphan_markers_prepend(body):
    block = render_merge_digest(ROWS)
    assert upsert_merge_digest(body, block) == block + "\n\n" + body


def test_upsert_empty_body():
    block = render_merge_digest(ROWS)
    assert upsert_merge_digest("", block) == block + "\n"
    assert upsert_merge_digest(None, block) == block + "\n"
