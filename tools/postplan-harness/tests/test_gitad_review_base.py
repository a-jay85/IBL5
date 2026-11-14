"""LiveGit.review_base(): the base a stacked PR's review-facing diff starts from."""
import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import MASTER_REVIEW_BASE, LiveGit, ReviewBase
from harness.state import HarnessError

_ENV = {"GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _sh(repo, *a):
    return subprocess.run(["git", "-C", str(repo), *a], check=True, capture_output=True,
                          text=True, env={**os.environ, **_ENV}).stdout.strip()


def _commit(repo, name, text):
    (repo / name).write_text(text)
    _sh(repo, "add", "-A")
    _sh(repo, "commit", "-m", f"add {name}")
    return _sh(repo, "rev-parse", "HEAD")


@pytest.fixture()
def stacked(tmp_path, monkeypatch):
    repo = tmp_path / "repo"
    repo.mkdir()
    subprocess.run(["git", "init", "-b", "master", str(repo)], check=True, capture_output=True)
    _sh(repo, "config", "user.email", "t@t")
    _sh(repo, "config", "user.name", "t")
    c0 = _commit(repo, "base.txt", "base\n")
    _sh(repo, "checkout", "-b", "parent")
    _commit(repo, "A.txt", "a\n")
    p = _commit(repo, "E.txt", "e\n")
    _sh(repo, "checkout", "-b", "child")
    c = _commit(repo, "B.txt", "b\n")
    _sh(repo, "checkout", "master")
    _commit(repo, "D.txt", "d\n")
    m = _commit(repo, "E.txt", "e\n")
    _sh(repo, "update-ref", "refs/remotes/origin/master", m)
    _sh(repo, "update-ref", "refs/remotes/origin/parent", p)
    _sh(repo, "config", "branch.child.iblBase", p)
    _sh(repo, "checkout", "child")
    monkeypatch.setattr(LiveGit, "merge_target", lambda self: "origin/parent")
    return LiveGit(str(repo)), repo, {"c0": c0, "P": p, "C": c, "M": m}


def test_master_diff_still_lists_parent_and_child_files(stacked):
    git, _, _ = stacked
    assert git.changed_files() == ["A.txt", "B.txt", "E.txt"]


def test_review_base_stacked_parent_resolves_parent_tip(stacked):
    git, _, shas = stacked
    rb = git.review_base()
    assert rb.ref == shas["P"]
    assert len(rb.ref) == 40
    assert rb.parent == "parent"


def test_review_base_review_diff_lists_only_child_files(stacked):
    git, _, _ = stacked
    rb = git.review_base()
    assert git.changed_files(rb.ref) == ["B.txt"]
    diff = git.diff_vs_base(rb.ref)
    assert "A.txt" not in diff
    assert "E.txt" not in diff


def test_review_base_rebased_parent_uses_recorded_tip(stacked):
    git, repo, shas = stacked
    _sh(repo, "checkout", "--detach", shas["M"])
    p2 = _commit(repo, "A.txt", "a\n")
    _sh(repo, "update-ref", "refs/remotes/origin/parent", p2)
    _sh(repo, "checkout", "child")
    rb = git.review_base()
    assert rb.ref == shas["P"]
    assert git.changed_files(rb.ref) == ["B.txt"]


def test_review_base_without_iblbase_uses_parent_merge_base(stacked):
    git, repo, shas = stacked
    _sh(repo, "config", "--unset", "branch.child.iblBase")
    assert git.review_base().ref == shas["P"]


def test_review_base_unstacked_branch_is_master(stacked, monkeypatch):
    git, _, _ = stacked
    monkeypatch.setattr(LiveGit, "merge_target", lambda self: "origin/master")
    assert git.review_base() == MASTER_REVIEW_BASE


def test_review_base_merge_target_error_is_master(stacked, monkeypatch):
    git, _, _ = stacked

    def boom(self):
        raise HarnessError("base-resolve", "boom")

    monkeypatch.setattr(LiveGit, "merge_target", boom)
    assert git.review_base() == MASTER_REVIEW_BASE


def test_review_base_squash_merged_parent_is_master(stacked):
    git, repo, _ = stacked
    _sh(repo, "checkout", "master")
    m2 = _commit(repo, "A.txt", "a\n")
    _sh(repo, "update-ref", "refs/remotes/origin/master", m2)
    _sh(repo, "checkout", "child")
    assert git.review_base() == MASTER_REVIEW_BASE


def test_review_base_missing_parent_ref_is_master(stacked):
    git, repo, _ = stacked
    _sh(repo, "update-ref", "-d", "refs/remotes/origin/parent")
    assert git.review_base() == MASTER_REVIEW_BASE


def test_review_base_parent_contains_head_is_master(stacked):
    git, repo, shas = stacked
    _sh(repo, "update-ref", "refs/remotes/origin/parent", shas["C"])
    assert git.review_base() == MASTER_REVIEW_BASE


def test_review_base_describe_forms():
    assert MASTER_REVIEW_BASE.describe() == "master"
    assert ReviewBase("a" * 40, "parent").describe() == f"parent parent@{'a' * 12}"
