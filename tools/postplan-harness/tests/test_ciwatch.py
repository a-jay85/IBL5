import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import ciwatch


class P:
    """Minimal stand-in for subprocess.CompletedProcess."""
    def __init__(self, returncode=0, stdout="", stderr=""):
        self.returncode = returncode
        self.stdout = stdout
        self.stderr = stderr


def test_probe_short_circuits_on_failed_bucket(monkeypatch):
    """Phase 4 early return fires on a failed-bucket probe; --watch is never called."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # --json probe: one check already in "fail" bucket, one pending
        return P(
            0,
            '[{"name":"build","state":"FAILURE","bucket":"fail"},'
            '{"name":"lint","state":"PENDING","bucket":"pending"}]',
            "",
        )

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    assert out.exit_code == 8
    assert out.failed == ["build"]
    assert "build" in out.evidence
    # The whole point of Phase 4: --watch must never have been invoked
    assert not any("--watch" in c for c in calls)


def test_loop_exhausted_message_carries_rc_and_stderr(monkeypatch):
    """After settle_tries exhausted, the message includes the exit code and stderr."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(1, "", "HTTP 401: Bad credentials")
        # probe: no failures yet
        return P(0, "[]", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1, settle_tries=2)

    assert out.exit_code == -1
    assert "never settled" in out.evidence
    assert "exit 1" in out.evidence
    assert "Bad credentials" in out.evidence


def test_probe_malformed_json_does_not_short_circuit(monkeypatch):
    """A parse error in the probe is treated as [] — falls through to --watch, does not raise."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: exit 0 but garbage JSON
        return P(0, "not json{", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    out = ciwatch.watch_live(".", 1)

    # Parse error must not raise and must fall through to --watch
    assert any("--watch" in c for c in calls)
    assert out.exit_code == 0
    assert out.failed == []


def test_probe_no_checks_reported_falls_through(monkeypatch):
    """gh exit 1 (no checks reported yet) from the probe returns [] and watch_live reaches --watch."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: gh exits 1 — "no checks reported" right after pr create
        return P(1, "", "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    assert ciwatch.probe_failed_checks(".", 1) == []

    calls.clear()
    ciwatch.watch_live(".", 1)
    assert any("--watch" in c for c in calls)


def test_probe_never_reports_green(monkeypatch):
    """The probe is a failure-only short-circuit; all-pass buckets fall through to --watch."""
    calls = []

    def fake_run(cmd, **kwargs):
        calls.append(list(cmd))
        if "--watch" in cmd:
            return P(0, "", "")
        # probe: all checks in the "pass" bucket
        return P(0, '[{"name":"build","state":"SUCCESS","bucket":"pass"}]', "")

    monkeypatch.setattr(ciwatch.subprocess, "run", fake_run)
    monkeypatch.setattr(ciwatch.time, "sleep", lambda s: None)

    assert ciwatch.probe_failed_checks(".", 1) == []

    calls.clear()
    ciwatch.watch_live(".", 1)
    # --watch must have been called — the probe must not short-circuit to "green"
    assert any("--watch" in c for c in calls)
