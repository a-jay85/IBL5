"""Plain-words hold lines in the sticky verdict (harness/hold_text.py)."""
from __future__ import annotations

import pathlib
import re
import subprocess

import pytest

from harness import fidelity, hold_text
from harness import merge_danger as md
from harness.armable import ArmInputs, evaluate
from harness.state import Classification, ConditionResult, Finding

REPO = pathlib.Path(__file__).resolve().parents[3]
HELD_PRS = REPO / "bin" / "lib" / "held-prs.sh"
TREE = "c" * 40


def _all_blocked():
    """One evaluate() call where every one of the 20 conditions holds."""
    body = "## Summary\nx\n\nDepends-on: #12, #13\n"
    return evaluate(ArmInputs(
        pr_body=body, pr_title="feat: x", pr_labels=["pipeline-authored"],
        classification=Classification(golden_changed=True),
        llm_safety_holds=["new SQL built from user input"], degraded_agents=["D"],
        findings=[Finding(source="code-review", agent="A", path="ibl5/a.php", line=7,
                          body="bug", score=90)],
        unresolved_conformance=["MISSING: tests/example", "MISSING-FILE: bin/x",
                                "UNREALISED-ASSERTION: row 4"],
        phase5_status="fail", plan_auto_merge_false=True, headless=True,
        dep_state_lookup=lambda n: "OPEN" if n == 12 else "UNKNOWN",
        exec_mutations_holding=None, fidelity_verdict="NOT READY",
        plan_slug_drift="other-slug", unresolved_findings=["unresolved-finding:85"],
        current_tree=TREE, conflict_resolved=True, conflict_verdict=None,
        failed_checks=["PHPUnit"], meta_checks_status="HELD",
        gate_backtest_status="HELD", gate_backtest_reason="flagged 2 clean PRs",
        merge_danger_state="ONE-WAY", removed_guards_holds=None))


def _held_sticky(decision):
    return fidelity.compose_sticky("", "", {"verdict_1": "READY", "reviewed_tree": TREE},
                                   decision, [], "", "READY")


def test_every_condition_has_a_plain_line_with_no_condition_name():
    decision = _all_blocked()
    assert sorted(c.number for c in decision.holds) == list(range(1, 21))
    body = _held_sticky(decision)
    lines = body.splitlines()
    start = lines.index("Auto-merge held:")
    bullets = lines[start + 1:start + 21]
    assert [b.split(")")[0] for b in bullets] == [f"- ({c.number}" for c in decision.holds]
    for c, bullet in zip(decision.holds, bullets):
        text = bullet.split(") ", 1)[1]
        assert c.name not in text
        assert "state=" not in text and "fail-closed" not in text
        assert "\n" not in hold_text.plain_hold(c)
    assert lines[start + 21] == fidelity.DISARM_LINE


def test_plain_lines_keep_the_detail_a_person_needs():
    by = {c.number: hold_text.plain_hold(c) for c in _all_blocked().holds}
    assert by[2] == "Code review found a likely bug that is not fixed yet: ibl5/a.php:7"
    assert by[3] == ("The plan asks for things this PR does not have yet: tests/example; "
                     "file bin/x; the test for matrix row 4")
    assert by[6] == ("This PR has to merge after other PRs that have not merged yet: "
                     "#12 (open), #13")
    assert by[7] == "The plan says a person must review and merge this PR."
    assert by[12] == ("The check that compares the PR to its plan did not pass "
                      "(it said NOT READY).")
    assert by[9] == ("The safety review flagged a risk: new SQL built from user input; "
                     "some review agents did not run (D)")
    assert by[15] == "These CI checks failed: PHPUnit."
    assert by[18] == "This change is hard to undo, so a person should merge it."


def test_raw_reasons_move_to_the_audit_trail():
    decision = _all_blocked()
    body = _held_sticky(decision)
    trail = body[body.index("<summary>Audit trail</summary>"):]
    for c in decision.holds:
        assert f"**Hold code:** ({c.number}) {c.name} — {c.reason}" in trail


