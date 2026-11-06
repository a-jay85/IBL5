"""Real-git tests for dropping refused gate paths out of the fidelity fixer's LOCAL commit.

A stub cannot prove the index and the working tree are clean, so these drive `LiveGit`
against a throwaway repo.
"""
from __future__ import annotations

import subprocess
import types
from pathlib import Path

import pytest

from harness import fidelity
from harness.adapters.gitad import LiveGit, ReplayGit
from harness.adapters.llm import FixtureLlm
from harness.state import HarnessError, UsageLedger


def _git(repo: Path, *args: str) -> str:
    return subprocess.run(["git", "-C", str(repo), *args], check=True,
                          capture_output=True, text=True).stdout


def _write(repo: Path, rel: str, text: str) -> None:
    path = repo / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)


def _commit_all(repo: Path, msg: str = "fixer") -> str:
    _git(repo, "add", "-A")
    _git(repo, "commit", "-q", "-m", msg)
    return _git(repo, "rev-parse", "HEAD").strip()


def _head(repo: Path) -> str:
    return _git(repo, "rev-parse", "HEAD").strip()


def _tracked(repo: Path) -> list[str]:
    return _git(repo, "ls-files").splitlines()


def _porcelain(repo: Path) -> str:
    return _git(repo, "status", "--porcelain").strip()


@pytest.fixture
def repo(tmp_path: Path) -> Path:
    # A subdirectory, so the verdict and packet files stay out of the repo's status.
    wt = tmp_path / "wt"
    wt.mkdir()
    _git(wt, "init", "-q")
    _git(wt, "config", "user.name", "t")
    _git(wt, "config", "user.email", "t@example.invalid")
    _git(wt, "config", "commit.gpgsign", "false")
    _git(wt, "config", "diff.renames", "true")
    _write(wt, "ibl5/a.php", "<?php // base\n")
    _write(wt, ".claude/rules/x.md", "v1\n")
    _write(wt, ".claude/rules/old.md", "old rule body\n")
    _commit_all(wt, "root")
    return wt


def test_strip_removes_new_protected_file(repo: Path) -> None:
    base = _head(repo)
    _write(repo, "ibl5/a.php", "<?php // fixed\n")
    _write(repo, ".claude/rules/new.md", "sneaked in\n")
    sha = _commit_all(repo)

    assert LiveGit(str(repo)).strip_head_paths(sha, [".claude/rules/new.md"]) == ""

    assert _head(repo) == base
    assert not (repo / ".claude/rules/new.md").exists()
    assert ".claude/rules/new.md" not in _tracked(repo)
    assert _git(repo, "diff", "--cached", "--name-only").split() == ["ibl5/a.php"]
    assert (repo / "ibl5/a.php").read_text() == "<?php // fixed\n"


def test_strip_restores_deleted_protected_file(repo: Path) -> None:
    base = _head(repo)
    (repo / ".claude/rules/x.md").unlink()
    sha = _commit_all(repo)

    assert LiveGit(str(repo)).strip_head_paths(sha, [".claude/rules/x.md"]) == ""

    assert _head(repo) == base
    assert (repo / ".claude/rules/x.md").read_text() == "v1\n"
    assert _porcelain(repo) == ""


def test_strip_restores_both_sides_of_renamed_protected_file(repo: Path) -> None:
    base = _head(repo)
    _git(repo, "mv", ".claude/rules/old.md", ".claude/rules/moved.md")
    sha = _commit_all(repo)

    err = LiveGit(str(repo)).strip_head_paths(sha, [".claude/rules/old.md", ".claude/rules/moved.md"])

    assert err == ""
    assert _head(repo) == base
    assert (repo / ".claude/rules/old.md").read_text() == "old rule body\n"
    assert not (repo / ".claude/rules/moved.md").exists()
    assert ".claude/rules/moved.md" not in _tracked(repo)
    assert _porcelain(repo) == ""


def test_strip_removes_staged_and_unstaged_protected_files(repo: Path) -> None:
    _write(repo, ".claude/rules/s.md", "staged\n")
    _git(repo, "add", ".claude/rules/s.md")
    _write(repo, ".claude/rules/u.md", "untracked\n")
    sha = _commit_all(repo)

    err = LiveGit(str(repo)).strip_head_paths(sha, [".claude/rules/s.md", ".claude/rules/u.md"])

    assert err == ""
    for rel in (".claude/rules/s.md", ".claude/rules/u.md"):
        assert not (repo / rel).exists()
        assert rel not in _tracked(repo)
    assert _porcelain(repo) == ""


