"""Phase 6.5 condition (21): the Phase 5.5 reviewer's typed holds. Add-only.

A reviewer hold appends a blocked condition 21. It never clears (3) or (18), the ADR-0231
second reviewer cannot clear it, and it stays out of the fix round and hold-repeat sets."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness import armable, fix_round, holdrepeat
from harness.armable import evaluate
from tests.test_armable import inputs

REVIEWER_HOLD = ["merge-danger: door=one-way: x"]


def _cond(decision, number):
    return next(c for c in decision.conditions if c.number == number)


def test_reviewer_hold_blocks_condition_21():
    d = evaluate(inputs(reviewer_arming_holds=["planned-landed: file:a.py"]))
    c21 = _cond(d, 21)
    assert not d.armed
    assert c21.blocked
    assert c21.name == "reviewer-arming-hold"
    assert c21.reason == "planned-landed: file:a.py"


def test_empty_reviewer_holds_leave_condition_21_clear():
    d = evaluate(inputs())
    c21 = _cond(d, 21)
    assert d.armed
    assert not c21.blocked
    assert c21.reason == ""


@pytest.mark.parametrize("base", [
    {},
    {"unresolved_conformance": ["MISSING: a.py"]},
    {"merge_danger_state": "ONE-WAY"},
])
def test_reviewer_holds_change_no_other_condition(base):
    def triples(d):
        return [(c.number, c.blocked, c.reason) for c in d.conditions if c.number != 21]
    without = evaluate(inputs(**base))
    with_hold = evaluate(inputs(reviewer_arming_holds=REVIEWER_HOLD, **base))
    assert triples(without) == triples(with_hold)


def test_python_condition_3_hold_survives_empty_reviewer_holds():
    d = evaluate(inputs(unresolved_conformance=["MISSING: a.py"], reviewer_arming_holds=[]))
    assert _cond(d, 3).blocked


def test_second_reviewer_cannot_clear_condition_21():
    inp = inputs(reviewer_arming_holds=["planned-landed: file:a.py"],
                 second_review_verdict="CLEAN", second_review_tree="t1", current_tree="t1",
                 second_review_clears={21: "looked fine"})
    d = evaluate(inp)
    assert armable.second_review_candidates(inp, d.conditions) == {}
    assert _cond(d, 21).blocked
    assert not d.second_review_cleared

    inp7 = inputs(reviewer_arming_holds=["planned-landed: file:a.py"],
                  plan_auto_merge_false=True, plan_auto_merge_value="false",
                  plan_hold_triggers=["security-surface"],
                  second_review_verdict="CLEAN", second_review_tree="t1", current_tree="t1",
                  second_review_clears={7: "ok", 21: "looked fine"})
    d7 = evaluate(inp7)
    assert _cond(d7, 7).blocked
    assert armable.second_review_candidates(inp7, d7.conditions) == {}
    assert _cond(d7, 21).blocked
    assert not d7.second_review_cleared


def test_condition_21_outside_fix_round_and_repeat_sets():
    assert 21 not in fix_round.TARGET_CONDITIONS
    assert 21 not in fix_round.REVIEW_CONDITIONS
    assert 21 not in holdrepeat.STRUCTURAL_CONDITIONS
    assert 21 not in holdrepeat.REBASE_ONLY_CONDITIONS
    assert 21 not in holdrepeat.HUMAN_ONLY_CONDITIONS
    d = evaluate(inputs(reviewer_arming_holds=REVIEWER_HOLD))
    assert [c.number for c in d.holds] == [21]
    assert fix_round.targets(d) == []


def test_observe_does_not_record_reviewer_only_hold(tmp_path):
    d = evaluate(inputs(reviewer_arming_holds=REVIEWER_HOLD))
    obs = holdrepeat.observe(tmp_path, "slug", armed=False, conditions=d.conditions,
                             fingerprint="fp", pr=1, now="2026-11-10T00:00:00Z")
    assert obs.action == "cleared"
    assert holdrepeat.load_record(tmp_path, "slug") is None
