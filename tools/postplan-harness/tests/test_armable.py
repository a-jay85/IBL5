import os
import subprocess
import sys
import tempfile

import pytest

sys.path.insert(0, os.path.dirname(os.path.dirname(os.path.abspath(__file__))))

from harness.adapters.gitad import LiveGit
from harness.armable import (ArmInputs, SENTINEL_RE, dep_numbers, evaluate, feat_hold,
                             manual_testing_clearance)
from harness.manual_rows import ManualRow, _assert_no_sentinel, render_rows
from harness.state import Classification, Finding, HarnessError

BODY_CLEARED = "## Summary\nx\n\n## Manual Testing\n\nNo manual testing needed — covered.\n"
BODY_HELD = "## Summary\nx\n\n## Manual Testing\n\n- [ ] eyeball the layout\n"


def inputs(**kw):
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(), findings=[], unresolved_conformance=[],
                phase5_status="pass", plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED", fidelity_verdict="READY",
                unresolved_findings=[], conflict_resolved=False)
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


def test_render_rows_never_emits_sentinel():
    row = ManualRow(number="1", text="No manual testing needed - looks fine",
                    raw="1 | No manual testing needed - looks fine | Truly-manual | post-impl | none")
    rendered = render_rows([row])
    assert not SENTINEL_RE.match(rendered)
    assert manual_testing_clearance("## Manual Testing\n\n" + rendered + "\n") == "HELD"


def test_assert_no_sentinel_has_teeth():
    with pytest.raises(ValueError, match="sentinel"):
        _assert_no_sentinel("No manual testing needed\n")


@pytest.mark.parametrize("verdict,expect_armed", [
    ("READY", True),
    ("READY WITH NOTES", True),
    ("READY  ", True),            # trailing whitespace, as the skill's sed strips it
    ("NOT READY", False),
    (None, False),                # Phase 5.5 never ran — indeterminate, fail-closed
    ("", False),
    ("   ", False),
    ("ready", False),             # case-sensitive, matching the skill's grep exactly
    ("READY, mostly", False),     # not the bare verdict word
])
def test_fidelity_verdict_states(verdict, expect_armed):
    d = evaluate(inputs(fidelity_verdict=verdict))
    assert d.armed is expect_armed
    if not expect_armed:
        assert [c.number for c in d.holds] == [12]


def test_fidelity_default_is_fail_closed():
    """A construction site that omits the field must HOLD, never arm."""
    base = dict(pr_body=BODY_CLEARED, pr_title="chore: x", pr_labels=[],
                classification=Classification(), findings=[], unresolved_conformance=[],
                phase5_status="pass", plan_auto_merge_false=False, headless=True,
                dep_state_lookup=lambda n: "MERGED")
    d = evaluate(ArmInputs(**base))          # fidelity_verdict deliberately omitted
    assert not d.armed
    assert any(c.number == 12 for c in d.holds)


def test_condition_set_is_skill_numbered():
    """The set is {1..15} with no gaps. The numbers track the SKILL's condition numbers,
    not this list's position: (11) shells out to bin/lib/pr-armable.sh for unresolved
    review-thread findings, (14) reads the conflict-resolved flag, and (15) checks for
    already-red CI checks on the PR head at arm time."""
    nums = sorted(c.number for c in evaluate(inputs()).conditions)
    assert nums == [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15]


def test_fidelity_is_additive_and_releases_nothing():
    """(12) may add a hold; it may never clear one."""
    feat = inputs(pr_title="feat: shiny new GM power")          # (8) holds
    assert {c.number for c in evaluate(feat).holds} == {8}
    both = inputs(pr_title="feat: shiny new GM power", fidelity_verdict=None)
    assert {c.number for c in evaluate(both).holds} == {8, 12}
    cleared = inputs(pr_title="feat: shiny new GM power", fidelity_verdict="READY")
    assert 8 in {c.number for c in evaluate(cleared).holds}      # (12) did not release (8)
    assert not evaluate(cleared).armed


# ---------------------------------------------------------------------------
# Condition (11) — slug-drift hold tests (f, g)
# ---------------------------------------------------------------------------

def test_slug_drift_blocks_arm():
    """(f) plan_slug_drift set -> condition 11 blocked, decision.armed False."""
    d = evaluate(inputs(plan_slug_drift="plan-x.md"))
    assert not d.armed
    assert any(c.number == 13 for c in d.holds)


def test_slug_drift_empty_does_not_block():
    """(g) plan_slug_drift="" on otherwise-armable input -> condition 11 not blocked."""
    d = evaluate(inputs(plan_slug_drift=""))
    assert d.armed
    assert not any(c.number == 13 and c.blocked for c in d.conditions)


# sect-7d -- Autonomy-contract arming integration


def test_armable_unmet_contract_blocks_arming():
    """UNMET-CONTRACT: items in unresolved_conformance block arming via condition (3).

    Two assertions are required:
    1. An UNMET-CONTRACT: item routes through condition (3) -- not a new condition.
    2. phase5_status='skipped' alone (empty unresolved_conformance) still arms,
       pinning that condition (4) was not touched.
    """
    # An UNMET-CONTRACT: item in unresolved_conformance blocks via condition (3).
    d = evaluate(inputs(unresolved_conformance=[
        "UNMET-CONTRACT: stop_condition tests-green declared but PHASE5_VERIFY_STATUS=skipped",
    ]))
    assert not d.armed
    assert any(c.blocked and c.name == "unresolved-MISSING-items" for c in d.conditions)

    # phase5_status='skipped' with empty unresolved_conformance still arms (condition (4) not touched).
    d2 = evaluate(inputs(phase5_status="skipped", unresolved_conformance=[]))
    assert d2.armed


