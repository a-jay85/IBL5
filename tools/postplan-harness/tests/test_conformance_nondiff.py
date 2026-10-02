"""Characterization of conformance.phase_omission_items.

The tests here pin behaviour that must survive the non-diff-phase exemption work
unchanged. They are green on master.
"""
import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import conformance
from harness.state import PhaseInfo, PlanInfo


def _plan(*phases: PhaseInfo, deferred: list[int] | None = None) -> PlanInfo:
    return PlanInfo(found=True, phases=list(phases),
                    deferred_phase_numbers=deferred or [])


def test_real_repo_path_untouched_holds():
    ph = PhaseInfo(number=1, heading="Phase 1: Mask the log", evidence_paths=["bin/wt-up"])
    items = conformance.phase_omission_items(_plan(ph), ["ibl5/docs/API_GUIDE.md"])
    assert len(items) == 1
    assert items[0].startswith("MISSING-PHASE: 1 — Phase 1: Mask the log")
    assert "phase cites bin/wt-up" in items[0]
    assert "none appeared in the diff" in items[0]


def test_real_repo_path_touched_by_basename_clears():
    ph = PhaseInfo(number=1, heading="Phase 1: Mask the log", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(_plan(ph), ["bin/wt-up"]) == []
    assert conformance.phase_omission_items(_plan(ph), ["ibl5/x/wt-up"]) == []


def test_no_evidence_phase_skipped():
    ph = PhaseInfo(number=1, heading="Phase 1: Nothing cited", evidence_paths=[])
    assert conformance.phase_omission_items(_plan(ph), []) == []


def test_bookkeeping_and_deferred_phases_skipped():
    book = PhaseInfo(number=1, heading="Phase 1: Tidy [phases: S]",
                     evidence_paths=["bin/wt-up"], bookkeeping=True)
    deferred = PhaseInfo(number=4, heading="Phase 4: Later", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(_plan(book, deferred, deferred=[4]), []) == []


def test_hold_line_shape_lists_three_and_more_count():
    paths = ["bin/wt-up", "tools/postplan-harness/harness/conformance.py",
             "ibl5/docs/API_GUIDE.md", "bin/check-plan", "bin/wt-new"]
    five = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths)
    item = conformance.phase_omission_items(_plan(five), [])[0]
    assert ("phase cites bin/wt-up, tools/postplan-harness/harness/conformance.py, "
            "ibl5/docs/API_GUIDE.md (+2 more);") in item
    four = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths[:4])
    assert "(+1 more)" in conformance.phase_omission_items(_plan(four), [])[0]
    three = PhaseInfo(number=1, heading="Phase 1: Many", evidence_paths=paths[:3])
    assert "more" not in conformance.phase_omission_items(_plan(three), [])[0]


def test_plan_not_found_yields_nothing():
    ph = PhaseInfo(number=1, heading="Phase 1: X", evidence_paths=["bin/wt-up"])
    assert conformance.phase_omission_items(PlanInfo(found=False, phases=[ph]), []) == []
    assert conformance.phase_omission_items(PlanInfo(found=True, phases=[]), []) == []
