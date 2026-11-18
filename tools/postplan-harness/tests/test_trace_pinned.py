"""The trace-pinned evidence checker.

Each test builds a throwaway git repo whose base commit holds a raw JSB capture and whose
head commit adds an engine change with a `TestTracePinned*` test. A fake `run_go` stands in
for the Go toolchain, so the suite needs no Go install. The `go test -json` parser reads
streams captured once from a real run (tests/fixtures/trace_pinned/)."""
import json
import os
import subprocess
from pathlib import Path

import pytest

from harness import trace_pinned as tp

REPO = Path(__file__).resolve().parents[3]
FIXTURES = Path(__file__).resolve().parent / "fixtures" / "trace_pinned"
BRANCH = "port-foul-roll"
TEST = "TestTracePinnedFoulRoll"
PACKAGE = "./internal/sim"
CAPTURE_NAME = "foul-roll.jsonl"
CAPTURE = f"engine/testdata/jsb-captures/{CAPTURE_NAME}"
TEST_FILE = "engine/internal/sim/foul_roll_trace_test.go"
ENGINE_FILE = "engine/internal/sim/foul.go"
GOLDEN = "engine/internal/sim/testdata/golden.json"
EVIDENCE = f"engine/trace-evidence/{BRANCH}.json"
CHANGED = [ENGINE_FILE, TEST_FILE, GOLDEN, EVIDENCE]

HEADER = {"format": "jsb-trace/1", "source": "frida", "jsb_build": "jsb-5.60",
          "function": "foul_roll", "address": "0x4a1f20"}
RECORDS = [{"in": {"roll": 17, "fouls": 3}, "out": {"foul": True, "site": "shooting"}},
           {"in": {"roll": 82, "fouls": 1}, "out": {"foul": False, "site": "none"}}]
TEST_SOURCE = f"""package sim

import (
\t"testing"

\t"github.com/a-jay85/IBL5/engine/internal/jsbtrace"
)

func {TEST}(t *testing.T) {{
\tc := jsbtrace.MustLoad(t, "{CAPTURE_NAME}")
\t_ = c
}}
"""
GIT_ENV = {**os.environ, "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@example.com",
           "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@example.com"}


def _capture_text(header=None, records=None) -> str:
    lines = [json.dumps(header or HEADER)] + [json.dumps(r) for r in (records or RECORDS)]
    return "\n".join(lines) + "\n"


def _git(root, *args) -> str:
    return subprocess.run(["git", *args], cwd=root, check=True, capture_output=True,
                          text=True, env=GIT_ENV).stdout.strip()


def _write(root, rel, body):
    path = Path(root) / rel
    path.parent.mkdir(parents=True, exist_ok=True)
    path.write_text(body)


def _commit(root, message) -> str:
    _git(root, "add", "-A")
    _git(root, "commit", "-q", "-m", message)
    return _git(root, "rev-parse", "HEAD")


def _make_repo(tmp_path, capture_text=None):
    root = tmp_path / "repo"
    root.mkdir()
    _git(root, "init", "-q")
    _write(root, "engine/go.mod", "module github.com/a-jay85/IBL5/engine\n\ngo 1.22\n")
    _write(root, CAPTURE, capture_text or _capture_text())
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 0 }\n")
    base = _commit(root, "base")
    _git(root, "update-ref", "refs/remotes/origin/master", base)
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 1 }\n")
    _write(root, TEST_FILE, TEST_SOURCE)
    _write(root, GOLDEN, "{}\n")
    _commit(root, "head")
    return root, base


def _fake_go(normal="pass", perturbed="fail", calls=None, capture_name=CAPTURE_NAME):
    def run_go(root, package, test, env=None):
        if calls is not None:
            calls.append(env)
        if env:
            moved = Path(env["JSB_TRACE_CAPTURE_DIR"]) / capture_name
            assert moved.read_text() != (Path(root) / tp.CAPTURE_DIR / capture_name).read_text()
            return perturbed
        return normal
    return run_go


