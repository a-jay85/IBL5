import os

_TESTS_DIR = os.path.dirname(os.path.abspath(__file__))
_REPO_ROOT = os.path.dirname(os.path.dirname(os.path.dirname(_TESTS_DIR)))
_CLAIMS_DOC = os.path.join(_REPO_ROOT, ".claude", "skills", "post-plan", "_pr-body-claims.md")
_LLM_CALLS = os.path.join(_REPO_ROOT, "tools", "postplan-harness", "harness", "llm_calls.py")
_BODY_NUMBERS = os.path.join(_REPO_ROOT, "tools", "postplan-harness", "harness", "body_numbers.py")


def _read(path: str) -> str:
    with open(path) as fh:
        return fh.read()


def _section(title: str) -> str:
    for chunk in _read(_CLAIMS_DOC).split("\n## "):
        if chunk.split("\n", 1)[0] == title:
            return chunk
    raise AssertionError(f"section {title!r} not found in {_CLAIMS_DOC}")


def test_edit_count_rule_sits_between_unseen_and_test_count():
    doc = _read(_CLAIMS_DOC)
    unseen = doc.index("## Unseen-count rule")
    edit = doc.index("## Edit-count rule")
    test_count = doc.index("## Test-count claims rule")
    assert unseen < edit < test_count


def test_edit_count_rule_forbids_hand_tallied_edit_counts():
    section = _section("Edit-count rule")
    assert "states no count of them" in section
    assert "even when the diff shows every one of them" in section


def test_edit_count_rule_keeps_subject_counts_and_measured_test_counts():
    section = _section("Edit-count rule")
    assert "the subject of the change stays allowed" in section
    assert "is a measurement and stays legal" in section


def test_edit_count_rule_keeps_check4_blocking():
    assert "keeps blocking a count the diff contradicts" in _section("Edit-count rule")


def test_edit_count_rule_matches_harness_code():
    section = _section("Edit-count rule")
    assert "`EDIT COUNTS:`" in section
    assert "`strip_edit_counts`" in section
    assert "EDIT COUNTS: never state how many existing" in _read(_LLM_CALLS)
    assert "def strip_edit_counts(" in _read(_BODY_NUMBERS)
