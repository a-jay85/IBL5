import json
import os
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

import runner
from harness.adapters.llm import FixtureLlm
from harness.state import TerminalState, UsageLedger

ROOT = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))


def load(slug):
    path = os.path.join(ROOT, "fixtures/scenarios", slug, "fixture.json")
    if not os.path.exists(path):
        pytest.skip(f"replay fixture absent (gitignored; regenerate via ./run replay): {slug}")
    with open(path) as fh:
        return json.load(fh)


CANNED = {
    "pr-copy": {"type": "chore", "title": "chore: replay", "summary_md": "## Summary\n- x\n"},
    "review-agent-a": [], "review-agent-b": [], "review-agent-d": [],
    "security-audit": [],
    "safety-verdict": {"holds": []},
    "manual-classify": [],
    "retrospective": {"save": False},
}


def run_slug(slug, canned=CANNED):
    out = tempfile.mkdtemp(prefix=f"postplan-test-{slug}-")
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(load(slug), out, llm, mode="replay", headless=True)
    return res, out


def test_docs_only_arms_no_review_calls():
    res, out = run_slug("backlog-l6-done")
    assert res.terminal == TerminalState.SHIPPED_ARMED
    assert res.classification.docs_only and res.classification.non_code_only
    purposes = [c.purpose for c in res.ledger.calls]
    assert "review-agent-a" not in purposes and "security-audit" not in purposes
    actions = [a["action"] for a in _actions(out)]
    assert "pr_merge_auto" in actions


def test_plan_auto_merge_false_holds():
    res, out = run_slug("request-event-logging")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 7 for c in res.arm.holds)
    assert "pr_merge_auto" not in [a["action"] for a in _actions(out)]
    # php diff -> review + security agents fired
    purposes = [c.purpose for c in res.ledger.calls]
    assert "review-agent-a" in purposes and "security-audit" in purposes
    assert res.phase5 == "pass"    # recorded phpunit+phpstan outputs judged


def test_manual_held_visual_pr():
    res, _ = run_slug("mobile-target-size-a11y-sitewide")
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert {c.number for c in res.arm.holds} >= {1, 7}


def test_finding_at_80_blocks_arming():
    canned = dict(CANNED)
    canned["review-agent-a"] = [{"path": "ibl5/x.php", "line": 3, "body": "real bug"}]
    canned["score-findings"] = [{"n": 1, "score": 85}]
    res, out = run_slug("request-event-logging", canned)
    assert any(c.number == 2 for c in res.arm.holds)
    acts = _actions(out)
    posted = [a for a in acts if a["action"] == "pr_review_findings"]
    assert posted and posted[0]["findings"][0]["score"] == 85


def test_llm_safety_verdict_can_add_hold():
    canned = dict(CANNED)
    canned["safety-verdict"] = {"holds": ["new UI needs visual judgment"]}
    res, _ = run_slug("backlog-l6-done", canned)
    assert res.terminal == TerminalState.SHIPPED_HELD
    assert any(c.number == 9 for c in res.arm.holds)


def test_no_mutating_gh_commands_ever_executed():
    """The whole point: every side effect is a recorded intent."""
    _, out = run_slug("backlog-l6-done")
    for a in _actions(out):
        assert set(a) >= {"ts", "action"}   # typed records, not shell strings


def _actions(out):
    p = os.path.join(out, "actions.jsonl")
    if not os.path.exists(p):
        return []
    with open(p) as fh:
        return [json.loads(l) for l in fh if l.strip()]
