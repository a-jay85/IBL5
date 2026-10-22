"""Executable mutation claims (ADR-0199): harness adapter and journal guard."""
import os
import re
import stat
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.mutations import DEFAULT_LIB, JOURNAL_DIRNAME, run_executable_mutations
from harness.state import MUTATION_JOURNAL_PENDING, HarnessError

FENCE = "`" * 3
PLAN = f"""# Fixture plan

## Verification Matrix

| # | What to verify | Test type | Timing | Test file / location |
|---|---------------|-----------|--------|---------------------|
| 1 | target flips | CLI-executable | post-impl | `bash check.sh` |

## Executable Mutations

{FENCE}mutation
row: 1
file: target.txt
match: ALPHA
replace: BETA
{FENCE}
"""


def _git(repo, *args):
    subprocess.run(["git", "-C", str(repo), *args], check=True, capture_output=True)


def _repo(tmp_path, check="grep -q ALPHA target.txt"):
    repo = tmp_path / "repo"
    repo.mkdir()
    _git(repo, "init", "-q")
    (repo / "target.txt").write_text("ALPHA\n")
    (repo / "check.sh").write_text(check + "\n")
    _git(repo, "add", "-A")
    _git(repo, "-c", "user.email=t@t", "-c", "user.name=t", "commit", "-qm", "fixture")
    plan = tmp_path / "plan.md"
    plan.write_text(PLAN)
    return repo, plan


def _stub(tmp_path, body):
    """A fake lib: `body` is a bash case over $1 (parse|run|recover)."""
    lib = tmp_path / "stub-lib"
    lib.write_text("#!/bin/bash\nS=" + str(tmp_path) + "\ncase \"$1\" in\n" + body + "\nesac\n")
    lib.chmod(lib.stat().st_mode | stat.S_IXUSR)
    return lib


ONE_RECORD = 'parse) printf "1\\0target.txt\\0bash check.sh\\0ALPHA\\0BETA\\n"; exit 0 ;;'


def test_tripped_leaves_tree_clean(tmp_path):
    repo, plan = _repo(tmp_path)
    report = run_executable_mutations(plan, repo, lib=DEFAULT_LIB)
    assert report.ran and report.holding == ()
    assert [r.kind for r in report.results] == ["TRIPPED"]
    assert report.tree_suspect is False
    assert LiveGit(str(repo)).is_dirty() is False
    assert not list((repo / ".git" / JOURNAL_DIRNAME).glob("*.meta"))


def test_held_row_is_reported(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, ONE_RECORD + '\nrun) echo "HELD row 20 — x still exited 0 under mutation"; exit 1 ;;')
    report = run_executable_mutations(plan, repo, lib=lib)
    assert report.holding == ("row 20 HELD — x still exited 0 under mutation",)
    assert report.tree_suspect is False


def test_revert_failed_marks_tree_suspect(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, ONE_RECORD + '\nrun) echo "REVERT-FAILED row 1 — status changed"; exit 3 ;;')
    report = run_executable_mutations(plan, repo, lib=lib)
    assert report.tree_suspect is True
    assert report.holding == ("row 1 REVERT-FAILED — status changed",)


def test_zero_records_skips_run(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, 'parse) exit 0 ;;\nrun) touch "$S/run-called"; exit 99 ;;')
    report = run_executable_mutations(plan, repo, lib=lib)
    assert report.ran is True and report.results == () and report.holding == ()
    assert not (tmp_path / "run-called").exists()


def test_parse_failed_holds(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, 'parse) echo "row 1: missing key match"; exit 1 ;;')
    report = run_executable_mutations(plan, repo, lib=lib)
    assert report.holding == ("executor-parse-failed: row 1: missing key match",)


def test_missing_lib_fails_closed(tmp_path):
    repo, plan = _repo(tmp_path)
    report = run_executable_mutations(plan, repo, lib=tmp_path / "nope")
    assert report.holding and report.holding[0].startswith("executor-unavailable")


@pytest.mark.parametrize("recover_rc,suspect", [(0, False), (1, True)])
def test_executor_timeout_recovers(tmp_path, recover_rc, suspect):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, ONE_RECORD + '\nrun) sleep 30 ;;\n'
                f'recover) touch "$S/recover-called"; exit {recover_rc} ;;')
    report = run_executable_mutations(plan, repo, lib=lib, per_check_timeout=0, outer_timeout=1)
    assert "executor-timeout" in report.holding
    assert (tmp_path / "recover-called").exists()
    assert report.tree_suspect is suspect


def _pending(repo):
    jdir = repo / ".git" / JOURNAL_DIRNAME
    jdir.mkdir(exist_ok=True)
    meta = jdir / "1.meta"
    meta.write_text("path=target.txt\n")
    return meta


def test_stage_all_refuses_with_pending_journal(tmp_path):
    repo, _ = _repo(tmp_path)
    git = LiveGit(str(repo))
    git.stage_all()  # no journal: the guard must not fire
    meta = _pending(repo)
    with pytest.raises(HarnessError) as e:
        git.stage_all()
    assert e.value.kind == MUTATION_JOURNAL_PENDING
    meta.unlink()
    git.stage_all()


def test_commit_all_refuses_with_pending_journal(tmp_path):
    repo, _ = _repo(tmp_path)
    git = LiveGit(str(repo))
    _pending(repo)
    (repo / "new.txt").write_text("x\n")
    with pytest.raises(HarnessError) as e:
        git.commit_all("should not commit")
    assert e.value.kind == MUTATION_JOURNAL_PENDING
    out = subprocess.run(["git", "-C", str(repo), "status", "--porcelain"],
                         capture_output=True, text=True).stdout
    assert "?? new.txt" in out  # nothing was staged


def test_mutation_step_runs_after_verify_before_arm():
    src = (Path(__file__).resolve().parents[1] / "runner.py").read_text()
    verify = src.index("tracks = verifier.run(cls)")
    step = src.index("mutation_report = _exec_mutations_step(")
    arm = src.index("inputs = ArmInputs(")
    first_eval = src.index("= evaluate(inputs)")
    assert verify < step < arm < first_eval
    # no stage/commit between the step and arming
    between = src[step:first_eval]
    assert not re.search(r"\.(stage_all|commit_all)\(", between)
