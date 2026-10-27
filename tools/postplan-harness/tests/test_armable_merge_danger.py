"""Phase 6.5 condition (18): the Merge danger door. Only a well-formed two-way line arms.

The shell twin is `pr_merge_danger_hold` in `bin/lib/pr-armable.sh`, exercised by
`bin/test-postplan-arm-conditions` (run_cond18)."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import ArmInputs, evaluate
from harness.state import Classification

from tests.test_armable import BODY_CLEARED, _CHARACTERIZATION_FIXTURES, inputs


def _cond(decision, number):
    return next(c for c in decision.conditions if c.number == number)


def test_two_way_state_does_not_block():
    d = evaluate(inputs(merge_danger_state="TWO-WAY"))
    assert d.armed
    assert not _cond(d, 18).blocked


def test_one_way_state_blocks_condition_18():
    d = evaluate(inputs(merge_danger_state="ONE-WAY"))
    assert not d.armed
    assert {c.number for c in d.holds} == {18}
    assert _cond(d, 18).reason == "state=ONE-WAY"


@pytest.mark.parametrize("state", ["MISSING", "MALFORMED", "UNREADABLE", "default"])
def test_missing_malformed_unreadable_block(state):
    if state == "default":
        inp = ArmInputs(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                        classification=Classification(), findings=[], unresolved_conformance=[], exec_mutations_holding=(), exec_mutations_tree_suspect=False,
                        phase5_status="pass", plan_auto_merge_false=False, headless=True,
                        dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                        unresolved_findings=[], conflict_resolved=False)   # merge_danger_state omitted
        expected = "MISSING"
    else:
        inp = inputs(merge_danger_state=state)
        expected = state
    d = evaluate(inp)
    assert not d.armed
    assert 18 in {c.number for c in d.holds}
    assert _cond(d, 18).reason == f"state={expected}"


_BASES = [("armed-base", {})] + [(f[0], f[1]) for f in _CHARACTERIZATION_FIXTURES]


@pytest.mark.parametrize("label,kwargs", _BASES, ids=[b[0] for b in _BASES])
def test_conditions_1_to_17_unchanged_by_new_field(label, kwargs):
    two = evaluate(inputs(**kwargs, merge_danger_state="TWO-WAY"))
    one = evaluate(inputs(**kwargs, merge_danger_state="ONE-WAY"))
    early_two = [(c.number, c.blocked, c.warning, c.reason) for c in two.conditions if c.number != 18]
    early_one = [(c.number, c.blocked, c.warning, c.reason) for c in one.conditions if c.number != 18]
    assert early_two == early_one
    assert [c.number for c in two.conditions if c.number == 18] == [18]
    assert not _cond(two, 18).blocked and _cond(one, 18).blocked
    # the new entry can only add a hold, never clear one
    assert {c.number for c in two.holds} <= {c.number for c in one.holds}
    assert one.armed is False
