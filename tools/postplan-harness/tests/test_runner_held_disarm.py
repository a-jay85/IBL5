"""A post-plan run that ends held removes any existing arm on its PR.

Replay runs drive runner.run() to SHIPPED_HELD (condition 7, plan_auto_merge_false) and
SHIPPED_ARMED, then read the Fake gh action log. The sticky builder test pins the line
that tells the reader why the arm came off.
"""
import json
import os
import sys
import types

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))
sys.path.insert(0, os.path.dirname(os.path.abspath(__file__)))

import runner
import sticky_shapes
from harness import holdrepeat
from harness.adapters.llm import FixtureLlm
from harness.state import TerminalState, UsageLedger
from test_holdrepeat_runner import CANNED, CLEAN_PLAN, HOLD_7_PLAN, _fixture

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

DISARM_LINE = "Any auto-merge already set on this PR is now turned off."


@pytest.fixture(autouse=True)
def _const_fingerprint(monkeypatch):
    monkeypatch.setattr(holdrepeat, "fingerprint", lambda *a, **k: "fp-const")


def _run(tmp_path, plan):
    out = str(tmp_path / "out")
    llm = FixtureLlm(UsageLedger(), dict(CANNED))
    res = runner.run(_fixture(plan), out, llm, mode="replay", live=False)
    return res, out


def _actions(out):
    p = os.path.join(out, "actions.jsonl")
    if not os.path.exists(p):
        return []
    with open(p) as fh:
        return [json.loads(line) for line in fh if line.strip()]


def _names(out):
    return [a["action"] for a in _actions(out)]


def test_held_run_disarms_armed_pr(tmp_path):
    res, out = _run(tmp_path, HOLD_7_PLAN)
    assert res.terminal == TerminalState.SHIPPED_HELD
    names = _names(out)
    assert names.count("pr_disable_auto_merge") == 1
    assert "pr_merge_auto" not in names


def test_armed_run_does_not_disarm(tmp_path):
    res, out = _run(tmp_path, CLEAN_PLAN)
    assert res.terminal == TerminalState.SHIPPED_ARMED
    names = _names(out)
    assert names.count("pr_disable_auto_merge") == 0
    assert names.count("pr_merge_auto") == 1


def _sticky(tmp_path, decision):
    inputs = sticky_shapes.pr3267_inputs(tmp_path, v1="READY", v2=None)
    inputs["decision"] = decision
    return sticky_shapes.render_sticky(inputs)


def test_held_sticky_records_disarm(tmp_path):
    held = types.SimpleNamespace(armed=False, holds=[types.SimpleNamespace(
        number=7, name="plan-auto-merge-hold", reason="plan sets auto_merge: false")])
    assert DISARM_LINE in _sticky(tmp_path, held)
    armed = types.SimpleNamespace(armed=True, holds=[])
    assert DISARM_LINE not in _sticky(tmp_path, armed)
