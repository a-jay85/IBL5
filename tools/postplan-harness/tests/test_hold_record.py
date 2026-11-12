"""The machine-read hold record in the post-plan sticky (`fidelity.machine_hold_record`).

`bin/lib/text-hold-recheck.sh` parses this line in CI to learn which conditions held
the PR, so its shape and its position in the sticky are both contracts.
"""
from __future__ import annotations

from harness import fidelity
from harness.armable import ConditionResult
from harness.state import ArmDecision

DIFF = "a" * 40
TREE = "c" * 40


def _held(*conds):
    return ArmDecision(armed=False, conditions=list(conds))


def test_record_lists_held_numbers_and_18_state():
    holds = [ConditionResult(18, "merge-danger-line", True, "state=MISSING after 1 retry"),
             ConditionResult(20, "removed-guards", True, "removed-guards:UNJUSTIFIED:x")]
    assert (fidelity.machine_hold_record(holds, DIFF)
            == f"<!-- pr-ready-holds: v1 diff={DIFF} held=18:MISSING,20 -->")
    one_way = [ConditionResult(18, "merge-danger-line", True, "state=ONE-WAY")]
    assert (fidelity.machine_hold_record(one_way, DIFF)
            == f"<!-- pr-ready-holds: v1 diff={DIFF} held=18:ONE-WAY -->")
    no_state = [ConditionResult(18, "merge-danger-line", True, "")]
    assert fidelity.machine_hold_record(no_state, DIFF).endswith(" held=18:UNKNOWN -->")


def test_record_omitted_without_held_decision_or_diff_id():
    holds = [ConditionResult(20, "removed-guards", True, "removed-guards:UNJUSTIFIED:x")]
    assert fidelity.machine_hold_record(holds, "a" * 39) == ""
    assert fidelity.machine_hold_record(holds, "") == ""
    assert fidelity.machine_hold_record(holds, "A" * 40) == ""
    assert fidelity.machine_hold_record([], DIFF) == ""


def _sticky(decision, diff_id=DIFF):
    fid = {"verdict_1": "READY", "error_kind": None, "reviewed_tree": TREE,
           "remediation_sha": None, "verdict_2": None, "reviewed_tree_2": None}
    return fidelity.compose_sticky(
        "REBASE=clean (HEAD already contains origin/master)",
        "CI: local verification pass; GitHub checks are watched after this comment",
        fid, decision,
        ["**What changed:** a thing", "**Why:** a reason", "**Watch:** a page",
         "**Touches:** a file", "**Machine-authored fixes:** none"],
        "finding one", "READY", diff_id=diff_id)


def test_record_sits_in_audit_trail_marker_last():
    decision = _held(ConditionResult(20, "removed-guards", True,
                                     "removed-guards:UNJUSTIFIED:x"))
    lines = _sticky(decision).splitlines()
    rec = [i for i, ln in enumerate(lines) if ln.startswith("<!-- pr-ready-holds:")]
    assert len(rec) == 1
    diff_at = lines.index(f"**Reviewed diff:** {DIFF}")
    assert rec[0] > diff_at
    assert lines[rec[0]] == f"<!-- pr-ready-holds: v1 diff={DIFF} held=20 -->"
    assert not any(ln.startswith("- (") and ln == lines[rec[0]] for ln in lines)
    # The held block (`Auto-merge held:` then contiguous `- ` bullets) never holds the record.
    held_at = lines.index("Auto-merge held:")
    j = held_at + 1
    while j < len(lines) and lines[j].startswith("- "):
        j += 1
    assert not held_at < rec[0] < j
    non_empty = [ln for ln in lines if ln.strip()]
    assert non_empty[-1] == fidelity.STICKY_MARKER == "<!-- pr-ready-verdict -->"


def test_armed_sticky_carries_no_record():
    decision = ArmDecision(armed=True, conditions=[])
    assert "<!-- pr-ready-holds:" not in _sticky(decision)
    held = _held(ConditionResult(20, "removed-guards", True, "x"))
    assert "<!-- pr-ready-holds:" not in _sticky(held, diff_id="")
