"""The second-reviewer rules replayed on three real holds.

Titles, labels, head refs and file paths are literals from
`gh pr view <n> -R a-jay85/IBL5 --json title,labels,files,headRefName`, captured 2026-11-06.
"""
from harness import fidelity
from harness.armable import evaluate
from harness.gate_backtest import _ARMING_PATHS
from harness.state import Classification

from tests.test_armable import inputs

TREE = "c" * 40

PR_3733 = dict(
    pr_title="chore(postplan): keep fixer's allowed edits when it also touches a gate file",
    pr_labels=[],
    branch="postplan-fixer-keep-fixes-on-gate-edit",
    classification=Classification(files=[
        "tools/postplan-harness/harness/adapters/gitad.py",
        "tools/postplan-harness/harness/fidelity.py",
        "tools/postplan-harness/runner.py",
        "tools/postplan-harness/tests/test_fidelity_filefix.py",
        "tools/postplan-harness/tests/test_fidelity_gate_strip.py",
        "tools/postplan-harness/tests/test_fidelity_remediation.py",
        "tools/postplan-harness/tests/test_fidelity_rounds.py",
    ]))

PR_3673 = dict(
    pr_title="chore(ci): stop label events from turning a red E2E gate green",
    pr_labels=[],
    branch="e2e-label-run-gate-hole",
    classification=Classification(files=[
        ".claude/rules/ci-gotchas.md",
        ".claude/rules/visual-review-prs-detail.md",
        ".claude/rules/visual-review-prs.md",
        ".github/workflows/all-checks-settled.yml",
        ".github/workflows/e2e-tests.yml",
        ".github/workflows/update-baselines.yml",
        "bin/check-composite-contracts",
        "bin/test-check-composite-contracts",
        "ibl5/docs/decisions/0225-update-baselines-caller-workflow.md",
        "ibl5/docs/decisions/README.md",
        "ibl5/tests/ts-unit/vr-workflow-guard.test.ts",
    ]))

PR_3730 = dict(
    pr_title="chore(ci): restart the merge line when its head PR settles",
    pr_labels=[],
    branch="merge-line-restart-on-settle",
    classification=Classification(files=[
        ".github/workflows/all-checks-settled.yml",
        ".github/workflows/update-behind-prs.yml",
        "bin/check-composite-contracts",
        "bin/settle-pr-status",
        "bin/test-check-composite-contracts",
        "bin/test-ci-checks-green",
        "bin/test-merge-line-select",
        "ibl5/docs/decisions/0081-scheduled-branch-update-for-armed-behind-prs.md",
    ]))


def _seat(*numbers):
    return dict(current_tree=TREE, second_review_verdict="CLEAN", second_review_tree=TREE,
                second_review_clears={n: "safe, the diff keeps the gate's rejections" for n in numbers})


def _merge_danger(shape):
    """Condition 18 goes ONE-WAY when a captured file sits in _ARMING_PATHS."""
    hit = set(shape["classification"].files) & set(_ARMING_PATHS)
    return "ONE-WAY" if hit else "TWO-WAY"


def _armed_unless_merge_rule(decision, shape):
    assert decision.armed is (_merge_danger(shape) == "TWO-WAY")


def test_backtest_3733_plan_time_hold_shape():
    hold = dict(plan_auto_merge_false=True, plan_auto_merge_value="false",
                merge_danger_state=_merge_danger(PR_3733))
    as_recorded = evaluate(inputs(**PR_3733, **hold, **_seat(7)))
    assert not as_recorded.armed
    assert 7 in {c.number for c in as_recorded.holds}

    with_trigger = evaluate(inputs(**PR_3733, **hold, plan_hold_triggers=("gate-weakening",),
                                   **_seat(7)))
    _armed_unless_merge_rule(with_trigger, PR_3733)


def test_backtest_3673_gate_backtest_shape():
    shared = dict(merge_danger_state=_merge_danger(PR_3673), **_seat(17))
    held = evaluate(inputs(**PR_3673, gate_backtest_status="HELD", **shared))
    _armed_unless_merge_rule(held, PR_3673)

    unknown = evaluate(inputs(**PR_3673, gate_backtest_status="UNKNOWN", **shared))
    assert not unknown.armed
    assert 17 in {c.number for c in unknown.holds}


def _verdict(tmp_path, tag):
    path = tmp_path / f"{tag.strip('[]')}.md"
    path.write_text(f"## FINDINGS\n\n- [BLOCKING] {tag} Check 3: unplanned edit of a CI gate.\n\nNOT READY\n")
    return str(path)


def test_backtest_3730_owner_decision_shape(tmp_path):
    shared = dict(fidelity_verdict="NOT READY", merge_danger_state=_merge_danger(PR_3730),
                  **_seat(12))
    gate_edit = fidelity.gate_edit_only(_verdict(tmp_path, "[GATE-EDIT]"))
    cleared = evaluate(inputs(**PR_3730, fidelity_gate_edit_only=gate_edit, **shared))
    _armed_unless_merge_rule(cleared, PR_3730)

    merge_rule = fidelity.gate_edit_only(_verdict(tmp_path, "[MERGE-RULE]"))
    held = evaluate(inputs(**PR_3730, fidelity_gate_edit_only=merge_rule, **shared))
    assert not held.armed
    assert 12 in {c.number for c in held.holds}
