"""Depth-aware ReviewPhase: gates, agent models and Agent A's history-section wording.

`_STANDARD_GATES` was pasted from a run of the unmodified code (the pre-impl characterization),
so the standard tier is pinned to what shipped before the depth argument existed. The old
Agent B keys map onto Agent A's section flags: B_history -> A_history, B_comments ->
A_comments, and "A" is now the union of the old A and B gates (the old A gate is A_core)."""
from __future__ import annotations

import json
import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import VERIFIER_PURPOSES, ReviewPhase
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
    "php_small": {'A': True, 'A_core': True, 'A_history': False, 'A_comments': False, 'C': False,
                  'D': False, 'security': True},
    "php_large": {'A': True, 'A_core': True, 'A_history': True, 'A_comments': True, 'C': True,
                  'D': False, 'security': True},
    "non_code": {'A': False, 'A_core': False, 'A_history': False, 'A_comments': False, 'C': False,
                 'D': False, 'security': False},
    "engine": {'A': False, 'A_core': False, 'A_history': False, 'A_comments': False, 'C': False,
               'D': False, 'security': False},
    "e2e_only": {'A': True, 'A_core': True, 'A_history': False, 'A_comments': False, 'C': False,
                 'D': True, 'security': False},
}


@pytest.mark.parametrize("name", list(_CASES))
def test_standard_gates_unchanged(name):
    c = _CASES[name]
    for phase in (ReviewPhase(None, None), ReviewPhase(None, None, depth="standard")):
        g = phase.gates(c)
        assert g == _STANDARD_GATES[name]
        assert "depth" not in g


def test_deep_forces_a_history_on_code_prs():
    deep = ReviewPhase(None, None, depth="deep")
    g = deep.gates(_CASES["php_small"])
    assert g["A_history"] is True
    assert g["A"] is True
    assert deep.gates(_CASES["e2e_only"])["A_history"] is True


def test_deep_keeps_non_code_skip():
    g = ReviewPhase(None, None, depth="deep").gates(_CASES["non_code"])
    assert g["A_history"] is False
    assert g["A"] is False


def test_deep_keeps_engine_core_and_security_skips():
    g = ReviewPhase(None, None, depth="deep").gates(_CASES["engine"])
    assert g["A_core"] is False
    assert g["security"] is False
    # Deep forces the history section on every code PR, so an engine-only diff gets an
    # Agent A call with the history section only (the old deep Agent B call).
    assert g["A_history"] is True
    assert g["A"] is True


@pytest.mark.parametrize("name", list(_CASES))
def test_deep_never_drops_an_agent(name):
    deep = ReviewPhase(None, None, depth="deep").gates(_CASES[name])
    for key, value in _STANDARD_GATES[name].items():
        if value is True:
            assert deep[key] is True, f"{key} dropped in deep for {name}"
    for key in ("A_core", "C", "D", "security", "A_comments"):
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
        self.prompts: dict[str, str] = {}

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.models[purpose] = model
        self.prompts[purpose] = prompt
        return super().call(purpose, model, prompt, validate,
                            max_retries=max_retries, normalizer=normalizer)

    def call_tooled(self, purpose, model, prompt, **kw):
        self.models[purpose] = model
        return super().call_tooled(purpose, model, prompt, **kw)


def _run_for(depth: str) -> _ModelLlm:
    finding = [{"path": "ibl5/a.php", "line": 1, "body": "an issue"}]
    canned = _make_canned({"A": finding, "D": finding, "security": finding})
    for purpose in VERIFIER_PURPOSES:
        canned[purpose] = json.dumps([{"n": i, "verdict": "real", "reason": "verified"}
                                      for i in range(1, 4)])
    llm = _ModelLlm(UsageLedger(), canned, sleep=0)
    cls = _php_cls(has_e2e_specs=True)
    ReviewPhase(llm, _NullGh(), depth=depth, worktree=tempfile.gettempdir()).run({}, cls, PlanInfo())
    return llm


def _models_for(depth: str) -> dict[str, str]:
    return _run_for(depth).models


def test_standard_models():
    models = _models_for("standard")
    assert models["review-agent-a"] == "sonnet"
    assert "review-agent-b" not in models
    assert models["review-agent-d"] == "sonnet"
    assert models["security-audit"] == "sonnet"
    assert all(models[p] == "sonnet" for p in VERIFIER_PURPOSES)


def test_deep_models():
    models = _models_for("deep")
    assert models["review-agent-a"] == "opus"
    assert models["security-audit"] == "opus"
    assert "review-agent-b" not in models
    assert models["review-agent-d"] == "sonnet"
    assert all(models[p] == "sonnet" for p in VERIFIER_PURPOSES)


def test_deep_agent_a_runs_history_on_opus_any_files():
    deep = _run_for("deep")
    assert deep.models["review-agent-a"] == "opus"
    deep_prompt = deep.prompts["review-agent-a"]
    assert "Look for regression risk" in deep_prompt
    assert "for the files with the most" in deep_prompt
    assert "PHP files with the most" not in deep_prompt
    standard = _run_for("standard")      # _php_cls changes 100 PHP lines, over the 50 threshold
    assert standard.models["review-agent-a"] == "sonnet"
    assert "for the PHP files with the most" in standard.prompts["review-agent-a"]
