"""review_resolution attempt counting (harness/conflict.py): real verdicts cost one call; a miss is retried once."""
import os
import shutil
import sys
import uuid
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
from harness import conflict
from harness.conflict import (VERDICT_ABSENT, VERDICT_CLEAN, VERDICT_FOUND_PROBLEM,
                              purge_verdict_artifacts, review_resolution, verdict_ok_path)
from harness.state import HarnessError
from harness.usage_pause import UsagePause


class _TooledLlm:
    """Replies per call, in order; an exception entry is raised instead of returned."""

    def __init__(self, replies):
        self.replies, self.calls = list(replies), []

    def call_tooled(self, purpose, model, prompt, *, cwd, **kw):
        self.calls.append(purpose)
        reply = self.replies[len(self.calls) - 1]
        if isinstance(reply, BaseException):
            raise reply
        return reply


def _run(*args, check=True):
    return "deadbeef\n" if args[:1] == ("rev-parse",) else ""


@pytest.fixture
def key():
    k = f"t-{uuid.uuid4().hex[:8]}"
    yield k
    purge_verdict_artifacts(k)


def _review(llm, tmp_path, key, retries=None):
    return review_resolution(llm, _run, worktree=str(tmp_path), key=key,
                             resolved_files=("a.php",), proof_out="TREE-EQUIVALENT",
                             retries_out=retries)


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


_CLEAN = "CONFLICT-REVIEW=CLEAN\nok"


def _line1(key):
    with open(verdict_ok_path(key, "deadbeef")) as fh:
        return fh.readline().rstrip()


def test_tokenless_then_clean_retries_once(tmp_path, key):
    llm = _TooledLlm(["no token here", _CLEAN])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_CLEAN
    assert llm.calls == ["conflict-review"] * 2
    assert retries == {"conflict-review": 1}
    assert _line1(key) == VERDICT_CLEAN


def test_empty_then_clean_retries_once(tmp_path, key):
    llm = _TooledLlm(["", _CLEAN])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_CLEAN
    assert llm.calls == ["conflict-review"] * 2
    assert retries == {"conflict-review": 1}
    assert _line1(key) == VERDICT_CLEAN


def test_exception_then_clean_retries_once(tmp_path, key):
    llm = _TooledLlm([HarnessError("llm-tooled-empty", "x"), _CLEAN])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_CLEAN
    assert len(llm.calls) == 2
    assert retries == {"conflict-review": 1}


def test_tokenless_twice_is_absent_after_one_retry(tmp_path, key):
    llm = _TooledLlm(["", "still nothing"])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_ABSENT
    assert len(llm.calls) == 2
    assert retries == {"conflict-review": 1}
    assert _line1(key) == VERDICT_ABSENT


def test_exception_twice_is_absent(tmp_path, key):
    llm = _TooledLlm([HarnessError("llm-tooled-cli", "a"), OSError("b")])
    assert _review(llm, tmp_path, key, {}) == VERDICT_ABSENT
    assert len(llm.calls) == 2


def test_usage_limit_error_is_not_retried(tmp_path, key):
    llm = _TooledLlm([HarnessError("llm-usage-limit", "x")])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_ABSENT
    assert len(llm.calls) == 1
    assert retries == {}


def test_auth_expired_error_is_not_retried(tmp_path, key):
    llm = _TooledLlm([HarnessError("llm-auth-expired", "x")])
    retries = {}
    assert _review(llm, tmp_path, key, retries) == VERDICT_ABSENT
    assert len(llm.calls) == 1
    assert retries == {}


def test_real_verdict_leaves_retries_empty(tmp_path, key):
    llm = _TooledLlm([_CLEAN])
    retries = {}
    _review(llm, tmp_path, key, retries)
    assert retries == {}


def test_pause_on_second_attempt_propagates(tmp_path, key):
    llm = _TooledLlm(["", UsagePause("conflict-review")])
    with pytest.raises(UsagePause):
        _review(llm, tmp_path, key, {})
    assert not os.path.exists(verdict_ok_path(key, "deadbeef"))


# ---- rereview_absent: the fix round's bounded re-ask ----

_NO_VERDICT = "I looked at it."


@pytest.fixture
def review_dir(key):
    d = Path(f"/tmp/postplan-conflict-review-{key}")
    d.mkdir(parents=True)
    (d / "resolved-files.txt").write_text("a.php\nb.php\n")
    (d / "proof-output.txt").write_text("TREE-EQUIVALENT")
    yield d
    shutil.rmtree(d, ignore_errors=True)


def _rereview(llm, tmp_path, key):
    return conflict.rereview_absent(llm, _run, worktree=str(tmp_path), key=key)


def test_rereview_absent_stops_on_verdict(tmp_path, key, review_dir):
    llm = _TooledLlm([_NO_VERDICT, _NO_VERDICT, "CONFLICT-REVIEW=CLEAN\nfine", _NO_VERDICT])
    assert _rereview(llm, tmp_path, key) == (VERDICT_CLEAN, 2, "reviewed")
    assert len(llm.calls) == 3
    with open(verdict_ok_path(key, "deadbeef")) as fh:
        assert fh.readline().rstrip() == VERDICT_CLEAN
    assert (review_dir / "resolved-files.txt").read_text() == "a.php\nb.php\n"


def test_rereview_absent_bounded_attempts(tmp_path, key, review_dir):
    llm = _TooledLlm([_NO_VERDICT] * 8)
    assert _rereview(llm, tmp_path, key) == (VERDICT_ABSENT, conflict.CONFLICT_REREVIEW_ATTEMPTS,
                                             "reviewed")
    assert llm.calls == ["conflict-review"] * (conflict.CONFLICT_REREVIEW_ATTEMPTS * 2)


def test_rereview_absent_skips_when_adaptations_present(tmp_path, key, review_dir):
    (review_dir / "adaptations.txt").write_text("- a.php: +x => +y [x -> y]\n")
    llm = _TooledLlm([])
    assert _rereview(llm, tmp_path, key) == (VERDICT_ABSENT, 0, "adaptations-present")
    assert llm.calls == []
    assert (review_dir / "adaptations.txt").exists()


def test_rereview_absent_needs_the_saved_inputs(tmp_path, key, review_dir):
    (review_dir / "proof-output.txt").unlink()
    llm = _TooledLlm([])
    assert _rereview(llm, tmp_path, key) == (VERDICT_ABSENT, 0, "artifacts-missing")
    assert llm.calls == []
