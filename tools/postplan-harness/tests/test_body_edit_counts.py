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
        "Phases 4 and 5 checks were updated",
        "Rows 7 and 11 tests were changed",
        "Phases 1, 2, and 3 checks were updated",
        "Phases 1, 2, or 3 checks were updated",
        "3 of the 5 tests were updated",
        "At most two tests were changed",
        "Just two tests were changed",
        "No two tests were changed",
        "Phases 4 and\n5 checks were updated",
        "Phases 4 and\r\n5 checks were updated",
        "3 of the\n5 tests were updated",
        "Phase\n2 tests were updated",
        "Phases 4\nand 5 checks were updated",
        "3 of\nthe 5 tests were updated",
        "only\ntwo tests were changed",
        "at least\ntwo tests were changed",
        "- Phases 4 and\n  5 checks were updated",
        "### Phase\n2 tests were updated",
        "3 fixtures\nTests were updated",
        "2 fixes\n\nTests were updated",
        "Two existing\nassertions were updated",
    ],
)
def test_strip_edit_counts_leaves_near_misses_unchanged(text):
    assert strip_edit_counts(text) == text


@pytest.mark.parametrize(
    "before",
    [
        "",
        "\n",
        "Intro line\n\n",
        "Intro line\n \n",
        "Intro line\r\n\r\n",
        "Done.\n",
        "Done!\n",
        "Done?\n",
        "Done:\n",
        "Done.  \n",
        "Done.\r\n",
        "- ",
        "* ",
        "+ ",
        "1. ",
        "- First item\n- ",
        "1. First item\n2. ",
        "- Done.\n  ",
    ],
)
def test_strip_edit_counts_strips_at_real_sentence_boundary(before):
    result = strip_edit_counts(f"{before}Two existing assertions were updated")
    assert result == f"{before}Existing assertions were updated"


def test_strip_edit_counts_keeps_lowercase_after_wrapped_allowed_word():
    assert strip_edit_counts("It adds cases, and\ntwo tests were changed.") == (
        "It adds cases, and\ntests were changed."
    )


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
    block = f"{FILES_CHANGED_BEGIN}\n\nTwo tests were updated\n\n{FILES_CHANGED_END}"
    body = f"Intro line.\n{block}\nOutro line.\n"
    result = correct_body_numbers(body, "", "")
    assert block in result


def test_strip_edit_counts_keeps_list_starting_with_one():
    text = "Phases one and two checks were updated.\n"
    assert strip_edit_counts(text) == text


def test_strip_edit_counts_keeps_one_of_the_fraction():
    text = "One of the 3 checks were updated.\n"
    assert strip_edit_counts(text) == text


