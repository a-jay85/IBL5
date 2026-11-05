import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.body_numbers import correct_body_numbers, strip_edit_counts
from harness.classify import FILES_CHANGED_BEGIN, FILES_CHANGED_END

_FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures", "edit_counts")


def _fixture(name: str) -> str:
    with open(os.path.join(_FIXTURES, name)) as fh:
        return fh.read()


def test_strip_edit_counts_drops_number_at_sentence_start():
    result = strip_edit_counts("Two existing assertions were updated for the renamed call.")
    assert result == "Existing assertions were updated for the renamed call."


@pytest.mark.parametrize("word", ["and", "but", "the", "these", "those"])
def test_strip_edit_counts_keeps_lowercase_mid_sentence(word):
    result = strip_edit_counts(f"It adds cases, {word} two tests were changed.")
    assert result == f"It adds cases, {word} tests were changed."


def test_strip_edit_counts_capitalizes_after_bullet_digit():
    assert strip_edit_counts("- 3 checks were reworded.") == "- Checks were reworded."


@pytest.mark.parametrize(
    "text",
    [
        "checks 3 and 4 were updated",
        "the 3 assertions in `foo_test.py` pass",
        "Adds 3 tests to `t.py`",
        "3 tests were added in `t.py`",
        "one test was updated",
        "120 rows were updated by the migration",
        "40 lines were rewritten",
        "The L-7 probe assertions were updated",
        "Phase 5.5 checks were updated",
        "### Phase 2\n\nTests were updated",
        "Only two tests were changed",
        "All 12 tests were updated",
        "Another two tests were changed",
        "At least two tests were changed",
        "Exactly three checks were updated",
        "Phase 2 tests were updated",
        "2 of 3 tests were updated",
        "At most two tests were changed",
        "Just two tests were changed",
        "No two tests were changed",
    ],
)
def test_strip_edit_counts_leaves_near_misses_unchanged(text):
    assert strip_edit_counts(text) == text


def test_strip_edit_counts_corpus():
    body = _fixture("pr-3694.txt")
    result = strip_edit_counts(body)
    assert "Two existing assertions were updated" not in result
    assert "Existing assertions were updated for the renamed call" in result
    before = body.splitlines()
    after = result.splitlines()
    assert len(before) == len(after)
    assert sum(1 for b, a in zip(before, after) if b != a) == 1

    for name in ("pr-3697.txt", "pr-3700.txt", "pr-3701.txt"):
        other = _fixture(name)
        assert strip_edit_counts(other) == other


def test_correct_body_numbers_strips_edit_count():
    result = correct_body_numbers("Two existing assertions were updated.\n", "", "")
    assert result == "Existing assertions were updated.\n"


def test_correct_body_numbers_leaves_edit_count_in_protected_span():
    block = f"{FILES_CHANGED_BEGIN}Two tests were updated{FILES_CHANGED_END}"
    body = f"Intro line.\n{block}\nOutro line.\n"
    result = correct_body_numbers(body, "", "")
    assert block in result


def test_correct_body_numbers_leaves_edit_count_in_protected_span_own_line():
    block = f"{FILES_CHANGED_BEGIN}\nTwo tests were updated\n{FILES_CHANGED_END}"
    body = f"Intro line.\n{block}\nOutro line.\n"
    result = correct_body_numbers(body, "", "")
    assert block in result
