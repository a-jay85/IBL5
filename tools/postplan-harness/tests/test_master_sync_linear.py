"""The Phase 2 master sync keeps a linear branch.

These tests drive the real LiveGit adapter against throwaway repos (llm=None) and pin
what `rebase_onto(base)` leaves behind: the branch's content, the history shape, the
fail-closed conflict contract, and the stacked squash-merged-parent protection.
"""
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError
from test_gitad_rebase import _is_ancestor, _make_clean_repo, _merge_head
from test_gitad_stacked_rebase import (
    _LOSTWORK_EQUIV,
    _cleanup_full,
    _make_simple_conflict_repo,
    _make_squash_repo,
    _cleanup_tmp,
    _rev,
    _sh,
)

import shutil

_GIT_ENV = {**os.environ,
            "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
            "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _merges(d, base="origin/master"):
    out = _sh(d, "rev-list", "--merges", f"{base}..HEAD").stdout.split()
    return out


def _rebase_dirs(d):
    return [
        sub for sub in ("rebase-merge", "rebase-apply")
        if os.path.exists(os.path.join(d, ".git", sub))
    ]


def test_sync_behind_branch_keeps_branch_content():
    d, key, branch, branch_shas, master_sha = _make_clean_repo()
    try:
        LiveGit(d).rebase_onto()
        assert os.path.exists(os.path.join(d, "m.txt"))
        assert open(os.path.join(d, "b.txt")).read() == "two\n"
        assert _is_ancestor(d, "origin/master", "HEAD")
    finally:
        _cleanup_full(key, branch, d)


def test_sync_behind_branch_history_shape():
    d, key, branch, branch_shas, master_sha = _make_clean_repo()
    try:
        LiveGit(d).rebase_onto()
        assert len(_merges(d)) == 0
        subjects = _sh(d, "log", "--format=%s", f"{master_sha}..HEAD").stdout.split("\n")
        assert [s for s in subjects if s] == ["feat: step two", "feat: step one"]
    finally:
        _cleanup_full(key, branch, d)


def test_sync_conflict_without_llm_restores_and_raises():
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    try:
        g = LiveGit(d)
        with pytest.raises(HarnessError) as exc:
            g.rebase_onto()
        assert exc.value.kind == "rebase-conflict"
        assert _rev(d, "HEAD") == pre
        assert _merge_head(d) == ""
        assert _rebase_dirs(d) == []
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(key, branch, d)


def test_sync_stacked_squash_parent_not_replayed():
    d, parent_tip, master_sha, key, branch = _make_squash_repo()
    try:
        LiveGit(d).rebase_onto()
        subjects = [
            s for s in _sh(d, "log", "--format=%s", f"{master_sha}..HEAD").stdout.split("\n") if s
        ]
        assert subjects == ["feat: feature work"]
        assert open(os.path.join(d, "parent.txt")).read() == "step2\n"
        assert open(os.path.join(d, "feature.txt")).read() == "feature content\n"
        assert _rebase_dirs(d) == []
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)


def test_sync_rebuilds_legacy_merge_commit_linear():
    d, key, branch, branch_shas, master_sha = _make_clean_repo()
    try:
        _sh(d, "merge", "--no-edit", "origin/master")
        assert len(_merges(d, base=master_sha)) == 1
        _sh(d, "checkout", "-q", "--detach", "origin/master")
        open(os.path.join(d, "m2.txt"), "w").write("master two\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: master moved")
        new_master = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", new_master)
        _sh(d, "checkout", "-q", branch)

        LiveGit(d).rebase_onto()

        assert _merges(d) == []
        assert _is_ancestor(d, new_master, "HEAD")
        for name, text in (("m.txt", "master\n"), ("m2.txt", "master two\n"), ("b.txt", "two\n")):
            assert open(os.path.join(d, name)).read() == text
    finally:
        _cleanup_full(key, branch, d)