@pytest.mark.parametrize(
    "text",
    [
        # Fractions and partial counts: the number sits behind another phrase.
        "Two out of the 3 tests were updated.",
        "2 out of these 5 checks were changed",
        "Two out of those five tests were updated",
        "Done! Two out of the 3 tests were updated",
        "Note: three out of the 5 tests were updated",
        "Two out the 3 tests were updated",
        "Two out of\nthe 3 tests were updated",
        "Two among those 3 tests were updated",
        "Two from the 3 tests were updated",
        "1 in the 3 tests were updated",
        "Some of the 5 tests were updated",
        "Most of the 5 tests were updated",
        "All of the 5 tests were updated",
        "Half of the 6 tests were updated",
        "Half these 6 tests were updated",
        "A few of these 5 tests were updated",
        "Each of the two checks were updated",
        "Of the 5 tests were updated",
        "Out of the 5 tests were updated",
        # A limiting word in front of the determiner.
        "Only the two tests were updated",
        "Only those 3 checks were changed",
        "Just the 2 tests were updated",
        "Not the two tests were updated",
        "All the two tests were updated",
        "Both the two tests were updated",
        "At least the two tests were updated",
        "At most these 3 tests were updated",
        "Exactly the 2 tests were updated",
        "Fewer than the 3 tests were updated",
        "More than the 3 tests were updated",
        # A conjunction whose left side is not a whole clause.
        "All but two tests were changed",
        "All but the 2 tests were changed",
        "Nothing but two tests were changed",
        "Everything but two tests were changed",
        "A hundred and two tests were changed",
        "One hundred and two tests were changed",
        "Tests and two tests were changed",
        "2 and the 3 tests were updated",
        "3 or the two tests were updated",
        "Phases 1 and the 3 tests were updated",
        "Phases 1, 2, and the 3 tests were updated",
        # A number label before the boundary: the count stays with its label.
        "Phases 2: two tests were updated",
        "Row 3: two tests were updated",
        "Row 3: the two tests were updated",
        "Rows 3 and 4: two tests were updated",
        "Rows 3-5: two tests were updated",
        "**Row 3**: two tests were updated",
        "L-7: two tests were updated",
        "Phase 2.5: two tests were updated",
        "Phases one: two tests were updated",
        "12:30: two tests were updated",
        "Phases 2:\ntwo tests were updated",
        "Phase 2. two tests were updated",
        "PR 3. Two tests were updated",
        # An abbreviation's period does not end a sentence.
        "e.g. two tests were changed",
        "i.e. two tests were changed",
        "vs. two tests were changed",
        "etc. two tests were changed",
        "B. two tests were updated",
        "Fig. 2 tests were updated",
        "No. 2 tests were updated",
        "Sec. 2 tests were updated",
        "Max. 3 tests were updated",
        "Approx. 3 tests were updated",
        "Vol. 2 tests were updated",
        "Pg. 2 tests were updated",
        "U.S. 2 tests were updated",
        "Ph.D. 2 tests were updated",
        "Row 2 : two tests were updated",
        "Rows 2a: two tests were updated",
        "Part II: two tests were updated",
        "Rows 1; the 3 tests were updated",
        # The word after the number belongs to the number.
        "2 x tests were updated",
        "Two percent tests were changed",
        "Two not tests were changed",
        "Two hundred tests were updated",
        "Two thousand tests were updated",
        "Three dozen tests were updated",
        "Two of tests were updated",
        "Two fewer tests were changed",
        # A year or an id, not a tally.
        "The 2024 tests were updated",
        "2024 tests were updated",
        # Quoted or code text is left as the author wrote it.
        '"Done. Two tests were updated" was the old line.',
        "`Note: two tests were updated` was the old line.",
        "```\nFirst line.\n\nTwo tests were updated\n```\n",
        "<!-- Note. Two tests were updated -->",
        "<!-- Note\n\nTwo tests were updated -->",
        "> Done. Two tests were updated",
        "~~~\nFirst line.\n\nTwo tests were updated\n~~~\n",
        "Intro.\n> Done. Two tests were updated",
        "<!-- Note -->\nTwo tests were updated",
    ],
)
def test_strip_edit_counts_leaves_phrases_around_number_unchanged(text):
    assert strip_edit_counts(text) == text


@pytest.mark.parametrize(
    ("text", "expected"),
    [
        ("Note: two tests were updated", "Note: Tests were updated"),
        ("Why: The two tests were updated", "Why: The tests were updated"),
        ("Done! 2 tests were updated", "Done! Tests were updated"),
        ("Why? 2 tests were updated", "Why? Tests were updated"),
        ("Phase 2\n\nTwo tests were updated", "Phase 2\n\nTests were updated"),
        ("And 2 tests were updated", "And tests were updated"),
        ("But two tests were updated", "But tests were updated"),
        ("And the two tests were updated", "And the tests were updated"),
        ("Moved files. And two tests were changed", "Moved files. And tests were changed"),
        ("Foo, and the 3 checks were changed", "Foo, and the checks were changed"),
        ("Foo; the 3 checks were changed", "Foo; the checks were changed"),
        ("The two tests were updated", "The tests were updated"),
        ("Two end-to-end tests were changed", "End-to-end tests were changed"),
        ("Two tests were changed. Three checks were updated.", "Tests were changed. Checks were updated."),
        ("Two tests were changed, and 3 checks were updated", "Tests were changed, and checks were updated"),
        ("Done. `Foo` passes. Two tests were updated", "Done. `Foo` passes. Tests were updated"),
        ('He said "done". Two tests were updated', 'He said "done". Tests were updated'),
        ("1. 2 tests were updated", "1. Tests were updated"),
        ("<!-- Note -->\n\nTwo tests were updated", "<!-- Note -->\n\nTests were updated"),
        ("```\ncode\n```\n\nTwo tests were updated", "```\ncode\n```\n\nTests were updated"),
        (
            "Two tests were changed and three checks were updated.",
            "Tests were changed and three checks were updated.",
        ),
    ],
)
def test_strip_edit_counts_still_strips_at_clause_openings(text, expected):
    assert strip_edit_counts(text) == expected


@pytest.mark.parametrize(
    "text",
    [
        "Intro line\n> Done. Two tests were updated",
        "- > Done. Two tests were updated",
        "1. > Done. Two tests were updated",
    ],
)
def test_strip_edit_counts_leaves_blockquote_text_unchanged(text):
    assert strip_edit_counts(text) == text
