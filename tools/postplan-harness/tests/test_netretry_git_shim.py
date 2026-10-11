"""Real-subprocess git PATH shim: transient fetch failures retry, others do not."""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
import time
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import netretry
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

REAL_GIT = shutil.which("git")
pytestmark = pytest.mark.skipif(REAL_GIT is None, reason="needs git")

SHIM = """#!/bin/sh
echo "$*" >> "$GIT_SHIM_LOG"
sub=""
skip=0
for a in "$@"; do
  if [ "$skip" = 1 ]; then skip=0; continue; fi
  if [ "$a" = "-C" ]; then skip=1; continue; fi
  sub="$a"; break
done
for c in $GIT_SHIM_LAND_THEN_FAIL; do
  if [ "$c" = "$sub" ] && [ ! -e "$GIT_SHIM_STATE/$sub.landed" ]; then
    "$REAL_GIT" "$@" || exit $?
    touch "$GIT_SHIM_STATE/$sub.landed"
    echo "${GIT_SHIM_MSG:-kex_exchange_identification: read: Connection reset by peer}" >&2
    exit 128
  fi
done
for c in $GIT_SHIM_FAIL_CMDS; do
  if [ "$c" = "$sub" ]; then
    if [ "$GIT_SHIM_ALWAYS" = 1 ] || [ ! -e "$GIT_SHIM_STATE/$sub.failed" ]; then
      touch "$GIT_SHIM_STATE/$sub.failed"
      echo "${GIT_SHIM_MSG:-kex_exchange_identification: read: Connection reset by peer}" >&2
      exit 128
    fi
  fi
done
exec "$REAL_GIT" "$@"
"""


def _git(path, *args):
    return subprocess.run([REAL_GIT, *args], cwd=str(path), check=True,
                          capture_output=True, text=True).stdout.strip()


def _git_repo(path):
    path.mkdir(parents=True, exist_ok=True)
    _git(path, "init", "-q", "-b", "master")
    _git(path, "config", "user.email", "t@example.com")
    _git(path, "config", "user.name", "T")
    (path / "f.txt").write_text("one\n")
    _git(path, "add", "-A")
    _git(path, "commit", "-qm", "base")
    return path


@pytest.fixture
def sleeps(monkeypatch):
    """Record netretry's sleeps only. Patching the global time.sleep would also
    catch subprocess.run's own wait loop, which sleeps when a shim is slow."""
    recorded = []
    monkeypatch.setattr(netretry, "time", SimpleNamespace(sleep=recorded.append,
                                                          monotonic=time.monotonic))
    return recorded


@pytest.fixture
def shim_repo(tmp_path, monkeypatch):
    seed = _git_repo(tmp_path / "seed")
    bare = tmp_path / "bare.git"
    subprocess.run([REAL_GIT, "init", "-q", "--bare", "-b", "master", str(bare)], check=True)
    _git(seed, "push", "-q", str(bare), "master")
    wt = tmp_path / "wt"
    subprocess.run([REAL_GIT, "clone", "-q", str(bare), str(wt)], check=True, capture_output=True)
    _git(wt, "config", "user.email", "t@example.com")
    _git(wt, "config", "user.name", "T")
    (seed / "f.txt").write_text("two\n")
    _git(seed, "commit", "-qam", "second")
    _git(seed, "push", "-q", str(bare), "master")
    new_sha = _git(seed, "rev-parse", "HEAD")
    log = _install_shim(tmp_path, monkeypatch)
    return LiveGit(str(wt)), wt, new_sha, log


def _install_shim(tmp_path, monkeypatch):
    shim_dir = tmp_path / "bin"
    shim_dir.mkdir()
    shim = shim_dir / "git"
    shim.write_text(SHIM)
    shim.chmod(0o755)
    state = tmp_path / "state"
    state.mkdir()
    log = tmp_path / "git.log"
    log.write_text("")
    monkeypatch.setenv("REAL_GIT", REAL_GIT)
    monkeypatch.setenv("GIT_SHIM_LOG", str(log))
    monkeypatch.setenv("GIT_SHIM_STATE", str(state))
    monkeypatch.setenv("PATH", f"{shim_dir}{os.pathsep}{os.environ['PATH']}")
    return log


