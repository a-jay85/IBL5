"""runner.run() end to end through Phase 5.5 rounds whose remediation is body-only.

Pins the two downstream consumers of `_last_remediation_commit` inside run(): the CI head
(`res.ci_head`, from the Phase 5.5 -> Phase 7 handoff) and the Phase 6.5 sticky comment's
"remediation commit ..." line. The literal "body-only" round label must reach neither.
Unit coverage of the helper alone lives in test_fidelity_body_only_round.py; a regression
that bypasses the helper at the call sites passes that suite but fails here.
"""
from __future__ import annotations

import os
import sys
import tempfile

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm  # noqa: F401
from harness.state import UsageLedger

from test_fidelity_body_only_round import (_AFTER_PROSE, _BEFORE_PROSE, _BodySeqGh)
from test_fidelity_rounds import (NOT_READY, _CountingGit, _ScriptedLlm,  # noqa: F401
                                   _cleanup, git_shim)
from test_runner_review_owed import CANNED, _fixture, _sticky_bodies

class _PhasedGh(_BodySeqGh):
    """pr_body_fresh() answers by how many fixer calls have run, not by read count.

    Under run() the body is read an unpredictable number of times before Phase 5.5, so a
    read-count script drifts. The fixer's raw `gh pr edit` is what changes the live body,
    so the number of completed fixer calls is the faithful clock.
    """
    def __init__(self, out_dir, llm, edit_after_call, **kw):
        super().__init__(out_dir, [_BEFORE_PROSE], **kw)
        self._llm, self._edit_after = llm, edit_after_call

    def pr_body_fresh(self):
        self._body_override = None
        self.fresh_calls += 1
        fixer = sum(1 for p, _m in self._llm.calls if p == "fidelity-remediation")
        return _AFTER_PROSE if fixer >= self._edit_after else _BEFORE_PROSE


def _drive_run(monkeypatch, pr, edit_after_call, commit_returns, scripts):
    out = tempfile.mkdtemp(prefix="postplan-test-body-only-e2e-")
    fx = _fixture(pr_number=pr, pr_meta={
        "number": pr, "title": "fix: synthetic", "headRefOid": "deadbeef",
        "body": "## Summary\n- 1 file changed\n\n## Manual Testing\n\nNone\n"})
    fx["diff"] = "diff --git a/ibl5/x.php b/ibl5/x.php\n+<?php echo 1;\n"
    fx["head_trees"] = ["a" * 40, "b" * 40, "c" * 40, "d" * 40]
    git = _CountingGit(fx, commit_returns=commit_returns)
    llm = _ScriptedLlm(UsageLedger(), {**{k: [v] for k, v in CANNED.items()}, **scripts})
    gh = _PhasedGh(out, llm, edit_after_call, fixture=fx)
    monkeypatch.setattr(runner, "ReplayGit", lambda _fx: git)
    monkeypatch.setattr(runner, "RecordingGh", lambda _out, _fx=None: gh)
    monkeypatch.setenv("REVIEW_OWED_PR_REVIEW_NOW", "/nonexistent")
    res = runner.run(fx, out, llm, mode="replay", headless=True)
    return res, out


def test_run_all_body_only_never_hands_the_label_downstream(tmp_path, git_shim, monkeypatch):
    try:
        res, out = _drive_run(
            monkeypatch, 9950, 1, [""],
            {"plan-fidelity-review": [NOT_READY],
             "fidelity-remediation": ["answered in the body"],
             "plan-fidelity-re-review-2": ["READY\n"]})
        assert [r["outcome"] for r in res.fidelity["rounds"]] == ["body-only"]
        assert res.fidelity["remediation_sha"] == "body-only"      # the label exists...
        assert res.ci_head != "body-only"                          # ...but is not a head
        sticky = "\n".join(_sticky_bodies(out))
        assert sticky, "no sticky verdict comment was posted"
        assert "remediation commit body-only" not in sticky
        assert "remediation commit" not in sticky                  # no real commit either
    finally:
        _cleanup(9950, "9950-2")


def test_run_body_only_round_after_a_commit_keeps_the_commit(tmp_path, git_shim, monkeypatch):
    try:
        res, out = _drive_run(
            monkeypatch, 9951, 2, ["phase2-sha", "round-sha-1", ""],
            {"plan-fidelity-review": [NOT_READY],
             "fidelity-remediation": ["committed a fix", "answered in the body"],
             "plan-fidelity-re-review-2": [NOT_READY],
             "plan-fidelity-re-review-3": ["READY\n"]})
        assert [r["outcome"] for r in res.fidelity["rounds"]] == ["committed", "body-only"]
        assert res.fidelity["remediation_sha"] == "body-only"
        assert res.ci_head == "round-sha-1"
        sticky = "\n".join(_sticky_bodies(out))
        assert "remediation commit round-sha-1 is inside that watch" in sticky
        assert "remediation commit body-only" not in sticky
    finally:
        _cleanup(9951, "9951-2", "9951-3")
