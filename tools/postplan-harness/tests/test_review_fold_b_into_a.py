"""Agent B folded into Agent A: the gate union, the section flags and the unchanged default prompt.

The two digests in `_BASE_DIGESTS` were computed from the base commit's `review.py` (before the
fold), so `test_default_agent_a_prompt_unchanged_without_fold_flags` pins that a three-argument
`agent_a_prompt` call still builds the exact prompt Agent A got before."""
from __future__ import annotations

import hashlib
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase, agent_a_prompt
from harness.state import Classification, PlanInfo, UsageLedger

from tests.test_review_parallel import _NullGh, _SleepingLlm, _make_canned


class _PromptLlm(_SleepingLlm):
    def __init__(self, *a, **kw):
        super().__init__(*a, **kw)
        self.calls: list[tuple[str, str, str]] = []     # (purpose, model, prompt)

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        self.calls.append((purpose, model, prompt))
        return super().call(purpose, model, prompt, validate,
                            max_retries=max_retries, normalizer=normalizer)


def _cls(**kw) -> Classification:
    base = dict(has_modified=True, files=["x"], filtered_diff="+x\n")
    base.update(kw)
    return Classification(**base)


def _run(cls: Classification, depth: str = "standard") -> _PromptLlm:
    llm = _PromptLlm(UsageLedger(), _make_canned(), sleep=0)
    ReviewPhase(llm, _NullGh(), depth=depth).run({}, cls, PlanInfo())
    return llm


def _agent_a_calls(llm: _PromptLlm) -> list[tuple[str, str, str]]:
    return [c for c in llm.calls if c[0] == "review-agent-a"]


# (non_code_only, engine_only, has_php, lines_php_changed, has_comments_in_diff, depth)
#   -> (A, A_core, A_history, A_comments)
_GATE_TABLE = [
    ("php-10-no-comments", (False, False, True, 10, False, "standard"), (True, True, False, False)),
    ("php-51", (False, False, True, 51, False, "standard"), (True, True, True, False)),
    ("engine-comments-standard", (False, True, False, 0, True, "standard"), (True, False, False, True)),
    ("engine-no-comments-standard", (False, True, False, 0, False, "standard"), (False, False, False, False)),
    ("engine-no-comments-deep", (False, True, False, 0, False, "deep"), (True, False, True, False)),
    ("non-code-comments", (True, False, False, 0, True, "standard"), (False, False, False, False)),
    ("docs-plus-php-10-comments", (False, False, True, 10, True, "standard"), (True, True, False, True)),
]


@pytest.mark.parametrize("row,flags,expected", _GATE_TABLE, ids=[r[0] for r in _GATE_TABLE])
def test_agent_a_gate_is_union_of_old_a_and_b(row, flags, expected):
    """gates()["A"] is true whenever the old A gate, b_hist or b_comm was true; no B key remains."""
    non_code_only, engine_only, has_php, lines, comments, depth = flags
    g = ReviewPhase(None, None, depth=depth).gates(Classification(
        non_code_only=non_code_only, engine_only=engine_only, has_php=has_php,
        lines_php_changed=lines, has_comments_in_diff=comments, has_modified=True))
    assert (g["A"], g["A_core"], g["A_history"], g["A_comments"]) == expected
    assert g["A"] == (g["A_core"] or g["A_history"] or g["A_comments"])
    for gone in ("B", "B_history", "B_comments"):
        assert gone not in g


def test_engine_only_with_comments_runs_agent_a_comments_only():
    plan = PlanInfo(found=True, has_reuse=True, reuse_section="reuse the helper")
    llm = _PromptLlm(UsageLedger(), _make_canned(), sleep=0)
    ReviewPhase(llm, _NullGh()).run({}, _cls(engine_only=True, has_comments_in_diff=True), plan)
    code_calls = [c for c in llm.calls if c[0].startswith("review-agent-")]
    assert len(code_calls) == 1
    purpose, model, prompt = code_calls[0]
    assert purpose == "review-agent-a"
    assert model == "sonnet"
    assert "code-comment compliance" in prompt
    assert "You are a Senior Software Engineer" in prompt
    assert "architectural-fitness" not in prompt
    assert "PLANNED REUSE" not in prompt


def test_engine_only_without_comments_skips_agent_a():
    llm = _run(_cls(engine_only=True, has_comments_in_diff=False))
    assert _agent_a_calls(llm) == []


def test_non_code_only_skips_agent_a_even_with_comments():
    llm = _run(_cls(non_code_only=True, has_comments_in_diff=True, files=["docs/x.md"]))
    assert _agent_a_calls(llm) == []


def test_history_section_threshold_is_more_than_50_php_lines():
    at_50 = _agent_a_calls(_run(_cls(has_php=True, lines_php_changed=50)))
    assert len(at_50) == 1
    assert "Look for regression risk" not in at_50[0][2]
    at_51 = _agent_a_calls(_run(_cls(has_php=True, lines_php_changed=51)))
    assert len(at_51) == 1
    assert "Look for regression risk" in at_51[0][2]
    assert "for the PHP files with the most" in at_51[0][2]


_GOLDEN_META = {"number": 42, "title": "Golden fixture"}


def _golden_php_cls() -> Classification:
    return Classification(has_php=True, migration_only=False, has_modified=True,
                          lines_php_changed=12, files=["ibl5/classes/X.php"],
                          filtered_diff="+<?php\n+$x = 1;\n")


def _golden_migration_cls() -> Classification:
    return Classification(has_php=False, migration_only=True, has_modified=True,
                          files=["ibl5/migrations/999_x.sql"],
                          filtered_diff="+ALTER TABLE t ADD c INT;\n")


# SHA-256 of agent_a_prompt(_GOLDEN_META, cls, PlanInfo()) on the base commit's review.py.
_BASE_DIGESTS = {
    "php": "2a34d823e08747cd5511b61d524cc663b468676030c98b8898ad8db9cac131da",
    "migration": "eb95944a42ad63b4ed83b528aec6a7832086604af96d4072c5e17de59d5aac56",
}


@pytest.mark.parametrize("name,make", [("php", _golden_php_cls), ("migration", _golden_migration_cls)])
def test_default_agent_a_prompt_unchanged_without_fold_flags(name, make):
    prompt = agent_a_prompt(_GOLDEN_META, make(), PlanInfo())
    assert hashlib.sha256(prompt.encode()).hexdigest() == _BASE_DIGESTS[name]
