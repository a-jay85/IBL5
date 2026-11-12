"""Post-plan reuses pre-fire's executable-mutations pass (ADR-0199).

Pre-fire writes a reuse sidecar keyed on the parsed rows; post-plan rebuilds its
MutationReport from it only when no dependency path changed since pre-fire.
"""
import os
import re
import stat
import subprocess
import sys
from pathlib import Path

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

REPO = Path(__file__).resolve().parents[3]
PREFIRE = REPO / "bin" / "lib" / "prefire-verify"

RECORD = b"1\x00target.txt\x00grep -q ALPHA target.txt && grep -q P checks/probe.txt\x00ALPHA\x00BETA\n"
TRIPPED = "TRIPPED row 1 — check exited 1 under mutation"
HELD = "HELD row 1 — check still exited 0 under mutation"


def _git(repo, *args, input=None):
    return subprocess.run(["git", "-C", str(repo), *args], capture_output=True,
                          input=input, check=True)


def _commit(repo, msg="c"):
    _git(repo, "add", "-A")
    _git(repo, "commit", "-qm", msg)


def _repo(tmp_path):
    repo = tmp_path / "repo"
    repo.mkdir()
    _git(repo, "init", "-q", "-b", "master")
    _git(repo, "config", "user.name", "t")
    _git(repo, "config", "user.email", "t@t")
    (repo / "target.txt").write_text("ALPHA\n")
    (repo / "checks").mkdir()
    (repo / "checks" / "probe.txt").write_text("P\n")
    _commit(repo, "fixture")
    plan = tmp_path / "plan.md"
    plan.write_text("# Fixture plan\n")
    return repo, plan


def _stub(tmp_path, records, run_body, pending_body="exit 1"):
    """A fake lib. `pending` exits 1 on an empty journal, like the real one."""
    (tmp_path / "records").write_bytes(records)
    lib = tmp_path / "stub-lib"
    lib.write_text(
        "#!/bin/bash\n"
        f"S={tmp_path}\n"
        "case \"$1\" in\n"
        f"parse) cat \"$S/records\" ;;\n"
        f"run) touch \"$S/run-called\"; {run_body} ;;\n"
        f"pending) {pending_body} ;;\n"
        "recover) exit 0 ;;\n"
        "esac\n")
    lib.chmod(lib.stat().st_mode | stat.S_IXUSR)
    return lib


def _prefire(repo, plan, lib):
    env = {**os.environ, "PREFIRE_EXEC_MUTATIONS_LIB": str(lib)}
    env.pop("CLAUDE_HEADLESS", None)
    return subprocess.run([str(PREFIRE), "--base", "master", "--plan", str(plan),
                           "--stage", "exec-mutations"],
                          cwd=str(repo), env=env, capture_output=True, text=True, timeout=120)


def _state_dir(repo):
    gp = Path(_git(repo, "rev-parse", "--git-path", "prefire-verify").stdout.decode().strip())
    return gp if gp.is_absolute() else Path(repo) / gp


def _key(data):
    return _git(".", "hash-object", "--stdin", input=data).stdout.decode().strip()


def _sidecars(repo):
    return sorted(_state_dir(repo).glob("exec-mutations.rows-*.reuse"))


# --- Phase 1: pre-fire writes the sidecar -------------------------------------

def test_prefire_pass_writes_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{TRIPPED}"; exit 0')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode == 0, proc.stdout + proc.stderr
    sidecar = _state_dir(repo) / f"exec-mutations.rows-{_key(RECORD)}.reuse"
    assert sidecar.is_file()
    lines = sidecar.read_text(encoding="utf-8").splitlines()
    assert lines[0] == "prefire-xm-reuse v1"
    assert lines[1] == f"rows={_key(RECORD)}"
    assert re.fullmatch(r"tree=[0-9a-f]{40}", lines[2])
    assert lines[3].startswith("at=")
    assert lines[4:] == [TRIPPED]
    gitdir = Path(_git(repo, "rev-parse", "--absolute-git-dir").stdout.decode().strip())
    assert sidecar.resolve().is_relative_to(gitdir.resolve())
    assert _git(repo, "status", "--porcelain", "--ignored").stdout == b""