@pytest.fixture
def push_repo(tmp_path, monkeypatch):
    """A bare push remote and a clone on branch `feature` one commit ahead."""
    seed = _git_repo(tmp_path / "seed")
    bare = tmp_path / "bare.git"
    subprocess.run([REAL_GIT, "init", "-q", "--bare", "-b", "master", str(bare)], check=True)
    _git(seed, "push", "-q", str(bare), "master")
    clone = tmp_path / "clone"
    subprocess.run([REAL_GIT, "clone", "-q", str(bare), str(clone)], check=True, capture_output=True)
    _git(clone, "config", "user.email", "t@example.com")
    _git(clone, "config", "user.name", "T")
    _git(clone, "checkout", "-qb", "feature")
    (clone / "g.txt").write_text("feature\n")
    _git(clone, "add", "-A")
    _git(clone, "commit", "-qm", "feature")
    log = _install_shim(tmp_path, monkeypatch)
    return LiveGit(str(clone), push_remote=str(bare)), clone, bare, log


def _count(log, sub):
    return sum(1 for line in log.read_text().splitlines()
               if sub in line.replace("-C ", "").split()[:3])


def test_shim_fetch_kex_once_then_succeeds(shim_repo, monkeypatch, sleeps):
    git, wt, new_sha, log = shim_repo
    monkeypatch.setenv("GIT_SHIM_FAIL_CMDS", "fetch")
    git.fetch_base("origin/master")
    assert _count(log, "fetch") == 2
    assert _git(wt, "rev-parse", "refs/remotes/origin/master") == new_sha
    assert sleeps == [5.0]


def _assert_fetch_single_attempt(shim_repo, monkeypatch, sleeps, msg):
    git, wt, new_sha, log = shim_repo
    monkeypatch.setenv("GIT_SHIM_FAIL_CMDS", "fetch")
    monkeypatch.setenv("GIT_SHIM_ALWAYS", "1")
    monkeypatch.setenv("GIT_SHIM_MSG", msg)
    with pytest.raises(HarnessError) as ei:
        git.fetch_base("origin/master")
    assert ei.value.kind == "git"
    assert _count(log, "fetch") == 1
    assert sleeps == []


def test_shim_fetch_repository_not_found_is_not_retried(shim_repo, monkeypatch, sleeps):
    _assert_fetch_single_attempt(shim_repo, monkeypatch, sleeps, "fatal: repository not found")


def test_shim_fetch_bare_could_not_read_is_not_retried(shim_repo, monkeypatch, sleeps):
    _assert_fetch_single_attempt(shim_repo, monkeypatch, sleeps,
                                 "fatal: Could not read from remote repository.")


def _bare_tip(bare):
    return subprocess.run([REAL_GIT, "-C", str(bare), "rev-parse", "refs/heads/feature"],
                          check=True, capture_output=True, text=True).stdout.strip()


def test_shim_push_kex_once_then_succeeds(push_repo, monkeypatch, sleeps):
    git, clone, bare, log = push_repo
    monkeypatch.setenv("GIT_SHIM_FAIL_CMDS", "push")
    assert git.push_ff() == _git(clone, "rev-parse", "HEAD")
    assert _count(log, "push") == 2
    assert _bare_tip(bare) == _git(clone, "rev-parse", "HEAD")
    assert sleeps == [5.0]


def test_shim_push_lands_then_errors_is_not_repushed(push_repo, monkeypatch, sleeps):
    git, clone, bare, log = push_repo
    monkeypatch.setenv("GIT_SHIM_LAND_THEN_FAIL", "push")
    git.push()
    lines = log.read_text().splitlines()
    push_idx = [i for i, line in enumerate(lines)
                if "push" in line.replace("-C ", "").split()[:3]]
    assert len(push_idx) == 1
    assert any("ls-remote" in line for line in lines[push_idx[0] + 1:])
    assert _bare_tip(bare) == _git(clone, "rev-parse", "HEAD")
    assert sleeps == []


def test_shim_push_repository_not_found_is_not_retried(push_repo, monkeypatch, sleeps):
    git, clone, bare, log = push_repo
    monkeypatch.setenv("GIT_SHIM_FAIL_CMDS", "push")
    monkeypatch.setenv("GIT_SHIM_ALWAYS", "1")
    monkeypatch.setenv("GIT_SHIM_MSG", "fatal: repository not found")
    with pytest.raises(HarnessError) as ei:
        git.push_ff()
    assert ei.value.kind == "push-failed"
    assert _count(log, "push") == 1
    assert sleeps == []
