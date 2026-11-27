"""main() claims $POSTPLAN_LOG_PATH so no child can write the live run log (backlog 1591)."""
import os
import signal
import subprocess
import sys

HARNESS_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
sys.path.insert(0, HARNESS_DIR)

import runner


def _preamble() -> str:
    return (
        "import os, signal, subprocess, sys\n"
        f"sys.path.insert(0, {HARNESS_DIR!r})\n"
        "import runner\n"
    )


def _run_driver(code: str, live) -> subprocess.CompletedProcess:
    return subprocess.run([sys.executable, "-c", code],
                          env={**os.environ, "POSTPLAN_LOG_PATH": str(live)}, timeout=60)


def test_claim_log_path_removes_variable_from_environ(monkeypatch, tmp_path):
    monkeypatch.setenv("POSTPLAN_LOG_PATH", str(tmp_path / "live.log"))
    runner._claim_log_path()
    assert "POSTPLAN_LOG_PATH" not in os.environ
    assert runner._run_log_path() == str(tmp_path / "live.log")


def test_claimed_runner_child_cannot_write_live_log(tmp_path):
    live = tmp_path / "live.log"
    live.write_text("")
    child = _preamble() + (
        "runner._install_sigterm_handler()\n"
        "os.kill(os.getpid(), signal.SIGTERM)\n"
    )
    driver = _preamble() + (
        "runner._claim_log_path()\n"
        f"p = subprocess.run([sys.executable, '-c', {child!r}], timeout=30)\n"
        "sys.exit(0 if p.returncode == 128 + signal.SIGTERM else 1)\n"
    )
    p = _run_driver(driver, live)
    assert p.returncode == 0
    assert "RESULT: post-plan KILLED" not in live.read_text()


def test_sigterm_after_claim_still_writes_killed_line(tmp_path):
    live = tmp_path / "live.log"
    driver = _preamble() + (
        "runner._claim_log_path()\n"
        "runner._active_audit = ['[t] phase4: review launched']\n"
        "runner._install_sigterm_handler()\n"
        "os.kill(os.getpid(), signal.SIGTERM)\n"
    )
    p = _run_driver(driver, live)
    assert p.returncode == 128 + signal.SIGTERM
    results = [l for l in live.read_text().splitlines() if l.startswith("RESULT:")]
    assert results == ["RESULT: post-plan KILLED (signal 15) at phase4"]


def test_nested_pytest_does_not_write_ambient_run_log(tmp_path):
    live = tmp_path / "live.log"
    live.write_text("")
    node = ("tests/test_runner_exit_codes.py::"
            "test_sigterm_handler_reaps_live_bounded_child_before_emergency_abort")
    p = subprocess.run(
        [sys.executable, "-m", "pytest", "-q", "-n", "0", "-p", "no:cacheprovider", node],
        cwd=HARNESS_DIR, env={**os.environ, "POSTPLAN_LOG_PATH": str(live)},
        capture_output=True, text=True, timeout=180)
    assert p.returncode == 0, p.stdout + p.stderr
    assert "RESULT: post-plan KILLED" not in live.read_text()
