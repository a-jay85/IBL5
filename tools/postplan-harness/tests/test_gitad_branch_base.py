import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit, StackedRebaseResult
from harness.state import HarnessError


@pytest.fixture()
def repo():
    d = tempfile.mkdtemp(prefix="postplan-git-test-")
    def sh(*a):
        subprocess.run(["git", "-C", d, *a], check=True, capture_output=True,
                       env={**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                            "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"})
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    subprocess.run(["git", "-C", d, "config", "user.email", "t@t"], check=True, capture_output=True)
    subprocess.run(["git", "-C", d, "config", "user.name", "t"], check=True, capture_output=True)
    open(os.path.join(d, "a.txt"), "w").write("base\n")
    sh("add", "-A"); sh("commit", "-m", "base")
    return d


def test_branch_base_resolves_config_to_full_sha(repo):
    """Config stores an abbreviated SHA; branch_base() must return the full 40-char SHA."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    # Record HEAD as the iblBase (abbreviated, 7 chars)
    full_sha = subprocess.run(
        ["git", "-C", repo, "rev-parse", "HEAD"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    abbrev = full_sha[:7]
    subprocess.run(["git", "-C", repo, "config", "branch.feature.iblBase", abbrev],
                   check=True, capture_output=True)
    result = LiveGit(repo).branch_base()
    assert result is not None
    assert len(result) == 40
    assert result == full_sha


def test_branch_base_none_when_config_unset(repo):
    """No iblBase key → None, no exception."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    result = LiveGit(repo).branch_base()
    assert result is None


def test_branch_base_none_when_recorded_sha_is_unresolvable(repo):
    """A recorded SHA that is garbage → None, no raise."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    garbage = "0123456789abcdef0123456789abcdef01234567"
    subprocess.run(["git", "-C", repo, "config", "branch.feature.iblBase", garbage],
                   check=True, capture_output=True)
    result = LiveGit(repo).branch_base()
    assert result is None


def test_branch_base_accepts_an_explicit_branch_name(repo):
    """With HEAD on master, branch_base("feature") resolves feature's key."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    full_sha = subprocess.run(
        ["git", "-C", repo, "rev-parse", "HEAD"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    subprocess.run(["git", "-C", repo, "config", "branch.feature.iblBase", full_sha],
                   check=True, capture_output=True)
    # Switch back to master — no iblBase key set there
    subprocess.run(["git", "-C", repo, "checkout", "master"],
                   check=True, capture_output=True)
    g = LiveGit(repo)
    assert g.branch_base("feature") == full_sha
    assert g.branch_base() is None  # master has no key


def test_branch_base_none_on_detached_head(repo):
    """Detached HEAD → None (rev-parse --abbrev-ref returns "HEAD")."""
    subprocess.run(["git", "-C", repo, "checkout", "--detach"],
                   check=True, capture_output=True)
    result = LiveGit(repo).branch_base()
    assert result is None


def test_autoresolve_declines_cleanly_without_ibl_base(repo):
    """No iblBase → declined, reason names iblBase, no manifest written."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    key = "feature"
    manifest = f"/tmp/postplan-conflict-files-{key}.txt"
    # Ensure pre-existing file from a prior run doesn't interfere
    if os.path.exists(manifest):
        os.unlink(manifest)
    g = LiveGit(repo)
    pre_head = g.head()
    result = g.autoresolve_stacked_rebase()
    assert result.resolved is False
    assert "iblBase" in result.reason
    assert result.post_resolution_sha == ""
    assert not os.path.exists(manifest)
    assert g.head() == pre_head


def test_autoresolve_declines_on_a_dirty_tree(repo):
    """Dirty worktree at entry → declined, reason names dirty, history untouched."""
    subprocess.run(["git", "-C", repo, "checkout", "-b", "feature"],
                   check=True, capture_output=True)
    full_sha = subprocess.run(
        ["git", "-C", repo, "rev-parse", "HEAD"],
        check=True, capture_output=True, text=True,
    ).stdout.strip()
    subprocess.run(["git", "-C", repo, "config", "branch.feature.iblBase", full_sha],
                   check=True, capture_output=True)
    # Dirty the tree
    open(os.path.join(repo, "dirty.txt"), "w").write("dirty\n")
    g = LiveGit(repo)
    pre_head = g.head()
    result = g.autoresolve_stacked_rebase()
    assert result.resolved is False
    assert "dirty" in result.reason
    assert g.head() == pre_head
