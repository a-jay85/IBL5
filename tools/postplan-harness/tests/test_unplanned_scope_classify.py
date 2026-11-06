"""The keep/remove/hold classifier for `[unplanned-scope]` fidelity findings."""
from __future__ import annotations

import os
import shutil
import subprocess
import sys
from pathlib import Path

import pytest

from harness import adr_draft, cifix, conformance_fix, fidelity, prosefix, unplanned_scope as us

GRID = "ibl5/design/components/negotiate.css"
TAGGED = f"- [BLOCKING] [unplanned-scope] Remove or plan the button grid in {GRID}."
FEAT = "feat: confine quick offer presets to three in a row"


def _classify(text=TAGGED, *, changed=(GRID,), subjects=None, preserve=(), removed=()):
    return us.classify(
        text,
        changed_paths=changed,
        subjects_by_path=subjects if subjects is not None else {},
        must_preserve=preserve,
        removed_guard_paths=removed,
    )


def _git(path, *args):
    return subprocess.run(["git", *args], cwd=str(path), check=True,
                          capture_output=True, text=True).stdout.strip()


def _repo_with_subjects(path, rel, subjects):
    """A throwaway repo with one base commit, then one commit per subject touching `rel`."""
    path.mkdir(parents=True, exist_ok=True)
    _git(path, "init", "-q", "-b", "master")
    _git(path, "config", "user.email", "t@example.com")
    _git(path, "config", "user.name", "T")
    (path / "README").write_text("base\n", encoding="utf-8")
    _git(path, "add", "README")
    _git(path, "commit", "-q", "-m", "base")
    base = _git(path, "rev-parse", "HEAD")
    target = path / rel
    target.parent.mkdir(parents=True, exist_ok=True)
    for i, subject in enumerate(subjects):
        target.write_text(f"{i}\n", encoding="utf-8")
        _git(path, "add", rel)
        _git(path, "commit", "-q", "-m", subject)
    return base


def test_untagged_check3_finding_is_not_unplanned():
    assert not us.is_unplanned_scope("- [BLOCKING] Scope creep: unplanned grid in negotiate.css")
    assert not us.is_unplanned_scope("- [BLOCKING] [unplanned-scopes] x")
    assert not us.is_unplanned_scope("- [BLOCKING] x [unplanned-scope]")
    assert us.is_unplanned_scope("- [BLOCKING] [unplanned-scope] x")
    assert us.is_unplanned_scope("- [NOTE] [unplanned-scope] x")


def test_machine_subjects_match_harness_constants():
    samples = [
        fidelity.REMEDIATION_COMMIT_MSG,
        cifix.CI_FIX_COMMIT_MSG.format(n=2),
        conformance_fix.COMMIT_MSG,
        prosefix.PROSE_FIX_COMMIT_MSG,
        adr_draft.ADR_COMMIT_MSG.format(number="0210", slug="x"),
        us.VR_BASELINE_SUBJECT,
    ]
    for subject in samples:
        assert us.is_machine_commit(subject), subject


def test_user_subjects_are_not_machine():
    assert not us.is_machine_commit(FEAT)
    assert not us.is_machine_commit("chore: address review comments")
    assert not us.is_machine_commit("chore: address Phase 5.5 plan-fidelity findings (manual)")


def test_classify_remove_when_only_machine_commits():
    assert _classify(subjects={GRID: [fidelity.REMEDIATION_COMMIT_MSG]}) == "remove"


def test_classify_keep_when_user_commit_touched_path():
    subjects = {GRID: ["chore: address Phase 5.5 plan-fidelity findings", FEAT]}
    assert _classify(subjects=subjects) == "keep"


def test_classify_keep_when_no_path_cited():
    text = "- [BLOCKING] [unplanned-scope] Remove the button grid."
    assert _classify(text, subjects={GRID: [fidelity.REMEDIATION_COMMIT_MSG]}) == "keep"


def test_classify_keep_when_history_empty():
    assert _classify(subjects={GRID: []}) == "keep"
    assert _classify(subjects={}) == "keep"


def test_classify_keep_when_one_of_two_cited_paths_has_a_user_commit():
    other = "ibl5/classes/Other.php"
    text = f"- [BLOCKING] [unplanned-scope] Remove {GRID} and {other}."
    subjects = {GRID: [fidelity.REMEDIATION_COMMIT_MSG], other: [FEAT]}
    assert _classify(text, changed=(GRID, other), subjects=subjects) == "keep"


