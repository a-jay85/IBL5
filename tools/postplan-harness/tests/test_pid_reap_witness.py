"""Unit tests for the `pid_reap_witness` fixture in conftest.py (backlog#1801)."""
import subprocess

import pytest

import test_scope_conformance


def test_pid_reap_witness_fails_when_pid_never_written(tmp_path, pid_reap_witness):
    seen = []
    with pytest.raises(pytest.fail.Exception, match="never wrote"):
        pid_reap_witness(seen.append, tmp_path / "x.pid", "child")
    assert seen == [1, 2, 4]


def test_pid_reap_witness_fails_when_pid_survives(tmp_path, pid_reap_witness):
    pidfile = tmp_path / "x.pid"
    proc = subprocess.Popen(["sleep", "30"])
    try:
        with pytest.raises(pytest.fail.Exception, match="survived"):
            pid_reap_witness(lambda timeout: pidfile.write_text(str(proc.pid)), pidfile,
                             "child", reap_deadline=0.5)
    finally:
        proc.kill()
        proc.wait()


def test_pid_reap_witness_returns_first_timeout_when_pid_is_gone(tmp_path, pid_reap_witness):
    pidfile = tmp_path / "x.pid"
    proc = subprocess.Popen(["true"])
    proc.wait()
    assert pid_reap_witness(lambda timeout: pidfile.write_text(str(proc.pid)), pidfile,
                            "child") == 1


def _has_real_timeout_group(fn) -> bool:
    return any(m.name == "xdist_group" and m.args == ("real_timeout",)
               for m in getattr(fn, "pytestmark", []))


def test_scope_timeout_test_carries_real_timeout_group():
    assert _has_real_timeout_group(
        test_scope_conformance.test_scope_notes_timeout_fails_closed_and_reaps)


def test_real_timeout_group_check_rejects_a_renamed_group():
    @pytest.mark.xdist_group("real_timeout_mutant")
    def mutant():
        pass

    assert not _has_real_timeout_group(mutant)