def test_strip_keeps_pr_own_earlier_protected_edit(repo: Path) -> None:
    _write(repo, ".claude/rules/x.md", "v2\n")
    pr_commit = _commit_all(repo, "the PR's own edit")
    _write(repo, ".claude/rules/x.md", "v3\n")
    sha = _commit_all(repo)

    assert LiveGit(str(repo)).strip_head_paths(sha, [".claude/rules/x.md"]) == ""

    assert _head(repo) == pr_commit
    assert (repo / ".claude/rules/x.md").read_text() == "v2\n"
    assert _porcelain(repo) == ""


def test_strip_refuses_when_head_is_not_sha(repo: Path) -> None:
    _write(repo, "ibl5/a.php", "<?php // c1\n")
    c1 = _commit_all(repo, "c1")
    _write(repo, "ibl5/a.php", "<?php // c2\n")
    c2 = _commit_all(repo, "c2")

    err = LiveGit(str(repo)).strip_head_paths(c1, ["ibl5/a.php"])

    assert err != ""
    assert _head(repo) == c2
    assert (repo / "ibl5/a.php").read_text() == "<?php // c2\n"
    assert _porcelain(repo) == ""


def test_strip_refuses_an_empty_path_list(repo: Path) -> None:
    _write(repo, "ibl5/a.php", "<?php // fixed\n")
    sha = _commit_all(repo)

    assert LiveGit(str(repo)).strip_head_paths(sha, []) != ""

    assert _head(repo) == sha
    assert (repo / "ibl5/a.php").read_text() == "<?php // fixed\n"


# --- remediate: the carve-out refused, so the refused paths get stripped ---------

FINDING = "Fix `.claude/rules/x.md` wording"


@pytest.fixture
def refused(monkeypatch):
    """The carve-out says no, which is the precondition every remediate test here needs."""
    monkeypatch.setattr(fidelity, "_find_procedure", lambda *a, **k: "PROCEDURE")
    monkeypatch.setattr(fidelity.fidelity_filefix, "snapshot",
                        lambda *a, **k: types.SimpleNamespace(active=False, eligible=(),
                                                              reason="test"))
    monkeypatch.setattr(fidelity.fidelity_filefix, "permit", lambda *a, **k: False)


def _verdict(tmp_path: Path) -> str:
    p = tmp_path / "verdict.md"
    p.write_text(f"6d checks\n\nNOT READY\n\n## FINDINGS\n- [BLOCKING] {FINDING}\n"
                 "  - more detail\n\n## DIGEST\nstuff\n")
    return str(p)


def _packet(tmp_path: Path) -> str:
    d = tmp_path / "packet"
    d.mkdir()
    (d / "diff.patch").write_text("diff\n")
    return str(d)


class _EditingLlm:
    """Stands in for the fixer: runs the scripted edit in the worktree it was given."""

    def __init__(self, edit):
        self.edit = edit

    def call_tooled(self, *a, cwd=None, **k):
        self.edit(Path(cwd))
        return "done"


def _remediate(tmp_path: Path, repo: Path, edit):
    """Run remediate against the real repo. Returns (sha, outcome, pushes)."""
    git = LiveGit(str(repo))
    pushes: list[int] = []
    outcome: dict = {}

    def push() -> str:
        pushes.append(1)
        return ""

    sha = fidelity.remediate(_EditingLlm(edit), git, str(tmp_path), str(repo), _packet(tmp_path),
                             _verdict(tmp_path), "deadbeef", commit=git.commit_all,
                             push=push, outcome=outcome)
    return sha, outcome, pushes


def test_remediate_keeps_allowed_edit_and_restores_protected_file(tmp_path, repo, refused):
    pre_round = _head(repo)

    def edit(cwd: Path) -> None:
        _write(cwd, "ibl5/a.php", "<?php // fixed\n")
        _write(cwd, ".claude/rules/x.md", "fixer rewrote the rule\n")

    sha, outcome, pushes = _remediate(tmp_path, repo, edit)

    assert sha
    assert _git(repo, "rev-parse", f"{sha}^").strip() == pre_round
    assert (repo / ".claude/rules/x.md").read_text() == "v1\n"
    assert _git(repo, "show", "HEAD:ibl5/a.php") == "<?php // fixed\n"
    assert _git(repo, "show", "HEAD:.claude/rules/x.md") == "v1\n"
    assert _porcelain(repo) == ""
    assert pushes == [1]
    assert outcome["reason"] == "committed"
    assert outcome["gate_reverted"] == [".claude/rules/x.md"]


def test_remediate_detects_rename_out_of_protected_dir(tmp_path, repo, refused):
    def edit(cwd: Path) -> None:
        (cwd / "docs").mkdir()
        _git(cwd, "mv", ".claude/rules/old.md", "docs/old.md")

    sha, outcome, pushes = _remediate(tmp_path, repo, edit)

    assert sha
    assert (repo / ".claude/rules/old.md").read_text() == "old rule body\n"
    assert _git(repo, "show", "HEAD:docs/old.md") == "old rule body\n"
    assert _git(repo, "show", "HEAD:.claude/rules/old.md") == "old rule body\n"
    assert _porcelain(repo) == ""
    assert pushes == [1]
    assert outcome["gate_reverted"] == [".claude/rules/old.md"]


