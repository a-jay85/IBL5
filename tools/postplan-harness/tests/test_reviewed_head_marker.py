"""Tests for the reviewed-head marker on clean Code review summaries (review.py)."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase, _reviewed_head_marker
from harness.state import Classification, PlanInfo


class _EmptyLlm:
    """LLM stub: the code review agent finds nothing."""

    def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
        resp = []
        if normalizer is not None:
            resp = normalizer(resp)
        validate(resp)
        return resp

    def call_tooled(self, purpose, model, prompt, *, cwd, allowed_tools=(), **kw):
        return []


class _NullGh:
    def __init__(self):
        self.summary_calls = []

    def post_review_findings(self, pr, sha, title, findings):
        pass

    def post_review_summary(self, pr, title, body):
        self.summary_calls.append((title, body))


def test_marker_exact_for_full_sha():
    sha = "1" * 40
    assert _reviewed_head_marker(sha) == f"\n\n<!-- reviewed-head: {sha} -->"


@pytest.mark.parametrize("bad", ["", "abc1234", "1" * 41, "A" * 40])
def test_marker_empty_for_malformed_sha(bad):
    assert _reviewed_head_marker(bad) == ""


def test_clean_code_review_summary_carries_marker(tmp_path):
    sha = "1" * 40
    gh = _NullGh()
    ReviewPhase(_EmptyLlm(), gh, worktree=str(tmp_path)).run(
        {"number": 7, "headRefOid": sha}, Classification(), PlanInfo()
    )
    code_posts = [body for title, body in gh.summary_calls if title == "Code review"]
    assert len(code_posts) == 1
    assert code_posts[0].startswith("No issues found.")
    assert code_posts[0].endswith(f"<!-- reviewed-head: {sha} -->")
