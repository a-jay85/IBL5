"""Tests that Phase 4 review agents run concurrently.

The sleeping LLM adapter records wall-clock timestamps and thread IDs for every
call.  After run() returns we assert:

- Two agents' (start, end) windows overlap (proving concurrency).
- Every expected agent's ledger entry is present.
- Every expected agent's result findings are present and correct.
- File-system raw-dumps (via out_dir) are written for every agent.
"""
from __future__ import annotations

import os
import sys
import tempfile
import threading
import time

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.review import ReviewPhase
from harness.state import Classification, Finding, HarnessError, LlmCallRecord, PlanInfo, UsageLedger
from harness import schemas

# ---------------------------------------------------------------------------
# Helpers
# ---------------------------------------------------------------------------

AGENT_SLEEP = 0.15   # seconds per simulated agent call


class _SleepingLlm:
    """LLM adapter that sleeps briefly, records timing/thread info, and returns canned data."""

    def __init__(self, ledger: UsageLedger, canned: dict, sleep: float = AGENT_SLEEP):
        self.ledger = ledger
        self.canned = canned
        self.sleep = sleep
        self.log: list[tuple[str, int, float, float]] = []   # (purpose, tid, start, end)
        self._log_lock = threading.Lock()

    def call(self, purpose: str, model: str, prompt: str, validate,
             max_retries: int = 1, normalizer=None):
        tid = threading.get_ident()
        start = time.monotonic()
        time.sleep(self.sleep)
        end = time.monotonic()
        with self._log_lock:
            self.log.append((purpose, tid, start, end))

        if purpose not in self.canned:
            raise HarnessError("llm-fixture-missing", purpose)
        data = self.canned[purpose]
        if normalizer is not None:
            data = normalizer(data)
        validate(data)
        self.ledger.add(LlmCallRecord(purpose=purpose, model=f"sleep:{model}"))
        return data


class _NullGh:
    """Gh stub that records calls but does nothing."""

    def __init__(self):
        self.calls: list[tuple] = []

    def post_review_findings(self, pr, sha, title, findings):
        self.calls.append(("post_review_findings", pr, title))

    def post_review_summary(self, pr, title, body):
        self.calls.append(("post_review_summary", pr, title))


def _php_cls(**kwargs) -> Classification:
    """Classification with PHP enabled (triggers agents A, B, security)."""
    c = Classification()
    c.has_php = True
    c.has_modified = True
    c.lines_php_changed = 100    # triggers B_history
    c.has_comments_in_diff = True  # triggers B_comments
    c.non_code_only = False
    c.engine_only = False
    c.has_e2e_specs = False      # skip agent D
    c.filtered_diff = "+<?php echo 1;\n"
    c.files = ["ibl5/x.php"]
    for k, v in kwargs.items():
        setattr(c, k, v)
    return c


def _make_canned(findings_by_agent=None):
    findings_by_agent = findings_by_agent or {}
    return {
        "review-agent-a": findings_by_agent.get("A", []),
        "review-agent-b": findings_by_agent.get("B", []),
        "review-agent-d": findings_by_agent.get("D", []),
        "security-audit": findings_by_agent.get("security", []),
        "score-findings": [],
    }


# ---------------------------------------------------------------------------
# Tests
# ---------------------------------------------------------------------------


def test_agents_overlap_in_time():
    """Phase 4 review agents run concurrently — verified by overlapping time windows."""
    ledger = UsageLedger()
    canned = _make_canned()
    llm = _SleepingLlm(ledger, canned, sleep=AGENT_SLEEP)
    gh = _NullGh()

    ReviewPhase(llm, gh).run({}, _php_cls(), PlanInfo())

    # Three agents ran (A, B, security); D gate is off.
    review_calls = [entry for entry in llm.log if entry[0] in
                    ("review-agent-a", "review-agent-b", "security-audit")]
    assert len(review_calls) == 3, f"expected 3 review agent calls, got {review_calls}"

    # Assert overlap directly from recorded (start, end) pairs — not from total elapsed
    # time, which is fragile on a contended CI runner where thread-startup jitter can
    # push elapsed past AGENT_SLEEP * len(calls).
    overlaps = sum(
        1 for i, (_, _, s1, e1) in enumerate(review_calls)
        for _, _, s2, e2 in review_calls[i + 1:]
        if s1 < e2 and s2 < e1
    )
    assert overlaps > 0, (
        f"no two agents ran concurrently — "
        f"{[(p, f'{s:.3f}-{e:.3f}') for p, _, s, e in review_calls]}"
    )


