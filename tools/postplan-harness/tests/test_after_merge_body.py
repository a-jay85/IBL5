"""## After merge PR-body section: upsert, protected span, and arming clearance.

The section sits after the ## Manual Testing window, so armable's condition (1)
scan never sees the post-merge rows it carries.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import armable
from harness.body_numbers import _protected_spans
from harness.classify import (AFTER_MERGE_END, MANUAL_TESTING_SENTINEL,
                              REVIEWER_VERIFICATION_BEGIN, REVIEWER_VERIFICATION_END,
                              render_after_merge_block, strip_manual_testing_section,
                              upsert_after_merge, upsert_reviewer_verification)
from harness.manual_rows import ManualRow

TAGGED_TEXT = "the operator reads the first nightly queue listing the morning after"
ROWS = [ManualRow(number="28", text=TAGGED_TEXT, raw="", post_merge_reason="launchd")]
BLOCK = render_after_merge_block(ROWS)


def _body(manual_section: str) -> str:
    return f"## Summary\n\nStuff.\n\n## Manual Testing\n\n{manual_section}\n\n## Other\n\nTail.\n"


def test_render_block_shape():
    assert BLOCK.startswith("## After merge\n")
    assert BLOCK.endswith(AFTER_MERGE_END)
    assert "- **Row 28** — " + TAGGED_TEXT in BLOCK
    assert "- [ ]" not in BLOCK
    assert render_after_merge_block([]) == ""


def test_upsert_inserts_after_manual_testing_window():
    out = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    mt = out.index("## Manual Testing")
    am = out.index("## After merge")
    other = out.index("## Other")
    assert mt < am < other
    assert "Tail." in out


def test_upsert_is_idempotent():
    once = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    twice = upsert_after_merge(once, BLOCK)
    assert twice == once
    assert twice.count("## After merge") == 1


def test_upsert_empty_block_removes_section():
    base = _body(MANUAL_TESTING_SENTINEL)
    out = upsert_after_merge(upsert_after_merge(base, BLOCK), "")
    assert "## After merge" not in out
    assert AFTER_MERGE_END not in out
    assert out == base


def test_upsert_appends_without_manual_testing():
    out = upsert_after_merge("## Summary\n\nStuff.\n", BLOCK)
    assert out.rstrip().endswith(AFTER_MERGE_END)
    assert out.startswith("## Summary")
    assert upsert_after_merge("", BLOCK) == BLOCK


def test_strip_manual_testing_section_keeps_after_merge():
    body = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    out, stripped = strip_manual_testing_section(body)
    assert stripped
    assert "## Manual Testing" not in out
    assert "## After merge" in out
    assert TAGGED_TEXT in out


def test_reviewer_verification_lands_above_after_merge_heading():
    body = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    rv = f"{REVIEWER_VERIFICATION_BEGIN}\nreviewer says ok\n{REVIEWER_VERIFICATION_END}"
    out = upsert_reviewer_verification(body, rv)
    assert out.index(REVIEWER_VERIFICATION_BEGIN) < out.index("## After merge")
    am = out.index("## After merge")
    end = out.index(AFTER_MERGE_END)
    assert REVIEWER_VERIFICATION_BEGIN not in out[am:end]


def test_body_numbers_protects_after_merge_span():
    body = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    start = body.index("## After merge")
    end = body.index(AFTER_MERGE_END) + len(AFTER_MERGE_END)
    spans = _protected_spans(body)
    assert any(s <= start and e >= end for s, e in spans)


def test_all_tagged_body_is_cleared():
    body = upsert_after_merge(_body(MANUAL_TESTING_SENTINEL), BLOCK)
    assert armable.manual_testing_clearance(body) == "CLEARED"
    section = armable._manual_section(body)
    assert section is not None
    assert TAGGED_TEXT not in "\n".join(section)


def test_mixed_body_is_held():
    body = upsert_after_merge(_body("- [ ] **Row 1** — the CSV opens in Excel"), BLOCK)
    assert armable.manual_testing_clearance(body) == "HELD"


def test_untagged_unchecked_row_still_held():
    body = _body("- [ ] **Row 2** — untagged row")
    assert armable.manual_testing_clearance(body) == "HELD"


def test_after_merge_without_sentinel_is_not_cleared():
    body = upsert_after_merge(_body(""), BLOCK)
    assert armable.manual_testing_clearance(body) != "CLEARED"
