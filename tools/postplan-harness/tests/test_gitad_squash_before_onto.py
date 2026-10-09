"""Squash a branch's merge-master commits before the stacked --onto replay."""
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError

_REPO_ROOT = os.path.dirname(
    os.path.dirname(os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
)
_LOSTWORK = os.path.join(
    _REPO_ROOT, ".claude", "review-shared", "scripts", "lostwork.sh"
)
_COLLAPSE = os.path.join(
    _REPO_ROOT, ".claude", "review-shared", "scripts", "collapse-guard.sh"
)


def _sh(d, *args, check=True):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
        "GIT_EDITOR": "true",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=check, capture_output=True, text=True, env=env
    )


def _rev(d, ref):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _cleanup_tmp(key):
    """Remove /tmp files written by the resolver and the proof scripts."""
    safe = key  # key already has / replaced by -
    patterns = [
        f"/tmp/postplan-conflict-files-{key}.txt",
        f"/tmp/postplan-conflict-resolution-{key}.md",
        f"/tmp/postplan-lostwork-{key}.sh",
        f"/tmp/postplan-collapse-guard-{key}.sh",
        f"/tmp/pr-ready-diff-pre-{key}.patch",
        f"/tmp/pr-ready-diff-post-{key}.patch",
        f"/tmp/pr-ready-numstat-pre-{key}.txt",
        f"/tmp/pr-ready-numstat-post-{key}.txt",
        f"/tmp/pr-ready-collapse-guard-{key}-{safe}.meta",
    ]
    for p in patterns:
        if os.path.exists(p):
            os.unlink(p)


@pytest.fixture(autouse=True)
def _no_editor(monkeypatch):
    # A conflicted-merge commit or `rebase --continue` can otherwise block on an editor.
    monkeypatch.setenv("GIT_EDITOR", "true")


def _write(d, name, content):
    with open(os.path.join(d, name), "w") as fh:
        fh.write(content)


def _make_merge_range_repo(suffix=None, lostwork_script=None):
    """Branch whose iblBase..HEAD range holds three merge-master commits.

    Returns (d, fork_sha, master_sha, key, branch). Merge 1 resolves a conflict
    that a per-commit --onto replay (which drops merges) hits again.
    """
    if suffix is None:
        suffix = uuid.uuid4().hex[:8]
    branch = f"feature-sqb-{suffix}"
    key = branch

    d = tempfile.mkdtemp(prefix="postplan-sqb-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")

    _write(d, "f.txt", "a\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")

    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    if lostwork_script is not None:
        _write(scripts_dir, "lostwork.sh", lostwork_script)
    else:
        shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")
    fork_sha = _rev(d, "HEAD")

    # c1: the only branch-only commit
    _sh(d, "checkout", "-b", branch)
    _write(d, "f.txt", "b\n")
    _write(d, "feature.txt", "feature\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")

    # m1 on master, merged in with a conflict on f.txt resolved to "d"
    _sh(d, "checkout", "master")
    _write(d, "f.txt", "c\n")
    _sh(d, "commit", "-am", "m1")
    _sh(d, "checkout", branch)
    _sh(d, "merge", "--no-edit", "master", check=False)
    _write(d, "f.txt", "d\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "--no-edit")

    # m2 on master (f.txt already "d"), merged in cleanly
    _sh(d, "checkout", "master")
    _write(d, "f.txt", "d\n")
    _sh(d, "commit", "-am", "m2")
    _sh(d, "checkout", branch)
    _sh(d, "merge", "--no-edit", "master")

    # m3 on master, merged in cleanly
    _sh(d, "checkout", "master")
    _write(d, "other.txt", "o\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "m3")
    master_sha = _rev(d, "HEAD")
    _sh(d, "checkout", branch)
    _sh(d, "merge", "--no-edit", "master")

    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)
    return d, fork_sha, master_sha, key, branch


def test_merge_range_fixture_conflicts_under_per_commit_replay():
    """Fixture guard: raw per-commit --onto replay conflicts, so the red/green test is not vacuous."""
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        pre = _rev(d, "HEAD")
        proc = _sh(d, "rebase", "--onto", master_sha, fork_sha, branch, check=False)
        assert proc.returncode != 0
        _sh(d, "rebase", "--abort")
        assert _rev(d, "HEAD") == pre
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_merge_range_squashes_then_onto_resolves_tree_equivalent():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        g = LiveGit(d)
        pre_tree = _rev(d, "HEAD^{tree}")
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert result.reason == ""
        assert _sh(d, "rev-list", "--count", f"{master_sha}..HEAD").stdout.strip() == "1"
        assert _sh(d, "rev-list", "--merges", "--count", f"{master_sha}..HEAD").stdout.strip() == "0"
        assert _rev(d, "HEAD^{tree}") == pre_tree  # master's content is already in the branch tree
        assert _rev(d, "HEAD^") == master_sha
        assert not g.is_dirty()
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def _reflog(d, branch):
    return _sh(d, "reflog", "--format=%gs", f"refs/heads/{branch}").stdout.splitlines()


def test_merge_range_still_conflicts_decline_restores_pre_squash_head():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        # add/add conflict on feature.txt survives the squash
        _sh(d, "checkout", "master")
        _write(d, "feature.txt", "master version\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "m4: feature.txt on master")
        _sh(d, "update-ref", "refs/remotes/origin/master", _rev(d, "HEAD"))
        _sh(d, "checkout", branch)

        g = LiveGit(d)
        pre = g.head()
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert result.reason.startswith("--onto rebase still conflicts:")
        assert result.squash_note.startswith("squashed ")
        assert g.head() == pre
        assert not g.is_dirty()
        log = _reflog(d, branch)
        assert log[0] == "postplan: restore pre-squash HEAD"
        assert log[1].startswith("postplan: squash")
        assert not any(e.startswith(("reset:", "rebase")) for e in log)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_merge_range_proof_decline_restores_pre_squash_head():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo(
        lostwork_script="#!/usr/bin/env bash\necho 'TREE DIVERGED'\nexit 0\n",
    )
    try:
        # clean replay onto a newer master, so the post tree differs from the pre tree
        _sh(d, "checkout", "master")
        _write(d, "late.txt", "late\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "late")
        _sh(d, "update-ref", "refs/remotes/origin/master", _rev(d, "HEAD"))
        _sh(d, "checkout", branch)

        g = LiveGit(d)
        pre = g.head()
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert "TREE DIVERGED" in result.reason
        assert g.head() == pre
        assert not os.path.exists(os.path.join(d, "late.txt"))
        assert _sh(d, "status", "--porcelain").stdout == ""
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_sigterm_between_squash_and_rebase_restores_pre_squash_head():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        g = LiveGit(d)
        pre = g.head()
        g._pre_rebase_sha = pre
        g._squashed_from = pre
        new, _note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, pre)
        assert not fatal
        assert g.head() != pre
        g.emergency_abort()
        assert g.head() == pre
        assert not g.is_dirty()
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
