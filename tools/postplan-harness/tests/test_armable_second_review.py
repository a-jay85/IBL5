"""Second-reviewer clears on the four clearable arming conditions, and the floor
that keeps every other hold with the owner."""
import pytest

from harness.armable import evaluate
from harness.state import Classification

from tests.test_armable import inputs


def _holds(decision):
    return {c.number for c in decision.holds}


@pytest.mark.parametrize("number,kwargs", [
    (7, {"plan_auto_merge_false": True, "plan_auto_merge_value": "false"}),
    (10, {"pr_labels": ["pipeline-authored"]}),
    (12, {"fidelity_verdict": "NOT READY"}),
    (17, {"gate_backtest_status": "HELD"}),
])
def test_characterize_clearable_conditions_hold(number, kwargs):
    decision = evaluate(inputs(**kwargs))
    assert not decision.armed
    assert _holds(decision) == {number}
