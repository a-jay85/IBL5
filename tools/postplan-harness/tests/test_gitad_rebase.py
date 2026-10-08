"""Phase 2 merges origin/master into the branch instead of rebasing onto it.

These tests drive LiveGit.rebase_onto() against scratch repos and pin the merge
shape: branch commits stay ancestors of HEAD, a resolved conflict lands as a
two-parent merge commit, and every failure path leaves no MERGE_HEAD behind.
"""
import os
import shutil
import subprocess
import sys
import tempfile
import uuid

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError
from test_gitad_stacked_rebase import (
    _LOSTWORK_EQUIV,
    _WritingLlm,
    _cleanup_full,
    _make_modify_conflict_repo,
    _make_simple_conflict_repo,
    _rev,
    _sh,
)


def _merge_head(d):
    return _sh(d, "rev-parse", "-q", "--verify", "MERGE_HEAD", check=False).stdout.strip()


def _parents(d, ref="HEAD"):
    return _sh(d, "rev-list", "--parents", "-n", "1", ref).stdout.split()[1:]


def _is_ancestor(d, a, b):
    return _sh(d, "merge-base", "--is-ancestor", a, b, check=False).returncode == 0


def _make_clean_repo():
    """Master and branch touch different files, so the merge has no conflict.

    Returns (d, key, branch, branch_shas, master_sha).
    """
    branch = f"feature-cl-{uuid.uuid4().hex[:8]}"
    d = tempfile.mkdtemp(prefix="postplan-cl-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    _sh(d, "config", "user.email", "t@t")
    _sh(d, "config", "user.name", "t")
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "base")
    base_sha = _rev(d, "HEAD")

    open(os.path.join(d, "m.txt"), "w").write("master\n")
    _sh(d, "add", "-A")
    _sh(d, "commit", "-m", "chore: master work")
    master_sha = _rev(d, "HEAD")
    _sh(d, "update-ref", "refs/remotes/origin/master", master_sha)

    _sh(d, "checkout", "-b", branch, base_sha)
    branch_shas = []
    for step in ("one", "two"):
        open(os.path.join(d, "b.txt"), "w").write(f"{step}\n")
        _sh(d, "add", "-A")
        _sh(d, "commit", "-m", f"feat: step {step}")
        branch_shas.append(_rev(d, "HEAD"))
    return d, branch, branch, branch_shas, master_sha


def test_rebase_onto_keeps_branch_commits_as_ancestors():
    d, key, branch, branch_shas, master_sha = _make_clean_repo()
    try:
        LiveGit(d).rebase_onto()
        for sha in branch_shas:
            assert _is_ancestor(d, sha, "HEAD"), f"branch commit {sha} was rewritten"
        assert _is_ancestor(d, master_sha, "HEAD")
        assert _parents(d) == [branch_shas[-1], master_sha]
        assert _merge_head(d) == ""
    finally:
        _cleanup_full(key, branch, d)


def test_rebase_onto_already_up_to_date_is_noop():
    d, key, branch, _branch_shas, _master_sha = _make_clean_repo()
    try:
        _sh(d, "merge", "--no-edit", "origin/master")
        before = _rev(d, "HEAD")
        LiveGit(d).rebase_onto()
        assert _rev(d, "HEAD") == before
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(key, branch, d)


def test_rebase_onto_conflict_without_llm_restores():
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    try:
        g = LiveGit(d)
        with pytest.raises(HarnessError) as exc:
            g.rebase_onto()
        assert exc.value.kind == "rebase-conflict"
        assert "feature.txt" in g.last_conflict_files
        assert _merge_head(d) == ""
        assert _rev(d, "HEAD") == pre
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(key, branch, d)


def test_rebase_onto_conflict_resolved_lands_merge_commit():
    d, _base, master_sha, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    from harness.armable import conflict_flag_path
    llm = _WritingLlm(d, "feature.txt", content="merged content\n")
    try:
        g = LiveGit(d, llm=llm)
        g.rebase_onto()
        assert g.last_conflict_resolution is not None
        assert g.last_conflict_resolution.auto_resolved is True
        assert g.last_conflict_resolution.manifest_path
        assert os.path.exists(conflict_flag_path(branch))
        assert _parents(d) == [pre, master_sha]
        assert _merge_head(d) == ""
        assert open(os.path.join(d, "feature.txt")).read() == "merged content\n"
    finally:
        _cleanup_full(key, branch, d)


def test_rebase_onto_merge_commit_hook_denial_restores():
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    hooks = os.path.join(d, ".git", "hooks")
    os.makedirs(hooks, exist_ok=True)
    hook = os.path.join(hooks, "pre-commit")
    with open(hook, "w") as fh:
        fh.write("#!/usr/bin/env bash\necho 'pre-commit-adr-gate: write the ADR'\nexit 1\n")
    os.chmod(hook, 0o755)
    _sh(d, "config", "core.hooksPath", hooks)
    llm = _WritingLlm(d, "feature.txt", content="merged content\n")
    try:
        with pytest.raises(HarnessError) as exc:
            LiveGit(d, llm=llm).rebase_onto()
        assert "merge commit failed" in exc.value.detail
        assert "pre-commit-adr-gate: write the ADR" in exc.value.detail
        assert _merge_head(d) == ""
        assert _rev(d, "HEAD") == pre
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(key, branch, d)


def test_emergency_abort_clears_merge_head():
    d, _base, _master, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    pre = _rev(d, "HEAD")
    try:
        _sh(d, "merge", "--no-edit", "origin/master", check=False)
        assert _merge_head(d) != "", "fixture must stop mid-merge"
        g = LiveGit(d)
        g._pre_rebase_sha = pre
        g.emergency_abort()
        assert _merge_head(d) == ""
        assert _rev(d, "HEAD") == pre
        assert _sh(d, "status", "--porcelain").stdout.strip() == ""
    finally:
        _cleanup_full(key, branch, d)


def test_resolve_all_orientation_per_path(monkeypatch):
    import harness.conflict as conflict

    seen = []

    def recording_resolve_all(llm, run, **kwargs):
        seen.append(kwargs)
        return conflict.ConflictResolutionResult(False, "stub: stop after recording")

    monkeypatch.setattr(conflict, "resolve_all", recording_resolve_all)

    d, _base, master_sha, key, branch = _make_simple_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    try:
        with pytest.raises(HarnessError):
            LiveGit(d, llm=_WritingLlm(d, "feature.txt")).rebase_onto()
    finally:
        _cleanup_full(key, branch, d)
    assert seen[-1]["branch_stage"] == 2
    assert seen[-1]["master_sha"] == master_sha

    d, _tip, master_sha, key, branch = _make_modify_conflict_repo(
        lostwork_script=_LOSTWORK_EQUIV)
    try:
        with pytest.raises(HarnessError):
            LiveGit(d, llm=_WritingLlm(d, "feature.txt")).autoresolve_stacked_rebase()
    finally:
        _cleanup_full(key, branch, d)
    assert seen[-1]["branch_stage"] == 3
    assert seen[-1]["master_sha"] == master_sha
    assert len(seen) == 2
