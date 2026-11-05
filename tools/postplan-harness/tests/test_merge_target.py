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
import time
from types import SimpleNamespace

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import netretry
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


def _online(monkeypatch):
    monkeypatch.delenv("BRANCH_BASE_OFFLINE")


def test_helper_publickey_and_gh_down_returns_2_prints_nothing(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", _err(OUTAGE_GIT_STDERR, 128))
    _gh_script(tmp_path, monkeypatch, _err(GH_DOWN_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""
    assert "origin/master" not in proc.stdout


def test_helper_lsremote_unreachable_gh_none_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", _err(OUTAGE_GIT_STDERR, 128))
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_absent_branch_gh_down_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch, _err(GH_DOWN_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_absent_branch_gh_none_returns_1(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 1
    assert proc.stdout == ""


def test_helper_gh_timeout_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch, "sleep 5; exit 0")
    proc = _helper(repo, pre="BRANCH_BASE_GH_TIMEOUT_SECS=1;")
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_lsremote_timeout_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", "sleep 5; exit 0")
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo, pre="BRANCH_BASE_LSREMOTE_TIMEOUT_SECS=1;")
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_git_5xx_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", _err(
        "fatal: unable to access 'https://github.com/a-jay85/IBL5.git/': "
        "The requested URL returned error: 503", 128))
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_gh_5xx_returns_2(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch,
               _err("HTTP 502: Bad Gateway (https://api.github.com/graphql)", 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_unrecorded_gh_down_returns_2_not_master(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _gh_script(tmp_path, monkeypatch, _err(GH_DOWN_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 2
    assert proc.stdout == ""


def test_helper_unrecorded_gh_none_returns_master(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 0
    assert proc.stdout == "origin/master\n"


def test_helper_lsremote_other_exit_stays_answer(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", _err("error: unexpected", 1))
    _gh_script(tmp_path, monkeypatch, _err(GH_NONE_STDERR, 1))
    proc = _helper(repo)
    assert proc.returncode == 1


def test_helper_gh_near_miss_text_is_unreachable(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "gone")
    _gh_script(tmp_path, monkeypatch, _err("no open pull requests", 1))
    proc = _helper(repo)
    assert proc.returncode == 2


def test_merge_target_outage_maps_to_remote_unreachable(repo, tmp_path, monkeypatch):
    _online(monkeypatch)
    _git(repo, "config", "branch.feature.iblBase", "master")
    _git_sub(tmp_path, monkeypatch, "ls-remote", _err(OUTAGE_GIT_STDERR, 128))
    _gh_script(tmp_path, monkeypatch, _err(GH_DOWN_STDERR, 1))
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo).merge_target()
    assert ei.value.kind == "remote-unreachable"
    assert "could not reach GitHub" in ei.value.detail
    assert "origin has no such branch" not in ei.value.detail


def test_fetch_publickey_is_remote_unreachable(repo, tmp_path, monkeypatch):
    _git_sub(tmp_path, monkeypatch, "fetch", _err(OUTAGE_GIT_STDERR, 128))
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo)._run("fetch", "origin", "master")
    assert ei.value.kind == "remote-unreachable"


def test_push_publickey_is_remote_unreachable(repo, tmp_path, monkeypatch):
    """_run_out never raises (it returns rc and output), so drive the real push path."""
    sleeps = []
    monkeypatch.setattr(netretry, "time", SimpleNamespace(sleep=sleeps.append,
                                                          monotonic=time.monotonic))
    _git_sub(tmp_path, monkeypatch, "push", _err(OUTAGE_GIT_STDERR, 128))
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo, push_remote="origin").push_ff()
    assert ei.value.kind == "remote-unreachable"
    assert "Permission denied (publickey)" in ei.value.output


def test_local_git_failure_stays_git_kind(repo, tmp_path, monkeypatch):
    _git_sub(tmp_path, monkeypatch, "rev-parse",
             _err("fatal: Could not resolve host: github.com", 128))
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo)._run("rev-parse", "HEAD")
    assert ei.value.kind == "git"


def test_fetch_local_permission_denied_stays_git_kind(repo, tmp_path, monkeypatch):
    _git_sub(tmp_path, monkeypatch, "fetch",
             _err("error: cannot open .git/FETCH_HEAD: Permission denied", 1))
    with pytest.raises(HarnessError) as ei:
        LiveGit(repo)._run("fetch", "origin", "master")
    assert ei.value.kind == "git"


def test_adapters_define_merge_target():
    assert hasattr(LiveGit, "merge_target") and hasattr(ReplayGit, "merge_target")
