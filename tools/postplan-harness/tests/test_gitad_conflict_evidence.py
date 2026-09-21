"""The conflicted path set survives every rebase exit, with or without an LLM.

`git rebase --abort` clears the unmerged index entries, so a fail-closed exit 3 used to
name no files. `LiveGit._snapshot_conflicted_paths()` records them before any abort; these
tests pin the capture on all four paths plus the reset on a clean rebase.

The fixtures are self-contained: each builds a throwaway repo in a tempdir and wires
`refs/remotes/origin/master` with `update-ref`, the same idiom
`tests/test_gitad_stacked_rebase.py` uses, so no bare remote is needed.
(The plan's ``## Critical Files`` named ``tests/test_gitad_onto_guard.py`` as the fixture
source; that file does not exist, so ``test_gitad_stacked_rebase.py`` — same ``update-ref``
idiom — was used instead. The substitution is sound.)
"""

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
    _REPO_ROOT, ".claude", "skills", "pr-ready", "scripts", "lostwork.sh"
)
_COLLAPSE = os.path.join(
    _REPO_ROOT, ".claude", "skills", "pr-ready", "scripts", "collapse-guard.sh"
)


def sh(d, *args, check=True):
    env = {
        **os.environ,
        "GIT_AUTHOR_NAME": "t",
        "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t",
        "GIT_COMMITTER_EMAIL": "t@t",
    }
    return subprocess.run(
        ["git", "-C", d, *args], check=check, capture_output=True, text=True, env=env
    )


def _sha(d, ref="HEAD"):
    return subprocess.run(
        ["git", "-C", d, "rev-parse", ref],
        check=True, capture_output=True, text=True,
    ).stdout.strip()


def _commit(d, path, body, message):
    open(os.path.join(d, path), "w").write(body)
    sh(d, "add", "-A")
    sh(d, "commit", "-m", message)
    return _sha(d)


@pytest.fixture()
def repo_with_origin():
    """A repo on `master` with one base commit and origin/master pointing at it."""
    d = tempfile.mkdtemp(prefix="postplan-evidence-test-")
    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    sh(d, "config", "user.email", "t@t")
    sh(d, "config", "user.name", "t")
    _commit(d, "a.txt", "base\n", "base")
    sh(d, "update-ref", "refs/remotes/origin/master", _sha(d))
    yield d
    shutil.rmtree(d, ignore_errors=True)


def _publish_as_origin_master(d, base_sha, path, body, message):
    """Build a throwaway `trunk` at base_sha that edits `path`, then make it origin/master."""
    branch = sh(d, "rev-parse", "--abbrev-ref", "HEAD").stdout.strip()
    sh(d, "checkout", "-b", "trunk", base_sha)
    _commit(d, path, body, message)
    trunk_sha = _sha(d)
    sh(d, "update-ref", "refs/remotes/origin/master", trunk_sha)
    sh(d, "checkout", branch)
    sh(d, "branch", "-D", "trunk")
    return trunk_sha


def _no_rebase_in_progress(d):
    return not os.path.exists(os.path.join(d, ".git", "rebase-merge")) and not os.path.exists(
        os.path.join(d, ".git", "rebase-apply")
    )


def test_llm_less_abort_names_conflicted_paths(repo_with_origin):
    """None-path: feat edits x.txt; origin/master edits the same line.

    rebase_onto() must abort (HEAD restored, no rebase dir) AND carry the path.
    Mutation caught: move _snapshot_conflicted_paths() below the abort call ->
    detail ends in 'conflicted: ?' and last_conflict_files == ().
    """
    d = repo_with_origin
    _commit(d, "x.txt", "shared\n", "X0")
    sh(d, "update-ref", "refs/remotes/origin/master", _sha(d))
    x0 = _sha(d)
    sh(d, "checkout", "-b", "feat")
    f1 = _commit(d, "x.txt", "feat side\n", "F1")
    _publish_as_origin_master(d, x0, "x.txt", "trunk side\n", "T1")

    g = LiveGit(d)                       # llm=None
    with pytest.raises(HarnessError) as ei:
        g.rebase_onto("origin/master")
    assert ei.value.kind == "rebase-conflict"
    assert "conflicted: x.txt" in ei.value.detail
    assert g.last_conflict_files == ("x.txt",)
    assert _sha(d) == f1
    assert _no_rebase_in_progress(d)


def test_llm_path_snapshot_survives_unresolvable_inventory(repo_with_origin):
    """LLM present but never called: feat DELETES x.txt while origin/master modifies it.

    inventory_conflicts() classifies delete/modify unresolvable and returns files=().
    The snapshot must still name the path. Mutation caught: reading inventory.files
    instead of the diff-filter snapshot -> last_conflict_files == ().
    """
    d = repo_with_origin
    _commit(d, "x.txt", "shared\n", "X0")
    x0 = _sha(d)
    sh(d, "update-ref", "refs/remotes/origin/master", x0)
    sh(d, "checkout", "-b", "feat")
    os.remove(os.path.join(d, "x.txt"))
    sh(d, "add", "-A")
    sh(d, "commit", "-m", "F1 deletes x")
    _publish_as_origin_master(d, x0, "x.txt", "shared\nmore\n", "T1")

    g = LiveGit(d, llm=object())          # sentinel: any .call() would raise AttributeError
    with pytest.raises(HarnessError) as ei:
        g.rebase_onto("origin/master")
    # Discriminates the branch taken: abort_and_restore reasons are left suffix-free by
    # design, so an unsuffixed detail proves this went through the LLM path, not the
    # LLM-less one (which raises the same kind with a populated snapshot).
    assert " | conflicted: " not in ei.value.detail
    assert g.last_conflict_files == ("x.txt",)
    assert _no_rebase_in_progress(d)


