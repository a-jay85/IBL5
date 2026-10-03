import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness import gate_backtest_replay as gbr
from harness.adapters.llm import FixtureLlm
from harness.gate_backtest import (GATE_BACKTEST_BEGIN, GateChange, Verdict, compute_verdict,
                                   render_gate_backtest)
from harness.state import TerminalState, UsageLedger

from tests.test_body_check import CANNED, _actions, _fixture

pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")

GATE_DIFF = ("diff --git a/bin/check-zzz b/bin/check-zzz\n"
             "new file mode 100755\n"
             "--- /dev/null\n+++ b/bin/check-zzz\n@@ -0,0 +1 @@\n+#!/bin/sh\n")


def _held_block():
    gates = [GateChange("bin/check-zzz", "check-script", "unspecified", None, "no replay spec")]
    v = Verdict("HELD", "bin/check-zzz has no replay spec", {})
    return render_gate_backtest(v, gates, [], {}, 0)


def _body_edits(out):
    return [a for a in _actions(out) if a.get("action") == "pr_edit_body"]


def _run(fx):
    out = tempfile.mkdtemp()
    res = runner.run(fx, out, FixtureLlm(UsageLedger(), CANNED), mode="replay")
    assert res.terminal != TerminalState.FAILED, res.error
    return out, res


def test_runner_gate_pr_body_has_block_and_holds():
    fx = _fixture(diff=GATE_DIFF, head_subject="fix: gate",
                  gate_backtest={"status": "HELD", "reason": "bin/check-zzz has no replay spec",
                                 "block": _held_block()})
    out, res = _run(fx)
    edits = _body_edits(out)
    assert len(edits) == 1
    assert GATE_BACKTEST_BEGIN in edits[0]["body"]
    assert any(c.number == 17 for c in res.arm.holds)


def test_runner_non_gate_pr_body_unchanged():
    out, res = _run(_fixture(head_subject="fix: plain"))
    for e in _body_edits(out):
        assert GATE_BACKTEST_BEGIN not in e["body"]
    assert not any(c.number == 17 for c in res.arm.holds)


def test_runner_replay_without_key_is_not_applicable():
    out, res = _run(_fixture(diff=GATE_DIFF, head_subject="fix: gate"))
    for e in _body_edits(out):
        assert GATE_BACKTEST_BEGIN not in e["body"]
    assert not any(c.number == 17 for c in res.arm.holds)


def test_gate_backtest_result_live_exception_is_unknown(monkeypatch):
    gbr._MEMO.clear()

    def boom(*a, **k):
        raise RuntimeError("boom")

    monkeypatch.setattr(gbr, "backtest_branch", boom)
    logs = []
    r = gbr.gate_backtest_result("/nonexistent", GATE_DIFF, pr=1, live=True, fixture=None,
                                 log=logs.append)
    assert r.status == "UNKNOWN"
    assert "gate-backtest-state: UNKNOWN" in r.block
    assert any("runner error" in m for m in logs)
    gbr._MEMO.clear()


def test_gate_backtest_result_memoizes(monkeypatch):
    gbr._MEMO.clear()
    calls = []

    def fake(repo, head, changes, **kw):
        calls.append(1)
        return compute_verdict([], [], {}), ""

    monkeypatch.setattr(gbr, "backtest_branch", fake)
    kw = dict(pr=1, live=True, fixture=None, log=lambda m: None)
    gbr.gate_backtest_result("/r", GATE_DIFF, **kw)
    gbr.gate_backtest_result("/r", GATE_DIFF, **kw)
    assert len(calls) == 1
    gbr.gate_backtest_result("/r", GATE_DIFF + "+more\n", **kw)
    assert len(calls) == 2
    gbr._MEMO.clear()
