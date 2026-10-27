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


def _sh_at(d, when, *args, author=("t", "t@t")):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": author[0],
        "GIT_AUTHOR_EMAIL": author[1],
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
        "GIT_AUTHOR_DATE": when,
        "GIT_COMMITTER_DATE": when,
        "GIT_EDITOR": "true",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=True, capture_output=True, text=True, env=env
    )


def _make_small_repo(branch):
    """Base + proof-scripts commit on master. Returns (d, fork_sha); HEAD is master."""
    d = tempfile.mkdtemp(prefix="postplan-sqb-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    _write(d, "f.txt", "a\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")
    return d, _rev(d, "HEAD")


def _ls_tree(d, ref="HEAD"):
    return _sh(d, "ls-tree", "-r", "--name-only", ref).stdout.split()


def _add_master_feature_conflict(d, branch):
    _sh(d, "checkout", "master")
    _write(d, "feature.txt", "master version\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "m4: feature.txt on master")
    _sh(d, "update-ref", "refs/remotes/origin/master", _rev(d, "HEAD"))
    _sh(d, "checkout", branch)


def test_squash_preserves_tree_and_collapses_range_to_one_commit():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        g = LiveGit(d)
        pre = g.head()
        pre_tree = _rev(d, "HEAD^{tree}")
        mb = _sh(d, "merge-base", fork_sha, pre).stdout.strip()
        pre_diff = _sh(d, "diff", f"{fork_sha}...{pre}").stdout
        total = _sh(d, "rev-list", "--count", f"{fork_sha}..{pre}").stdout.strip()
        assert total == "7"
        new, note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, pre)
        assert fatal is False and new and g.head() == new
        assert _rev(d, f"{new}^{{tree}}") == pre_tree
        assert _sh(d, "rev-list", "--count", f"{mb}..HEAD").stdout.strip() == "1"
        assert _rev(d, "HEAD^") == mb
        assert _sh(d, "diff", f"{fork_sha}...HEAD").stdout == pre_diff  # tree-proof input unchanged
        assert not g.is_dirty() and _sh(d, "status", "--porcelain").stdout == ""
        subject = _sh(d, "reflog", "-1", "--format=%gs", f"refs/heads/{branch}").stdout
        assert subject.startswith(f"postplan: squash {total} commits (3 merges)")
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_linear_range_is_not_rewritten():
    branch = f"feature-sqb-{uuid.uuid4().hex[:8]}"
    key = branch
    d, fork_sha = _make_small_repo(branch)
    try:
        _sh(d, "checkout", "-b", branch)
        _write(d, "one.txt", "1\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "feat: one")
        _write(d, "two.txt", "2\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "feat: two")
        c2_sha = _rev(d, "HEAD")
        _sh(d, "checkout", "master")
        _write(d, "z.txt", "z\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "m1")
        m1 = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", m1)
        _sh(d, "checkout", branch)
        _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)

        g = LiveGit(d)
        assert g._squash_merge_range_for_replay(fork_sha, branch, c2_sha) == ("", "", False)
        assert g.head() == c2_sha
        assert not any(e.startswith("postplan: squash") for e in _reflog(d, branch))

        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert result.squash_note == ""
        assert _sh(d, "rev-list", "--count", f"{m1}..HEAD").stdout.strip() == "2"
        subjects = _sh(d, "log", "--format=%s", f"{m1}..HEAD").stdout.split("\n")
        assert "feat: one" in subjects and "feat: two" in subjects
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


class _NoCallLlm:
    def call_tooled(self, purpose, *a, **kw):
        raise AssertionError(f"unexpected model call: {purpose}")


def test_linear_range_is_not_rewritten_with_llm():
    """With a model wired in, a linear range that replays cleanly still keeps its commits."""
    d, fork_sha, branch, tip = _make_linear_branch(2)
    try:
        _sh(d, "checkout", "master")
        _write(d, "z.txt", "z\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "m1")
        m1 = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", m1)
        _sh(d, "checkout", branch)
        _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)

        result = LiveGit(d, llm=_NoCallLlm()).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert result.squash_note == ""
        assert _sh(d, "rev-list", "--count", f"{m1}..HEAD").stdout.strip() == "2"
        assert not any(e.startswith("postplan: squash") for e in _reflog(d, branch))
    finally:
        _cleanup_tmp(branch)
        shutil.rmtree(d, ignore_errors=True)


def _make_linear_branch(n):
    """`n` linear branch commits on top of the small repo. Returns (d, fork_sha, branch, tip)."""
    branch = f"feature-sqb-{uuid.uuid4().hex[:8]}"
    d, fork_sha = _make_small_repo(branch)
    _sh(d, "checkout", "-b", branch)
    for i in range(n):
        _write(d, f"lin{i}.txt", f"{i}\n")
        _write(d, "f.txt", f"a\nedit {i}\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", f"feat: linear {i}")
    return d, fork_sha, branch, _rev(d, "HEAD")


def test_linear_squash_net_diff_is_byte_identical():
    d, fork_sha, branch, tip = _make_linear_branch(3)
    try:
        g = LiveGit(d)
        new, note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, tip, include_linear=True)
        assert fatal is False and new, note
        assert note.startswith("squashed 3 commits (0 merges)")
        pre = subprocess.run(["git", "-C", d, "diff", "--binary", fork_sha, tip],
                             check=True, capture_output=True).stdout
        post = subprocess.run(["git", "-C", d, "diff", "--binary", fork_sha, new],
                              check=True, capture_output=True).stdout
        assert pre == post and pre
        assert _sh(d, "rev-list", "--count", f"{fork_sha}..{new}").stdout.strip() == "1"
        assert _rev(d, f"{new}^{{tree}}") == _rev(d, f"{tip}^{{tree}}")
        assert g.head() == new
    finally:
        _cleanup_tmp(branch)
        shutil.rmtree(d, ignore_errors=True)


def test_squash_identity_guard_declines_without_moving_ref(monkeypatch):
    d, fork_sha, branch, tip = _make_linear_branch(3)
    try:
        g = LiveGit(d)
        real = g._run_bytes
        calls = {"diff": 0}

        def tampered(*args, **kw):
            if args and args[0] == "diff":
                calls["diff"] += 1
                if calls["diff"] == 2:
                    return b"tampered"
            return real(*args, **kw)

        monkeypatch.setattr(g, "_run_bytes", tampered)
        new, note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, tip, include_linear=True)
        assert new == "" and fatal is True
        assert note.startswith("squash aborted: net diff"), note
        assert _rev(d, f"refs/heads/{branch}") == tip
        assert not any(e.startswith("postplan: squash") for e in _reflog(d, branch))
    finally:
        _cleanup_tmp(branch)
        shutil.rmtree(d, ignore_errors=True)


def test_linear_single_commit_range_is_not_squashed():
    d, fork_sha, branch, tip = _make_linear_branch(1)
    try:
        g = LiveGit(d)
        assert g._squash_merge_range_for_replay(
            fork_sha, branch, tip, include_linear=True) == ("", "", False)
        assert _rev(d, f"refs/heads/{branch}") == tip
        assert not any(e.startswith("postplan: squash") for e in _reflog(d, branch))
    finally:
        _cleanup_tmp(branch)
        shutil.rmtree(d, ignore_errors=True)


def test_squash_message_uses_first_parent_branch_commit():
    branch = f"feature-sqb-{uuid.uuid4().hex[:8]}"
    key = branch
    d, fork_sha = _make_small_repo(branch)
    try:
        _write(d, "f.txt", "c\n")
        _sh_at(d, "2026-01-02T12:00:00+0000", "commit", "-am", "m1: master change",
               author=("Master Author", "m@m"))
        _sh(d, "checkout", "-b", branch, fork_sha)
        _write(d, "feature.txt", "feature\n")
        _sh_at(d, "2026-01-03T12:00:00+0000", "add", "-A")
        _sh_at(d, "2026-01-03T12:00:00+0000", "commit", "-m", "feat: branch work\n\nbody line",
               author=("Branch Author", "b@b"))
        c1 = _rev(d, "HEAD")
        _sh(d, "merge", "--no-edit", "master")
        _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)

        g = LiveGit(d)
        new, _note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, g.head())
        assert not fatal and new
        assert _sh(d, "log", "-1", "--format=%an", "HEAD").stdout.strip() == "Branch Author"
        body = _sh(d, "log", "-1", "--format=%B", "HEAD").stdout
        assert body.startswith("feat: branch work") and "body line" in body
        assert (_sh(d, "log", "-1", "--format=%ad", "--date=raw", "HEAD").stdout
                == _sh(d, "log", "-1", "--format=%ad", "--date=raw", c1).stdout)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_squash_message_falls_back_when_no_branch_commit():
    branch = f"feature-sqb-{uuid.uuid4().hex[:8]}"
    key = branch
    d, fork_sha = _make_small_repo(branch)
    try:
        _sh(d, "branch", branch, fork_sha)
        _write(d, "f.txt", "c\n")
        _sh(d, "commit", "-am", "m1")
        _sh(d, "checkout", branch)
        _sh(d, "merge", "--no-ff", "--no-edit", "master")
        _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)

        g = LiveGit(d)
        pre = g.head()
        pre_tree = _rev(d, "HEAD^{tree}")
        new, _note, fatal = g._squash_merge_range_for_replay(fork_sha, branch, pre)
        assert not fatal and new
        assert _sh(d, "log", "-1", "--format=%B", "HEAD").stdout.startswith(
            "chore: squash branch history before replay")
        assert _rev(d, "HEAD^{tree}") == pre_tree
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def _make_stale_base_repo():
    """Merge-range fixture whose iblBase names a local ref that is not an ancestor of HEAD."""
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    _sh(d, "checkout", "-b", "stale-master", fork_sha)
    _write(d, "z.txt", "z\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "local-only base commit")
    stale = _rev(d, "HEAD")
    _sh(d, "checkout", branch)
    _sh(d, "config", f"branch.{branch}.iblBase", stale)
    assert _sh(d, "merge-base", "--is-ancestor", stale, "HEAD", check=False).returncode == 1
    return d, fork_sha, master_sha, key, branch, stale


def test_stale_ibl_base_not_ancestor_squashes_onto_merge_base():
    d, fork_sha, master_sha, key, branch, stale = _make_stale_base_repo()
    d2 = None
    try:
        g = LiveGit(d)
        pre = g.head()
        pre_tree = _rev(d, "HEAD^{tree}")
        new, _note, fatal = g._squash_merge_range_for_replay(stale, branch, pre)
        assert not fatal and new
        assert _rev(d, "HEAD^") == fork_sha
        assert _rev(d, "HEAD^") != stale
        assert _rev(d, "HEAD^{tree}") == pre_tree
        assert "z.txt" not in _ls_tree(d)

        d2, fork2, master2, key2, branch2, _stale2 = _make_stale_base_repo()
        result = LiveGit(d2).autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert _sh(d2, "rev-list", "--count", f"{master2}..HEAD").stdout.strip() == "1"
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
        if d2:
            _cleanup_tmp(key2)
            shutil.rmtree(d2, ignore_errors=True)


def _init_scripts_repo(prefix):
    """master with f.txt, x.txt and the two proof scripts; returns (dir, fork_sha)."""
    d = tempfile.mkdtemp(prefix=prefix)
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    _write(d, "f.txt", "a\n")
    _write(d, "x.txt", "x\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    scripts_dir = os.path.join(d, ".claude", "review-shared", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: proof scripts")
    return d, _rev(d, "HEAD")


def _finish_fixture(d, branch, fork_sha):
    master_sha = _rev(d, "master")
    _sh(d, "checkout", branch)
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    _sh(d, "config", f"branch.{branch}.iblBase", fork_sha)  # the ordinary `iblBase=master` shape, stale
    assert _sh(d, "merge-base", "--is-ancestor", fork_sha, master_sha, check=False).returncode == 0
    return master_sha


def _make_master_reversed_deletion_repo():
    """(a) master deletes x.txt, the branch merge-pulls that, master re-adds x.txt with
    identical content, the branch has not pulled again. The branch never touched x.txt."""
    branch = f"feature-rev-{uuid.uuid4().hex[:8]}"
    d, fork_sha = _init_scripts_repo("postplan-rev-test-")
    _sh(d, "checkout", "-b", branch)
    _write(d, "feature.txt", "feature\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: feature work")
    _sh(d, "checkout", "master")
    _sh(d, "rm", "-q", "x.txt")
    _sh(d, "commit", "-m", "m1: delete x.txt")
    _sh(d, "checkout", branch)
    _sh(d, "merge", "--no-edit", "master")
    _sh(d, "checkout", "master")
    _write(d, "x.txt", "x\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "m2: re-add x.txt")
    master_sha = _finish_fixture(d, branch, fork_sha)
    assert "x.txt" not in _ls_tree(d)
    return d, fork_sha, master_sha, branch, branch


def _make_branch_deletion_repo(master_edits_x):
    """(b) the branch itself deletes x.txt, merge-pulls an unrelated master commit, and
    origin/master is one commit further; with master_edits_x that commit edits x.txt."""
    branch = f"feature-del-{uuid.uuid4().hex[:8]}"
    d, fork_sha = _init_scripts_repo("postplan-del-test-")
    _sh(d, "checkout", "-b", branch)
    _sh(d, "rm", "-q", "x.txt")
    _write(d, "feature.txt", "feature\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "feat: delete x.txt, add feature")
    _sh(d, "checkout", "master")
    _write(d, "other.txt", "o\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "m1: unrelated")
    _sh(d, "checkout", branch)
    _sh(d, "merge", "--no-edit", "master")
    _sh(d, "checkout", "master")
    if master_edits_x:
        _write(d, "x.txt", "x2\n")
    else:
        _write(d, "later.txt", "l\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "m2")
    master_sha = _finish_fixture(d, branch, fork_sha)
    return d, fork_sha, master_sha, branch, branch


def _spy_diff_ranges(g):
    """Record every `_run_bytes` call so a test can pin the pre-patch range."""
    calls = []
    orig = g._run_bytes

    def spy(*args, **kw):
        calls.append(args)
        return orig(*args, **kw)

    g._run_bytes = spy
    return calls


def test_stale_master_base_merge_pull_reversed_deletion_proves_equivalent():
    d, fork_sha, master_sha, key, branch = _make_master_reversed_deletion_repo()
    try:
        g = LiveGit(d)
        calls = _spy_diff_ranges(g)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert ("diff", f"{master_sha}...HEAD") in calls
        assert ("diff", f"{fork_sha}...HEAD") not in calls
        assert "x.txt" in _ls_tree(d)           # master's re-added file survives the replay
        assert "feature.txt" in _ls_tree(d)
        assert _rev(d, "HEAD^") == master_sha
        assert _sh(d, "rev-list", "--count", f"{master_sha}..HEAD").stdout.strip() == "1"
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_stacked_path_branch_deletion_is_still_charged_to_the_branch():
    d, fork_sha, master_sha, key, branch = _make_branch_deletion_repo(master_edits_x=False)
    try:
        g = LiveGit(d)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert "x.txt" not in _ls_tree(d)       # the branch's own deletion is applied
        pre = open(f"/tmp/pr-ready-diff-pre-{key}.patch").read()
        assert "deleted file mode 100644" in pre and "a/x.txt" in pre
        assert "other.txt" not in pre           # master's m1 is not in the branch's patch
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_stacked_path_branch_deletion_vs_master_edit_declines_not_silently_kept():
    """A real deletion the replay cannot honour (master edited x.txt) must decline and
    restore the branch; it must never come back as an x.txt the proof then waves through."""
    d, fork_sha, master_sha, key, branch = _make_branch_deletion_repo(master_edits_x=True)
    try:
        g = LiveGit(d)
        pre = g.head()
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert g.head() == pre
        assert "x.txt" not in _ls_tree(d)
        assert not os.path.exists(os.path.join(d, ".git", "rebase-merge"))
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_true_stacked_parent_keeps_ibl_base_range():
    d, fork_sha, master_sha, key, branch, stale = _make_stale_base_repo()
    try:
        g = LiveGit(d)
        calls = _spy_diff_ranges(g)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert ("diff", f"{stale}...HEAD") in calls
        assert ("diff", f"{master_sha}...HEAD") not in calls
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_hold_commit_is_squashed_with_clean_tree():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        _write(d, "hold.txt", "held\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: hold commit")
        pre_tree = _rev(d, "HEAD^{tree}")
        g = LiveGit(d)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        assert _sh(d, "rev-list", "--count", f"{master_sha}..HEAD").stdout.strip() == "1"
        assert _sh(d, "status", "--porcelain").stdout == ""
        assert _rev(d, "HEAD^{tree}") == pre_tree
        assert "hold.txt" in _ls_tree(d)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_hold_commit_restored_exactly_on_decline():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        _write(d, "hold.txt", "held\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: hold commit")
        hold_sha = _rev(d, "HEAD")
        _add_master_feature_conflict(d, branch)
        g = LiveGit(d)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert g.head() == hold_sha
        assert _sh(d, "log", "-1", "--format=%s").stdout.strip() == "chore: hold commit"
        assert _sh(d, "status", "--porcelain").stdout == ""
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_collapse_guard_check_not_tripped_after_squash():
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    try:
        g = LiveGit(d)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        proc = subprocess.run(
            ["bash", f"/tmp/postplan-collapse-guard-{key}.sh", "check", key, branch],
            cwd=d, capture_output=True, text=True,
        )
        out = proc.stdout + proc.stderr
        assert proc.returncode == 0, out
        assert "STOP:" not in out
        assert "NO-COLLAPSE" in out
        log = _reflog(d, branch)
        assert any(e.startswith("postplan: squash") for e in log)
        assert not any(e.startswith(("reset:", "amend", "filter-branch")) for e in log)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def _setup_pushed_merge_range(push_remote="pub"):
    """Phase 1 fixture plus a bare remote already holding the pre-squash tip."""
    d, fork_sha, master_sha, key, branch = _make_merge_range_repo()
    bare = tempfile.mkdtemp(prefix="postplan-sqb-bare-")
    subprocess.run(["git", "init", "--bare", bare], check=True, capture_output=True)
    _sh(d, "remote", "add", push_remote, bare)
    _sh(d, "push", push_remote, f"{branch}:refs/heads/{branch}")
    g = LiveGit(d, push_remote=push_remote)
    pre_tip = g.head()
    assert _rev(d, f"refs/remotes/{push_remote}/{branch}") == pre_tip
    return d, fork_sha, master_sha, key, branch, bare, g, pre_tip


def test_push_after_squash_leases_against_pre_squash_remote_tip():
    d, fork_sha, master_sha, key, branch, bare, g, pre_tip = _setup_pushed_merge_range()
    try:
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason

        recorded = []
        original_run_out = g._run_out

        def spy_run_out(*args):
            recorded.append(args)
            return original_run_out(*args)

        g._run_out = spy_run_out
        g.push()

        push_args = [a for a in recorded if a and a[0] == "push"]
        assert push_args, "no push argv recorded"
        assert f"--force-with-lease={branch}:{pre_tip}" in push_args[0]
        assert f"HEAD:refs/heads/{branch}" in push_args[0]
        remote_tip = subprocess.run(
            ["git", "-C", bare, "rev-parse", f"refs/heads/{branch}"],
            check=True, capture_output=True, text=True,
        ).stdout.strip()
        assert remote_tip == g.head()
        assert g.head() != pre_tip
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
        shutil.rmtree(bare, ignore_errors=True)


def test_push_after_squash_rejects_when_remote_moved():
    d, fork_sha, master_sha, key, branch, bare, g, pre_tip = _setup_pushed_merge_range()
    try:
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is True, result.reason
        # Move the remote behind the worktree's back. git 2.56 can update
        # refs/remotes/pub/<branch> on a URL push, so pin it back to pre_tip.
        _sh(d, "push", "--force", bare, f"{master_sha}:refs/heads/{branch}")
        _sh(d, "update-ref", f"refs/remotes/pub/{branch}", pre_tip)
        assert _rev(d, f"refs/remotes/pub/{branch}") == pre_tip
        with pytest.raises(HarnessError) as ei:
            g.push()
        assert ei.value.kind == "push-failed"
        remote_tip = subprocess.run(
            ["git", "-C", bare, "rev-parse", f"refs/heads/{branch}"],
            check=True, capture_output=True, text=True,
        ).stdout.strip()
        assert remote_tip == master_sha
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
        shutil.rmtree(bare, ignore_errors=True)


def test_declined_squash_run_leaves_branch_not_diverged_from_remote():
    d, fork_sha, master_sha, key, branch, bare, g, pre_tip = _setup_pushed_merge_range()
    try:
        _add_master_feature_conflict(d, branch)
        result = g.autoresolve_stacked_rebase()
        assert result.resolved is False
        assert result.squash_note.startswith("squashed ")
        counts = _sh(d, "rev-list", "--left-right", "--count",
                     f"refs/remotes/pub/{branch}...HEAD").stdout.split()
        assert counts == ["0", "0"]
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
        shutil.rmtree(bare, ignore_errors=True)