@pytest.mark.parametrize("cond, want", [
    (ConditionResult(1, "manual-testing-clearance", True, "state=UNKNOWN"),
     "The PR body has no Manual Testing section, so nobody has signed off on manual tests."),
    (ConditionResult(4, "phase5-verify", True,
                     "Phase 5 status never recorded (indeterminate, not clean)"),
     "The local test step never finished, so nobody knows if the tests pass."),
    (ConditionResult(11, "unresolved-review-threads", True,
                     "cannot verify review-thread state (GitHub API error) — fail-closed"),
     "GitHub would not return the PR's review comments, so it waits to be safe."),
    (ConditionResult(11, "unresolved-review-threads", True,
                     "3 unresolved review thread(s) (1 scored, 2 unscored): a b c"),
     "3 review comment threads on the PR are still open."),
    (ConditionResult(12, "plan-fidelity-verdict", True,
                     "fidelity verdict=None (source: verdict-1); need READY or READY WITH NOTES"),
     "The check that compares the PR to its plan never gave a verdict."),
    (ConditionResult(18, "merge-danger", True, "state=MISSING after 1 retry"),
     "The PR body has no Merge danger line saying whether this is easy to undo."),
    (ConditionResult(19, "exec-mutations", True, "executable-mutation step did not run"),
     "The step that plants bugs on purpose, to prove the tests catch them, did not run."),
    (ConditionResult(20, "removed-guards", True, "state=UNCOMPUTED"),
     "The harness could not check whether this PR removes a safety check."),
    # Real reasons, copied from past result.json files.
    (ConditionResult(2, "review-finding>=80", True,
                     "ibl5/a.php:7 score=90; ibl5/a.php:7 score=85; bin/x:3 score=80"),
     "Code review found likely bugs that are not fixed yet: ibl5/a.php:7, bin/x:3"),
    (ConditionResult(3, "unresolved-MISSING-items", True,
                     "MISSING-PHASE: 1 — Phase 1: Add the thing (phase cites bin/x); "
                     "MISSING: tests/t.py (matrix planned a test the diff never wrote); "
                     "MISSING: bin/y (plan Critical File never appeared in the diff)"),
     "The plan asks for things this PR does not have yet: Phase 1: Add the thing; "
     "tests/t.py; bin/y"),
    (ConditionResult(7, "plan-auto-merge-hold", True,
                     "auto_merge: docs-only-ready failed: "
                     "docs-refresh:paths:.claude/skills/update-cc/SKILL.md, "
                     "docs-refresh:verdict:NOT READY (verdict source: verdict-2)"),
     "The plan lets this PR merge on its own only if it is a clean docs-only refresh, "
     "and this PR does not qualify: .claude/skills/update-cc/SKILL.md is not a docs "
     "file; the plan check said NOT READY"),
    (ConditionResult(7, "plan-auto-merge-hold", True,
                     "auto_merge: docs-only-ready failed: docs-refresh:branch:<empty>, "
                     "docs-refresh:paths:empty, docs-refresh:merge-danger:MISSING, "
                     "docs-refresh:verdict:<none> (verdict source: verdict-1)"),
     "The plan lets this PR merge on its own only if it is a clean docs-only refresh, "
     "and this PR does not qualify: the branch name is missing; it changes no files; "
     "its Merge danger line does not say it is easy to undo; the plan check gave no "
     "verdict"),
    (ConditionResult(19, "exec-mutations", True,
                     "tree not verified clean after mutation; row 2 NOT-APPLIED — match "
                     "occurs 0 times in bin/x (need exactly 1); row 3 BASELINE-FAILED — "
                     "bin/test-x exited 1 before mutation; row 4 HELD — passed"),
     "Some planned bug-planting checks did not work: the code may not be back to normal "
     "after the planted bugs; row 2 (the bug could not be planted); row 3 (the test "
     "failed before any bug was planted); row 4 (the test still passed with the bug "
     "planted). The PR body's mutation section has the detail."),
    (ConditionResult(20, "removed-guards", True,
                     "removed-guards:UNJUSTIFIED:catch@ibl5/classes/A.php#b2eb25e1; "
                     "removed-guards:UNJUSTIFIED:catch@ibl5/classes/A.php#0df54b62; "
                     "removed-guards:UNLISTED:command-guard@bin/fleet-status#58bc4462"),
     "This PR removes safety checks, and the PR body does not explain a command-exists "
     "check in bin/fleet-status; and the PR body explains these too briefly: an error "
     "catch in ibl5/classes/A.php."),
    (ConditionResult(20, "removed-guards", True, "removed-guards:MALFORMED"),
     "The PR body's list of removed safety checks could not be read."),
])
def test_variant_reasons(cond, want):
    assert hold_text.plain_hold(cond) == want


def test_condition_19_plan_checks_wording():
    cond = ConditionResult(19, "exec-mutations", True,
                           "Plan checks CI job concluded FAILURE; "
                           "could not add the plan-checks label")
    assert hold_text.plain_hold(cond) == (
        "The Plan checks CI job has not passed, or post-plan could not confirm its block: "
        "Plan checks CI job concluded FAILURE; could not add the plan-checks label.")


def test_self_governing_merge_danger_has_its_own_words():
    c = ConditionResult(18, "merge-danger", True, "state=SELF-GOVERNING")
    assert hold_text.plain_hold(c) == (
        "This PR changes the auto-merge machinery itself, so a person should merge it.")


def test_every_held_merge_danger_state_has_distinct_words():
    """Each held state classify_body can return gets its own line. A new state with no
    words falls through to the MALFORMED line, and this test goes red."""
    import inspect
    from harness import merge_danger
    doc = inspect.getdoc(merge_danger.classify_body) or ""
    states = re.findall(r'"([A-Z-]+)"', doc.split("\n", 1)[0])
    held = [s for s in states if s != "TWO-WAY"]
    assert {"ONE-WAY", "SELF-GOVERNING", "MISSING", "MALFORMED", "UNREADABLE"} <= set(held)
    words = {s: hold_text.plain_hold(ConditionResult(18, "merge-danger", True, f"state={s}"))
             for s in held}
    fallback = words["MALFORMED"]
    for s in held:
        if s not in ("MALFORMED", "UNREADABLE"):
            assert words[s] != fallback, f"{s} has no plain words of its own"


def test_unknown_condition_falls_back_to_name_and_reason():
    c = ConditionResult(99, "new-thing", True, "state=X")
    assert hold_text.plain_hold(c) == "new-thing — state=X"


def test_shell_reader_still_parses_the_plain_block():
    decision = _all_blocked()
    body = _held_sticky(decision)
    out = subprocess.run(
        ["bash", "-c", 'source "$1"; hp_verdict_holds "$2" ok', "_", str(HELD_PRS), body],
        capture_output=True, text=True, check=True).stdout.split()
    assert out == [f"verdict-hold:{c.number}" for c in decision.holds]
    assert len(out) == 20


def test_self_governing_hold_text_is_not_unreadable():
    fix = pathlib.Path(__file__).parent / "fixtures" / "merge_danger"
    for name in ("pr3829-merge-danger-line.txt", "pr3832-merge-danger-line.txt"):
        state = md.classify_body((fix / name).read_text(encoding="utf-8").rstrip("\n"))
        words = hold_text.plain_hold(ConditionResult(18, "merge-danger", True, f"state={state}"))
        assert "auto-merge machinery itself" in words
        assert "could not be read" not in words