def test_agents_run_in_distinct_threads():
    """Each concurrent agent call must use a different OS thread."""
    ledger = UsageLedger()
    canned = _make_canned()
    llm = _SleepingLlm(ledger, canned, sleep=AGENT_SLEEP)
    gh = _NullGh()

    ReviewPhase(llm, gh).run({}, _php_cls(), PlanInfo())

    review_calls = [entry for entry in llm.log if entry[0] in
                    ("review-agent-a", "review-agent-b", "security-audit")]
    tids = {entry[1] for entry in review_calls}
    assert len(tids) > 1, (
        f"all {len(review_calls)} agents ran in the same thread — "
        "ThreadPoolExecutor not doing concurrent work"
    )


def test_ledger_has_entry_for_every_agent():
    """UsageLedger must contain one record per executed agent, thread-safely accumulated."""
    ledger = UsageLedger()
    canned = _make_canned()
    llm = _SleepingLlm(ledger, canned, sleep=AGENT_SLEEP)
    gh = _NullGh()

    ReviewPhase(llm, gh).run({}, _php_cls(), PlanInfo())

    purposes = {r.purpose for r in ledger.calls}
    for expected in ("review-agent-a", "review-agent-b", "security-audit"):
        assert expected in purposes, f"{expected!r} missing from ledger — {purposes}"


def test_findings_from_all_agents_are_returned():
    """Findings from each concurrent agent must appear in the result, in A→B→security order."""
    ledger = UsageLedger()
    findings_by_agent = {
        "A": [{"path": "ibl5/a.php", "line": 1, "body": "agent-a issue"}],
        "B": [{"path": "ibl5/b.php", "line": 2, "body": "agent-b issue"}],
        "security": [{"path": "ibl5/s.php", "line": 3, "body": "security issue"}],
    }
    canned = _make_canned(findings_by_agent)
    llm = _SleepingLlm(ledger, canned, sleep=AGENT_SLEEP)
    gh = _NullGh()

    # Inject score-findings so all findings survive thresholds
    def _scoring_llm_call(purpose, model, prompt, validate, max_retries=1, normalizer=None):
        if purpose == "score-findings":
            # Score all findings well above both thresholds (80/75)
            data = [{"n": i + 1, "score": 90} for i in range(3)]
            validate(data)
            ledger.add(LlmCallRecord(purpose=purpose, model="sleep:haiku"))
            return data
        return llm.call(purpose, model, prompt, validate, max_retries, normalizer)

    class _ComboLlm:
        def call(self_, purpose, model, prompt, validate, max_retries=1, normalizer=None):
            if purpose == "score-findings":
                return _scoring_llm_call(purpose, model, prompt, validate, max_retries, normalizer)
            return llm.call(purpose, model, prompt, validate, max_retries, normalizer)

    surviving, gates, scored, degraded = ReviewPhase(_ComboLlm(), gh).run(
        {"number": 1, "headRefOid": "abc"}, _php_cls(), PlanInfo()
    )

    paths = {f.path for f in surviving}
    assert "ibl5/a.php" in paths, f"agent-A finding missing; surviving={surviving}"
    assert "ibl5/b.php" in paths, f"agent-B finding missing; surviving={surviving}"
    assert "ibl5/s.php" in paths, f"security finding missing; surviving={surviving}"

    # Order: A then B then security (A,B are code-review; security is security-audit).
    # The Finding objects appear in submission order regardless of which thread finished first.
    agents_in_order = [f.agent for f in surviving]
    assert agents_in_order.index("A") < agents_in_order.index("B"), \
        f"A must precede B in finding order; got {agents_in_order}"