def test_classify_hold_on_must_preserve_conflict():
    subjects = {GRID: [fidelity.REMEDIATION_COMMIT_MSG]}
    assert _classify(subjects=subjects, preserve=(GRID,), removed=(GRID,)) == "hold"


def test_classify_no_hold_for_removed_guard_outside_must_preserve():
    assert _classify(subjects={GRID: [FEAT]}, removed=(GRID,)) == "keep"


def test_classify_no_hold_for_must_preserve_path_with_no_removed_guard():
    assert _classify(subjects={GRID: [FEAT]}, preserve=(GRID,)) == "keep"


def test_classify_untagged_returns_none():
    assert _classify("- [BLOCKING] Rename the helper.") is None


def test_must_preserve_paths_parses_section():
    plan = (
        "# Plan\n\n"
        "## Must preserve\n\n"
        "- `a/b.py` keeps its guard.\n"
        "- `c/d.md` the sentence `not this one` stays.\n\n"
        "### Intended removals\n\n"
        "- `e/f.py` goes away.\n\n"
        "## Critical Files\n\n"
        "- `g/h.py`\n"
    )
    assert us.must_preserve_paths(plan) == {"a/b.py", "c/d.md"}
    assert us.must_preserve_paths("## Must preserve notes\n\n- `a/b.py`\n") == set()
    assert us.must_preserve_paths("# Plan with no such section\n") == set()


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_commit_subjects_for_real_git(tmp_path):
    repo = tmp_path / "repo"
    base = _repo_with_subjects(repo, "src/a.txt", ["first subject", "second subject"])

    got = us.commit_subjects_for(str(repo), base, ["src/a.txt", "src/untouched.txt"])
    assert got == {"src/a.txt": ["second subject", "first subject"], "src/untouched.txt": []}

    assert us.commit_subjects_for(str(repo), "0" * 39 + "1", ["src/a.txt"]) == {"src/a.txt": []}


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_subjects_for_follows_a_rename_on_the_branch(tmp_path):
    repo = tmp_path / "repo"
    base = _repo_with_subjects(repo, "src/old.txt", ["user wrote this"])
    (repo / "src" / "old.txt").write_text("".join(f"line {i}\n" for i in range(40)), encoding="utf-8")
    _git(repo, "add", "src/old.txt")
    _git(repo, "commit", "-m", "user grew this")
    _git(repo, "mv", "src/old.txt", "src/new.txt")
    _git(repo, "commit", "-m", "fix: machine rename")

    assert us._subjects_for(str(repo), base, "src/new.txt") == [
        "fix: machine rename", "user grew this", "user wrote this"]


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_non_utf8_commit_subject_maps_to_empty_and_keeps(tmp_path):
    repo = tmp_path / "repo"
    base = _repo_with_subjects(repo, GRID, [])
    target = repo / GRID
    target.parent.mkdir(parents=True, exist_ok=True)
    target.write_text("grid\n", encoding="utf-8")
    _git(repo, "add", GRID)
    # `git commit` rewrites a Latin-1 message to UTF-8, so write the commit object directly.
    identity = b"T <t@example.com> 1700000000 +0000"
    raw = (b"tree " + _git(repo, "write-tree").encode() + b"\n"
           b"parent " + base.encode() + b"\n"
           b"author " + identity + b"\ncommitter " + identity + b"\n\ncaf\xe9 grid\n")
    made = subprocess.run(["git", "hash-object", "-t", "commit", "-w", "--stdin"],
                          cwd=str(repo), input=raw, check=True, capture_output=True)
    _git(repo, "update-ref", "HEAD", made.stdout.decode().strip())

    assert us._subjects_for(str(repo), base, GRID) == []
    subjects = us.commit_subjects_for(str(repo), base, [GRID])
    assert subjects == {GRID: []}
    assert _classify(subjects=subjects) == "keep"