def test_remediate_only_protected_edit_returns_none_with_hold(tmp_path, repo, refused):
    pre_round = _head(repo)

    def edit(cwd: Path) -> None:
        _write(cwd, ".claude/rules/x.md", "fixer rewrote the rule\n")

    sha, outcome, pushes = _remediate(tmp_path, repo, edit)

    assert sha is None
    assert _head(repo) == pre_round
    assert (repo / ".claude/rules/x.md").read_text() == "v1\n"
    assert _porcelain(repo) == ""
    assert pushes == []
    assert outcome["reason"] == "gate-path-edit"
    assert outcome["gate_hold"] == [{"path": ".claude/rules/x.md", "finding": FINDING}]


class _FailClosedGit(ReplayGit):
    """A replay tree whose fixer commit touched one protected path."""

    def __init__(self, strip_result: str = "", dirty_after_strip: bool = False):
        super().__init__({"slug": "demo", "worktree_diff": ""})
        self.strip_result = strip_result
        self.dirty_after_strip = dirty_after_strip
        self.strip_calls = 0

    def strip_head_paths(self, sha, paths) -> str:
        self.strip_calls += 1
        return self.strip_result

    def changed_files(self, base="origin/master"):
        return ["ibl5/a.php"] if self.strip_calls else [".claude/rules/x.md"]

    def is_dirty(self) -> bool:
        return self.dirty_after_strip and self.strip_calls > 0


def _remediate_fail_closed(tmp_path: Path, git: _FailClosedGit, outcome: dict, **kw):
    llm = FixtureLlm(UsageLedger(), {"fidelity-remediation": "done"})
    return fidelity.remediate(llm, git, str(tmp_path), str(tmp_path), _packet(tmp_path),
                              _verdict(tmp_path), "deadbeef", outcome=outcome, **kw)


def test_remediate_strip_error_fails_closed(tmp_path, refused):
    git = _FailClosedGit(strip_result="boom")
    outcome: dict = {}

    with pytest.raises(HarnessError) as ei:
        _remediate_fail_closed(tmp_path, git, outcome)

    assert ei.value.kind == "gate-path-edit"
    assert "boom" in (ei.value.detail or "")
    assert git.pushes == 0
    assert outcome["reason"] == "gate-path-edit"
    assert "gate_hold" not in outcome


def test_remediate_dirty_tree_after_strip_fails_closed(tmp_path, refused):
    git = _FailClosedGit(dirty_after_strip=True)
    outcome: dict = {}

    with pytest.raises(HarnessError) as ei:
        _remediate_fail_closed(tmp_path, git, outcome)

    assert ei.value.kind == "gate-path-edit"
    assert "dirty=True" in (ei.value.detail or "")
    assert git.pushes == 0
    assert outcome["reason"] == "gate-path-edit"


def test_remediate_recommit_error_becomes_gate_path_edit(tmp_path, refused):
    git = _FailClosedGit()
    calls: list[str] = []

    def commit(message: str) -> str:
        calls.append(message)
        if len(calls) == 2:
            raise HarnessError("local-gate", "hook said no")
        return "fixer-sha"

    outcome: dict = {}
    with pytest.raises(HarnessError) as ei:
        _remediate_fail_closed(tmp_path, git, outcome, commit=commit)

    assert ei.value.kind == "gate-path-edit"
    assert "local-gate" in (ei.value.detail or "")
    assert git.pushes == 0
    assert outcome["reason"] == "gate-path-edit"


def test_gate_hold_record_matches_finding_by_path() -> None:
    record = fidelity.gate_hold_record([".claude/rules/x.md"],
                                       ["Fix `.claude/rules/x.md` wording\nmore"])
    assert record == [{"path": ".claude/rules/x.md",
                       "finding": "Fix `.claude/rules/x.md` wording"}]


def test_gate_hold_record_ignores_other_file_finding() -> None:
    record = fidelity.gate_hold_record([".claude/rules/x.md"], ["Fix `.claude/rules/other.md`"])
    assert record == [{"path": ".claude/rules/x.md", "finding": None}]


def test_gate_hold_record_drops_the_bullet_and_marker() -> None:
    record = fidelity.gate_hold_record([".claude/rules/x.md"],
                                       ["- [BLOCKING] Fix `.claude/rules/x.md` wording"])
    assert record[0]["finding"] == "Fix `.claude/rules/x.md` wording"
