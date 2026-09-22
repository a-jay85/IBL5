import os
import subprocess
import pytest
from harness.conformance import main

CRITICAL = "src/thing.py"
MATRIX_TEST = "tests/test_thing.py"

PLAN_TEXT = f"""---
impl_model: sonnet
auto_merge: false
---

# Synthetic plan for seam tests

## Critical Files

- `{CRITICAL}` (modified)

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | thing works | PHPUnit | post-impl | `{MATRIX_TEST}` |
"""


def _run_git(repo, *args):
    subprocess.run(["git", "-c", "user.email=t@example.com",
                    "-c", "user.name=Seam Test", *args],
                   cwd=repo, check=True, capture_output=True, text=True)


def _make_repo(tmp_path):
    """A git repo with one commit and an origin/master ref pointing at it."""
    repo = tmp_path / "repo"
    (repo / "src").mkdir(parents=True)
    (repo / "tests").mkdir()
    (repo / "README.md").write_text("seed\n")
    _run_git(repo, "init", "-q", ".")
    _run_git(repo, "add", "README.md")
    _run_git(repo, "commit", "-qm", "seed")
    _run_git(repo, "update-ref", "refs/remotes/origin/master", "HEAD")
    return repo


def _write_plan(tmp_path):
    """Plan file OUTSIDE the repo, mirroring ~/claude-plans/ residency.

    Asserts the synthetic markdown actually parses. Without this the whole suite
    could pass vacuously on a fixture planfile.py never recognized as a plan.
    """
    from harness.planfile import locate_plan
    plan_path = tmp_path / "plans" / "seam.md"
    plan_path.parent.mkdir(parents=True)
    plan_path.write_text(PLAN_TEXT)
    parsed = locate_plan(slug=None, plans_dir=None, explicit_path=str(plan_path))
    assert parsed.found, "fixture plan did not parse as a plan"
    assert parsed.has_matrix, "fixture plan has no parsed Verification Matrix"
    assert [p for p, _a, ex in parsed.critical_files if not ex], "no non-exempt Critical File parsed"
    assert parsed.planned_test_paths, "no matrix test path parsed"
    return plan_path


def test_clean_case(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    (repo / CRITICAL).write_text("x = 1\n")
    (repo / MATRIX_TEST).write_text("def test_x(): pass\n")
    _run_git(repo, "add", CRITICAL, MATRIX_TEST)
    _run_git(repo, "commit", "-qm", "work")
    assert main([str(plan), str(repo)]) == 0
    assert capsys.readouterr().out == ""


def test_missing_critical_file(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    (repo / MATRIX_TEST).write_text("def test_x(): pass\n")
    _run_git(repo, "add", MATRIX_TEST)
    _run_git(repo, "commit", "-qm", "test only")
    assert main([str(plan), str(repo)]) == 1
    out = capsys.readouterr().out
    assert "MISSING-FILE:" in out and CRITICAL in out
    assert "UNMET-CONTRACT:" not in out
    assert "MISSING-METHOD:" not in out


def test_missing_matrix_test(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    (repo / CRITICAL).write_text("x = 1\n")
    _run_git(repo, "add", CRITICAL)
    _run_git(repo, "commit", "-qm", "impl only")
    assert main([str(plan), str(repo)]) == 1
    out = capsys.readouterr().out
    assert "MISSING:" in out and MATRIX_TEST in out


def test_dirty_uncommitted_tracked_resolves(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    (repo / CRITICAL).write_text("x = 1\n")
    (repo / MATRIX_TEST).write_text("def test_x(): pass\n")
    _run_git(repo, "add", CRITICAL, MATRIX_TEST)  # staged, never committed
    assert main([str(plan), str(repo)]) == 0
    assert capsys.readouterr().out == ""


def test_dirty_untracked_resolves(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    plan = _write_plan(tmp_path)
    (repo / CRITICAL).write_text("x = 1\n")
    (repo / MATRIX_TEST).write_text("def test_x(): pass\n")
    # no git add at all — untracked, not gitignored
    assert main([str(plan), str(repo)]) == 0
    assert capsys.readouterr().out == ""


def test_unreadable_plan_exits_2(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    ghost = tmp_path / "plans" / "does-not-exist.md"
    assert main([str(ghost), str(repo)]) == 2
    cap = capsys.readouterr()
    assert cap.err.strip() != ""
    assert cap.out == ""


def test_bare_filename_exits_2(tmp_path, capsys):
    repo = _make_repo(tmp_path)
    _write_plan(tmp_path)
    assert main(["seam.md", str(repo)]) == 2
    cap = capsys.readouterr()
    assert "absolute" in cap.err
    assert cap.out == ""