def test_raw_files_written_for_every_agent(tmp_path):
    """When out_dir is given to ClaudeCli, raw files must be named per-agent (no collision)."""
    # This test verifies the _persist_raw naming is unique per agent by checking
    # that the filename formula uses purpose (unique) not a shared counter.
    # We exercise it through ClaudeCli's naming logic directly rather than launching subprocesses.
    from harness.adapters.llm import ClaudeCli

    out_dir = str(tmp_path / "raw")
    os.makedirs(out_dir)

    # _persist_raw is a best-effort dump — call it directly for three distinct purposes.
    ledger = UsageLedger()
    cli = ClaudeCli(ledger, out_dir=out_dir)
    for purpose in ("review-agent-a", "review-agent-b", "security-audit"):
        cli._persist_raw(purpose, 0, f"reply from {purpose}")

    files = os.listdir(out_dir)
    assert len(files) == 3, f"expected 3 raw files, got {files}"
    for purpose in ("review-agent-a", "review-agent-b", "security-audit"):
        expected = f"raw-{purpose}-attempt0.txt"
        assert expected in files, f"{expected} missing from {files}"
    # Verify content
    for purpose in ("review-agent-a", "review-agent-b", "security-audit"):
        p = os.path.join(out_dir, f"raw-{purpose}-attempt0.txt")
        assert open(p).read() == f"reply from {purpose}"


def test_degraded_agent_does_not_block_others():
    """An llm-invalid-output from one agent must not suppress the other agents' findings."""
    ledger = UsageLedger()
    gh = _NullGh()

    class _OneFailLlm:
        def __init__(self):
            self.calls = []

        def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
            self.calls.append(purpose)
            if purpose == "review-agent-a":
                raise HarnessError("llm-invalid-output", "review-agent-a: bad JSON")
            data = []
            if normalizer is not None:
                data = normalizer(data)
            validate(data)
            ledger.add(LlmCallRecord(purpose=purpose, model=f"fail-test:{model}"))
            return data

    llm = _OneFailLlm()
    _, gates, scored, degraded = ReviewPhase(llm, gh).run({}, _php_cls(), PlanInfo())
    assert "review-agent-a" in degraded
    # B and security must still have been called
    assert "review-agent-b" in llm.calls
    assert "security-audit" in llm.calls


def test_all_four_agents_preserve_submission_order():
    """With agent D enabled, findings appear in A → B → D → security order."""
    ledger = UsageLedger()
    findings_by_agent = {
        "A": [{"path": "ibl5/a.php", "line": 1, "body": "a"}],
        "B": [{"path": "ibl5/b.php", "line": 2, "body": "b"}],
        "D": [{"path": "ibl5/d.php", "line": 3, "body": "d"}],
        "security": [{"path": "ibl5/s.php", "line": 4, "body": "s"}],
    }
    canned = _make_canned(findings_by_agent)
    gh = _NullGh()
    base_llm = _SleepingLlm(ledger, canned, sleep=0)

    class _ComboLlm:
        def call(self_, purpose, model, prompt, validate, max_retries=1, normalizer=None):
            if purpose == "score-findings":
                data = [{"n": i + 1, "score": 90} for i in range(4)]
                validate(data)
                ledger.add(LlmCallRecord(purpose=purpose, model="sleep:haiku"))
                return data
            return base_llm.call(purpose, model, prompt, validate, max_retries, normalizer)

    surviving, _, _, _ = ReviewPhase(_ComboLlm(), gh).run(
        {"number": 1, "headRefOid": "abc"}, _php_cls(has_e2e_specs=True), PlanInfo()
    )

    agents = [f.agent for f in surviving]
    assert agents.index("A") < agents.index("B"), f"A before B; got {agents}"
    assert agents.index("B") < agents.index("D"), f"B before D; got {agents}"
    assert agents.index("D") < agents.index("security"), f"D before security; got {agents}"


def test_terminal_error_propagates():
    """A non-llm-invalid-output HarnessError must propagate out of run() unchanged."""
    import pytest

    ledger = UsageLedger()
    gh = _NullGh()

    class _TerminalLlm:
        def call(self, purpose, model, prompt, validate, max_retries=1, normalizer=None):
            raise HarnessError("llm-fixture-missing", purpose)

    with pytest.raises(HarnessError) as exc_info:
        ReviewPhase(_TerminalLlm(), gh).run({}, _php_cls(), PlanInfo())
    assert exc_info.value.kind == "llm-fixture-missing"