def _entry(root, **overrides):
    entry = {"package": PACKAGE, "test": TEST, "file": TEST_FILE,
             "test_blob": _git(root, "hash-object", TEST_FILE), "capture": CAPTURE,
             "capture_sha256": tp._sha256((Path(root) / CAPTURE).read_bytes())}
    entry.update(overrides)
    return entry


def _write_evidence(root, **overrides):
    data = {"schema": 1, "token": "trace-pinned", "branch": BRANCH, "tests": [_entry(root, **overrides)]}
    _write(root, EVIDENCE, json.dumps(data))


def _verdict(root, base, changed=CHANGED, run_go=None):
    return tp.evaluate_evidence(root, BRANCH, base, changed, run_go=run_go or _fake_go())


@pytest.fixture
def repo(tmp_path):
    root, base = _make_repo(tmp_path)
    _write_evidence(root)
    return root, base


def _stream(name) -> str:
    return (FIXTURES / f"go-test-{name}.jsonl").read_text()


# ---- happy path ----

def test_full_evidence_is_ok(repo):
    root, base = repo
    calls = []
    assert _verdict(root, base, run_go=_fake_go(calls=calls)) == "ok"
    assert calls[0] is None and "JSB_TRACE_CAPTURE_DIR" in calls[1]


def test_arm_input_value_ok_on_full_evidence(repo, monkeypatch):
    root, _ = repo
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(("trace-pinned",), root, BRANCH, CHANGED) == "ok"


def test_arm_input_value_not_applicable_without_token(repo, monkeypatch):
    root, _ = repo
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.arm_input_value(("golden-new",), root, BRANCH, CHANGED) == "not-applicable"
    assert tp.arm_input_value((), root, BRANCH, CHANGED) == "not-applicable"


def test_arm_input_value_never_raises(repo, monkeypatch):
    root, _ = repo

    def boom(*a, **k):
        raise RuntimeError("x")
    monkeypatch.setattr(tp, "resolve_base", boom)
    assert tp.arm_input_value(("trace-pinned",), root, BRANCH, CHANGED) == "error: RuntimeError"


# ---- evidence file ----

def test_missing_evidence_holds(tmp_path):
    root, base = _make_repo(tmp_path)
    assert _verdict(root, base) == "evidence-missing"


def test_malformed_evidence_holds(repo):
    root, base = repo
    _write(root, EVIDENCE, json.dumps({"schema": 1, "token": "trace-pinned", "branch": "other",
                                       "tests": [_entry(root)]}))
    assert _verdict(root, base) == "evidence-malformed"
    _write(root, EVIDENCE, "{not json")
    assert _verdict(root, base) == "evidence-malformed"


# ---- (a) path allowlist ----

def test_allowlist_rejects_non_engine_file(repo):
    root, base = repo
    path = "ibl5/classes/Trade/TradeService.php"
    assert _verdict(root, base, changed=[*CHANGED, path]) == f"non-engine-path: {path}"


@pytest.mark.parametrize("path", ["engine/go.mod", "engine/go.sum", "engine/Makefile",
                                  "engine/docs/j27-foul-volume-port.md"])
def test_allowlist_rejects_engine_non_go_files(repo, path):
    root, base = repo
    assert _verdict(root, base, changed=[*CHANGED, path]) == f"non-engine-path: {path}"


def test_allowlist_rejects_capture_in_diff(repo):
    root, base = repo
    assert _verdict(root, base, changed=[*CHANGED, CAPTURE]) == f"capture-in-diff: {CAPTURE}"
    readme = "engine/testdata/jsb-captures/README.md"
    assert tp.allowlist_verdict([readme], BRANCH) == f"capture-in-diff: {readme}"


def test_allowlist_accepts_engine_go_golden_and_evidence():
    files = [ENGINE_FILE, TEST_FILE, "engine/cmd/x/main.go", *tp.GOLDEN_FILES, EVIDENCE]
    assert tp.allowlist_verdict(files, BRANCH) == "ok"
    assert tp.allowlist_verdict(["engine/trace-evidence/other.json"], BRANCH) \
        == "non-engine-path: engine/trace-evidence/other.json"


