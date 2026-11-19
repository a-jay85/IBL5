"""Arming conditions (5) and (7) with the self-cleared `trace-pinned` plan token.

The token lifts both holds only when it is the plan's only token, its evidence from
harness.trace_pinned is "ok", and no raw JSB capture changed. Any other shape keeps
the owner's hold and never reaches the second-review seat.
"""
from harness.armable import evaluate, second_review_candidates
from harness.classify import classify
from harness.state import Classification

from tests.test_armable import inputs

_PLAN_HOLD = {"plan_auto_merge_false": True, "plan_auto_merge_value": "false"}
GOLDEN = "engine/internal/sim/testdata/golden.json"
SEEDS = "engine/internal/sim/testdata/golden_seeds.sha256"
CAPTURE = "engine/testdata/jsb-captures/foul-roll.jsonl"
GO_FILE = "engine/internal/sim/foul.go"


def _holds(decision):
    return {c.number for c in decision.holds}


def _cond(decision, number):
    return next(c for c in decision.conditions if c.number == number)


def _golden_inputs(evidence="ok", triggers=("trace-pinned",), files=(GO_FILE, GOLDEN), **kw):
    cls = classify(list(files), "")
    return inputs(classification=cls, plan_hold_triggers=triggers,
                  trace_pinned_evidence=evidence, **_PLAN_HOLD, **kw)


def test_c5_lifted_by_trace_pinned_ok():
    decision = evaluate(_golden_inputs())
    c5 = _cond(decision, 5)
    assert not c5.blocked
    assert c5.warning == "golden change cleared by trace-pinned evidence (harness.trace_pinned)"
    assert 5 not in _holds(decision)


def test_c7_lifted_by_trace_pinned_ok():
    decision = evaluate(_golden_inputs())
    assert not _cond(decision, 7).blocked
    assert decision.armed
    assert not decision.holds


def test_c5_c7_held_when_evidence_not_ok():
    for evidence in ("test-fail", "unchecked", "evidence-missing", "not-applicable"):
        inp = _golden_inputs(evidence=evidence)
        decision = evaluate(inp)
        assert not decision.armed
        assert {5, 7} <= _holds(decision), evidence
        assert _cond(decision, 5).reason == "golden snapshot or JSB capture changed in headless mode"
        assert second_review_candidates(inp, decision.conditions) == {}


def test_c5_held_when_extra_token():
    inp = _golden_inputs(triggers=("trace-pinned", "gate-weakening"))
    decision = evaluate(inp)
    assert not decision.armed
    assert {5, 7} <= _holds(decision)


def test_golden_seeds_only_triggers_c5():
    cls = classify([GO_FILE, SEEDS], "")
    assert cls.golden_changed
    decision = evaluate(inputs(classification=cls))
    assert _holds(decision) == {5}
    assert _cond(decision, 5).reason == "golden snapshot or JSB capture changed in headless mode"


def test_golden_seeds_only_interactive_warns():
    cls = classify([SEEDS], "")
    decision = evaluate(inputs(classification=cls, headless=False))
    assert not _cond(decision, 5).blocked
    assert _cond(decision, 5).warning


def test_capture_dir_change_triggers_c5_and_is_not_liftable():
    cls = classify([CAPTURE], "")
    assert cls.jsb_capture_changed and not cls.golden_changed
    held = evaluate(inputs(classification=cls))
    assert _holds(held) == {5}
    inp = _golden_inputs(files=(GO_FILE, GOLDEN, CAPTURE))
    decision = evaluate(inp)
    assert not decision.armed
    assert {5, 7} <= _holds(decision)
    assert second_review_candidates(inp, decision.conditions) == {}


def test_unrelated_engine_file_is_not_golden():
    cls = classify([GO_FILE], "")
    assert not cls.golden_changed and not cls.jsb_capture_changed
    assert 5 not in _holds(evaluate(inputs(classification=cls)))


def test_trace_pinned_without_golden_change_still_lifts_c7_only_when_ok():
    inp = inputs(classification=Classification(files=[GO_FILE]),
                 plan_hold_triggers=("trace-pinned",), trace_pinned_evidence="test-fail",
                 **_PLAN_HOLD)
    decision = evaluate(inp)
    assert _holds(decision) == {7}
    assert second_review_candidates(inp, decision.conditions) == {}
