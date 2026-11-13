"""The SQL-equivalence evidence recorder and the arming-time validator.

Each test builds a throwaway git repo, records evidence for one rewritten source
file, then checks the verdict `arm_input_value` would hand to armable."""
import ast
import json
import subprocess
from pathlib import Path

import pytest

from harness import sql_equivalence as se

BRANCH = "pair-queries-faster"
SOURCE = "ibl5/classes/Foo/FooRepository.php"
BIND_FILE = "ibl5/tests/Foo/FooRepositoryTest.php"
BIND_TEST = f"{BIND_FILE}::testPairQueryBindsEveryUserValue"
EVIDENCE = f"ibl5/tests/sql-equivalence/{BRANCH}.json"
CHANGED = [EVIDENCE, SOURCE, BIND_FILE]
DUMP = "noise line\nHASH case-1 aaa\nHASH\tcase-2 bbb\nHASH case-3 ccc\n"


@pytest.fixture
def repo(tmp_path):
    root = tmp_path / "repo"
    root.mkdir()
    subprocess.run(["git", "init", "-q"], cwd=root, check=True)
    for rel, body in ((SOURCE, "<?php // new query\n"), (BIND_FILE, "<?php function testPairQueryBindsEveryUserValue() {}\n")):
        (root / rel).parent.mkdir(parents=True, exist_ok=True)
        (root / rel).write_text(body)
    return root


def _dump(tmp_path, name, text):
    p = tmp_path / name
    p.write_text(text)
    return p


def _record(repo, tmp_path, before=DUMP, after=DUMP, sources=(SOURCE,), bind_tests=(BIND_TEST,)):
    return se.record(repo, BRANCH, _dump(tmp_path, "before.txt", before),
                     _dump(tmp_path, "after.txt", after), list(sources), list(bind_tests))


def _evidence(repo):
    return json.loads((repo / EVIDENCE).read_text())


def _rewrite(repo, **changes):
    data = _evidence(repo)
    data.update(changes)
    (repo / EVIDENCE).write_text(json.dumps(data))


# ---- record ----

def test_record_writes_pass_for_identical_hash_lines(repo, tmp_path):
    assert _record(repo, tmp_path) == 0
    data = _evidence(repo)
    assert data["status"] == "pass"
    assert data["cases"] == 3
    assert data["before_digest"] == data["after_digest"]
    assert data["bind_tests"] == [BIND_TEST]
    blob = subprocess.run(["git", "hash-object", SOURCE], cwd=repo, check=True,
                          capture_output=True, text=True).stdout.strip()
    assert data["sources"] == {SOURCE: blob}


def test_record_writes_fail_and_returns_1_on_mismatch(repo, tmp_path):
    after = DUMP.replace("HASH case-3 ccc", "HASH case-3 ddd")
    assert _record(repo, tmp_path, after=after) == 1
    data = _evidence(repo)
    assert data["status"] == "fail"
    assert data["before_digest"] != data["after_digest"]


def test_record_writes_fail_on_reordered_cases(repo, tmp_path):
    reordered = "HASH case-2 bbb\nHASH case-1 aaa\nHASH case-3 ccc\n"
    assert _record(repo, tmp_path, after=reordered) == 1
    assert _evidence(repo)["status"] == "fail"


def test_record_returns_1_on_zero_cases(repo, tmp_path):
    assert _record(repo, tmp_path, before="", after="") == 1
    data = _evidence(repo)
    assert data["status"] == "fail"
    assert data["cases"] == 0


def test_record_refuses_without_bind_test(repo, tmp_path):
    assert _record(repo, tmp_path, bind_tests=()) == 2
    assert not (repo / EVIDENCE).exists()


def test_record_refuses_without_source(repo, tmp_path):
    assert _record(repo, tmp_path, sources=()) == 2
    assert not (repo / EVIDENCE).exists()


def test_hash_line_near_miss_not_counted(repo, tmp_path):
    near_miss = "HASHES 3\n HASH x\nhash case-1 aaa\n"
    assert se.read_hash_lines(_dump(tmp_path, "near.txt", near_miss)) == []
    assert _record(repo, tmp_path, before=near_miss, after=near_miss) == 1
    assert _evidence(repo)["cases"] == 0


def test_evidence_path_rejects_unsafe_branch(repo):
    for bad in ("a/b", "..", "a b", ""):
        with pytest.raises(ValueError):
            se.evidence_path(repo, bad)


# ---- evaluate_evidence ----

def test_evaluate_ok_on_fresh_evidence(repo, tmp_path):
    _record(repo, tmp_path)
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "ok"


def test_evaluate_absent(repo):
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "absent"


def test_evaluate_malformed_json(repo, tmp_path):
    _record(repo, tmp_path)
    (repo / EVIDENCE).write_text("{not json")
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "malformed"
    (repo / EVIDENCE).write_text("[1, 2]")
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "malformed"
    _record(repo, tmp_path)
    _rewrite(repo, schema=2)
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "malformed"


def test_evaluate_not_in_diff(repo, tmp_path):
    _record(repo, tmp_path)
    assert se.evaluate_evidence(repo, BRANCH, [SOURCE, BIND_FILE]) == "not in diff"


