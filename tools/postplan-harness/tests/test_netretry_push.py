"""LiveGit.push / push_ff retry transient failures through netretry, with a
remote-tip landed check so a lost-reply push is never re-pushed."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import netretry
from harness.adapters import gitad
from harness.adapters.gitad import LiveGit, _LOCAL_GATE_MARKERS, _ZERO_SHA
from harness.state import HarnessError

HEAD = "a" * 40
OLD = "b" * 40
LEASE = "c" * 40
KEX = "kex_exchange_identification: read: Connection reset by peer"


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(netretry.time, "sleep", recorded.append)
    return recorded


def _git(monkeypatch, *, push, ls_remote=(), lease=LEASE):
    """LiveGit with branch/head/lease patched and a scripted _run_out.

    `push` and `ls_remote` are lists of (rc, out) returned in order per
    subcommand. Every _run_out argv is recorded on git.calls."""
    git = LiveGit("/nonexistent", push_remote="origin")
    monkeypatch.setattr(git, "branch", lambda: "b")
    monkeypatch.setattr(git, "head", lambda: HEAD)
    monkeypatch.setattr(git, "_run", lambda *a, check=True: lease)
    scripts = {"push": list(push), "ls-remote": list(ls_remote)}
    git.calls = []

    def _run_out(*args):
        git.calls.append(args)
        return scripts[args[0]].pop(0)

    monkeypatch.setattr(git, "_run_out", _run_out)
    return git


def _count(git, sub):
    return sum(1 for a in git.calls if a[0] == sub)


def test_push_retries_transient_then_succeeds(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(128, KEX), (0, "")],
               ls_remote=[(0, f"{OLD}\trefs/heads/b")])
    git.push()
    assert _count(git, "push") == 2
    assert sleeps == [5.0]


def test_push_landed_after_lost_reply_does_not_repush(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(128, KEX)],
               ls_remote=[(0, f"{HEAD}\trefs/heads/b")])
    git.push()
    assert _count(git, "push") == 1
    assert sleeps == []


def test_push_landed_check_failure_reraises_original(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(128, KEX)],
               ls_remote=[(128, "fatal: unable to access")])
    with pytest.raises(HarnessError) as ei:
        git.push()
    assert ei.value.kind == "push-failed"
    assert KEX in ei.value.detail
    assert _count(git, "push") == 1
    assert sleeps == []


def test_push_non_transient_rejection_is_single_attempt(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(1, " ! [rejected] b -> b (stale info)")])
    with pytest.raises(HarnessError) as ei:
        git.push()
    assert ei.value.kind == "push-failed"
    assert _count(git, "push") == 1
    assert _count(git, "ls-remote") == 0
    assert sleeps == []


def test_push_local_gate_is_single_attempt(monkeypatch, sleeps):
    out = f"{_LOCAL_GATE_MARKERS[0]} denied\nConnection reset by peer"
    git = _git(monkeypatch, push=[(1, out)])
    with pytest.raises(HarnessError) as ei:
        git.push()
    assert ei.value.kind == "local-gate"
    assert _count(git, "push") == 1
    assert sleeps == []


def test_lease_read_retries_transient_ls_remote(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(0, "")],
               ls_remote=[(128, "ssh: connect to host github.com port 22: Connection timed out"),
                          (0, "")],
               lease="")
    git.push()
    assert _count(git, "ls-remote") == 2
    pushes = [a for a in git.calls if a[0] == "push"]
    assert len(pushes) == 1
    assert pushes[0][1] == f"--force-with-lease=b:{_ZERO_SHA}"
    assert sleeps == [5.0]


def test_push_ff_landed_after_lost_reply_returns_head(monkeypatch, sleeps):
    git = _git(monkeypatch, push=[(128, KEX)],
               ls_remote=[(0, f"{HEAD}\trefs/heads/b")])
    assert git.push_ff() == HEAD
    assert _count(git, "push") == 1
    assert sleeps == []


def test_gitad_has_no_inline_transient_budget():
    assert not hasattr(gitad, "FETCH_TRANSIENT_RETRIES")
