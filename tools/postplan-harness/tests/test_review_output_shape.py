import json
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import extract_json
from harness.state import Classification, HarnessError, PlanInfo
from harness import review, schemas

FIXTURES = os.path.join(os.path.dirname(os.path.abspath(__file__)), "fixtures")


def _raw(name):
    with open(os.path.join(FIXTURES, name), encoding="utf-8") as fh:
        return fh.read()


def _chain(name):
    """extract_json -> unwrap_findings_envelope -> validate_findings, as review.py runs it."""
    data = schemas.unwrap_findings_envelope(extract_json(_raw(name)))
    schemas.validate_findings(data)
    return data


def _rejected(data, match):
    unwrapped = schemas.unwrap_findings_envelope(data)
    with pytest.raises(HarnessError, match=match) as exc:
        schemas.validate_findings(unwrapped)
    assert exc.value.kind == "schema"
    return unwrapped


# --- the four real raw replies from the live run ---


def test_agent_a_attempt0_has_no_parseable_json():
    with pytest.raises(ValueError, match="no parseable JSON in model reply"):
        extract_json(_raw("raw-review-agent-a-attempt0.txt"))


def test_agent_b_attempt0_has_no_parseable_json():
    with pytest.raises(ValueError, match="no parseable JSON in model reply"):
        extract_json(_raw("raw-review-agent-b-attempt0.txt"))


def test_agent_a_attempt1_sectioned_dict_normalizes_to_empty():
    name = "raw-review-agent-a-attempt1.txt"
    assert extract_json(_raw(name)) == {"section1": [], "section2": []}
    assert _chain(name) == []


def test_agent_b_attempt1_aliased_finding_normalizes():
    data = _chain("raw-review-agent-b-attempt1.txt")
    assert len(data) == 1
    item = data[0]
    assert item["path"] == "tools/postplan-harness/harness/adapters/llm.py"
    assert isinstance(item["line"], int)
    assert item["line"] == 0
    assert item["body"].startswith("The class docstring")
    assert "call_tooled()" in item["body"]
    assert set(item) == {"path", "line", "body"}


# --- the gate-weakening bound: shapes the widened normalizer must still reject ---


def test_empty_dict_is_not_a_clean_review():
    assert _rejected({}, "findings must be a JSON array") == {}


def test_single_wrong_envelope_key_still_rejected():
    data = {"not_findings": []}
    assert _rejected(data, "findings must be a JSON array") == data


def test_string_findings_value_still_rejected():
    data = {"findings": "none"}
    assert _rejected(data, "findings must be a JSON array") == data


def test_flat_dict_of_scalars_still_rejected():
    data = {"alpha": 1, "beta": "x"}
    assert _rejected(data, "findings must be a JSON array") == data


def test_flattened_non_dict_items_still_rejected():
    data = {"one": [1, 2], "two": ["x"]}
    assert _rejected(data, r"finding\[0\] missing keys") == [1, 2, "x"]


def test_string_line_is_not_coerced():
    _rejected([{"path": "a.py", "line": "123", "body": "b"}], r"\.line must be int")


def test_null_line_is_not_coerced():
    _rejected([{"path": "a.py", "line": None, "body": "b"}], r"\.line must be int")


def test_canonical_key_wins_over_alias():
    data = [{"path": "a.py", "file": "b.py", "line": 7, "body": "B", "detail": "D"}]
    assert schemas.unwrap_findings_envelope(data) == [{"path": "a.py", "line": 7, "body": "B"}]


# --- the prompt output contract ---


def _prompts():
    cls = Classification(files=["ibl5/classes/A.php"], has_php=True,
                         filtered_diff="diff --git a/ibl5/classes/A.php b/ibl5/classes/A.php\n"
                                       "@@ -1 +1 @@\n+$x = 1;\n")
    meta, plan = {"number": 1, "title": "t"}, PlanInfo()
    return {
        "a": review.agent_a_prompt(meta, cls, plan),
        "b": review.agent_b_prompt(meta, cls, True, True),
        "d": review.agent_d_prompt(meta, cls),
        "security": review.security_prompt(meta, cls, plan),
    }


BUILDERS = ["a", "b", "d", "security"]


@pytest.mark.parametrize("builder", BUILDERS)
def test_contract_is_first_text_in_every_prompt(builder):
    assert _prompts()[builder].startswith("OUTPUT CONTRACT")


@pytest.mark.parametrize("builder", BUILDERS)
def test_contract_is_also_last_text_in_every_prompt(builder):
    assert _prompts()[builder].rstrip().endswith(review.OUTPUT_CONTRACT_EXAMPLE)


@pytest.mark.parametrize("builder", BUILDERS)
def test_contract_appears_exactly_twice(builder):
    assert _prompts()[builder].count("Return ONLY a JSON array of findings") == 2


@pytest.mark.parametrize("builder", BUILDERS)
def test_no_section_label_survives(builder):
    prompt = _prompts()[builder]
    for n in (1, 2, 3):
        assert f"Section {n} —" not in prompt


def test_contract_example_validates():
    example = json.loads(review.OUTPUT_CONTRACT_EXAMPLE)
    schemas.validate_findings(example)
    assert schemas.unwrap_findings_envelope(example) == example


def test_contract_forbids_the_observed_wrong_keys():
    for key in ("category", "severity", "symbol", "detail"):
        assert f'"{key}"' in review.OUTPUT_CONTRACT_HEAD
    assert "one flat array" in review.OUTPUT_CONTRACT_HEAD