def test_prefire_held_writes_no_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{HELD}"; exit 1')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode != 0
    assert _sidecars(repo) == []


def test_prefire_tree_change_drops_sidecar(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{TRIPPED}"; touch {repo}/stray.txt; exit 0')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode == 4, proc.stdout + proc.stderr
    assert _sidecars(repo) == []


# --- Phase 2: post-plan loads and validates the sidecar -------------------------

from harness.armable import ArmInputs, evaluate  # noqa: E402
from harness.classify import render_exec_mutations  # noqa: E402
from harness.mutations import (DEFAULT_LIB, MutationReport, MutationResult,  # noqa: E402
                               _rows_identity, reuse_prefire_pass)
from harness.state import Classification  # noqa: E402


def _rec(check, match="ALPHA"):
    return b"1\x00target.txt\x00" + check.encode() + b"\x00" + match.encode() + b"\x00BETA\n"


def _seed(tmp_path, repo, body, *, key=None, tree=None, magic="prefire-xm-reuse v1"):
    tree = tree or _git(repo, "rev-parse", "HEAD^{tree}").stdout.decode().strip()
    key = key or _key(RECORD)
    sd = _state_dir(repo)
    sd.mkdir(parents=True, exist_ok=True)
    text = f"{magic}\nrows={key}\ntree={tree}\nat=2026-11-12T00:00:00Z\n"
    text += "".join(line + "\n" for line in body)
    (sd / f"exec-mutations.rows-{key}.reuse").write_text(text, encoding="utf-8")


def _edit_commit(repo, rel, text="changed\n"):
    path = Path(repo) / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(text)
    _commit(repo, f"edit {rel}")


def _reuse_stub(tmp_path, records=RECORD, pending_body="exit 1"):
    return _stub(tmp_path, records, "exit 0", pending_body)


def _cond19_blocked(report):
    inp = ArmInputs(pr_body="## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed — covered.\n",
                    pr_title="chore: x", pr_labels=[],
                    classification=Classification(files=["tools/postplan-harness/harness/mutations.py"]),
                    findings=[], unresolved_conformance=[],
                    exec_mutations_holding=report.holding,
                    exec_mutations_tree_suspect=report.tree_suspect,
                    phase5_status="pass", plan_auto_merge_false=False, headless=True,
                    dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                    unresolved_findings=[], conflict_resolved=False, current_tree="a" * 40,
                    merge_danger_state="TWO-WAY", removed_guards_holds=[])
    return 19 in {c.number for c in evaluate(inp).holds}


def _assert_not_run(tmp_path):
    assert not (tmp_path / "run-called").exists()


@pytest.mark.parametrize("data", [RECORD, RECORD * 2, b""])
def test_rows_identity_matches_git_hash_object(data):
    assert _rows_identity(data) == _key(data)


def test_prefire_pass_then_postplan_reuses(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _stub(tmp_path, RECORD, f'echo "{TRIPPED}"; exit 0')
    proc = _prefire(repo, plan, lib)
    assert proc.returncode == 0, proc.stdout + proc.stderr
    (tmp_path / "run-called").unlink()
    _edit_commit(repo, "other.txt")
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why
    assert report.results == (MutationResult(1, "TRIPPED", "check exited 1 under mutation"),)
    _assert_not_run(tmp_path)


def test_reuse_when_deps_unchanged(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, ["TRIPPED row 1 — x"])
    _edit_commit(repo, "other.txt")
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why
    assert report.holding == ()
    assert report.tree_suspect is False
    assert why.startswith("rows ")
    fresh = MutationReport(True, (MutationResult(1, "TRIPPED", "x"),), (), False)
    assert render_exec_mutations(report) == render_exec_mutations(fresh)
    assert render_exec_mutations(report) != ""
    assert _cond19_blocked(report) is False
    _assert_not_run(tmp_path)


def test_rebase_touching_check_file_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [TRIPPED])
    _edit_commit(repo, "checks/probe.txt", "P2\n")
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "dependency changed since pre-fire")


