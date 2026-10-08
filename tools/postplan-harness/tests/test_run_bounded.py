"""Tests for harness.adapters.llm.run_bounded — the typed-timeout wrapper over _run_reaped.

Run: python -m pytest tools/postplan-harness/tests/test_run_bounded.py -q
"""
import os
import sys
import time

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.llm import run_bounded
from harness.state import HarnessError


def _script(tmp_path, body):
    path = tmp_path / "child.sh"
    path.write_text(body)
    return str(path)


def test_run_bounded_timeout_raises_typed_error_and_reaps_group(tmp_path):
    script = _script(tmp_path, f"sleep 300 &\necho $! > {tmp_path}/grandchild.pid\nsleep 300\n")
    start = time.monotonic()
    with pytest.raises(HarnessError) as ei:
        run_bounded(["bash", script], step="probe", timeout=1, cwd=str(tmp_path))
    assert time.monotonic() - start < 15
    assert ei.value.kind == "subprocess-timeout"
    assert "probe" in ei.value.detail
    assert "1s" in ei.value.detail
    assert "child.sh" in ei.value.cmd
    grandchild = int((tmp_path / "grandchild.pid").read_text().strip())
    deadline = time.monotonic() + 5
    while time.monotonic() < deadline:
        try:
            os.kill(grandchild, 0)
        except ProcessLookupError:
            return
        time.sleep(0.1)
    pytest.fail("grandchild survived the process-group reap")


def test_run_bounded_success_returns_completed_process(tmp_path):
    cp = run_bounded(["printf", "ok"], step="probe", timeout=10, cwd=str(tmp_path))
    assert cp.returncode == 0
    assert cp.stdout == "ok"


def test_run_bounded_nonzero_rc_is_not_an_error(tmp_path):
    cp = run_bounded(["bash", "-c", "exit 2"], step="probe", timeout=10, cwd=str(tmp_path))
    assert cp.returncode == 2
