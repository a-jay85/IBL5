"""Phase 2 merges the PR's declared base (iblBase / gh), never master, for a stacked PR.

Real git: bare origin + clone. `parent` and `feature` stack on master; then both
origin/master and origin/parent advance by one commit."""
import os
import shutil
import subprocess
import sys
import tempfile
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import LiveGit
from harness.state import HarnessError, RunResult, TerminalState

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *a, check=True):
    return subprocess.run(["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *a],
                          check=check, capture_output=True, text=True, env=_ENV)


def _commit(d, rel, text, msg):
    p = Path(d) / rel
    p.parent.mkdir(parents=True, exist_ok=True)
    p.write_text(text)
    _git(d, "add", "-A")
    _git(d, "commit", "-qm", msg)


@pytest.fixture()
def stack(monkeypatch):
    root = Path(tempfile.mkdtemp(prefix="postplan-p2-stack-"))
    monkeypatch.setenv("BRANCH_BASE_OFFLINE", "1")
    origin, seed, wt = root / "origin.git", root / "seed", root / "wt"
    subprocess.run(["git", "init", "-q", "--bare", "-b", "master", str(origin)], check=True)
    subprocess.run(["git", "init", "-q", "-b", "master", str(seed)], check=True)
    _commit(seed, "a.txt", "base\n", "base")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    _git(seed, "checkout", "-qb", "parent")
    _commit(seed, "parent.txt", "parent work\n", "parent work")
    _git(seed, "push", "-q", "origin", "parent")
    subprocess.run(["git", "clone", "-q", str(origin), str(wt)], check=True)
    _git(wt, "config", "user.email", "t@t")
    _git(wt, "config", "user.name", "t")
    _git(wt, "checkout", "-qb", "feature", "origin/parent")
    _commit(wt, "feature.txt", "feature work\n", "feature work")
    # Both remotes advance after the stack was cut.
    _git(seed, "checkout", "-q", "master")
    _commit(seed, "master_only.txt", "master moved\n", "master moved")
    _git(seed, "push", "-q", "origin", "master")
    _git(seed, "checkout", "-q", "parent")
    _commit(seed, "parent2.txt", "parent moved\n", "parent moved")
    _git(seed, "push", "-q", "origin", "parent")
    _git(wt, "fetch", "-q", "origin")
    yield wt
    shutil.rmtree(root, ignore_errors=True)


def _res():
    return RunResult(terminal=TerminalState.FAILED)


def _is_ancestor(wt, a, b="HEAD"):
    return _git(wt, "merge-base", "--is-ancestor", a, b, check=False).returncode == 0


def test_phase2_stacked_merges_parent_not_master(stack):
    _git(stack, "config", "branch.feature.iblBase", "parent")
    lines = []
    runner._phase2_rebase(LiveGit(str(stack)), _res(), lines.append)
    assert _is_ancestor(stack, "origin/parent")
    assert not _is_ancestor(stack, "origin/master")
    master_tip = _git(stack, "rev-parse", "origin/master").stdout.strip()
    merges = _git(stack, "rev-list", "--merges", "--parents", "origin/parent..HEAD").stdout
    assert master_tip not in merges
    assert any("stacked base origin/parent" in ln for ln in lines)


def test_phase2_unstacked_still_merges_master(stack):
    lines = []
    runner._phase2_rebase(LiveGit(str(stack)), _res(), lines.append)
    assert _is_ancestor(stack, "origin/master")


def test_phase2_stacked_unresolvable_raises_before_merge(stack):
    _git(stack, "config", "branch.feature.iblBase", "gone")
    before = _git(stack, "rev-parse", "HEAD").stdout.strip()
    with pytest.raises(HarnessError) as ei:
        runner._phase2_rebase(LiveGit(str(stack)), _res(), lambda *_a, **_k: None)
    assert ei.value.kind == "base-resolve"
    assert _git(stack, "rev-parse", "HEAD").stdout.strip() == before


def test_refresh_and_reprove_follows_stacked_base():
    calls = {"fetch": [], "rebase": []}

    class _Git:
        def branch(self): return "feature"
        def head(self): return "a" * 40
        def merge_target(self): return "origin/parent"
        def capture_lostwork_pre(self, key): return True
        def fetch_base(self, base="origin/master"): calls["fetch"].append(base)
        def rebase_onto(self, base="origin/master"): calls["rebase"].append(base)
        def prove_lostwork(self, key): return (True, "TREE-EQUIVALENT")

    runner._refresh_and_reprove(_Git(), lambda *_a, **_k: None, "push", 1)
    assert calls["fetch"] == ["origin/parent"]
    assert calls["rebase"] == ["origin/parent"]


def test_base_resolve_failure_exits_fail_closed():
    """exit 3 keeps bin/post-plan-now from re-running the /post-plan skill, which would
    merge master into the stacked branch."""
    res = RunResult(terminal=TerminalState.FAILED)
    res.error_kind = "base-resolve"
    assert runner.exit_code_for(res) == 3
