"""history_shape: a stacked branch carrying a master merge its parent never took."""
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import history_shape

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *a):
    return subprocess.run(["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *a],
                          check=True, capture_output=True, text=True, env=_ENV).stdout.strip()


def _commit(d, rel, text, msg):
    (Path(d) / rel).write_text(text)
    _git(d, "add", "-A")
    _git(d, "commit", "-qm", msg)


@pytest.fixture()
def stack():
    """work clone on `feature` (off `parent`); origin/master and origin/parent each
    advance once after the stack was cut. `seed` pushes later advances."""
    root = Path(tempfile.mkdtemp(prefix="postplan-hs-"))
    origin, seed, wt = root / "origin.git", root / "seed", root / "wt"
    subprocess.run(["git", "init", "-q", "--bare", "-b", "master", str(origin)], check=True)
    subprocess.run(["git", "init", "-q", "-b", "master", str(seed)], check=True)
    _commit(seed, "a.txt", "base\n", "base")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    _git(seed, "checkout", "-qb", "parent")
    _commit(seed, "parent.txt", "p\n", "parent work")
    _git(seed, "push", "-q", "origin", "parent")
    subprocess.run(["git", "clone", "-q", str(origin), str(wt)], check=True)
    _git(wt, "checkout", "-qb", "feature", "origin/parent")
    _commit(wt, "feature.txt", "f\n", "feature work")
    _git(seed, "checkout", "-q", "master")
    _commit(seed, "m.txt", "m\n", "master moved")
    _git(seed, "push", "-q", "origin", "master")
    _git(wt, "fetch", "-q", "origin")
    yield wt, seed
    shutil.rmtree(root, ignore_errors=True)


def test_stray_master_merge_detected(stack):
    wt, _ = stack
    _git(wt, "merge", "--no-edit", "origin/master")
    merge = _git(wt, "rev-parse", "HEAD")
    assert history_shape.stray_master_merges(str(wt), "origin/parent") == [merge]


def test_parent_merge_not_flagged(stack):
    wt, seed = stack
    _git(seed, "checkout", "-q", "parent")
    _commit(seed, "parent2.txt", "p2\n", "parent moved")
    _git(seed, "push", "-q", "origin", "parent")
    _git(wt, "fetch", "-q", "origin")
    _git(wt, "merge", "--no-edit", "origin/parent")
    # Parent work not yet pushed: on neither the base nor master, so only the
    # master-ancestry test keeps it from being flagged.
    _git(wt, "checkout", "-qb", "parent-local", "origin/parent")
    _commit(wt, "parent3.txt", "p3\n", "unpushed parent work")
    _git(wt, "checkout", "-q", "feature")
    _git(wt, "merge", "--no-ff", "--no-edit", "parent-local")
    assert history_shape.stray_master_merges(str(wt), "origin/parent") == []


def test_unstacked_base_returns_empty(stack, monkeypatch):
    wt, _ = stack
    _git(wt, "merge", "--no-edit", "origin/master")
    calls = []
    real_git = history_shape._git

    def spy(worktree, *args):
        calls.append(args)
        return real_git(worktree, *args)

    monkeypatch.setattr(history_shape, "_git", spy)
    assert history_shape.stray_master_merges(str(wt), "origin/master") == []
    assert calls == [], "an unstacked base must short-circuit before any git call"


def test_master_merge_shared_with_parent_not_flagged(stack):
    wt, seed = stack
    _git(seed, "checkout", "-q", "parent")
    _git(seed, "merge", "--no-edit", "master")
    _git(seed, "push", "-q", "origin", "parent")
    _git(wt, "fetch", "-q", "origin")
    # Feature also merges the same master commit directly: its merged parent is on
    # master, and only its presence on the base keeps it from being flagged.
    _git(wt, "merge", "--no-ff", "--no-edit", "origin/master")
    _git(wt, "merge", "--no-edit", "origin/parent")
    assert history_shape.stray_master_merges(str(wt), "origin/parent") == []


def test_empty_range_returns_empty(stack):
    wt, _ = stack
    _git(wt, "reset", "-q", "--hard", "origin/parent")
    assert history_shape.stray_master_merges(str(wt), "origin/parent") == []


def test_unresolvable_base_returns_empty(stack):
    wt, _ = stack
    assert history_shape.stray_master_merges(str(wt), "origin/nonexistent") == []


def test_next_action_names_base_and_retarget():
    sha = "0123456789abcdef0123456789abcdef01234567"
    text = history_shape.next_action("feature", "origin/parent", [sha])
    assert "origin/parent" in text
    assert sha[:12] in text
    assert "retarget the PR to master" in text
