"""LiveGit.fetch_base against real git, over an ssh-form remote whose transport is a shim.

The shim fails the first connections with the recorded kex stderr (exit 255, like ssh),
then serves a local bare repo. That exercises the real stderr shape `_run` captures and a
real ref update, with no network.
"""
from __future__ import annotations

import os
import shutil
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import netretry
from harness.adapters import gitad
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

pytestmark = pytest.mark.skipif(shutil.which("git") is None, reason="needs git")

SHIM = """#!/bin/sh
# Fake ssh transport. Counts connections; fails the first $SHIM_FAILS of them
# with $SHIM_MSG on stderr (exit 255, like ssh), then runs the remote command locally.
n=$(cat "$SHIM_COUNT_FILE" 2>/dev/null || echo 0)
n=$((n + 1))
echo "$n" > "$SHIM_COUNT_FILE"
if [ "$n" -le "${SHIM_FAILS:-1}" ]; then
  echo "${SHIM_MSG:-kex_exchange_identification: read: Operation timed out}" >&2
  exit 255
fi
eval "last=\\${$#}"
exec sh -c "$last"
"""


def _git(path, *args):
    return subprocess.run(["git", *args], cwd=str(path), check=True,
                          capture_output=True, text=True).stdout.strip()


def _git_repo(path):
    path.mkdir(parents=True, exist_ok=True)
    run = lambda *a: subprocess.run(["git", *a], cwd=str(path), check=True, capture_output=True)
    run("init", "-q", "-b", "master")
    run("config", "user.email", "t@example.com")
    run("config", "user.name", "T")
    (path / "f.txt").write_text("one\n")
    run("add", "-A")
    run("commit", "-qm", "base")
    return path


@pytest.fixture
def real_remote(tmp_path, monkeypatch):
    seed = _git_repo(tmp_path / "seed")
    bare = tmp_path / "bare.git"
    subprocess.run(["git", "init", "-q", "--bare", "-b", "master", str(bare)], check=True)
    _git(seed, "push", "-q", str(bare), "master")
    wt = tmp_path / "wt"
    subprocess.run(["git", "clone", "-q", str(bare), str(wt)], check=True, capture_output=True)
    _git(wt, "remote", "set-url", "origin", f"ssh://fakehost{bare}")
    (seed / "f.txt").write_text("two\n")
    _git(seed, "commit", "-qam", "second")
    _git(seed, "push", "-q", str(bare), "master")
    new_sha = _git(seed, "rev-parse", "HEAD")

    shim = tmp_path / "fake-ssh"
    shim.write_text(SHIM)
    shim.chmod(0o755)
    count_file = tmp_path / "ssh-count"
    monkeypatch.setenv("GIT_SSH_COMMAND", str(shim))
    monkeypatch.setenv("GIT_SSH_VARIANT", "simple")
    monkeypatch.setenv("SHIM_COUNT_FILE", str(count_file))
    return LiveGit(str(wt)), wt, new_sha, count_file


@pytest.fixture
def sleeps(monkeypatch):
    recorded = []
    monkeypatch.setattr(gitad.time, "sleep", recorded.append)
    return recorded


def test_real_fetch_recovers_after_one_kex_timeout(real_remote, monkeypatch, sleeps):
    git, wt, new_sha, count_file = real_remote
    monkeypatch.setenv("SHIM_FAILS", "1")
    git.fetch_base("origin/master")
    assert count_file.read_text().strip() == "2"
    assert _git(wt, "rev-parse", "origin/master") == new_sha
    assert sleeps == [netretry.RETRY_DELAYS[0]]


def test_real_fetch_persistent_kex_timeout_raises_after_four_attempts(
        real_remote, monkeypatch, sleeps):
    git, wt, new_sha, count_file = real_remote
    monkeypatch.setenv("SHIM_FAILS", "99")
    before = _git(wt, "rev-parse", "origin/master")
    with pytest.raises(HarnessError) as ei:
        git.fetch_base("origin/master")
    assert ei.value.kind == "git"
    assert "kex_exchange_identification" in ei.value.detail
    assert count_file.read_text().strip() == "4"
    assert sleeps == list(netretry.RETRY_DELAYS)
    assert _git(wt, "rev-parse", "origin/master") == before


def test_real_fetch_resolve_host_failure_is_not_retried(real_remote, monkeypatch, sleeps):
    git, wt, new_sha, count_file = real_remote
    monkeypatch.setenv("SHIM_FAILS", "99")
    monkeypatch.setenv("SHIM_MSG", "ssh: Could not resolve host: fakehost")
    with pytest.raises(HarnessError) as ei:
        git.fetch_base("origin/master")
    assert ei.value.kind == "git"
    assert count_file.read_text().strip() == "1"
    assert sleeps == []