def test_rebase_touching_target_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [TRIPPED])
    _edit_commit(repo, "target.txt", "ALPHA\nmore\n")
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "dependency changed since pre-fire")


def test_missing_record_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "no pre-fire record for these rows")


@pytest.mark.parametrize("body, magic, reason", [
    (["TRIPPED row 1 - x"], "prefire-xm-reuse v1", "record malformed: result line"),
    (["TRIPPED row 2 — x"], "prefire-xm-reuse v1", "record malformed: row ids differ from the plan"),
    ([TRIPPED], "prefire-xm-reuse v2", "record malformed: header"),
    ([], "prefire-xm-reuse v1", "record malformed: header"),
])
def test_corrupt_record_runs(tmp_path, body, magic, reason):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, body, magic=magic)
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, reason)


def test_reused_held_row_still_holds(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [HELD])
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why
    assert report.holding == ("row 1 HELD — check still exited 0 under mutation",)
    assert report.tree_suspect is False
    assert _cond19_blocked(report) is True


def test_changed_mutation_block_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [TRIPPED], key=_rows_identity(RECORD))
    (tmp_path / "records").write_bytes(RECORD.replace(b"\x00ALPHA\x00", b"\x00ALPH\x00"))
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "no pre-fire record for these rows")


def test_pending_journal_blocks_reuse(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path, pending_body='echo "row 1 target.txt"; exit 0')
    _seed(tmp_path, repo, [TRIPPED])
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "journal not empty")


def test_pending_contract_empty_journal(tmp_path):
    """The real lib answers an empty journal with exit 1 and no output; `_reuse` keys on that."""
    repo, _ = _repo(tmp_path)
    proc = subprocess.run([str(DEFAULT_LIB), "pending", "--root", str(repo)],
                          capture_output=True, timeout=60, cwd=str(repo))
    assert proc.returncode == 1
    assert proc.stdout == b""


@pytest.mark.parametrize("check, reason", [
    ("grep -q 'a/b' target.txt", "dependency unresolved: row 1 unresolved token a/b"),
    ("grep -q X checks/*.txt target.txt", "dependency unresolved: row 1 unsafe token checks/*.txt"),
    ('grep -q "X target.txt', "dependency unresolved: row 1 check is not shell-splittable"),
    ("grep -q ALPHA target.txt checks/probe.txt >/dev/null 2>&1", None),
])
def test_unresolved_check_token_runs(tmp_path, check, reason):
    repo, plan = _repo(tmp_path)
    rec = _rec(check)
    lib = _reuse_stub(tmp_path, records=rec)
    _seed(tmp_path, repo, [TRIPPED], key=_key(rec))
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    if reason is None:
        assert report is not None, why
    else:
        assert (report, why) == (None, reason)


def _cd_fixture(tmp_path):
    repo, plan = _repo(tmp_path)
    (repo / "pkg" / "sub").mkdir(parents=True)
    (repo / "pkg" / "sub" / "inner.txt").write_text("X\n")
    (repo / "pkg" / "sub" / "other.txt").write_text("O\n")
    _commit(repo, "pkg")
    rec = _rec("cd pkg/sub && grep -q X inner.txt")
    lib = _reuse_stub(tmp_path, records=rec)
    _seed(tmp_path, repo, [TRIPPED], key=_key(rec))
    return repo, plan, lib


def test_cd_base_resolves_relative_token(tmp_path):
    repo, plan, lib = _cd_fixture(tmp_path)
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why


def test_cd_argument_is_not_a_dependency(tmp_path):
    repo, plan, lib = _cd_fixture(tmp_path)
    _edit_commit(repo, "pkg/sub/other.txt")
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why


def test_check_without_tracked_path_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    (repo / ".gitignore").write_text("vendor/\n")
    _commit(repo, "ignore vendor")
    (repo / "vendor" / "bin").mkdir(parents=True)
    (repo / "vendor" / "bin" / "phpunit").write_text("#!/bin/sh\n")
    rec = _rec("vendor/bin/phpunit --filter testX")
    lib = _reuse_stub(tmp_path, records=rec)
    _seed(tmp_path, repo, [TRIPPED], key=_key(rec))
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "no tracked dependency in check: row 1")


