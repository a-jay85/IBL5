"""Phase 6.5 `retried_stages`: the "after 1 retry" suffix decorates only a no-verdict hold.

A real verdict (FOUND-PROBLEM, ONE-WAY, MALFORMED, UNREADABLE) keeps its byte-identical
reason, and a retry never clears a hold."""
import os
import sys

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import evaluate

from tests.test_armable import _CHARACTERIZATION_FIXTURES, inputs

_C14_PREFIX = "this branch carries a /post-plan auto-resolved rebase conflict — conflict-review "


def _cond(decision, number):
    return next(c for c in decision.conditions if c.number == number)


def test_default_retried_stages_is_empty():
    assert inputs().retried_stages == []


def test_c14_absent_line_after_retry_carries_suffix():
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=ABSENT",
                        retried_stages=["conflict-review"]))
    c = _cond(d, 14)
    assert c.blocked
    assert c.reason == (_C14_PREFIX + "verdict='CONFLICT-REVIEW=ABSENT' after 1 retry; "
                        "need CONFLICT-REVIEW=CLEAN")


def test_c14_absent_line_without_retry_has_no_suffix():
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=ABSENT",
                        retried_stages=[]))
    c = _cond(d, 14)
    assert "after 1 retry" not in c.reason
    assert c.reason == (_C14_PREFIX + "verdict='CONFLICT-REVIEW=ABSENT'; "
                        "need CONFLICT-REVIEW=CLEAN")


def test_c14_no_verdict_file_never_claims_a_retry():
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict=None,
                        retried_stages=["conflict-review"]))
    assert _cond(d, 14).reason == (_C14_PREFIX + "verdict=ABSENT; need CONFLICT-REVIEW=CLEAN")


def test_c14_found_problem_never_carries_suffix():
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=FOUND-PROBLEM",
                        retried_stages=["conflict-review"]))
    c = _cond(d, 14)
    assert c.blocked
    assert "after 1 retry" not in c.reason
    assert c.reason == (_C14_PREFIX + "verdict='CONFLICT-REVIEW=FOUND-PROBLEM'; "
                        "need CONFLICT-REVIEW=CLEAN")


def test_c14_clean_after_retry_arms():
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=CLEAN",
                        retried_stages=["conflict-review"]))
    assert not _cond(d, 14).blocked
    assert d.armed


def test_c18_missing_after_retry_carries_suffix():
    d = evaluate(inputs(merge_danger_state="MISSING", retried_stages=["merge-danger"]))
    c = _cond(d, 18)
    assert c.blocked
    assert c.reason == "state=MISSING after 1 retry"


def test_c18_missing_without_retry_has_no_suffix():
    d = evaluate(inputs(merge_danger_state="MISSING", retried_stages=[]))
    assert _cond(d, 18).reason == "state=MISSING"


@pytest.mark.parametrize("state", ["ONE-WAY", "MALFORMED", "UNREADABLE"])
def test_c18_real_verdicts_never_carry_suffix(state):
    d = evaluate(inputs(merge_danger_state=state, retried_stages=["merge-danger"]))
    c = _cond(d, 18)
    assert c.blocked
    assert c.reason == f"state={state}"


def test_c18_two_way_after_retry_arms():
    d = evaluate(inputs(merge_danger_state="TWO-WAY", retried_stages=["merge-danger"]))
    assert not _cond(d, 18).blocked


_BASES = [("armed-base", {})] + [(f[0], f[1]) for f in _CHARACTERIZATION_FIXTURES]


@pytest.mark.parametrize("label,kwargs", _BASES, ids=[b[0] for b in _BASES])
def test_conditions_1_to_17_unchanged_by_retried_stages(label, kwargs):
    def early(d):
        return [(c.number, c.blocked, c.reason) for c in d.conditions if c.number <= 17]

    retried = evaluate(inputs(**kwargs, retried_stages=["conflict-review", "merge-danger"]))
    plain = evaluate(inputs(**kwargs))
    assert early(retried) == early(plain)
