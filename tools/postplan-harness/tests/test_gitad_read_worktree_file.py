"""LiveGit.read_worktree_file: the working-tree reader behind conformance.check's
MISSING-METHOD fallback. Each test is named for the mutation it kills.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit, ReplayGit


def test_read_worktree_file_returns_text_under_worktree(tmp_path):
    (tmp_path / "a").mkdir()
    (tmp_path / "a" / "b.php").write_text("x")
    assert LiveGit(str(tmp_path)).read_worktree_file("a/b.php") == "x"


def test_read_worktree_file_refuses_parent_traversal(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    (tmp_path / "b.php").write_text("secret")
    assert LiveGit(str(wt)).read_worktree_file("../b.php") is None


def test_read_worktree_file_refuses_absolute_path(tmp_path):
    (tmp_path / "a").mkdir()
    (tmp_path / "a" / "b.php").write_text("x")
    assert LiveGit(str(tmp_path)).read_worktree_file(str(tmp_path / "a/b.php")) is None


def test_read_worktree_file_refuses_symlink_escape(tmp_path):
    wt = tmp_path / "wt"
    wt.mkdir()
    (tmp_path / "outside.txt").write_text("secret")
    os.symlink(tmp_path / "outside.txt", wt / "link.txt")
    assert LiveGit(str(wt)).read_worktree_file("link.txt") is None


def test_read_worktree_file_missing_or_binary_is_none(tmp_path):
    (tmp_path / "bin.dat").write_bytes(b"\xff\xfe\x00")
    git = LiveGit(str(tmp_path))
    assert git.read_worktree_file("nope.php") is None
    assert git.read_worktree_file("bin.dat") is None


def test_replay_git_read_worktree_file_is_none():
    assert ReplayGit({"slug": "x", "diff": ""}).read_worktree_file("anything") is None
