"""`runner._closer_commit_push`: a push failure keeps the closer commit and says so."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.closer import CloserOutcome
from harness.state import HarnessError

PRE = "a" * 40
NEW = "b" * 40
WT = "/wt/closer-slug"


class FakeGit:
    def __init__(self, commit_exc=None, run_exc_on=None):
        self.commit_exc, self.run_exc_on = commit_exc, run_exc_on
        self.run_calls = []
        self._head = PRE

    def is_dirty(self):
        return True

    def commit_all(self, msg):
        if self.commit_exc:
            raise self.commit_exc
        self._head = NEW

    def head(self):
        return self._head

    def _run(self, *argv):
        self.run_calls.append(tuple(argv))
        if self.run_exc_on and argv[0] == self.run_exc_on:
            raise RuntimeError("boom")
        return ""


def _outcome():
    return CloserOutcome(status="edited", verdict="fixed", note="closer fixed it",
                         pre_head=PRE)


def _push_raises(monkeypatch, exc):
    def raiser(*a, **kw):
        raise exc
    monkeypatch.setattr(runner, "_push_with_gate_fix", raiser)


def _call(git, failure=None):
    return runner._closer_commit_push(git, None, lambda m: None, None, _outcome(),
                                      worktree=WT, out_dir="/tmp/out", gh=None, pr=3695,
                                      phase="closer", failure=failure)


def test_push_rejection_keeps_commit_and_reports_sha(monkeypatch):
    _push_raises(monkeypatch, HarnessError(
        "push-rejected", "! [rejected] HEAD -> wake (non-fast-forward)"))
    git, failure = FakeGit(), {}
    note = _call(git, failure)
    ref = "refs/postplan-closer/3695-bbbbbbbbbbbb"
    assert "push failed (push-rejected/non-fast-forward)" in note
    assert "bbbbbbbbbbbb" in note
    assert ref in note
    assert WT in note
    assert "cherry-pick" in note
    assert git.run_calls.index(("update-ref", ref, NEW)) \
        < git.run_calls.index(("reset", "--hard", PRE))
    assert failure["stage"] == "push"
    assert failure["kept"] is True
    assert failure["sha"] == NEW


def test_commit_failure_discards_and_reports_reason(monkeypatch):
    _push_raises(monkeypatch, HarnessError("never", "unused"))
    git, failure = FakeGit(commit_exc=HarnessError(
        "local-gate-denied", "pre-commit hook declined")), {}
    note = _call(git, failure)
    assert note.startswith("commit failed (local-gate-denied/hook-deny)")
    assert not [c for c in git.run_calls if c[0] == "update-ref"]
    assert ("reset", "--hard", PRE) in git.run_calls
    assert failure["stage"] == "commit"


def test_update_ref_failure_says_not_kept(monkeypatch):
    _push_raises(monkeypatch, HarnessError("push-rejected", "[rejected] non-fast-forward"))
    git = FakeGit(run_exc_on="update-ref")
    note = _call(git)
    assert "NOT kept (update-ref failed)" in note
    assert ("reset", "--hard", PRE) in git.run_calls


def test_remote_head_diverged_still_raises(monkeypatch):
    _push_raises(monkeypatch, HarnessError("remote-head-diverged", "x"))
    git = FakeGit()
    with pytest.raises(HarnessError):
        _call(git)
    assert git.run_calls == []
