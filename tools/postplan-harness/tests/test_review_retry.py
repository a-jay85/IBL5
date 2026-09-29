"""Tests for _call_agent retry behavior on llm-invalid-output (review.py Phase 1)."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase
from harness.state import Classification, HarnessError, PlanInfo


class _CountingLlm:
    """LLM stub that returns pre-configured responses per purpose, in order."""

    def __init__(self, responses_by_purpose):
        self._resp = responses_by_purpose
        self._calls = {}

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        idx = self._calls.get(purpose, 0)
        self._calls[purpose] = idx + 1
        responses = self._resp.get(purpose, [])
        if idx < len(responses):
            resp = responses[idx]
        else:
            raise HarnessError("llm-fixture-missing", f"no response #{idx} for {purpose}")
        if isinstance(resp, Exception):
            raise resp
        if normalizer is not None:
            resp = normalizer(resp)
        validate(resp)
        return resp


class _NullGh:
    def __init__(self):
        self.summary_calls = []

    def post_review_findings(self, pr, sha, title, findings):
        pass

    def post_review_summary(self, pr, title, body):
        self.summary_calls.append((title, body))


def _code_only_cls():
    """Classification that fires only gate A (no security, B, or D gates)."""
    return Classification()  # defaults: non_code_only=False, engine_only=False, has_php=False


def test_first_invalid_output_retries_once():
    valid = [{"path": "f.py", "line": 1, "body": "x"}]
    llm = _CountingLlm({
        "review-agent-a": [HarnessError("llm-invalid-output", "bad parse"), valid],
        "score-findings": [[{"n": 1, "score": 90}]],
    })
    gh = _NullGh()
    surviving, _, _, degraded = ReviewPhase(llm, gh).run(
        {"number": 1, "headRefOid": "abc"}, _code_only_cls(), PlanInfo()
    )
    assert len(surviving) == 1
    assert "review-agent-a" not in degraded
    assert llm._calls["review-agent-a"] == 2


def test_second_invalid_output_degrades_to_unavailable():
    llm = _CountingLlm({
        "review-agent-a": [
            HarnessError("llm-invalid-output", "fail 1"),
            HarnessError("llm-invalid-output", "fail 2"),
        ],
    })
    gh = _NullGh()
    surviving, _, _, degraded = ReviewPhase(llm, gh).run(
        {"number": 1, "headRefOid": "abc"}, _code_only_cls(), PlanInfo()
    )
    assert surviving == []
    assert "review-agent-a" in degraded
    assert llm._calls["review-agent-a"] == 2
    assert any("Review unavailable" in body for _, body in gh.summary_calls)
    assert not any("No issues found" in body for _, body in gh.summary_calls)


def test_non_parse_error_raises_immediately():
    llm = _CountingLlm({
        "review-agent-a": [HarnessError("llm-fixture-missing", "missing")],
    })
    gh = _NullGh()
    with pytest.raises(HarnessError) as exc_info:
        ReviewPhase(llm, gh).run(
            {"number": 1, "headRefOid": "abc"}, _code_only_cls(), PlanInfo()
        )
    assert exc_info.value.kind == "llm-fixture-missing"
    assert llm._calls.get("review-agent-a") == 1
