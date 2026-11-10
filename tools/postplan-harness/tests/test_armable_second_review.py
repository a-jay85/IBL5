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


TREE = "a" * 40
OTHER_TREE = "b" * 40

_PLAN_HOLD = {"plan_auto_merge_false": True, "plan_auto_merge_value": "false"}

CLEARABLE_CASES = [
    (7, {**_PLAN_HOLD, "plan_hold_triggers": ("gate-weakening",)}),
    (10, {"pr_labels": ["pipeline-authored"],
          "classification": Classification(files=["bin/check-prose"])}),
    (12, {"fidelity_verdict": "NOT READY", "fidelity_gate_edit_only": True}),
    (17, {"gate_backtest_status": "HELD"}),
]


def _seat(numbers, verdict="CLEAN", tree=TREE, **extra):
    """A seat verdict with a CLEARS line for each hold in `numbers`, on the current tree."""
    return dict(current_tree=TREE, second_review_verdict=verdict, second_review_tree=tree,
                second_review_clears={n: "safe because the diff is tooling only" for n in numbers},
                **extra)


def _assert_held_with_clean(numbers, expected_holds=None, **kwargs):
    """A CLEAN verdict on the current tree with a CLEARS line for each hold must not arm."""
    decision = evaluate(inputs(**kwargs, **_seat(numbers)))
    assert not decision.armed
    assert _holds(decision) == set(expected_holds or numbers)
    assert decision.second_review_cleared == []


@pytest.mark.parametrize("number,kwargs", CLEARABLE_CASES)
def test_clearable_hold_clears_on_clean(number, kwargs):
    decision = evaluate(inputs(**kwargs, **_seat([number])))
    assert decision.armed
    assert not decision.holds
    assert len(decision.second_review_cleared) == 1
    assert f"({number})" in decision.second_review_cleared[0]
    assert "safe because the diff is tooling only" in decision.second_review_cleared[0]


@pytest.mark.parametrize("number,kwargs", CLEARABLE_CASES)
def test_clearable_hold_holds_on_reject(number, kwargs):
    decision = evaluate(inputs(**kwargs, **_seat([number], verdict="REJECT",
                                                 second_review_reject_reason="not safe")))
    assert not decision.armed
    assert _holds(decision) == {number}
    assert "second reviewer: REJECT not safe" in decision.holds[0].reason


@pytest.mark.parametrize("number,kwargs", CLEARABLE_CASES)
def test_clearable_hold_holds_on_absent_verdict(number, kwargs):
    decision = evaluate(inputs(**kwargs, current_tree=TREE))
    assert not decision.armed
    assert _holds(decision) == {number}
    assert "second reviewer: absent" in decision.holds[0].reason


@pytest.mark.parametrize("number,kwargs", CLEARABLE_CASES)
def test_clearable_hold_holds_on_stale_tree(number, kwargs):
    decision = evaluate(inputs(**kwargs, **_seat([number], tree=OTHER_TREE)))
    assert not decision.armed
    assert _holds(decision) == {number}
    assert "second reviewer: stale tree" in decision.holds[0].reason


def test_clearable_hold_holds_on_empty_current_tree():
    kwargs = dict(CLEARABLE_CASES[0][1])
    decision = evaluate(inputs(**kwargs, **{**_seat([7], tree=""), "current_tree": ""}))
    assert not decision.armed


@pytest.mark.parametrize("number,kwargs", CLEARABLE_CASES)
def test_clearable_hold_holds_without_clears_line(number, kwargs):
    decision = evaluate(inputs(**kwargs, **_seat([])))
    assert not decision.armed
    assert _holds(decision) == {number}
    assert "second reviewer: no CLEARS line" in decision.holds[0].reason


def test_never_clear_ui_plan_trigger():
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=("ui",))


def test_never_clear_absent_plan_trigger():
    _assert_held_with_clean([7], **_PLAN_HOLD)


def test_never_clear_unknown_plan_trigger():
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=("security-surface and ui",))


def test_never_clear_one_way_door():
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=("one-way",))


def test_never_clear_merge_rule():
    _assert_held_with_clean([7, 18], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening",),
                            merge_danger_state="ONE-WAY")


def test_never_clear_red_ci():
    _assert_held_with_clean([7, 15], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening",),
                            failed_checks=["unit-tests"])


def test_never_clear_security_finding():
    _assert_held_with_clean([7, 9], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening",),
                            llm_safety_holds=["touches an auth route"])


def test_never_clear_planted_bug():
    _assert_held_with_clean([7, 19], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening",),
                            exec_mutations_holding=["row 3 held"])


def test_never_clear_ui_pipeline_pr():
    _assert_held_with_clean([10], pr_labels=["pipeline-authored", "human-approved"],
                            classification=Classification(files=["ibl5/design/foo.css"]))


def test_never_clear_gate_backtest_unknown():
    _assert_held_with_clean([17], gate_backtest_status="UNKNOWN")


# The UI floor covers every clearable condition, not condition 10 only. Each case
# carries `human-approved`, which clears condition 8, so the seat's own UI check
# is what keeps the hold.
_UI_FILES = (["ibl5/design/input.css"], ["ibl5/classes/Foo/FooView.php"])


@pytest.mark.parametrize("files", _UI_FILES)
def test_ui_floor_holds_plan_trigger_on_clean(files):
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening",),
                            pr_labels=["human-approved"], classification=Classification(files=files))


@pytest.mark.parametrize("files", _UI_FILES)
def test_ui_floor_holds_pipeline_on_clean(files):
    _assert_held_with_clean([10], pr_labels=["pipeline-authored", "human-approved"],
                            classification=Classification(files=files))


@pytest.mark.parametrize("files", _UI_FILES)
def test_ui_floor_holds_fidelity_on_clean(files):
    _assert_held_with_clean([12], fidelity_verdict="NOT READY", fidelity_gate_edit_only=True,
                            pr_labels=["human-approved"], classification=Classification(files=files))


@pytest.mark.parametrize("files", _UI_FILES)
def test_ui_floor_holds_gate_backtest_on_clean(files):
    _assert_held_with_clean([17], gate_backtest_status="HELD",
                            pr_labels=["human-approved"], classification=Classification(files=files))


# The must-ask shapes of gate 14(b), 14(e) and 14(f) have owner-held tokens.
@pytest.mark.parametrize("token", ["bootstrap-hazard", "security-irreducible", "golden-new"])
def test_owner_held_must_ask_token_holds_on_clean(token):
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=(token,))
    _assert_held_with_clean([7], **_PLAN_HOLD, plan_hold_triggers=("gate-weakening", token))


def test_owner_held_and_clearable_tokens_never_overlap():
    from harness.armable import CLEARABLE_PLAN_TRIGGERS, OWNER_HELD_PLAN_TRIGGERS
    assert not CLEARABLE_PLAN_TRIGGERS & OWNER_HELD_PLAN_TRIGGERS
    assert {"bootstrap-hazard", "security-irreducible", "golden-new"} <= OWNER_HELD_PLAN_TRIGGERS


def test_ui_floor_holds_on_rename_source_in_ui_paths():
    """A UI file renamed to a non-UI path: classification.files lists only the new
    path, ui_paths lists both. The owner's human-approved label clears condition 8,
    but the seat must still not clear the pipeline hold for a UI-touching PR."""
    _assert_held_with_clean([10], pr_labels=["pipeline-authored", "human-approved"],
                            classification=Classification(files=["bin/check-prose"]),
                            ui_paths=["bin/check-prose", "ibl5/design/input.css"])
