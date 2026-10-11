"""gitutil fetch and ls-remote sites retry a transient rc and still fail closed otherwise."""
from __future__ import annotations

import os
import sys
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import gitutil, netretry

KEX = "kex_exchange_identification: read: Connection reset by peer"


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(netretry.time, "sleep", recorded.append)
    return recorded


def _rc(rc=0, out="", err=""):
    return SimpleNamespace(returncode=rc, stdout=out, stderr=err)


def _fake(script):
    """run_git fake: script maps a subcommand to a list of results consumed in order."""
    log = []

    def run_git(args, cwd):
        log.append(list(args))
        queue = script.get(args[0], [])
        return queue.pop(0) if queue else _rc(0, "abc\trefs/heads/b\n")

    return run_git, log


def test_content_equivalent_retries_transient_fetch(sleeps):
    run_git, log = _fake({"fetch": [_rc(128, err=KEX)]})
    gitutil.content_equivalent("a", "b", "/wt", run_git=run_git)
    assert [a[0] for a in log].count("fetch") == 2
    assert sleeps == [5.0]


def test_probe_remote_tip_retries_transient_ls_remote(sleeps):
    run_git, log = _fake({"ls-remote": [_rc(128, err="Connection timed out")]})
    gitutil.probe_remote_tip("origin", "b", "/wt", run_git=run_git)
    assert [a[0] for a in log].count("ls-remote") == 2
    assert "fetch" in [a[0] for a in log]
    assert sleeps == [5.0]


def test_gitutil_non_transient_fetch_fails_closed_without_retry(sleeps):
    run_git, log = _fake({"fetch": [_rc(128, err="fatal: Could not read from remote repository.")]})
    assert gitutil.content_equivalent("a", "b", "/wt", run_git=run_git) is False
    assert [a[0] for a in log].count("fetch") == 1
    assert sleeps == []
