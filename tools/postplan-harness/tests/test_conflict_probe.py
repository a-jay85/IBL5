import os
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
import runner
from harness.adapters.gitad import LiveGit
from harness.adapters.ghad import RecordingGh
from harness.adapters.llm import FixtureLlm, UsageLedger
from harness.state import HarnessError, TerminalState

_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
        "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}


def _git(d, *args, check=True):
    # core.hooksPath=/dev/null: a developer's global hooks must not fire on fixture pushes.
    return subprocess.run(
        ["git", "-c", "core.hooksPath=/dev/null", "-C", str(d), *args],
        check=check, capture_output=True, text=True, env=_ENV,
    )


def _rev(d, ref): return _git(d, "rev-parse", ref).stdout.strip()
def _write(d, rel, text): (Path(d) / rel).write_text(text)
def _commit_all(d, msg): _git(d, "add", "-A"); _git(d, "commit", "-qm", msg)


def _seed(tmp_path):
    """Bare origin + seed clone with one base commit on master. Returns (origin, seed)."""
    origin, seed = tmp_path / "origin.git", tmp_path / "seed"
    _git(tmp_path, "init", "-q", "--bare", "-b", "master", str(origin))
    _git(tmp_path, "init", "-q", "-b", "master", str(seed))
    _write(seed, "a.txt", "base\n")
    _commit_all(seed, "base")
    _git(seed, "remote", "add", "origin", str(origin))
    _git(seed, "push", "-q", "origin", "master")
    return origin, seed


def _clone(tmp_path, origin):
    wt = tmp_path / "wt"
    _git(tmp_path, "clone", "-q", str(origin), str(wt))
    _git(wt, "config", "user.email", "t@t")
    _git(wt, "config", "user.name", "t")
    return wt


def _plain_scenario(tmp_path, *, conflict, dirty):
    """Non-stacked branch. Master advances AFTER the clone, so wt's origin/master is stale
    until fetch_base(): a probe run before the fetch would miss the conflict."""
    origin, seed = _seed(tmp_path)
    wt = _clone(tmp_path, origin)
    _git(wt, "checkout", "-qb", "feature")
    _write(seed, "a.txt" if conflict else "m.txt", "master\n")
    _commit_all(seed, "master moves")
    _git(seed, "push", "-q", "origin", "master")
    _write(wt, "a.txt" if conflict else "b.txt", "branch\n")
    if not dirty:
        _commit_all(wt, "feat: branch work")
    return wt


def _stacked_scenario(tmp_path, *, ibl_base_valid=True):
    """Stacked branch whose parent was squash-merged, then master edited a parent file.
    Returns (wt, parent_tip)."""
    origin, seed = _seed(tmp_path)
    _git(seed, "checkout", "-qb", "parent")
    _write(seed, "parent.txt", "step1\n")
    _commit_all(seed, "feat: parent step1")
    _write(seed, "parent.txt", "step2\n")
    _commit_all(seed, "feat: parent step2")
    parent_tip = _rev(seed, "HEAD")
    _git(seed, "push", "-q", "origin", "parent")
    _git(seed, "checkout", "-q", "master")
    _git(seed, "merge", "--squash", "parent")
    _git(seed, "commit", "-qm", "squash: parent (#1)")
    _write(seed, "parent.txt", "step3\n")
    _commit_all(seed, "fix: master edits parent.txt post-squash")
    _git(seed, "push", "-q", "origin", "master")
    wt = _clone(tmp_path, origin)
    _git(wt, "checkout", "-qb", "feature", parent_tip)
    _write(wt, "feature.txt", "feature\n")
    _commit_all(wt, "feat: feature work")
    _write(wt, "feature2.txt", "uncommitted\n")
    _git(wt, "config", "branch.feature.iblBase",
         parent_tip if ibl_base_valid else "0" * 40)
    return wt, parent_tip


# ---------------------------------------------------------------------------
# Adapter-level tests (direct LiveGit call)
# ---------------------------------------------------------------------------

@pytest.mark.parametrize("dirty", [False, True])
def test_probe_predicts_conflict(tmp_path, dirty):
    wt = _plain_scenario(tmp_path, conflict=True, dirty=dirty)
    g = LiveGit(str(wt))
    g.fetch_base()
    g.stage_all()
    head0 = _rev(wt, "HEAD")
    status0 = _git(wt, "status", "--porcelain").stdout
    assert g.predict_rebase_conflict() == ("a.txt",)
    assert _rev(wt, "HEAD") == head0
    assert _git(wt, "status", "--porcelain").stdout == status0
    assert not (wt / ".git" / "rebase-merge").exists()
    assert not (wt / ".git" / "rebase-apply").exists()
    assert _git(wt, "stash", "list").stdout == ""