def test_clean_rebase_resets_stale_snapshot(repo_with_origin):
    """Negative path: a rebase that succeeds leaves last_conflict_files empty.

    Mutation caught: delete the reset at rebase_onto() entry -> the pre-seeded tuple
    survives into the audit log as a stale path set.
    """
    d = repo_with_origin
    sh(d, "checkout", "-b", "feat")
    _commit(d, "y.txt", "feat\n", "F1")
    g = LiveGit(d)
    g.last_conflict_files = ("stale.txt",)
    g.rebase_onto("origin/master")          # no-op rebase, succeeds
    assert g.last_conflict_files == ()


def _make_conflicting_squash_repo():
    """Squash-trap shape whose `--onto` replay hits a REAL conflict on x.txt.

    Same construction as tests/test_gitad_stacked_rebase.py::_make_squash_repo (the real
    lostwork.sh / collapse-guard.sh are committed before the parent branch so Steps 5 and 6
    pass unchanged), with x.txt added so the replayed feature commit conflicts with master.
    """
    suffix = uuid.uuid4().hex[:8]
    feature_branch = f"feature-{suffix}"
    key = feature_branch.replace("/", "-")
    d = tempfile.mkdtemp(prefix="postplan-evidence-sq-")

    subprocess.run(["git", "init", "-b", "master", d], check=True, capture_output=True)
    sh(d, "config", "user.email", "t@t")
    sh(d, "config", "user.name", "t")
    _commit(d, "a.txt", "base\n", "base")

    scripts_dir = os.path.join(d, ".claude", "skills", "pr-ready", "scripts")
    os.makedirs(scripts_dir, exist_ok=True)
    shutil.copy(_LOSTWORK, os.path.join(scripts_dir, "lostwork.sh"))
    shutil.copy(_COLLAPSE, os.path.join(scripts_dir, "collapse-guard.sh"))
    sh(d, "add", "-A")
    sh(d, "commit", "-m", "chore: proof scripts")

    # Parent branch: two commits so the squash trap fires, plus x.txt at "p".
    sh(d, "checkout", "-b", "parent")
    _commit(d, "parent.txt", "step1\n", "feat: parent step1")
    _commit(d, "parent.txt", "step2\n", "feat: parent step2")
    parent_tip = _commit(d, "x.txt", "p\n", "feat: parent adds x")

    # Squash-merge parent into master, then move x.txt so the replay conflicts.
    sh(d, "checkout", "master")
    sh(d, "merge", "--squash", "parent")
    sh(d, "commit", "-m", "squash: parent")
    master_sha = _commit(d, "x.txt", "trunk\n", "chore: master moves x")

    # Feature branch from parent_tip, editing the same line.
    sh(d, "checkout", "-b", feature_branch, parent_tip)
    _commit(d, "x.txt", "feature\n", "feat: feature edits x")

    sh(d, "update-ref", "refs/remotes/origin/master", master_sha)
    sh(d, "config", f"branch.{feature_branch}.iblBase", parent_tip)
    sh(d, "checkout", feature_branch)
    return d, key


def _cleanup_tmp(key):
    for p in (
        f"/tmp/postplan-conflict-files-{key}.txt",
        f"/tmp/postplan-conflict-resolution-{key}.md",
        f"/tmp/postplan-lostwork-{key}.sh",
        f"/tmp/postplan-collapse-guard-{key}.sh",
        f"/tmp/pr-ready-diff-pre-{key}.patch",
        f"/tmp/pr-ready-diff-post-{key}.patch",
        f"/tmp/pr-ready-numstat-pre-{key}.txt",
        f"/tmp/pr-ready-numstat-post-{key}.txt",
        f"/tmp/pr-ready-collapse-guard-{key}-{key}.meta",
    ):
        if os.path.exists(p):
            os.unlink(p)


def test_onto_decline_without_llm_names_conflicted_paths():
    """The LLM-less `--onto` decline reason ends with the conflicted path set.

    Mutation caught: dropping the snapshot before the abort in
    autoresolve_stacked_rebase -> reason ends in 'conflicted: ?'.
    """
    d, key = _make_conflicting_squash_repo()
    try:
        g = LiveGit(d)                      # llm=None
        res = g.autoresolve_stacked_rebase()
        assert res.resolved is False
        assert res.reason.startswith("--onto rebase still conflicts:")
        assert res.reason.endswith("| conflicted: x.txt")
        assert g.last_conflict_files == ("x.txt",)
        assert _no_rebase_in_progress(d)
    finally:
        _cleanup_tmp(key)
        shutil.rmtree(d, ignore_errors=True)
