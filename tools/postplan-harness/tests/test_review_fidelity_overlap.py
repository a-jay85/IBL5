"""Phase 4 runs in the background, under Phase 5 → 6 → the Phase 5.5 review call."""
from __future__ import annotations

import os
import threading

import runner
from harness.state import TerminalState, UsageLedger
from test_runner_replay import (CANNED, ScriptedToolLlm, _fixture, _verdict_doc,  # noqa: F401
                                sticky_tmp)


class _OverlapLlm(ScriptedToolLlm):
    """Review agent A blocks until the fidelity review has started, then records order."""

    def __init__(self, ledger, canned, scripts):
        super().__init__(ledger, canned, scripts)
        self.fidelity_started = threading.Event()
        self.saw_overlap = None
        self.events = []

    def call(self, purpose, model, prompt, validate, **kw):
        if purpose == "review-agent-a":
            # Serial sequencing never reaches Phase 5.5 while this waits, so it times out.
            self.saw_overlap = self.fidelity_started.wait(timeout=5)
            self.events.append("review-agent-a-done")
        return super().call(purpose, model, prompt, validate, **kw)

    def call_tooled(self, purpose, model, prompt, **kw):
        if purpose == "plan-fidelity-review":
            self.fidelity_started.set()
        self.events.append(purpose)
        return super().call_tooled(purpose, model, prompt, **kw)


def _overlap_run(tmp_path, pr, scripts):
    out = str(tmp_path / f"out{pr}")
    canned = dict(CANNED)
    canned["plan-fidelity-review"] = ""       # opts the fixture into the real Phase 5.5 path
    llm = _OverlapLlm(UsageLedger(), canned, scripts)
    fx = _fixture(pr_number=pr)
    fx["pr_meta"] = dict(fx["pr_meta"], number=pr)
    return runner.run(fx, out, llm, mode="replay"), out, llm


def test_fidelity_review_overlaps_code_review(tmp_path, sticky_tmp):
    """Mutation: run Phase 4 inline again → agent A times out before Phase 5.5 starts."""
    pr = sticky_tmp(7301)
    res, out, llm = _overlap_run(tmp_path, pr,
                                 {"plan-fidelity-review": [_verdict_doc("READY")]})
    assert llm.saw_overlap is True
    assert res.terminal == TerminalState.SHIPPED_ARMED
    with open(os.path.join(out, "audit.log")) as fh:
        audit = fh.read()
    assert audit.index("phase4 gates=") < audit.index("phase6.5")


def test_code_review_finishes_before_fidelity_remediation(tmp_path, sticky_tmp):
    """The head must not move under a review that is still posting against it.
    Mutation: drop the before_remediation join → remediation starts first."""
    pr = sticky_tmp(7302)
    res, out, llm = _overlap_run(tmp_path, pr, {
        "plan-fidelity-review": [_verdict_doc("NOT READY")],
        "fidelity-remediation": ["edits made"],
        "plan-fidelity-re-review-2": [_verdict_doc("READY")],
    })
    assert llm.saw_overlap is True
    assert llm.events.index("review-agent-a-done") < llm.events.index("fidelity-remediation"), llm.events