def test_probe_stacked_squash_parent_reports_clean(tmp_path):
    wt, parent_tip = _stacked_scenario(tmp_path)
    g = LiveGit(str(wt))
    g.stage_all()
    # Characterization guard: the default merge-tree conflicts; with --merge-base it's clean.
    assert _git(wt, "merge-tree", "--write-tree", "--name-only", "--no-messages",
                "origin/master", "HEAD", check=False).returncode == 1
    assert _git(wt, "merge-tree", "--write-tree", "--name-only", "--no-messages",
                "--merge-base", parent_tip,
                "origin/master", "HEAD", check=False).returncode == 0
    assert g.branch_base() == parent_tip
    assert g.predict_rebase_conflict() == ()


def test_probe_stale_ibl_base_falls_back_to_default_merge_base(tmp_path):
    wt, _ = _stacked_scenario(tmp_path, ibl_base_valid=False)
    g = LiveGit(str(wt))
    g.stage_all()
    # branch_base() degrades to None for an unresolvable SHA.
    assert g.branch_base() is None
    # Without --merge-base the plain merge-base is used and parent.txt conflicts.
    assert g.predict_rebase_conflict() == ("parent.txt",)


@pytest.mark.parametrize("dirty", [False, True])
def test_probe_clean_branch_reports_clean(tmp_path, dirty):
    wt = _plain_scenario(tmp_path, conflict=False, dirty=dirty)
    g = LiveGit(str(wt))
    g.fetch_base()
    g.stage_all()
    assert g.predict_rebase_conflict() == ()


def test_probe_bad_base_raises_git_kind_not_conflict(tmp_path):
    wt = _plain_scenario(tmp_path, conflict=False, dirty=False)
    g = LiveGit(str(wt))
    g.fetch_base()
    with pytest.raises(HarnessError) as ei:
        g.predict_rebase_conflict("origin/does-not-exist")
    assert ei.value.kind == "git"


# ---------------------------------------------------------------------------
# Runner-level tests (runner.run with live=True, only LLM seams stubbed)
# ---------------------------------------------------------------------------

class _Stop(Exception):
    pass


_COPY = {"type": "chore", "title": "chore: probe", "commit_subject": "chore: probe",
         "summary_md": "## Summary\n- x\n"}


def _run_live(tmp_path, wt, monkeypatch, body_check, *, calls=None):
    if calls is None:
        calls = {"pr_copy": 0}

    def fake_pr_copy(llm, git, gh, fixture, slug, cls, plan, log):
        calls["pr_copy"] += 1
        return dict(_COPY), False

    monkeypatch.setattr(runner, "_pr_copy", fake_pr_copy)
    monkeypatch.setattr(runner, "_body_check", body_check)
    monkeypatch.setattr(runner, "LiveGh",
                        lambda out_dir, worktree, slug: RecordingGh(out_dir))
    (tmp_path / "plans").mkdir(exist_ok=True)
    res = runner.run(
        None, str(tmp_path / "out"),
        FixtureLlm(UsageLedger(), {}),
        mode="live", live=True, worktree=str(wt),
        plans_dir=str(tmp_path / "plans"),
        state_dir=str(tmp_path / "state"),
    )
    return res, calls


@pytest.mark.parametrize("dirty", [False, True])
def test_runner_conflict_fails_closed_before_body_check(tmp_path, monkeypatch, dirty):
    wt = _plain_scenario(tmp_path, conflict=True, dirty=dirty)
    head0 = _rev(wt, "HEAD")

    def spy(*a, **k):
        raise AssertionError("_body_check must not run on a predicted conflict")

    res, calls = _run_live(tmp_path, wt, monkeypatch, spy)
    assert res.terminal == TerminalState.FAILED
    assert res.error_kind == "rebase-conflict"
    assert "a.txt" in res.error
    assert runner.exit_code_for(res) == 3
    assert calls["pr_copy"] == 0
    assert any("conflicted paths (probe) = a.txt" in l for l in res.audit)
    assert _rev(wt, "HEAD") == head0


def test_runner_clean_branch_reaches_body_check(tmp_path, monkeypatch):
    wt = _plain_scenario(tmp_path, conflict=False, dirty=True)
    hits = []
    calls = {"pr_copy": 0}

    def spy(*a, **k):
        hits.append(1)
        raise _Stop()

    with pytest.raises(_Stop):
        _run_live(tmp_path, wt, monkeypatch, spy, calls=calls)

    assert hits == [1]
    assert calls["pr_copy"] == 1
    audit_text = (tmp_path / "out" / "audit.log").read_text()
    assert "phase2 conflict-probe: predicted" not in audit_text


def test_runner_stacked_squash_parent_reaches_body_check(tmp_path, monkeypatch):
    wt, _ = _stacked_scenario(tmp_path)
    hits = []

    def spy(*a, **k):
        hits.append(1)
        raise _Stop()

    with pytest.raises(_Stop):
        _run_live(tmp_path, wt, monkeypatch, spy)

    assert hits == [1]