def test_evaluate_failed_status(repo, tmp_path):
    _record(repo, tmp_path)
    _rewrite(repo, status="fail")
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "failed"


def test_evaluate_zero_cases(repo, tmp_path):
    _record(repo, tmp_path)
    _rewrite(repo, cases=0)
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "no cases"
    _rewrite(repo, cases=True)
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "no cases"
    _rewrite(repo, cases="3")
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "no cases"


def test_evaluate_digest_mismatch(repo, tmp_path):
    _record(repo, tmp_path)
    _rewrite(repo, after_digest="0" * 64)
    assert _evidence(repo)["status"] == "pass"
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "digest mismatch"


def test_evaluate_no_sources_or_bind_test(repo, tmp_path):
    _record(repo, tmp_path)
    _rewrite(repo, sources={})
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "no sources"
    _record(repo, tmp_path)
    _rewrite(repo, bind_tests=[])
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "no bind test"


def test_evaluate_source_not_in_diff(repo, tmp_path):
    _record(repo, tmp_path)
    assert se.evaluate_evidence(repo, BRANCH, [EVIDENCE, BIND_FILE]) == f"source not in diff: {SOURCE}"


def test_evaluate_stale_source_blob(repo, tmp_path):
    _record(repo, tmp_path)
    (repo / SOURCE).write_text("<?php // edited after the recording\n")
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == f"stale: {SOURCE}"
    (repo / SOURCE).unlink()
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == f"stale: {SOURCE}"


@pytest.mark.parametrize("bad", ["../x.php", "/etc/passwd", "ibl5/../../x.php"])
def test_evaluate_rejects_path_escape(repo, tmp_path, bad):
    _record(repo, tmp_path)
    _rewrite(repo, sources={bad: "0" * 40})
    assert se.evaluate_evidence(repo, BRANCH, [*CHANGED, bad]) == "bad path"
    _record(repo, tmp_path)
    _rewrite(repo, bind_tests=[f"{bad}::testX"])
    assert se.evaluate_evidence(repo, BRANCH, [*CHANGED, bad]) == "bad path"


def test_evaluate_bind_test_missing(repo, tmp_path):
    _record(repo, tmp_path)
    (repo / BIND_FILE).unlink()
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == f"bind test missing: {BIND_TEST}"


@pytest.mark.parametrize("entry", [f"{BIND_FILE}::testNoSuchMethod", BIND_FILE,
                                   f"{SOURCE}::testPairQueryBindsEveryUserValue"])
def test_evaluate_bind_test_method_or_location_wrong(repo, tmp_path, entry):
    _record(repo, tmp_path)
    _rewrite(repo, bind_tests=[entry])
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == f"bind test missing: {entry}"


def test_evaluate_crash_fails_closed(repo, tmp_path, monkeypatch):
    _record(repo, tmp_path)

    def boom(*args, **kwargs):
        raise OSError("git exploded")

    monkeypatch.setattr(se.subprocess, "run", boom)
    assert se.evaluate_evidence(repo, BRANCH, CHANGED) == "error"


# ---- arm_input_value ----

def test_arm_input_value_not_applicable_without_token(tmp_path):
    missing_root = tmp_path / "no-such-repo"
    assert se.arm_input_value(("gate-weakening",), missing_root, BRANCH, []) == "not-applicable"
    assert se.arm_input_value((), missing_root, BRANCH, []) == "not-applicable"
    assert not missing_root.exists()


def test_arm_input_value_evaluates_with_token(repo, tmp_path):
    assert se.arm_input_value(("sql-equivalent",), repo, BRANCH, CHANGED) == "absent"
    _record(repo, tmp_path)
    assert se.arm_input_value(("security-surface", "sql-equivalent"), repo, BRANCH, CHANGED) == "ok"


# ---- CLI and wiring ----

def test_main_rejects_unknown_flag():
    with pytest.raises(SystemExit) as exc:
        se.main(["check", "--branch", "x", "--bogus"])
    assert exc.value.code == 2


def _repo_root() -> Path:
    out = subprocess.run(["git", "rev-parse", "--show-toplevel"], cwd=Path(__file__).parent,
                         check=True, capture_output=True, text=True).stdout.strip()
    return Path(out)


def test_evidence_dir_is_not_gitignored():
    proc = subprocess.run(["git", "check-ignore", "-q", "ibl5/tests/sql-equivalence/probe.json"],
                          cwd=_repo_root())
    assert proc.returncode == 1


def test_runner_wires_sql_equivalence_evidence():
    runner = Path(__file__).resolve().parents[1] / "runner.py"
    tree = ast.parse(runner.read_text())
    calls = [n for n in ast.walk(tree)
             if isinstance(n, ast.Call) and getattr(n.func, "id", None) == "ArmInputs"]
    assert calls, "runner.py builds no ArmInputs"
    for call in calls:
        kw = {k.arg: k.value for k in call.keywords}
        assert "sql_equivalence_evidence" in kw, f"ArmInputs at line {call.lineno} drops the evidence"
        value = kw["sql_equivalence_evidence"]
        assert isinstance(value, ast.Call)
        assert ast.unparse(value.func) == "sql_equivalence.arm_input_value"
