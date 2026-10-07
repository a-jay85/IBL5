"""_inject_residual_phases: exemption notes reach the audit log, never the PR block."""
from __future__ import annotations

import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from runner import _inject_residual_phases
from harness import conformance
from harness.state import PhaseInfo, PlanInfo


@pytest.fixture(autouse=True)
def _tracked(monkeypatch):
    monkeypatch.setattr(conformance, "_tracked_files", lambda *a, **k: ("bin/wt-up",))


def test_uncheckable_phase_logs_exempt_line_and_no_residual_block():
    ph = PhaseInfo(number=1, heading="Phase 1: Elsewhere", evidence_paths=["/proc/self/fd/1"])
    copy = {"summary_md": ""}
    logs: list[str] = []
    items = _inject_residual_phases(copy, PlanInfo(found=True, phases=[ph]),
                                    ["ibl5/docs/API_GUIDE.md"], logs.append)
    assert items == []
    exempt = [ln for ln in logs if ln.startswith("phase2 residual-phase-exempt: UNCHECKABLE-PHASE: 1")]
    assert len(exempt) == 1
    assert not any(ln.startswith("phase2 residual-phase:") for ln in logs)
    assert "## Residual Phases" not in copy["summary_md"]


def test_no_diff_phase_logs_reason():
    ph = PhaseInfo(number=1, heading="Phase 1: Queue", evidence_paths=["bin/wt-up"],
                   no_diff_reason="queues the parked plan after merge")
    logs: list[str] = []
    _inject_residual_phases({"summary_md": ""}, PlanInfo(found=True, phases=[ph]),
                            ["ibl5/docs/API_GUIDE.md"], logs.append)
    assert len(logs) == 1
    assert logs[0].startswith("phase2 residual-phase-exempt: NO-DIFF-PHASE: 1")
    assert "queues the parked plan after merge" in logs[0]


def test_held_phase_still_logs_residual_and_block():
    ph = PhaseInfo(number=1, heading="Phase 1: Edit", evidence_paths=["bin/wt-up"])
    copy = {"summary_md": ""}
    logs: list[str] = []
    _inject_residual_phases(copy, PlanInfo(found=True, phases=[ph]),
                            ["ibl5/docs/API_GUIDE.md"], logs.append)
    assert len([ln for ln in logs if ln.startswith("phase2 residual-phase: MISSING-PHASE: 1")]) == 1
    assert not any("-exempt:" in ln for ln in logs)
    assert "## Residual Phases" in copy["summary_md"]
