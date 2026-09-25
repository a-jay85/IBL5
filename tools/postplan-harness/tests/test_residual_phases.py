"""Phase 4b: _inject_residual_phases helper in runner.py."""
from __future__ import annotations

import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from runner import _inject_residual_phases
from harness.state import PhaseInfo, PlanInfo


def test_inject_adds_block_and_logs_when_phase_missing():
    """Phase 3 cites harness/nope.py, not in files → block added and logged.

    Mutation caught: forgetting to assign back to copy["summary_md"] leaves body unchanged.
    """
    ph = PhaseInfo(number=3, heading="Phase 3: Do stuff", evidence_paths=["harness/nope.py"])
    plan = PlanInfo(found=True, phases=[ph])
    copy = {"summary_md": "## Summary\n- x"}
    logs: list[str] = []
    items = _inject_residual_phases(copy, plan, ["harness/other.py"], logs.append)
    assert len(items) == 1
    assert "## Residual Phases" in copy["summary_md"]
    assert "3 — " in copy["summary_md"]
    assert len(logs) == 1
    assert logs[0].startswith("phase2 residual-phase: MISSING-PHASE: 3 —")


def test_inject_is_noop_for_plan_blind_and_shipped_runs():
    """found=False and all phases shipped both return [] and leave body unchanged.

    Mutation caught: calling check() instead of phase_omission_items() adds MISSING: items.
    """
    body = "## Summary\n- x"

    # plan-blind
    plan_blind = PlanInfo(found=False)
    copy1 = {"summary_md": body}
    logs1: list[str] = []
    items1 = _inject_residual_phases(copy1, plan_blind, [], logs1.append)
    assert items1 == []
    assert copy1["summary_md"] == body
    assert logs1 == []

    # fully shipped
    ph = PhaseInfo(number=2, heading="Phase 2: B", evidence_paths=["harness/b.py"])
    plan_shipped = PlanInfo(found=True, phases=[ph])
    copy2 = {"summary_md": body}
    logs2: list[str] = []
    items2 = _inject_residual_phases(copy2, plan_shipped, ["harness/b.py"], logs2.append)
    assert items2 == []
    assert copy2["summary_md"] == body
    assert logs2 == []


def test_inject_is_idempotent_across_reruns():
    """Two calls with same inputs give one block; third call with phase shipped removes it.

    Mutation caught: append-only upsert duplicates the block on the second call.
    """
    from harness.classify import RESIDUAL_PHASES_BEGIN

    ph = PhaseInfo(number=2, heading="Phase 2: B", evidence_paths=["harness/b.py"])
    plan = PlanInfo(found=True, phases=[ph])
    orig_body = "## Summary\n- x"
    copy = {"summary_md": orig_body}

    _inject_residual_phases(copy, plan, [], [].append)
    body_after_1 = copy["summary_md"]
    assert body_after_1.count(RESIDUAL_PHASES_BEGIN) == 1

    _inject_residual_phases(copy, plan, [], [].append)
    assert copy["summary_md"] == body_after_1  # idempotent

    # phase now shipped
    _inject_residual_phases(copy, plan, ["harness/b.py"], [].append)
    assert copy["summary_md"].strip() == orig_body.strip()