def test_sync_keeps_edit_made_inside_legacy_merge_commit():
    d, key, branch, branch_shas, master_sha = _make_clean_repo()
    try:
        _sh(d, "merge", "--no-commit", "--no-ff", "origin/master")
        open(os.path.join(d, "inside.txt"), "w").write("edit inside merge\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "merge with its own edit")
        assert len(_merges(d, base=master_sha)) == 1
        _sh(d, "checkout", "-q", "--detach", "origin/master")
        open(os.path.join(d, "m2.txt"), "w").write("master two\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", "chore: master moved")
        new_master = _rev(d, "HEAD")
        _sh(d, "update-ref", "refs/remotes/origin/master", new_master)
        _sh(d, "checkout", "-q", branch)

        LiveGit(d).rebase_onto()

        assert _merges(d) == []
        assert _is_ancestor(d, new_master, "HEAD")
        assert open(os.path.join(d, "inside.txt")).read() == "edit inside merge\n"
    finally:
        _cleanup_full(key, branch, d)


def _git(*args, cwd=None):
    return subprocess.run(["git", *args], cwd=cwd, check=True, capture_output=True,
                          text=True, env=_GIT_ENV).stdout.strip()


def _commit_file(repo, name, text="x\n"):
    (repo / name).write_text(text)
    _git("add", "-A", cwd=str(repo))
    _git("commit", "-m", name, cwd=str(repo))


def _synced_feature_repo(tmp_path):
    """Published `feature` (tracking ref set), master moved on the remote, then a real
    rebase_onto sync that rewrites HEAD. Returns (LiveGit, wt, bare, tracking_sha)."""
    wt, bare = tmp_path / "wt", tmp_path / "bare"
    wt.mkdir()
    bare.mkdir()
    _git("init", "-b", "master", str(wt))
    _git("config", "user.email", "t@t", cwd=str(wt))
    _git("config", "user.name", "t", cwd=str(wt))
    _commit_file(wt, "base.txt")
    _git("init", "--bare", str(bare))
    _git("remote", "add", "origin", str(bare), cwd=str(wt))
    _git("push", "origin", "master", cwd=str(wt))
    _git("checkout", "-b", "feature", cwd=str(wt))
    _commit_file(wt, "feat.txt")
    _git("push", "origin", "feature", cwd=str(wt))
    other = tmp_path / "other"
    _git("clone", "-b", "master", str(bare), str(other))
    _git("config", "user.email", "o@o", cwd=str(other))
    _git("config", "user.name", "o", cwd=str(other))
    _commit_file(other, "master-moved.txt")
    _git("push", "origin", "master", cwd=str(other))
    _git("fetch", "origin", cwd=str(wt))
    g = LiveGit(str(wt), push_remote="origin")
    pre = _git("rev-parse", "HEAD", cwd=str(wt))
    g.rebase_onto()
    assert _git("rev-parse", "HEAD", cwd=str(wt)) != pre
    tracking = _git("rev-parse", "refs/remotes/origin/feature", cwd=str(wt))
    return g, wt, bare, tracking


def test_push_uses_explicit_refs_heads_lease(tmp_path):
    g, wt, bare, tracking = _synced_feature_repo(tmp_path)
    argvs = []
    orig = g._run_out

    def spy(*args):
        argvs.append(args)
        return orig(*args)

    g._run_out = spy
    g.push()
    pushes = [a for a in argvs if a and a[0] == "push"]
    assert pushes == [("push", f"--force-with-lease=refs/heads/feature:{tracking}",
                       "origin", "HEAD:refs/heads/feature")]
    assert _git("rev-parse", "refs/heads/feature", cwd=str(bare)) == \
        _git("rev-parse", "HEAD", cwd=str(wt))


def test_push_after_sync_rejected_when_remote_moved(tmp_path):
    g, wt, bare, tracking = _synced_feature_repo(tmp_path)
    rival = tmp_path / "rival"
    _git("clone", "-b", "feature", str(bare), str(rival))
    _git("config", "user.email", "r@r", cwd=str(rival))
    _git("config", "user.name", "r", cwd=str(rival))
    _commit_file(rival, "rival.txt")
    _git("push", "origin", "HEAD:refs/heads/feature", cwd=str(rival))
    x = _git("rev-parse", "HEAD", cwd=str(rival))

    with pytest.raises(HarnessError) as exc:
        g.push()
    assert exc.value.kind == "push-failed"
    assert _git("ls-remote", str(bare), "refs/heads/feature").split()[0] == x


class _RecordingLlm:
    """Records each prompt and declines with FAILED."""

    def __init__(self):
        self.prompts = []

    def call_tooled(self, purpose, model, prompt, **_):
        self.prompts.append(prompt)
        return "FAILED"


def test_sync_modify_delete_branch_side_named_from_stage_3(tmp_path):
    from harness import conflict
    from test_conflict_resolver import _StubRun

    assert conflict.SYNC_BRANCH_STAGE == 3
    stages = frozenset({1, 2})  # stage 3 missing: under the rebase sync the branch deleted it

    # Branch side = stage 3: the branch deleted the file, so the resolver keeps the deletion
    # without asking the model.
    llm = _RecordingLlm()
    run = _StubRun(str(tmp_path))
    ok, reason = conflict.resolve_one(
        llm, run, worktree=str(tmp_path), key="msl-branch-deleted", path="gone.txt",
        stages=stages, branch_stage=conflict.SYNC_BRANCH_STAGE)
    assert ok is True and reason == ""
    assert llm.prompts == []
    assert ("rm", "-q", "--", "gone.txt") in run.calls

    # Branch side = stage 2 (merge orientation): the same stage set means master deleted it,
    # so the model is asked and the prompt names the branch as stage 2 (ours).
    llm = _RecordingLlm()
    run = _StubRun(str(tmp_path))
    ok, _ = conflict.resolve_one(
        llm, run, worktree=str(tmp_path), key="msl-master-deleted", path="gone.txt",
        stages=stages, branch_stage=2, max_rounds=1)
    assert ok is False
    assert ("rm", "-q", "--", "gone.txt") not in run.calls
    assert len(llm.prompts) == 1
    assert "Stage 2 (ours) is the branch; stage 3 (theirs) is master." in llm.prompts[0]
    assert "One side deleted this file." in llm.prompts[0]
