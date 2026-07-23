import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.state import HarnessError


@pytest.fixture()
def repo():
    d = tempfile.mkdtemp(prefix="postplan-git-test-")
    def sh(*a):
        subprocess.run(["git", "-C", d, *a], check=True, capture_output=True,
                       env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                            "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    sh("add", "-A"); sh("commit", "-m", "base")
    return d


def test_dirty_commit_and_changed_files(repo):
    g = LiveGit(repo)
    assert g.branch() == "master"
    assert not g.is_dirty()
    open(os.path.join(repo, "b.php"), "w").write("<?php\n")
    assert g.is_dirty()
    assert g.changed_files(base="HEAD") == ["b.php"]
    sha = g.commit_all("chore: add b")
    assert sha and not g.is_dirty()
    assert g.commit_all("noop") == ""      # nothing staged -> no empty commit


def test_dirty_tree_ships_from_merge_base(repo):
    """post-plan-now fires on a DIRTY worktree: uncommitted + untracked changes
    must appear in diff_vs_base, or every run is a false nothing-to-ship."""
    g = LiveGit(repo)
    open(os.path.join(repo, "new.sh"), "w").write("#!/bin/bash\n")   # untracked
    open(os.path.join(repo, "a.txt"), "a").write("edit\n")            # unstaged
    g.stage_all()
    diff = g.diff_vs_base(base="HEAD")
    assert "new.sh" in diff and "edit" in diff
    assert set(g.changed_files(base="HEAD")) == {"a.txt", "new.sh"}
    assert g.modified_files(base="HEAD") == ["a.txt"]


def test_diff_vs_base_uses_merge_base_not_two_dot(repo):
    """base moving ahead must not reverse-bleed its commits into our diff."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "mine.txt"), "w").write("mine\n")
    g = LiveGit(repo)
    g.stage_all()
    g.commit_all("mine")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "master-only.txt"), "w").write("m\n")
    gm = LiveGit(repo)
    gm.stage_all()
    gm.commit_all("master moved")
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    diff = LiveGit(repo).diff_vs_base(base="master")
    assert "mine.txt" in diff and "master-only.txt" not in diff


def test_rebase_onto_moved_base(repo):
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "mine.txt"), "w").write("mine\n")
    g = LiveGit(repo)
    g.stage_all(); g.commit_all("mine")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "other.txt"), "w").write("m\n")
    gm = LiveGit(repo)
    gm.stage_all(); gm.commit_all("master moved")
    master_tip = gm.head()
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    g.rebase_onto(base="master")
    assert g._merge_base("master") == master_tip     # feature now sits on master tip


def test_rebase_conflict_aborts_and_types_failure(repo):
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    open(os.path.join(repo, "a.txt"), "w").write("feature version\n")
    g = LiveGit(repo)
    g.stage_all(); g.commit_all("feature edit")
    subprocess.run(["git", "-C", repo, "checkout", "master"], check=True, capture_output=True)
    open(os.path.join(repo, "a.txt"), "w").write("master version\n")
    gm = LiveGit(repo)
    gm.stage_all(); gm.commit_all("conflicting master edit")
    subprocess.run(["git", "-C", repo, "checkout", "feature"], check=True, capture_output=True)
    with pytest.raises(HarnessError) as e:
        g.rebase_onto(base="master")
    assert e.value.kind == "rebase-conflict"
    assert not g.is_dirty()                          # abort restored the tree


def test_push_disabled_is_typed_failure(repo):
    g = LiveGit(repo)   # no push_remote configured
    with pytest.raises(HarnessError) as e:
        g.push()
    assert e.value.kind == "push-disabled"
