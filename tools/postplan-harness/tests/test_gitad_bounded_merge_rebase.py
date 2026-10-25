"""The merge, rebase and emergency_abort git calls run through run_bounded.

A hang in any of them must surface as the typed subprocess-timeout HarnessError (or, in
the best-effort SIGTERM path, be swallowed without skipping the later abort steps), not
sit until the 90-minute fleet budget. Each test makes ONE git call hang by swapping its
argv for `sleep`, so the real run_bounded timeout and process-group reap are exercised.

Mutation caught: revert any of these calls to a bare subprocess.run and the real git
call finishes at once, so the hang is never injected and the raise assertion fails.
"""
import os
import shutil
import subprocess
import sys
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import harness.adapters.gitad as gitad
from harness.adapters.gitad import LiveGit
from harness.state import SUBPROCESS_TIMEOUT, HarnessError
from test_gitad_rebase import _make_clean_repo, _merge_head
from test_gitad_stacked_rebase import (
    _LOSTWORK_EQUIV,
    _WritingLlm,
    _cleanup_full,
    _cleanup_tmp,
    _make_modify_conflict_repo,
    _make_simple_conflict_repo,
    _make_squash_repo,
    _rev,
    _sh,
)


def _subcommand(argv):
    """The git args after `git -C <wt>` and any leading `-c key=val` pairs."""
    rest = list(argv[3:])
    while len(rest) >= 2 and rest[0] == "-c":
        rest = rest[2:]
    return tuple(rest)


def _hang_on(monkeypatch, *prefix, timeout_attr=None):
    """Make every git call whose subcommand starts with `prefix` hang. Returns the list
    of subcommand tuples seen, in order, for ordering assertions."""
    real = gitad.run_bounded
    seen = []

    def wrapper(argv, **kw):
        sub = _subcommand(argv)
        seen.append(sub)
        if sub[:len(prefix)] == prefix:
            argv = ["sleep", "30"]
            kw["timeout"] = 1
        return real(argv, **kw)

    monkeypatch.setattr(gitad, "run_bounded", wrapper)
    return seen


def _assert_timeout(exc_info, step, started):
    assert time.monotonic() - started < 15
    assert exc_info.value.kind == SUBPROCESS_TIMEOUT, f"kind was {exc_info.value.kind!r}"
    assert f"step '{step}'" in exc_info.value.detail


def _no_index_lock(d):
    return not os.path.exists(os.path.join(d, ".git", "index.lock"))


def test_merge_hang_raises_subprocess_timeout_naming_git_merge(monkeypatch):
    d, key, branch, _shas, _master = _make_clean_repo()
    try:
        _hang_on(monkeypatch, "merge", "--no-edit")
        start = time.monotonic()
        with pytest.raises(HarnessError) as ei:
            LiveGit(d).rebase_onto()
        # The -c rerere pair must not leak into the step name.
        _assert_timeout(ei, "git merge", start)
        assert _no_index_lock(d)
    finally:
        _cleanup_full(key, branch, d)


def test_merge_abort_hang_in_no_llm_conflict_path_raises_timeout(monkeypatch):
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    try:
        _hang_on(monkeypatch, "merge", "--abort")
        start = time.monotonic()
        with pytest.raises(HarnessError) as ei:
            LiveGit(d).rebase_onto()   # no llm: conflict -> immediate merge --abort
        _assert_timeout(ei, "git merge", start)
    finally:
        _cleanup_full(key, branch, d)


def test_onto_rebase_hang_raises_subprocess_timeout():
    # monkeypatch-free fixture build, then patch for the call under test
    d, _parent_tip, _master_sha, key, branch = _make_squash_repo()
    mp = pytest.MonkeyPatch()
    try:
        _hang_on(mp, "rebase", "--onto")
        start = time.monotonic()
        with pytest.raises(HarnessError) as ei:
            LiveGit(d).autoresolve_stacked_rebase()
        _assert_timeout(ei, "git rebase", start)
        assert _no_index_lock(d)
    finally:
        mp.undo()
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def _seed_onto_conflict(d, branch):
    _sh(d, "checkout", "master")
    with open(os.path.join(d, "feature.txt"), "w") as fh:
        fh.write("master version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: conflict seed")
    _sh(d, "update-ref", "refs/remotes/origin/master", _rev(d, "HEAD"))
    _sh(d, "checkout", branch)


