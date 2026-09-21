"""Hold (16) capture: `run_meta_checks_local` records failing check names and output.

The failing names used to be joined into the flag file and dropped. The Phase 5.5
work list needs them, so the function now extends an injected out-list.
"""
from __future__ import annotations

import os
import subprocess
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.gitad import ReplayGit

FLAG = "/tmp/ibl5-meta-checks-prepush-demo.failed"


def _run(monkeypatch, tmp_path, rc, stdout, failures_out=None):
    def fake_run(argv, **kwargs):
        return subprocess.CompletedProcess(argv, rc, stdout=stdout, stderr="")

    monkeypatch.setattr(runner.subprocess, "run", fake_run)
    git = ReplayGit({"slug": "demo"})
    kwargs = {} if failures_out is None else {"failures_out": failures_out}
    try:
        return runner.run_meta_checks_local(
            git, str(tmp_path), "origin/master", lambda m: None, **kwargs)
    finally:
        try:
            os.unlink(FLAG)
        except FileNotFoundError:
            pass


def test_failing_run_records_names_and_output(monkeypatch, tmp_path):
    stdout = ("PASS  check-a\n"
              "FAIL  check-docs\n"
              "META-CHECK-FAILED: check-docs\n"
              "META-CHECK-FAILED: check-prose\n")
    failures: list[dict] = []
    ok = _run(monkeypatch, tmp_path, 1, stdout, failures)
    assert ok is False
    assert [f["name"] for f in failures] == ["check-docs", "check-prose"]
    assert all(f["output"] == stdout.strip() for f in failures)


def test_passing_run_leaves_failures_empty(monkeypatch, tmp_path):
    failures: list[dict] = []
    assert _run(monkeypatch, tmp_path, 0, "PASS  check-a\n", failures) is True
    assert failures == []


def test_failure_without_marker_lines_records_unknown(monkeypatch, tmp_path):
    failures: list[dict] = []
    assert _run(monkeypatch, tmp_path, 1, "FAIL  something\n", failures) is False
    assert [f["name"] for f in failures] == ["unknown"]


def test_output_excerpt_is_capped(monkeypatch, tmp_path):
    stdout = "x" * 20_000 + "\nMETA-CHECK-FAILED: check-docs\n"
    failures: list[dict] = []
    assert _run(monkeypatch, tmp_path, 1, stdout, failures) is False
    assert len(failures) == 1
    assert len(failures[0]["output"]) <= runner.META_CHECK_OUTPUT_CAP
    assert failures[0]["output"].endswith("META-CHECK-FAILED: check-docs")


def test_caller_without_out_list_is_unchanged(monkeypatch, tmp_path):
    assert _run(monkeypatch, tmp_path, 1, "META-CHECK-FAILED: check-docs\n") is False


def test_run_result_carries_an_empty_failure_list_by_default():
    from harness.state import RunResult

    assert RunResult(terminal="ok").meta_check_failures == []
