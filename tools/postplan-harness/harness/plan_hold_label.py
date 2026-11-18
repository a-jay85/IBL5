"""The orange `auto-merge-held-by-plan` PR label.

A PR gets the label when its plan's line-1 frontmatter says `auto_merge: false` and
the second reviewer can never clear that hold. Then only the owner can release the
PR, so no post-plan run will ever arm auto-merge on its own.

A held plan whose `hold_trigger:` tokens are all reviewer-clearable gets no label,
because the second reviewer may clear those at arming time (armable.py
`plan_triggers_clearable`). A plan with no tokens fails closed there, so it is
labeled.

Each run syncs the label both ways. A found plan that no longer holds loses it. A
plan-blind run leaves the label alone, since it cannot read the plan.
"""
from __future__ import annotations

from .armable import CLEARABLE_PLAN_TRIGGERS

LABEL = "auto-merge-held-by-plan"
COLOR = "F9A03F"   # orange
DESCRIPTION = "The plan holds auto-merge (auto_merge: false); only the owner can merge it"


def plan_holds_for_owner(plan) -> bool:
    """True when the plan holds auto-merge and the second reviewer cannot clear it."""
    if not getattr(plan, "found", False):
        return False
    if not (getattr(plan, "auto_merge_false", False)
            or getattr(plan, "auto_merge_value", None) == "false"):
        return False
    tokens = set(getattr(plan, "hold_triggers", ()) or ())
    return not tokens or not tokens <= CLEARABLE_PLAN_TRIGGERS


def sync(gh, pr, plan, log) -> str:
    """Add or remove the label on `pr`. Best-effort: never raises. Returns the action."""
    if not pr or not getattr(plan, "found", False):
        return "skip"
    try:
        if plan_holds_for_owner(plan):
            gh.label_ensure(LABEL, COLOR, DESCRIPTION)
            ok = gh.pr_label_add_checked(pr, LABEL)
            log(f"plan-hold label: {'added' if ok else 'add failed'} on PR #{pr}")
            return "added" if ok else "add-failed"
        gh.pr_label_remove(pr, LABEL)
        return "removed"
    except Exception as exc:  # noqa: BLE001 - a label must never fail the run
        log(f"plan-hold label: error {exc!r}; ignored")
        return "error"
