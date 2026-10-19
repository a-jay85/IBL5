"""Depth-aware ReviewPhase: gates, agent models and the Agent B prompt.

`_STANDARD_GATES` was pasted from a run of the unmodified code (the pre-impl characterization),
so the standard tier is pinned to what shipped before the depth argument existed."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase, agent_b_prompt
from harness.state import Classification, PlanInfo, UsageLedger

from tests.test_review_parallel import _NullGh, _SleepingLlm, _make_canned, _php_cls

_CASES = {
    "php_small": Classification(has_php=True, lines_php_changed=10, has_modified=True),
    "php_large": Classification(has_php=True, lines_php_changed=120, has_modified=True,
                                has_comments_in_diff=True),
    "non_code": Classification(non_code_only=True),
    "engine": Classification(engine_only=True, has_modified=True),
    "e2e_only": Classification(has_e2e_specs=True, has_modified=True),
}

_STANDARD_GATES = {
    "php_small": {'A': True, 'B_history': False, 'B_comments': False, 'B': False, 'C': False,
                  'D': False, 'security': True},
    "php_large": {'A': True, 'B_history': True, 'B_comments': True, 'B': True, 'C': True,
                  'D': False, 'security': True},
    "non_code": {'A': False, 'B_history': False, 'B_comments': False, 'B': False, 'C': False,
                 'D': False, 'security': False},
    "engine": {'A': False, 'B_history': False, 'B_comments': False, 'B': False, 'C': False,
               'D': False, 'security': False},
    "e2e_only": {'A': True, 'B_history': False, 'B_comments': False, 'B': False, 'C': False,
                 'D': True, 'security': False},
}


@pytest.mark.parametrize("name", list(_CASES))
def test_standard_gates_unchanged(name):
    c = _CASES[name]
    for phase in (ReviewPhase(None, None), ReviewPhase(None, None, depth="standard")):
        g = phase.gates(c)
        assert g == _STANDARD_GATES[name]
        assert "depth" not in g


def test_deep_forces_b_history_on_code_prs():
    deep = ReviewPhase(None, None, depth="deep")
    g = deep.gates(_CASES["php_small"])
    assert g["B_history"] is True
    assert g["B"] is True
    assert deep.gates(_CASES["e2e_only"])["B_history"] is True


def test_deep_keeps_non_code_skip():
    g = ReviewPhase(None, None, depth="deep").gates(_CASES["non_code"])
    assert g["B_history"] is False
    assert g["A"] is False


def test_deep_keeps_engine_and_security_skips():
    g = ReviewPhase(None, None, depth="deep").gates(_CASES["engine"])
    assert g["A"] is False
    assert g["security"] is False


@pytest.mark.parametrize("name", list(_CASES))
def test_deep_never_drops_an_agent(name):
    deep = ReviewPhase(None, None, depth="deep").gates(_CASES[name])
    for key, value in _STANDARD_GATES[name].items():
        if value is True:
            assert deep[key] is True, f"{key} dropped in deep for {name}"
    for key in ("A", "C", "D", "security", "B_comments"):
        assert deep[key] == _STANDARD_GATES[name][key]


@pytest.mark.parametrize("name", list(_CASES))
def test_deep_gates_carry_depth_key(name):
    g = ReviewPhase(None, None, depth="deep").gates(_CASES[name])
    assert g["depth"] == "deep"
    assert g != _STANDARD_GATES[name]


def test_unknown_depth_is_deep():
    g = ReviewPhase(None, None, depth="Standard").gates(_CASES["php_small"])
    assert g["depth"] == "deep"


class _ModelLlm(_SleepingLlm):
    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        self.models: dict[str, str] = {}

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.models[purpose] = model
        return super().call(purpose, model, prompt, validate,
                            max_retries=max_retries, normalizer=normalizer)


def _models_for(depth: str) -> dict[str, str]:
    finding = [{"path": "ibl5/a.php", "line": 1, "body": "an issue"}]
    canned = _make_canned({"A": finding, "B": finding, "D": finding, "security": finding})
    canned["score-findings"] = [{"n": i, "score": 90} for i in range(1, 5)]
    llm = _ModelLlm(UsageLedger(), canned, sleep=0)
    cls = _php_cls(has_e2e_specs=True)
    ReviewPhase(llm, _NullGh(), depth=depth).run({}, cls, PlanInfo())
    return llm.models


def test_standard_models():
    models = _models_for("standard")
    assert models["review-agent-a"] == "sonnet"
    assert models["review-agent-b"] == "sonnet"
    assert models["review-agent-d"] == "sonnet"
    assert models["security-audit"] == "sonnet"
    assert models["score-findings"] == "haiku"


def test_deep_models():
    models = _models_for("deep")
    assert models["review-agent-a"] == "opus"
    assert models["security-audit"] == "opus"
    assert models["review-agent-b"] == "sonnet"
    assert models["review-agent-d"] == "sonnet"
    assert models["score-findings"] == "haiku"


def test_b_prompt_wording_by_depth():
    cls = _php_cls()
    default = agent_b_prompt({}, cls, True, False)
    assert "for the PHP files with the most" in default
    deep = agent_b_prompt({}, cls, True, False, any_files=True)
    assert "for the files with the most" in deep
    assert "PHP files" not in deep