def _cli_fixture(tmp_path):
    """A repo with a user commit on one path and a fixer commit on another, plus inputs."""
    repo = tmp_path / "repo"
    user_path, fix_path = "src/user.css", "src/fixer.css"
    base = _repo_with_subjects(repo, user_path, [FEAT])
    (repo / fix_path).write_text("fix\n", encoding="utf-8")
    _git(repo, "add", fix_path)
    _git(repo, "commit", "-q", "-m", fidelity.REMEDIATION_COMMIT_MSG)
    verdict = tmp_path / "verdict.md"
    verdict.write_text(
        "## FINDINGS\n"
        f"- [BLOCKING] [unplanned-scope] Remove the grid in {user_path}. Next action: go.\n"
        f"- [BLOCKING] [unplanned-scope] Remove the helper in {fix_path}.\n"
        "- [BLOCKING] Rename the helper.\n"
        "\nNOT READY\n", encoding="utf-8")
    plan = tmp_path / "plan.md"
    plan.write_text("# Plan\n\n## Must preserve\n\n## Critical Files\n", encoding="utf-8")
    # The detector is bin/lib/deleted-guards under --worktree; a temp repo has none.
    guards = repo / "bin" / "lib" / "deleted-guards"
    guards.parent.mkdir(parents=True)
    guards.write_text("#!/usr/bin/env bash\nexit 0\n", encoding="utf-8")
    guards.chmod(0o755)
    args = ["--verdict", str(verdict), "--plan", str(plan), "--base", base,
            "--worktree", str(repo)]
    return args, user_path, fix_path


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_prints_branch_lines(tmp_path, capsys):
    args, user_path, fix_path = _cli_fixture(tmp_path)
    assert us.main(args) == 0
    assert capsys.readouterr().out.splitlines() == [
        f"KEEP\tRemove the grid in {user_path}\t{user_path}",
        f"REMOVE\tRemove the helper in {fix_path}.\t{fix_path}",
    ]


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_render_block(tmp_path, capsys):
    args, user_path, fix_path = _cli_fixture(tmp_path)
    assert us.main([*args, "--render-block"]) == 0
    out = capsys.readouterr().out
    assert out.startswith("<!-- kept-unplanned:begin -->\n")
    assert user_path in out
    assert fix_path not in out


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_tagged_note_items_count(tmp_path, capsys):
    args, user_path, _ = _cli_fixture(tmp_path)
    verdict = args[args.index("--verdict") + 1]
    with open(verdict, "w", encoding="utf-8") as fh:
        fh.write(f"## FINDINGS\n- [NOTE] [unplanned-scope] Doc bump in {user_path}.\n\nREADY\n")
    assert us.main(args) == 0
    assert capsys.readouterr().out == f"KEEP\tDoc bump in {user_path}.\t{user_path}\n"


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_bad_base_exits_3(tmp_path, capsys):
    args, _, _ = _cli_fixture(tmp_path)
    args[args.index("--base") + 1] = "0" * 39 + "1"
    assert us.main(args) == 3
    captured = capsys.readouterr()
    assert captured.out == ""
    assert "unplanned_scope: git diff" in captured.err


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_detector_failure_exits_3(tmp_path, capsys):
    args, _, _ = _cli_fixture(tmp_path)
    repo = args[args.index("--worktree") + 1]
    guards = f"{repo}/bin/lib/deleted-guards"
    with open(guards, "w", encoding="utf-8") as fh:
        fh.write("#!/usr/bin/env bash\nexit 2\n")
    assert us.main(args) == 3
    assert capsys.readouterr().out == ""


@pytest.mark.skipif(shutil.which("git") is None, reason="needs git")
def test_cli_non_utf8_diff_exits_3_without_traceback(tmp_path):
    args, _, _ = _cli_fixture(tmp_path)
    repo = Path(args[args.index("--worktree") + 1])
    (repo / "latin1.txt").write_bytes(b"caf\xe9\n")
    _git(repo, "add", "latin1.txt")
    _git(repo, "commit", "-q", "-m", "add a Latin-1 file")

    proc = subprocess.run(
        [sys.executable, "-m", "harness.unplanned_scope", *args],
        cwd=str(Path(us.__file__).resolve().parents[1]),
        env={**os.environ, "PYTHONUTF8": "1"},
        capture_output=True, text=True, timeout=60,
    )

    assert proc.returncode == 3
    assert "Traceback" not in proc.stderr
    assert "unplanned_scope: git diff" in proc.stderr
    assert proc.stdout == ""


def test_cli_missing_verdict_exits_2(tmp_path, capsys):
    plan = tmp_path / "plan.md"
    plan.write_text("# Plan\n", encoding="utf-8")
    code = us.main(["--verdict", str(tmp_path / "gone.md"), "--plan", str(plan),
                    "--base", "HEAD", "--worktree", str(tmp_path)])
    assert code == 2
    assert capsys.readouterr().out == ""


def test_cli_usage_error_exits_2(capsys):
    with pytest.raises(SystemExit) as caught:
        us.main(["--verdict", "x"])
    assert caught.value.code == 2
