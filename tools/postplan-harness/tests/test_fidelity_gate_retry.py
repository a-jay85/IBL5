"""Reset helper for a Phase 5.5 local-gate retry, tested against pure fakes.

`runner._reset_for_gate_retry` puts the worktree and the PR body back to their
pre-round state after a refused commit, and refuses (False) whenever it cannot prove
that, so the existing fail-closed terminal path keeps running.
"""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner

DIRTY_PATH = ".claude/rules/workflow-continuity-detail.md"


class _FakeGit:
    def __init__(self, head_value="aaa", dirty=None, is_dirty_after_restore=False):
        self.head_value = head_value
        self.dirty = list(dirty or [])
        self.is_dirty_after_restore = is_dirty_after_restore
        self.restored: list[list[str]] = []

    def head(self):
        return self.head_value

    def dirty_paths(self):
        return list(self.dirty)

    def restore_paths(self, paths):
        self.restored.append(list(paths))
        self.dirty = []

    def is_dirty(self):
        return self.is_dirty_after_restore


class _FakeGh:
    def __init__(self, live=""):
        self.live = live
        self.edits: list[tuple[int, str]] = []

    def pr_body_fresh(self):
        return self.live

    def pr_edit_body(self, pr, body):
        self.edits.append((pr, body))


def test_reset_restores_pre_round_body_and_dirty_paths():
    git = _FakeGit(dirty=[DIRTY_PATH])
    gh = _FakeGh(live="fixed body")
    log: list[str] = []
    ok = runner._reset_for_gate_retry(git, gh, 9400, "pre-round body", "aaa", log.append, 1)
    assert ok is True
    assert git.restored == [[DIRTY_PATH]]
    assert gh.edits == [(9400, "pre-round body")]


def test_reset_refuses_when_head_moved():
    git = _FakeGit(head_value="bbb", dirty=[DIRTY_PATH])
    gh = _FakeGh(live="fixed body")
    log: list[str] = []
    ok = runner._reset_for_gate_retry(git, gh, 9400, "pre-round body", "aaa", log.append, 1)
    assert ok is False
    assert git.restored == []
    assert gh.edits == []


def test_reset_refuses_when_tree_stays_dirty():
    git = _FakeGit(dirty=[DIRTY_PATH], is_dirty_after_restore=True)
    gh = _FakeGh(live="fixed body")
    log: list[str] = []
    ok = runner._reset_for_gate_retry(git, gh, 9400, "pre-round body", "aaa", log.append, 1)
    assert ok is False
    assert gh.edits == []


def test_reset_refuses_when_body_unreadable():
    git = _FakeGit(dirty=[DIRTY_PATH])
    gh = _FakeGh(live="")
    log: list[str] = []
    ok = runner._reset_for_gate_retry(git, gh, 9400, "pre-round body", "aaa", log.append, 1)
    assert ok is False
