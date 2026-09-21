"""Phase 5.5 body-only round detection: helper unit tests and end-to-end cases."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import fidelity
from harness.adapters.ghad import RecordingGh
from harness.adapters.llm import FixtureLlm
from harness.classify import FILES_CHANGED_BEGIN, FILES_CHANGED_END
from harness.state import UsageLedger

from test_fidelity_rounds import (NOT_READY, TREE_1, TREE_2, _ScriptedLlm,  # noqa: F401
                                   _cleanup, _counting_git, _plan, _Res,
                                   git_shim)


# ---------------------------------------------------------------------------
# _body_signature unit tests (matrix rows 1-4)
# ---------------------------------------------------------------------------

BEGIN = FILES_CHANGED_BEGIN
END = FILES_CHANGED_END

_BLOCK_V1 = f"{BEGIN}\n- file_a.py\n{END}"
_BLOCK_V2 = f"{BEGIN}\n- file_a.py\n- file_b.py\n{END}"


def test_body_signature_ignores_the_files_changed_block():
    body_a = f"## Summary\n\nSome prose.\n\n{_BLOCK_V1}\n"
    body_b = f"## Summary\n\nSome prose.\n\n{_BLOCK_V2}\n"
    assert runner._body_signature(body_a) == runner._body_signature(body_b)


def test_body_signature_sees_prose_edits_outside_the_block():
    body_a = f"## Summary\n\nOriginal prose.\n\n{_BLOCK_V1}\n"
    body_b = f"## Summary\n\nOriginal prose.\n\nExtra paragraph added.\n\n{_BLOCK_V1}\n"
    assert runner._body_signature(body_a) != runner._body_signature(body_b)


def test_body_signature_normalizes_absent_and_blank_bodies():
    assert runner._body_signature(None) == ""
    assert runner._body_signature("") == ""
    assert runner._body_signature("   \n\n ") == ""


def test_body_signature_leaves_an_unbalanced_marker_pair_intact():
    # BEGIN with no END
    body_begin_only = f"Some text {BEGIN} more text"
    assert runner._body_signature(body_begin_only) == body_begin_only.strip()

    # END before BEGIN (reversed order)
    body_reversed = f"Some text {END} middle {BEGIN} end"
    assert runner._body_signature(body_reversed) == body_reversed.strip()


# ---------------------------------------------------------------------------
# Constants pin (matrix row 5)
# ---------------------------------------------------------------------------

def test_body_outcomes_are_not_transient():
    assert runner.BODY_ONLY_SHA == "body-only"
    assert runner.BODY_ONLY_SHA not in runner._TRANSIENT_ROUND_REASONS
    assert runner.BODY_FETCH_FAILED_REASON not in runner._TRANSIENT_ROUND_REASONS
    assert "no-edits" in runner._TRANSIENT_ROUND_REASONS   # unchanged by this plan


# ---------------------------------------------------------------------------
# Scripted-body adapter shared by Phases 5 and 6 (5c)
# ---------------------------------------------------------------------------

class _BodySeqGh(RecordingGh):
    """pr_body_fresh() answers from a scripted sequence of successive live reads.

    The real fixer edits the body with a raw `gh pr edit` subprocess that bypasses the
    adapter entirely, so no fixture mutation reproduces it; scripting the reads is the
    faithful stand-in. A body-only round reads three times -- the before-snapshot, the
    after-snapshot, and the `live_body = gh.pr_body_fresh() or body` line that feeds the
    re-review. The final scripted value is reused for any call past the end.
    """
    def __init__(self, out_dir, bodies, **kw):
        super().__init__(out_dir, **kw)
        self._bodies = list(bodies)
        self.fresh_calls = 0

    def pr_body_fresh(self):
        self._body_override = None
        value = self._bodies[min(self.fresh_calls, len(self._bodies) - 1)]
        self.fresh_calls += 1
        return value


# ---------------------------------------------------------------------------
# Round-record and audit assertions (matrix rows 6-8)
# ---------------------------------------------------------------------------

def test_body_only_round_records_the_sentinel_and_counts(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path), ["BEFORE body prose", "AFTER body prose",
                                     "AFTER body prose"],
                    fixture={"pr_number": 9930})
    git = _counting_git(commit_returns=[""])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["answered in the body"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9930, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        rounds = res.fidelity["rounds"]
        assert rounds[0]["outcome"] == "body-only"
        assert rounds[0]["remediation_sha"] == "body-only"
        assert rounds[0]["retries"] == 0
        assert res.fidelity["rounds_completed"] == 1
    finally:
        _cleanup(9930, "9930-2")


def test_body_only_round_logs_a_distinct_outcome_line(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path), ["BEFORE body prose", "AFTER body prose",
                                     "AFTER body prose"],
                    fixture={"pr_number": 9931})
    git = _counting_git(commit_returns=[""])
    llm = _ScriptedLlm(UsageLedger(), {
        "plan-fidelity-review": [NOT_READY],
        "fidelity-remediation": ["answered in the body"],
        "plan-fidelity-re-review-2": ["READY\n"],
    })
    res = _Res()
    logged = []
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9931, "dead" * 10, TREE_1, False,
                             logged.append, res)
        assert any(m.startswith("phase5.5 round 1: body-only fix detected") for m in logged)
        assert any(m.startswith("phase5.5 round 1: model=sonnet retries=0 outcome=body-only")
                   for m in logged)
    finally:
        _cleanup(9931, "9931-2")


def test_terminal_line_names_the_body_only_remediation():
    result = fidelity.terminal_line("NOT READY", None, "body-only", "READY", TREE_2, 1)
    assert result.startswith("READY (re-review)")
    assert "body-only" in result


# ---------------------------------------------------------------------------
# End-to-end regression tests (matrix rows 9-13)
# ---------------------------------------------------------------------------

_BEFORE_PROSE = "## Summary\n\nOriginal finding answer.\n\n" + _BLOCK_V1 + "\n"
_AFTER_PROSE = "## Summary\n\nOriginal finding answer.\n\nAdded context.\n\n" + _BLOCK_V1 + "\n"
_SAME_BODY = "## Summary\n\nSame prose throughout.\n\n" + _BLOCK_V1 + "\n"
_BEFORE_FC = "## Prose\n\n" + BEGIN + "\n- old_file.py\n" + END + "\n"
_AFTER_FC = "## Prose\n\n" + BEGIN + "\n- new_file.py\n" + END + "\n"


def test_body_only_fix_clears_the_verdict_end_to_end(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path),
                    [_BEFORE_PROSE, _AFTER_PROSE, _AFTER_PROSE],
                    fixture={"pr_number": 9932})
    git = _counting_git(commit_returns=[""])
    llm = FixtureLlm(UsageLedger(), {
        "plan-fidelity-review": NOT_READY,
        "fidelity-remediation": "answered in the body",
        "plan-fidelity-re-review-2": "READY\n",
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9932, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        assert res.fidelity["verdict_2"] == "READY"
        assert res.fidelity["rounds_completed"] == 1
        assert len(res.fidelity["rounds"]) == 1
        assert gh.fresh_calls == 3
        tl = fidelity.terminal_line(
            res.fidelity["verdict_1"], res.fidelity.get("error_kind"),
            res.fidelity["remediation_sha"], res.fidelity["verdict_2"],
            res.fidelity["reviewed_tree_2"], res.fidelity["rounds_completed"])
        assert tl.startswith("READY (re-review)")
    finally:
        _cleanup(9932, "9932-2")


def test_unchanged_body_and_no_commit_still_retries(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path), [_SAME_BODY],
                    fixture={"pr_number": 9933})
    git = _counting_git(commit_returns=[""])
    llm = FixtureLlm(UsageLedger(), {
        "plan-fidelity-review": NOT_READY,
        "fidelity-remediation": "looked, changed nothing",
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9933, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        rounds = res.fidelity["rounds"]
        assert rounds[0]["outcome"] == "no-edits"
        assert rounds[0]["retries"] == 1
        assert res.fidelity["rounds_completed"] == 0
        assert res.fidelity["verdict_2"] is None
    finally:
        _cleanup(9933, "9933-2")


def test_committed_round_ignores_the_body_entirely(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path),
                    [_BEFORE_PROSE, _AFTER_PROSE, _AFTER_PROSE],
                    fixture={"pr_number": 9934})
    git = _counting_git(commit_returns=["round-sha-1"])
    llm = FixtureLlm(UsageLedger(), {
        "plan-fidelity-review": NOT_READY,
        "fidelity-remediation": "committed a fix",
        "plan-fidelity-re-review-2": "READY\n",
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9934, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        rounds = res.fidelity["rounds"]
        assert rounds[0]["outcome"] == "committed"
        assert rounds[0]["remediation_sha"] == "round-sha-1"
        assert gh.fresh_calls == 2
    finally:
        _cleanup(9934, "9934-2")


def test_files_changed_block_churn_is_not_a_body_change(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path), [_BEFORE_FC, _AFTER_FC, _AFTER_FC],
                    fixture={"pr_number": 9935})
    git = _counting_git(commit_returns=[""])
    llm = FixtureLlm(UsageLedger(), {
        "plan-fidelity-review": NOT_READY,
        "fidelity-remediation": "no prose change",
    })
    res = _Res()
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9935, "dead" * 10, TREE_1, False,
                             lambda _m: None, res)
        assert res.fidelity["rounds"][0]["outcome"] == "no-edits"
        assert res.fidelity["rounds_completed"] == 0
    finally:
        _cleanup(9935, "9935-2")


def test_empty_body_read_back_is_terminal_not_a_body_fix(tmp_path, git_shim):
    gh = _BodySeqGh(str(tmp_path), ["BEFORE body prose", ""],
                    fixture={"pr_number": 9936})
    git = _counting_git(commit_returns=[""])
    llm = FixtureLlm(UsageLedger(), {
        "plan-fidelity-review": NOT_READY,
        "fidelity-remediation": "looked around",
    })
    res = _Res()
    logged = []
    try:
        runner._run_fidelity(llm, str(tmp_path), str(tmp_path), git, gh, _plan(),
                             "diff", "original-body", 9936, "dead" * 10, TREE_1, False,
                             logged.append, res)
        assert res.fidelity["rounds"][0]["outcome"] == "body-fetch-failed"
        assert res.fidelity["rounds_completed"] == 0
        assert res.fidelity["verdict_2"] is None
        assert len(res.fidelity["rounds"]) == 1
        assert any("read back empty after remediation" in m for m in logged)
    finally:
        _cleanup(9936, "9936-2")
