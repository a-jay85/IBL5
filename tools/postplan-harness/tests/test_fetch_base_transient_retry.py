"""LiveGit.fetch_base retries one transient network failure, separately from the lock race."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters import gitad
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

KEX_ERR = ("git fetch origin master: kex_exchange_identification: read: Operation timed out\n"
           "fatal: Could not read from remote repository.")
LOCK_ERR = ("git fetch origin master: error: cannot lock ref 'refs/remotes/origin/master': "
            "is at 5fd2c05 but expected 258a77c")


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(gitad.time, "sleep", recorded.append)
    return recorded


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


def test_kex_timeout_then_success_retries_once(monkeypatch, sleeps):
    g, calls = _git_with(monkeypatch, [HarnessError("git", KEX_ERR)])
    g.fetch_base()
    assert len(calls) == 2
    assert sleeps == [gitad.FETCH_TRANSIENT_DELAY]


@pytest.mark.parametrize("marker", gitad.FETCH_TRANSIENT_MARKERS)
def test_each_transient_marker_retries(monkeypatch, sleeps, marker):
    g, calls = _git_with(monkeypatch, [HarnessError("git", f"git fetch origin master: {marker}")])
    g.fetch_base()
    assert len(calls) == 2


def test_persistent_transient_failure_raises_after_two_attempts(monkeypatch, sleeps):
    g, calls = _git_with(monkeypatch, [HarnessError("git", KEX_ERR)] * 3)
    with pytest.raises(HarnessError) as ei:
        g.fetch_base()
    assert ei.value.kind == "git"
    assert len(calls) == 2
    assert len(sleeps) == 1


def test_could_not_read_alone_is_not_retried(monkeypatch, sleeps):
    g, calls = _git_with(monkeypatch, [HarnessError(
        "git", "git fetch origin master: fatal: Could not read from remote repository.")])
    with pytest.raises(HarnessError):
        g.fetch_base()
    assert len(calls) == 1
    assert sleeps == []


def test_could_not_resolve_host_is_not_retried(monkeypatch, sleeps):
    g, calls = _git_with(monkeypatch, [HarnessError(
        "git", "git fetch origin master: ssh: Could not resolve host: github.com")])
    with pytest.raises(HarnessError):
        g.fetch_base()
    assert len(calls) == 1
    assert sleeps == []


def test_transient_and_lock_race_budgets_are_independent(monkeypatch, sleeps):
    g, calls = _git_with(monkeypatch, [HarnessError("git", KEX_ERR), HarnessError("git", LOCK_ERR)])
    g.fetch_base()
    assert len(calls) == 3
    assert sleeps == [gitad.FETCH_TRANSIENT_DELAY, gitad.FETCH_LOCK_BACKOFF]