def test_empty_range_holds(repo):
    root, base = repo
    assert _verdict(root, base, changed=[]) == "empty-range"


# ---- (b) the test ----

def test_test_name_without_prefix_holds(repo):
    root, base = repo
    _write_evidence(root, test="TestFoulRoll")
    assert _verdict(root, base) == "not-trace-pinned-name"


def test_test_file_not_in_diff_holds(repo):
    root, base = repo
    changed = [f for f in CHANGED if f != TEST_FILE]
    assert _verdict(root, base, changed=changed) == "test-not-in-diff"


def test_test_without_mustload_holds(repo):
    root, base = repo
    _write(root, TEST_FILE, TEST_SOURCE.replace("jsbtrace.MustLoad(", "jsbtrace.Load("))
    _write_evidence(root)
    assert _verdict(root, base) == "test-shape"


def test_stale_test_blob_holds(repo):
    root, base = repo
    _write(root, TEST_FILE, TEST_SOURCE + "\n// edited after record\n")
    assert _verdict(root, base) == "stale-test-blob"


# ---- capture provenance ----

def test_capture_missing_on_base_holds(repo):
    root, base = repo
    other = "engine/testdata/jsb-captures/new-routine.jsonl"
    _write(root, other, _capture_text())
    _write_evidence(root, capture=other, capture_sha256=tp._sha256((Path(root) / other).read_bytes()))
    assert _verdict(root, base) == "capture-not-on-base"


def test_capture_hash_mismatch_holds(repo):
    root, base = repo
    _write_evidence(root, capture_sha256="0" * 64)
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_worktree_capture_edit_holds_as_hash_mismatch(repo):
    root, base = repo
    _write(root, CAPTURE, _capture_text(records=RECORDS[:1]))
    assert _verdict(root, base) == "capture-hash-mismatch"


def test_unreadable_capture_holds(tmp_path):
    root, base = _make_repo(tmp_path, capture_text=_capture_text(header={**HEADER, "format": "jsb-trace/2"}))
    _write_evidence(root)
    assert _verdict(root, base) == "capture-unreadable"


def test_base_unresolved_holds(repo):
    root, _ = repo
    assert _verdict(root, "") == "base-unresolved"


# ---- (c) go test -json ----

def test_parse_go_json_pass():
    assert tp.parse_go_test_json(_stream("pass"), TEST) == "pass"


def test_parse_go_json_skip_counts_as_not_run():
    assert tp.parse_go_test_json(_stream("skip"), TEST) == "skip"


def test_parse_go_json_fail():
    assert tp.parse_go_test_json(_stream("fail"), TEST) == "fail"


def test_parse_go_json_absent(repo):
    assert tp.parse_go_test_json(_stream("pass"), "TestTracePinnedOther") == "absent"
    assert tp.parse_go_test_json("", TEST) == "absent"
    root, base = repo
    absent = _fake_go(normal=tp.parse_go_test_json("", TEST), perturbed="fail")
    assert _verdict(root, base, run_go=absent) == "test-absent"


def _stream_go(normal, perturbed="fail"):
    return _fake_go(normal=tp.parse_go_test_json(_stream(normal), TEST), perturbed=perturbed)


def test_skipped_trace_test_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_stream_go("skip")) == "test-skip"


def test_failed_trace_test_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_stream_go("fail")) == "test-fail"


def test_go_unavailable_holds(repo, monkeypatch):
    root, base = repo
    real_run = subprocess.run

    def fake_run(args, *a, **k):
        if args and args[0] == "go":
            return subprocess.CompletedProcess(args, 0, stdout=_stream("pass"), stderr="")
        return real_run(args, *a, **k)
    monkeypatch.setattr(tp.shutil, "which", lambda name: None)
    monkeypatch.setattr(tp.subprocess, "run", fake_run)
    assert tp.evaluate_evidence(root, BRANCH, base, CHANGED) == "test-go-unavailable"


# ---- perturbation run ----

