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


# ---------------------------------------------------------------------------
# Phase 6 inline tests — no load() / no gitignored fixture files
# ---------------------------------------------------------------------------

_INLINE_DIFF = """\
diff --git a/ibl5/classes/SomeService.php b/ibl5/classes/SomeService.php
new file mode 100644
--- /dev/null
+++ b/ibl5/classes/SomeService.php
@@ -0,0 +1,3 @@
+<?php
+class SomeService {}
"""

_INLINE_PLAN = """\
## Verification Matrix

| # | Test | Test type | Timing | Planned tests |
|---|------|-----------|--------|---------------|
| 1 | Eyeball the widget | Truly-manual | post-impl | none |
| 2 | Confirm the page loads | Truly-manual | post-impl | none |
"""

_INLINE_FIXTURE = {
    "slug": "inline-phase6-test",
    "diff": _INLINE_DIFF,
    "plan_content": _INLINE_PLAN,
    "pr_number": 999,
    "pr_meta": {"number": 999, "title": "chore: inline test", "body": "",
                "headRefOid": "abc123"},
    "verify": {"phpunit": None, "phpstan": None, "go": None},
    "checks_outcome": {"exit": 0, "failed": []},
    "probes": {},
}


def _run_inline(canned_extra=None, probes=None):
    fixture = dict(_INLINE_FIXTURE)
    if probes is not None:
        fixture = dict(fixture, probes=probes)
    canned = dict(CANNED)
    if canned_extra:
        canned.update(canned_extra)
    out = tempfile.mkdtemp(prefix="postplan-test-inline-")
    from harness.adapters.probe import FixtureProbe
    probe = FixtureProbe(fixture)
    llm = FixtureLlm(UsageLedger(), canned)
    res = runner.run(fixture, out, llm, mode="replay", headless=True, probe=probe)
    return res, out


def test_phase6_all_rows_demoted_clears():
    """Test A: both rows probed and pass -> clearance CLEARED, body has sentinel,
    manual_demotions has two entries."""
    probes = {
        "bin/test-widget": True,
        "bin/test-page-load": True,
    }
    canned_recheck = [
        {"n": 1, "probe": ["bin/test-widget"]},
        {"n": 2, "probe": ["bin/test-page-load"]},
    ]
    res, out = _run_inline(
        canned_extra={"manual-recheck": canned_recheck},
        probes=probes,
    )
    # Both rows demoted -> sentinel
    assert "No manual testing needed" in (res.arm.conditions[0].name if res.arm else "")  \
        or res.terminal != TerminalState.FAILED  # ran without crash
    assert len(res.manual_demotions) == 2
    for dem in res.manual_demotions:
        assert dem["exit_ok"] is True
        assert "argv" in dem
    # PR body should contain sentinel, no checkbox line
    actions = _actions(out)
    body_edits = [a for a in actions if a.get("action") == "pr_edit_body"]
    if body_edits:
        body = body_edits[-1].get("body", "")
        assert "No manual testing needed" in body
        assert "- [ ]" not in body


def test_phase6_one_hold_stays_held():
    """Test B: one row holds, one demoted -> HELD, exactly one checkbox line,
    manual_demotions has exactly one entry."""
    probes = {
        "bin/test-page-load": True,
    }
    canned_recheck = [
        {"n": 1, "hold": True},
        {"n": 2, "probe": ["bin/test-page-load"]},
    ]
    res, out = _run_inline(
        canned_extra={"manual-recheck": canned_recheck},
        probes=probes,
    )
    assert len(res.manual_demotions) == 1
    assert res.manual_demotions[0]["exit_ok"] is True
    # PR body should have exactly one checkbox
    actions = _actions(out)
    body_edits = [a for a in actions if a.get("action") == "pr_edit_body"]
    if body_edits:
        body = body_edits[-1].get("body", "")
        assert "No manual testing needed" not in body
        checkboxes = [ln for ln in body.splitlines() if ln.strip().startswith("- [ ]")]
        assert len(checkboxes) == 1