# ---------------------------------------------------------------------------
# Condition (15) — red-ci-check hold tests
# ---------------------------------------------------------------------------

def test_red_ci_check_blocks_arm():
    """(15) non-empty failed_checks blocks arming."""
    d = evaluate(inputs(failed_checks=["pytest (stdlib harness)"]))
    assert not d.armed
    assert any(c.number == 15 for c in d.holds)


def test_red_ci_check_empty_does_not_block():
    """(15) empty failed_checks (fail-open on pending) does not block."""
    d = evaluate(inputs(failed_checks=[]))
    assert d.armed
    assert not any(c.number == 15 and c.blocked for c in d.conditions)


def test_red_ci_check_reason_lists_names():
    """(15) reason string joins all failing check names."""
    d = evaluate(inputs(failed_checks=["check-a", "check-b"]))
    c15 = next(c for c in d.conditions if c.number == 15)
    assert c15.blocked
    assert "check-a" in c15.reason
    assert "check-b" in c15.reason


# ── Condition (14) — scenarios 4-7 ────────────────────────────────────────────

def test_condition_14_auto_resolve_alone_blocked():
    """Scenario 4: flag written (conflict_resolved=True), verdict absent -> blocked."""
    d = evaluate(inputs(conflict_resolved=True))
    assert not d.armed
    assert any(c.number == 14 and c.blocked for c in d.conditions)


def test_condition_14_clean_verdict_clears():
    """Scenario 5: flag + CONFLICT-REVIEW=CLEAN -> condition (14) not blocked, arm passes."""
    d = evaluate(inputs(conflict_resolved=True, conflict_verdict="CONFLICT-REVIEW=CLEAN"))
    assert d.armed
    assert not any(c.number == 14 and c.blocked for c in d.conditions)


def test_condition_14_found_problem_holds():
    """Scenario 6: FOUND-PROBLEM verdict -> blocked, verdict value appears in reason."""
    d = evaluate(inputs(conflict_resolved=True,
                        conflict_verdict="CONFLICT-REVIEW=FOUND-PROBLEM"))
    assert not d.armed
    c14 = next(c for c in d.conditions if c.number == 14)
    assert c14.blocked
    assert "FOUND-PROBLEM" in c14.reason


def test_condition_14_no_auto_resolve_passes():
    """Scenario 7: no flag (conflict_resolved=False) -> condition (14) not blocked."""
    d = evaluate(inputs(conflict_resolved=False))
    assert d.armed
    assert not any(c.number == 14 and c.blocked for c in d.conditions)


def test_rebase_conflict_fails_the_run_before_evaluate():
    """A conflicted rebase raises HarnessError('rebase-conflict') and aborts before
    evaluate() is ever reached, so THIS run never auto-resolves. Condition (14) covers
    the other case: a flag an EARLIER skill run left on the same branch."""
    import shutil
    d = tempfile.mkdtemp(prefix="postplan-arm-test-")
    try:
        git_env = {**os.environ,
                   "GIT_AUTHOR_NAME": "t", "GIT_AUTHOR_EMAIL": "t@t",
                   "GIT_COMMITTER_NAME": "t", "GIT_COMMITTER_EMAIL": "t@t"}

        def sh(*a):
            subprocess.run(["git", "-C", d, *a], check=True, capture_output=True,
                           env=git_env)

        subprocess.run(["git", "init", "-b", "master", d],
                       check=True, capture_output=True)
        subprocess.run(["git", "-C", d, "config", "user.email", "t@t"],
                       check=True, capture_output=True)
        subprocess.run(["git", "-C", d, "config", "user.name", "t"],
                       check=True, capture_output=True)

        # Base commit: a file both branches will edit
        with open(os.path.join(d, "a.txt"), "w") as f:
            f.write("original line\n")
        sh("add", "-A")
        sh("commit", "-m", "base")

        # Feature branch: edit the same line
        sh("checkout", "-b", "feature")
        with open(os.path.join(d, "a.txt"), "w") as f:
            f.write("feature change\n")
        sh("add", "-A")
        sh("commit", "-m", "feature edit")

        # Master: conflicting edit on the same line
        sh("checkout", "master")
        with open(os.path.join(d, "a.txt"), "w") as f:
            f.write("master change\n")
        sh("add", "-A")
        sh("commit", "-m", "master edit")

        # Return to feature and attempt rebase — must conflict
        sh("checkout", "feature")
        with pytest.raises(HarnessError) as exc:
            LiveGit(d).rebase_onto("master")
        assert exc.value.kind == "rebase-conflict"

        # Prove git rebase --abort actually ran: no in-progress rebase in the tree.
        # `git status --porcelain=v2` never emits the word "rebase" — mid-rebase it
        # prints only `# branch.oid`, `# branch.head (detached)` and the unmerged rows —
        # so a substring probe of that output is vacuous. The state directories are the
        # predicate git itself uses, and exactly one of them exists mid-rebase.
        for state_dir in ("rebase-merge", "rebase-apply"):
            assert not os.path.exists(os.path.join(d, ".git", state_dir)), (
                "git rebase --abort did not run: .git/%s still present" % state_dir)
    finally:
        shutil.rmtree(d, ignore_errors=True)