def test_perturbed_run_still_passing_holds_as_circular(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(perturbed="pass")) \
        == f"circular: {TEST} passes with perturbed JSB outputs"


def test_perturbed_run_other_verdict_holds(repo):
    root, base = repo
    assert _verdict(root, base, run_go=_fake_go(perturbed="skip")) == "perturbation-skip"


def test_perturb_shifts_every_leaf():
    value, n = tp.perturb({"a": 1, "b": [True, "x", 2.5], "c": None, "d": {"e": False}})
    assert value == {"a": 2, "b": [False, "x~perturbed", 3.5], "c": None, "d": {"e": True}}
    assert n == 5


def test_perturb_without_leaf_holds(tmp_path):
    records = [{"in": {"roll": 1}, "out": {}}, {"in": {"roll": 2}, "out": {"x": None}}]
    root, base = _make_repo(tmp_path, capture_text=_capture_text(records=records))
    _write_evidence(root)
    assert _verdict(root, base) == "capture-out-unperturbable"


# ---- base resolution ----

def test_stacked_base_resolves_declared_base(tmp_path):
    root, master_base = _make_repo(tmp_path)
    _git(root, "reset", "-q", "--hard", master_base)
    stacked = "engine/testdata/jsb-captures/stacked.jsonl"
    _write(root, stacked, _capture_text())
    _write(root, "ibl5/classes/Parent/Thing.php", "<?php\n")
    parent = _commit(root, "parent PR")
    _git(root, "update-ref", "refs/remotes/origin/parent-branch", parent)
    _write(root, ENGINE_FILE, "package sim\n\nfunc foulRoll() int { return 2 }\n")
    _write(root, TEST_FILE, TEST_SOURCE.replace(CAPTURE_NAME, "stacked.jsonl"))
    _commit(root, "child")
    _git(root, "config", f"branch.{BRANCH}.iblBase", "parent-branch")
    _write_evidence(root, capture=stacked, capture_sha256=tp._sha256((Path(root) / stacked).read_bytes()))

    base = tp.resolve_base(root, BRANCH)
    assert base == parent
    changed = tp.changed_files_since(root, base)
    assert "ibl5/classes/Parent/Thing.php" not in changed
    assert tp.evaluate_evidence(root, BRANCH, base, changed,
                                run_go=_fake_go(capture_name="stacked.jsonl")) == "ok"


def test_resolve_base_defaults_to_origin_master(repo):
    root, base = repo
    assert tp.resolve_base(root, BRANCH) == base


# ---- CLI and evidence path ----

def test_cli_rejects_unknown_flag(repo, monkeypatch):
    root, _ = repo
    monkeypatch.chdir(root)
    for argv in (["record", "--bogus"], ["check"], ["check", "--branch", BRANCH, "--bogus"]):
        with pytest.raises(SystemExit) as exc:
            tp.main(argv)
        assert exc.value.code == 2, argv


def test_evidence_path_not_gitignored(tmp_path, monkeypatch):
    root, _ = _make_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go())
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME]) == 0
    written = root / "engine" / "trace-evidence" / f"{BRANCH}.json"
    assert written.is_file()
    assert tp.evidence_path(root, BRANCH) == written
    data = json.loads(written.read_text())
    assert data["tests"][0]["file"] == TEST_FILE
    ignored = subprocess.run(["git", "check-ignore", "-q", f"engine/trace-evidence/{BRANCH}.json"],
                             cwd=REPO)
    assert ignored.returncode == 1


def test_record_refuses_on_failing_check(tmp_path, monkeypatch):
    root, _ = _make_repo(tmp_path)
    monkeypatch.setattr(tp, "run_go_test", _fake_go(perturbed="pass"))
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], [CAPTURE_NAME]) == 1
    assert not (root / EVIDENCE).exists()


def test_record_requires_equal_test_and_capture_counts(tmp_path):
    root, _ = _make_repo(tmp_path)
    assert tp.record(root, BRANCH, [f"{PACKAGE}:{TEST}"], []) == 2
