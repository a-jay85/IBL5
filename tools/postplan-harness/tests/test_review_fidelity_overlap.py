"""Phase 4 completes before Phase 5.5 builds its fidelity packet (early join)."""
from __future__ import annotations

import os

import pytest

import runner
from harness.state import TerminalState, UsageLedger
from test_runner_replay import (CANNED, ScriptedToolLlm, _fixture, _verdict_doc,  # noqa: F401
                                sticky_tmp)

# Phase 5.5 builds its packet by reading the procedure doc out of `origin/master`. Under
# `actions/checkout` on a pull_request event that ref does not exist, so build_packet
# raised fidelity-procedure-missing, Phase 5.5 returned before the review call, and the
# ordering these tests assert never happened. The push-event runs on master do have the
# ref, which is why only PR CI went red. Same opt-in as test_runner_replay.
pytestmark = pytest.mark.usefixtures("stub_ambient_git_show")


class _OverlapLlm(ScriptedToolLlm):
    """Records the call order of review-agent-a and fidelity calls."""

    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, canned, scripts)
        self.events = []

    def call(self, purpose, model, prompt, validate, **kw):
        if purpose == "review-agent-a":
            self.events.append("review-agent-a-done")
        return super().call(purpose, model, prompt, validate, **kw)

    def call_tooled(self, purpose, model, prompt, **kw):
        self.events.append(purpose)
        return super().call_tooled(purpose, model, prompt, **kw)


def _overlap_run(tmp_path, pr, scripts):
    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""       # opts the fixture into the real Phase 5.5 path
    llm = _OverlapLlm(UsageLedger(), canned, scripts)
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    res = runner.run(fx, out, llm, mode="replay")
    assert "plan-fidelity-review" in llm.events, f"Phase 5.5 never ran: {llm.events}"
    return res, out, llm


def test_phase4_completes_before_fidelity_packet(tmp_path, sticky_tmp):
    """Phase 4 completes before fidelity builds its packet (early join, not inline Phase 4)."""
    pr = sticky_tmp(7301)
    res, out, llm = _overlap_run(tmp_path, pr,
                                 {"plan-fidelity-review": [_verdict_doc("READY")]})
    assert llm.events.index("review-agent-a-done") < llm.events.index("plan-fidelity-review"), llm.events
    assert res.terminal == TerminalState.SHIPPED_ARMED
    with open(os.path.join(out, "audit.log")) as fh:
        audit = fh.read()
    assert audit.index("phase4 gates=") < audit.index("phase6.5")


def test_code_review_finishes_before_fidelity_remediation(tmp_path, sticky_tmp):
    """Phase 4 completes before fidelity runs, so the head never moves under a live review."""
    pr = sticky_tmp(7302)
    res, out, llm = _overlap_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    assert llm.events.index("review-agent-a-done") < llm.events.index("plan-fidelity-review"), llm.events
    assert llm.events.index("review-agent-a-done") < llm.events.index("fidelity-remediation"), llm.events
