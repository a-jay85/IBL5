"""LiveGit.fetch_base retries a concurrent-fetch ref-lock race and nothing else."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters import gitad
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

LOCK_ERR = ("git fetch origin master: error: cannot lock ref 'refs/remotes/origin/master': "
            "is at 5fd2c05 but expected 258a77c")


@pytest.fixture(autouse=True)
def _no_sleep(monkeypatch):
    monkeypatch.setattr(gitad.time, "sleep", lambda _s: None)


def _git_with(monkeypatch, errors):
    """LiveGit whose _run raises each queued error in turn, then succeeds."""
    g = LiveGit("/nonexistent")
    calls = []
    queue = list(errors)

    def fake_run(*args, check=True):
        calls.append(args)
        if queue:
            raise queue.pop(0)
        return ""

    monkeypatch.setattr(g, "_run", fake_run)
    return g, calls


def test_lock_race_retries_then_succeeds(monkeypatch):
    g, calls = _git_with(monkeypatch, [HarnessError("git", LOCK_ERR)] * 2)
    g.fetch_base()
    assert calls == [("fetch", "origin", "master")] * 3


def test_lock_race_gives_up_after_cap(monkeypatch):
    n = gitad.FETCH_LOCK_RETRIES + 1
    g, calls = _git_with(monkeypatch, [HarnessError("git", LOCK_ERR)] * n)
    with pytest.raises(HarnessError, match="cannot lock ref"):
        g.fetch_base()
    assert len(calls) == n


def test_other_fetch_error_is_not_retried(monkeypatch):
    g, calls = _git_with(monkeypatch,
                         [HarnessError("git", "git fetch origin master: Could not resolve host")])
    with pytest.raises(HarnessError, match="resolve host"):
        g.fetch_base()
    assert len(calls) == 1


def test_bare_base_does_not_fetch(monkeypatch):
    g, calls = _git_with(monkeypatch, [])
    g.fetch_base("master")
    assert calls == []