def test_rebase_abort_hang_in_no_llm_decline_raises_timeout():
    d, _parent_tip, _master_sha, key, branch = _make_squash_repo()
    mp = pytest.MonkeyPatch()
    try:
        _seed_onto_conflict(d, branch)
        _hang_on(mp, "rebase", "--abort")
        start = time.monotonic()
        with pytest.raises(HarnessError) as ei:
            LiveGit(d).autoresolve_stacked_rebase()   # no llm: conflict -> rebase --abort
        _assert_timeout(ei, "git rebase", start)
    finally:
        mp.undo()
        _sh(d, "rebase", "--abort", check=False)
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_rebase_continue_hang_raises_subprocess_timeout():
    d, _parent_tip, _master_sha, key, branch = _make_modify_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    mp = pytest.MonkeyPatch()
    try:
        _hang_on(mp, "rebase", "--continue")
        llm = _WritingLlm(d, "feature.txt", content="merged content\n")
        start = time.monotonic()
        with pytest.raises(HarnessError) as ei:
            LiveGit(d, llm=llm).autoresolve_stacked_rebase()
        _assert_timeout(ei, "git rebase", start)
    finally:
        mp.undo()
        _sh(d, "rebase", "--abort", check=False)
        _cleanup_full(key, branch, d)


def _mid_merge_repo():
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    _sh(d, "merge", "--no-edit", "origin/master", check=False)
    assert _merge_head(d) != "", "fixture must stop mid-merge"
    g = LiveGit(d)
    g._pre_rebase_sha = pre
    return g, d, key, branch, pre


def test_emergency_abort_hung_merge_abort_still_runs_later_steps(monkeypatch):
    """A hung `merge --abort` is swallowed and `rebase --abort` + `reset --hard` still
    run: the reset is what clears MERGE_HEAD and restores HEAD here."""
    g, d, key, branch, pre = _mid_merge_repo()
    try:
        monkeypatch.setattr(gitad, "EMERGENCY_GIT_TIMEOUT", 1)
        seen = _hang_on(monkeypatch, "merge", "--abort")
        start = time.monotonic()
        g.emergency_abort()          # must not raise
        assert time.monotonic() - start < 15
        assert ("rebase", "--abort") in seen
        assert ("reset", "--hard", pre) in seen
        assert _merge_head(d) == ""
        assert _rev(d, "HEAD") == pre
        assert _no_index_lock(d)
    finally:
        _cleanup_full(key, branch, d)


def test_emergency_abort_hung_reset_does_not_raise(monkeypatch):
    g, d, key, branch, pre = _mid_merge_repo()
    try:
        monkeypatch.setattr(gitad, "EMERGENCY_GIT_TIMEOUT", 1)
        seen = _hang_on(monkeypatch, "reset", "--hard")
        start = time.monotonic()
        g.emergency_abort()          # must not raise
        assert time.monotonic() - start < 15
        assert ("reset", "--hard", pre) in seen
        assert _no_index_lock(d)
    finally:
        _cleanup_full(key, branch, d)


def test_emergency_abort_every_git_call_hanging_never_raises(monkeypatch):
    """Even the detection calls hang: emergency_abort returns without raising."""
    g, d, key, branch, _pre = _mid_merge_repo()
    try:
        monkeypatch.setattr(gitad, "EMERGENCY_GIT_TIMEOUT", 1)
        _hang_on(monkeypatch)        # empty prefix matches every call
        start = time.monotonic()
        g.emergency_abort()
        assert time.monotonic() - start < 15
    finally:
        _cleanup_full(key, branch, d)
