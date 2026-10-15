"""review_resolution attempt counting (harness/conflict.py): real verdicts cost one call; a miss is retried once (Phase 3)."""
import os
import sys
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import conflict
from harness.conflict import (VERDICT_ABSENT, VERDICT_CLEAN, VERDICT_FOUND_PROBLEM,
                              purge_verdict_artifacts, review_resolution, verdict_ok_path)
from harness.state import HarnessError


class _TooledLlm:
    """Replies per call, in order; an Exception entry is raised instead of returned."""

    def __init__(self, replies):
        self.replies, self.calls = list(replies), []

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        self.calls.append(purpose)
        reply = self.replies[len(self.calls) - 1]
        if isinstance(reply, Exception):
            raise reply
        return reply


def _run(*args, check=True):
    return "deadbeef\n" if args[:1] == ("rev-parse",) else ""


@pytest.fixture
def key():
    k = f"t-{uuid.uuid4().hex[:8]}"
    yield k
    purge_verdict_artifacts(k)


def _review(llm, tmp_path, key):
    return review_resolution(llm, _run, worktree=str(tmp_path), key=key,
                             resolved_files=("a.php",), proof_out="TREE-EQUIVALENT")


def test_clean_first_reply_costs_one_call(tmp_path, key):
    llm = _TooledLlm(["CONFLICT-REVIEW=CLEAN\nfine"])
    assert _review(llm, tmp_path, key) == VERDICT_CLEAN
    assert llm.calls == ["conflict-review"]
    with open(verdict_ok_path(key, "deadbeef")) as fh:
        assert fh.readline().rstrip() == VERDICT_CLEAN


def test_found_problem_first_reply_costs_one_call(tmp_path, key):
    llm = _TooledLlm(["CONFLICT-REVIEW=FOUND-PROBLEM\nx"])
    assert _review(llm, tmp_path, key) == VERDICT_FOUND_PROBLEM
    assert len(llm.calls) == 1


def test_both_tokens_is_found_problem_one_call(tmp_path, key):
    llm = _TooledLlm(["CONFLICT-REVIEW=CLEAN\nCONFLICT-REVIEW=FOUND-PROBLEM\nboth"])
    assert _review(llm, tmp_path, key) == VERDICT_FOUND_PROBLEM
    assert len(llm.calls) == 1


def test_verdict_file_second_line_onward_is_raw_reply(tmp_path, key):
    llm = _TooledLlm(["CONFLICT-REVIEW=CLEAN\nreason"])
    _review(llm, tmp_path, key)
    with open(verdict_ok_path(key, "deadbeef")) as fh:
        assert fh.read() == "CONFLICT-REVIEW=CLEAN\nCONFLICT-REVIEW=CLEAN\nreason"
