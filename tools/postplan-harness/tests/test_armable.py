import os
import sys

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.armable import (ArmInputs, dep_numbers, evaluate, feat_hold,
                             manual_testing_clearance)
from harness.state import Classification, Finding

BODY_CLEARED = "## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed — covered.\n"
BODY_HELD = "## Summary\nx\n\n## Manual Testing\n\n- [ ] eyeball the layout\n"


def inputs(**kw):
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(), findings=[], unresolved_conformance=[],
                phase5_status="pass", plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED")
    base.update(kw)
    return ArmInputs(**base)


def test_clearance_states():
    assert manual_testing_clearance(BODY_CLEARED) == "CLEARED"
    assert manual_testing_clearance(BODY_HELD) == "HELD"
    assert manual_testing_clearance("## Summary\nonly\n") == "UNKNOWN"


def test_all_pass_arms():
    d = evaluate(inputs())
    assert d.armed and not d.holds


def test_each_condition_blocks():
    cases = [
        inputs(pr_body=BODY_HELD),                                          # (1)
        inputs(findings=[Finding("code-review", "A", "f.php", 1, "bug", 85)]),  # (2)
        inputs(unresolved_conformance=["MISSING: t.php"]),                  # (3)
        inputs(phase5_status="fail"),                                       # (4)
        inputs(classification=Classification(golden_changed=True)),         # (5) headless
        inputs(dep_state_lookup=lambda n: "OPEN",
               pr_body=BODY_CLEARED + "\nDepends-on: #1400\n"),             # (6)
        inputs(plan_auto_merge_false=True),                                 # (7)
        inputs(pr_title="feat: shiny new GM power"),                        # (8)
        inputs(llm_safety_holds=["new UI needs visual judgment"]),          # (9)
        inputs(pr_labels=["pipeline-authored"]),                            # (10)
    ]
    for i, inp in enumerate(cases, 1):
        d = evaluate(inp)
        assert not d.armed, f"condition {i} should block"
        assert any(c.number == i for c in d.holds), f"condition {i} not the blocker"


def test_fail_closed_on_unknown_dep():
    d = evaluate(inputs(pr_body=BODY_CLEARED + "\nDepends-on: #999\n",
                        dep_state_lookup=lambda n: "UNKNOWN"))
    assert not d.armed and any(c.number == 6 for c in d.holds)


def test_golden_interactive_warns_not_blocks():
    d = evaluate(inputs(classification=Classification(golden_changed=True), headless=False))
    assert d.armed
    c5 = next(c for c in d.conditions if c.number == 5)
    assert c5.warning and not c5.blocked


def test_feat_hold_and_override():
    assert feat_hold("feat(trade): new button", [])
    assert feat_hold("FEAT!: breaking", [])
    assert not feat_hold("feat(trade): new button", ["human-approved"])
    assert not feat_hold("chore: feat-adjacent", [])
    assert not feat_hold("refactor: featherweight", [])


def test_dep_numbers_anchored_only():
    body = "Depends-on: #1400, #1401\nsee also #999 in prose\n  depends-on: #7\n"
    assert dep_numbers(body) == [1400, 1401, 7]


def test_findings_below_80_do_not_block():
    d = evaluate(inputs(findings=[Finding("code-review", "A", "f.php", 1, "meh", 79)]))
    assert d.armed


def test_phase5_skipped_does_not_block():
    assert evaluate(inputs(phase5_status="skipped")).armed
    assert evaluate(inputs(phase5_status=None)).armed