def test_state_dir_joined_onto_worktree(tmp_path, monkeypatch):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [TRIPPED])
    monkeypatch.chdir(tmp_path)
    report, why = reuse_prefire_pass(plan, repo, lib=lib)
    assert report is not None, why


def test_missing_prefire_tree_runs(tmp_path):
    repo, plan = _repo(tmp_path)
    lib = _reuse_stub(tmp_path)
    _seed(tmp_path, repo, [TRIPPED], tree="0" * 40)
    assert reuse_prefire_pass(plan, repo, lib=lib) == (None, "pre-fire tree missing")


# --- Phase 3: the runner reuses the record and logs the start line -------------

from types import SimpleNamespace  # noqa: E402

import runner  # noqa: E402


def _plan_ns(tmp_path):
    return SimpleNamespace(found=True, path=str(tmp_path / "plan.md"))


def test_step_reuses_and_skips_run(tmp_path, monkeypatch):
    rep = MutationReport(True, (MutationResult(1, "TRIPPED", "x"),), (), False)
    monkeypatch.setattr(runner, "reuse_prefire_pass", lambda *a, **k: (rep, "rows abc"))

    def boom(*a, **k):
        raise AssertionError("executor must not run")
    monkeypatch.setattr(runner, "run_executable_mutations", boom)
    lines = []
    got = runner._exec_mutations_step(_plan_ns(tmp_path), str(tmp_path), True, lines.append)
    assert got is rep
    assert "exec-mutations: reused pre-fire pass (rows abc)" in lines
    assert "exec-mutations: TRIPPED row 1 — x" in lines


def test_step_logs_running_before_run(tmp_path, monkeypatch):
    monkeypatch.setattr(runner, "reuse_prefire_pass",
                        lambda *a, **k: (None, "no pre-fire record for these rows"))
    lines, seen = [], []

    def fake_run(*a, **k):
        seen.append(list(lines))
        return MutationReport(True, (), (), False)
    monkeypatch.setattr(runner, "run_executable_mutations", fake_run)
    runner._exec_mutations_step(_plan_ns(tmp_path), str(tmp_path), True, lines.append)
    assert len(seen) == 1
    assert "exec-mutations: running (no pre-fire record for these rows)" in seen[0]


def test_step_reuse_crash_runs_fresh(tmp_path, monkeypatch):
    def crash(*a, **k):
        raise RuntimeError("boom")
    monkeypatch.setattr(runner, "reuse_prefire_pass", crash)
    calls = []

    def fake_run(*a, **k):
        calls.append(a)
        return MutationReport(True, (), (), False)
    monkeypatch.setattr(runner, "run_executable_mutations", fake_run)
    lines = []
    runner._exec_mutations_step(_plan_ns(tmp_path), str(tmp_path), True, lines.append)
    assert len(calls) == 1
    assert "exec-mutations: running (reuse check crashed: boom)" in lines


def test_fix_round_check_never_reuses(tmp_path, monkeypatch):
    reuse_calls = []
    reused = MutationReport(True, (MutationResult(1, "TRIPPED", "x"),), (), False)

    def fake_reuse(*a, **k):
        reuse_calls.append(a)
        return reused, "rows abc"
    sentinel = MutationReport(True, (), ("sentinel",), False)
    monkeypatch.setattr(runner, "reuse_prefire_pass", fake_reuse)
    monkeypatch.setattr(runner, "run_executable_mutations", lambda *a, **k: sentinel)
    got = runner._fix_round_mutation_check(str(tmp_path / "plan.md"), str(tmp_path), 100)
    assert got is sentinel
    assert reuse_calls == []


def test_replay_branch_never_consults_record(tmp_path, monkeypatch):
    def boom(*a, **k):
        raise AssertionError("replay must not consult the record")
    monkeypatch.setattr(runner, "reuse_prefire_pass", boom)
    lines = []
    report = runner._exec_mutations_step(_plan_ns(tmp_path), str(tmp_path), False, lines.append,
                                         fixture={"exec_mutations_holding": ["h"]})
    assert report.holding == ("h",)
