"""LiveGit.merge_target / bin/lib/branch-base.sh branch_base_merge_target.

The merge target is the PR's declared base (iblBase config, then gh baseRefName) with no
ancestry check. A stacked branch never falls back to master."""
import os
import shlex
import shutil
import stat
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters import gitad
from harness.adapters.gitad import LiveGit, ReplayGit
from harness.state import HarnessError

_ENV = {"GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *a):
    subprocess.run(["git", "-C", d, *a], check=True, capture_output=True,
                   env={**os.environ, **_ENV})


@pytest.fixture()
def repo(monkeypatch):
    root = tempfile.mkdtemp(prefix="postplan-mt-test-")
    d = os.path.join(root, "work")
    origin = os.path.join(root, "origin.git")
    subprocess.run(["git", "init", "-q", "--bare", origin], check=True, capture_output=True)
    subprocess.run(["git", "init", "-q", "-b", "master", d], check=True, capture_output=True)
    _git(d, "config", "user.email", "t@t")
    _git(d, "config", "user.name", "t")
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _git(d, "add", "-A")
    _git(d, "commit", "-m", "base")
    _git(d, "remote", "add", "origin", origin)
    _git(d, "push", "-q", "origin", "master")
    _git(d, "checkout", "-q", "-b", "feature")
    monkeypatch.setenv("BRANCH_BASE_OFFLINE", "1")
    yield d
    shutil.rmtree(root, ignore_errors=True)


def _push_branch(d, name):
    _git(d, "branch", name)
    _git(d, "push", "-q", "origin", name)


def _fake_gh(tmp_path, monkeypatch, answer):
    bindir = tmp_path / "bin"
    bindir.mkdir()
    gh = bindir / "gh"
    gh.write_text(f"#!/bin/sh\necho {answer}\n")
    gh.chmod(gh.stat().st_mode | stat.S_IEXEC)
    monkeypatch.setenv("PATH", f"{bindir}{os.pathsep}{os.environ['PATH']}")


REAL_GIT = shutil.which("git")   # resolved before any test prepends a shim dir
# 2026-11-04 03:00 stderr, verbatim from automouse log 2026-11-04-8163.log line 23.
OUTAGE_GIT_STDERR = ("git@github.com: Permission denied (publickey).\n"
                     "fatal: Could not read from remote repository.\n\n"
                     "Please make sure you have the correct access rights\n"
                     "and the repository exists.")
GH_DOWN_STDERR = ("error connecting to api.github.com\n"
                  "check your internet connection or https://githubstatus.com")
GH_NONE_STDERR = 'no pull requests found for branch "feature"'


def _shim_dir(tmp_path, monkeypatch):
    bindir = tmp_path / "shims"
    if not bindir.exists():
        bindir.mkdir()
        monkeypatch.setenv("PATH", f"{bindir}{os.pathsep}{os.environ['PATH']}")
    return bindir


def _write_exe(path, text):
    path.write_text(text)
    path.chmod(path.stat().st_mode | stat.S_IEXEC)


def _gh_script(tmp_path, monkeypatch, body):
    """gh shim running <body> (sh). body must end with an exit."""
    _write_exe(_shim_dir(tmp_path, monkeypatch) / "gh", f"#!/bin/sh\n{body}\n")


def _git_sub(tmp_path, monkeypatch, sub, body):
    """git shim: runs <body> when any argv word equals <sub>, else execs the real git."""
    _write_exe(_shim_dir(tmp_path, monkeypatch) / "git",
               f'#!/bin/sh\nfor a in "$@"; do\n  if [ "$a" = {sub} ]; then\n'
               f'    {body}\n  fi\ndone\nexec {REAL_GIT} "$@"\n')


def _err(text, rc):
    """sh body printing <text> to stderr and exiting <rc>."""
    return f"printf '%s\\n' {shlex.quote(text)} >&2; exit {rc}"


def _helper(repo, pre=""):
    """Run the real branch_base_merge_target. <pre> runs after sourcing, e.g. a timeout."""
    return subprocess.run(
        ["bash", "-c", f'. "$1" && {pre} branch_base_merge_target "$2" "$3"',
         "bb", str(gitad.BRANCH_BASE_LIB), repo, "feature"],
        capture_output=True, text=True, stdin=subprocess.DEVNULL, timeout=60)


def test_merge_target_unstacked_returns_origin_master(repo):
    assert LiveGit(repo).merge_target() == "origin/master"


def test_merge_target_config_parent_on_remote(repo):
    _push_branch(repo, "parent")
    _git(repo, "config", "branch.feature.iblBase", "parent")
    assert LiveGit(repo).merge_target() == "origin/parent"


def test_merge_target_config_missing_on_remote_fails_closed(repo):
    _git(repo, "config", "branch.feature.iblBase", "gone")
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo).merge_target()
    assert ei.value.kind == "base-resolve"
    assert "iblBase" in ei.value.detail


def test_merge_target_rejects_option_shaped_config(repo):
    _git(repo, "config", "branch.feature.iblBase", "--upload-pack=x")
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo).merge_target()
    assert ei.value.kind == "base-resolve"


def test_merge_target_gh_layer_resolves_retargeted_base(repo, tmp_path, monkeypatch):
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _fake_gh(tmp_path, monkeypatch, "master")
    monkeypatch.delenv("BRANCH_BASE_OFFLINE")
    assert LiveGit(repo).merge_target() == "origin/master"


def test_merge_target_gh_layer_without_config(repo, tmp_path, monkeypatch):
    _fake_gh(tmp_path, monkeypatch, "parent")
    monkeypatch.delenv("BRANCH_BASE_OFFLINE")
    assert LiveGit(repo).merge_target() == "origin/parent"


def test_merge_target_offline_empty_keeps_gh_layer(repo, tmp_path, monkeypatch):
    _fake_gh(tmp_path, monkeypatch, "parent")
    monkeypatch.setenv("BRANCH_BASE_OFFLINE", "")
    assert LiveGit(repo).merge_target() == "origin/parent"


def test_merge_target_detached_head_returns_origin_master(repo, monkeypatch):
    _git(repo, "checkout", "-q", "--detach")
    monkeypatch.setattr(gitad, "BRANCH_BASE_LIB", gitad.Path("/nonexistent/branch-base.sh"))
    assert LiveGit(repo).merge_target() == "origin/master"


def test_merge_target_absent_base_keeps_message(repo, tmp_path, monkeypatch):
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    monkeypatch.delenv("BRANCH_BASE_OFFLINE")
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo).merge_target()
    assert ei.value.kind == "base-resolve"
    assert "origin has no such branch and gh gave no answer" in ei.value.detail


@pytest.mark.parametrize("body", [
    "return 5", "printf 'garbage\\n'; return 0", "return 0", "printf 'origin/a..b\\n'; return 0",
], ids=["rc5", "garbage", "empty", "dotdot"])
def test_merge_target_helper_anomaly_fails_closed(repo, tmp_path, monkeypatch, body):
    lib = tmp_path / "bb.sh"
    lib.write_text(f"branch_base_merge_target() {{ {body}; }}\n")
    monkeypatch.setattr(gitad, "BRANCH_BASE_LIB", lib)
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo).merge_target()
    assert ei.value.kind == "base-resolve"


def test_adapters_define_merge_target():
    assert hasattr(LiveGit, "merge_target") and hasattr(ReplayGit, "merge_target")
