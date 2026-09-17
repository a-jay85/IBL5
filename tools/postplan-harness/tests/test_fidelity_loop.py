"""Phase 1 characterization — single-round _run_fidelity baseline.

These tests pin the current single-shot behavior so that Phases 2-4's changes read as
intentional diffs rather than silent drops. Phase 4 edits these tests to assert the
bounded loop.
"""
import os
import stat
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import fidelity
from harness.state import HarnessError, UsageLedger
from harness.adapters.llm import FixtureLlm
from harness.adapters.gitad import ReplayGit
from harness.adapters.ghad import RecordingGh

import runner

TREE_1 = "a" * 40
TREE_2 = "b" * 40

GIT_SHIM = """#!/usr/bin/env bash
if [ "$1" = "show" ]; then
  echo "PROCEDURE BODY"
  exit 0
fi
exit 0
"""


@pytest.fixture()
def git_shim(tmp_path, monkeypatch):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    g = bindir / "git"
    g.write_text(GIT_SHIM)
    g.chmod(g.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}:{os.environ['PATH']}")
    return monkeypatch


def _plan(auto_merge_false=False):
    return types.SimpleNamespace(found=False, path="", auto_merge_false=auto_merge_false)


def _git(dirty=False, head_trees=None):
    return ReplayGit({
        "slug": "demo",
        "worktree_diff": "diff --git a/x b/x\n" if dirty else "",
        "diff": "diff --git a/x b/x\n",
        "head_trees": head_trees or [TREE_1, TREE_2],
    })


class _Res:
    def __init__(self):
        self.fidelity = {}


def _drive(tmp_path, canned, git_obj=None, plan_obj=None):
    """Run _run_fidelity and return (res, remediate_call_count, re_review_call_count)."""
    llm = FixtureLlm(UsageLedger(), canned)
    if git_obj is None:
        git_obj = _git()
    gh = RecordingGh(str(tmp_path))
    res = _Res()
    plan_obj = plan_obj or _plan()

    remediate_count = [0]
    re_review_count = [0]
    orig_remediate = runner.fidelity.remediate
    orig_re_review = runner.fidelity.re_review

    def _spy_remediate(*a, **kw):
        remediate_count[0] += 1
        return orig_remediate(*a, **kw)

    def _spy_re_review(*a, **kw):
        re_review_count[0] += 1
        return orig_re_review(*a, **kw)

    runner.fidelity.remediate = _spy_remediate
    runner.fidelity.re_review = _spy_re_review
    try:
        runner._run_fidelity(
            llm, str(tmp_path), str(tmp_path), git_obj, gh, plan_obj,
            "diff", "body", 99, "dead" * 10, TREE_1, False, lambda m: None, res,
        )
    finally:
        runner.fidelity.remediate = orig_remediate
        runner.fidelity.re_review = orig_re_review

    return res, remediate_count[0], re_review_count[0]


def _cleanup(*suffixes):
    for s in suffixes:
        p = fidelity.verdict_path(s)
        if os.path.exists(p):
            os.unlink(p)


def test_baseline_not_ready_runs_exactly_one_remediation_and_one_re_review(tmp_path, git_shim):
    """Phase 4 flips these counts to 3 and 3 in the bounded loop."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review": "NOT READY\n",
    }
    try:
        _, rmed, rrev = _drive(tmp_path, canned)
        assert rmed == 1
        assert rrev == 1
    finally:
        _cleanup(99, "99-2")


def test_baseline_fidelity_keys(tmp_path, git_shim):
    """Phase 4 only adds keys; widened, never rewritten, proving aliases survived."""
    canned = {
        "plan-fidelity-review": "6d checks\n\nNOT READY\n",
        "fidelity-remediation": "edited",
        "plan-fidelity-re-review": "NOT READY\n",
    }
    try:
        res, _, _ = _drive(tmp_path, canned)
        assert set(res.fidelity) == {
            "verdict_1", "error_kind", "reviewed_tree", "verdict_path",
            "remediation_sha", "verdict_2", "reviewed_tree_2",
        }
    finally:
        _cleanup(99, "99-2")


def test_baseline_push_failed_propagates(tmp_path, git_shim):
    """A push-failed HarnessError re-raises out of _run_fidelity. Phase 4 must keep this."""
    canned = {"plan-fidelity-review": "6d checks\n\nNOT READY\n"}

    def _raising_remediate(*a, **kw):
        raise HarnessError("push-failed", "test")

    orig = runner.fidelity.remediate
    runner.fidelity.remediate = _raising_remediate
    try:
        with pytest.raises(HarnessError) as exc_info:
            llm = FixtureLlm(UsageLedger(), canned)
            gh = RecordingGh(str(tmp_path))
            res = _Res()
            runner._run_fidelity(
                llm, str(tmp_path), str(tmp_path), _git(), gh, _plan(),
                "diff", "body", 99, "dead" * 10, TREE_1, False, lambda m: None, res,
            )
        assert exc_info.value.kind == "push-failed"
    finally:
        runner.fidelity.remediate = orig
        _cleanup(99)


def test_baseline_remediate_none_leaves_verdict_1_standing(tmp_path, git_shim):
    """remediate returning None keeps verdict_1=NOT READY and remediation_sha=None."""
    # dirty=True makes fidelity.remediate return None before calling the LLM
    canned = {"plan-fidelity-review": "6d checks\n\nNOT READY\n"}
    try:
        res, _, rrev = _drive(tmp_path, canned, git_obj=_git(dirty=True))
        assert res.fidelity["verdict_1"] == "NOT READY"
        assert res.fidelity["remediation_sha"] is None
        assert rrev == 0
    finally:
        _cleanup(99)
