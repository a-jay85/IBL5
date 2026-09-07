"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

import re

from .state import PlanInfo


def _contract_items(plan: PlanInfo, changed_files: list[str],
                    phase5_status: str | None) -> list[str]:
    """Returns unresolved `UNMET-CONTRACT:` items for a plan's autonomy contract.

    Empty for every plan that declares neither `stop_condition:` nor `evidence:`,
    which is every plan authored before the fields existed.
    """
    if not plan.stop_condition and not plan.evidence and not plan.contract_error:
        return []
    if plan.contract_error:
        # The parsed values are unusable; evaluating the other two rules against
        # them would emit misleading follow-on items.
        return [f"UNMET-CONTRACT: malformed autonomy contract — {plan.contract_error} "
                "(plan was hand-edited after bin/check-plan cleared it)"]
    items: list[str] = []
    # `!= "pass"` on purpose: unmet on "fail", on "skipped" AND on None. armable.py
    # condition (4) blocks only on "fail", so the other two arm today — narrowing
    # this to `== "fail"` removes the feature while leaving it looking present.
    if plan.stop_condition == "tests-green" and phase5_status != "pass":
        items.append("UNMET-CONTRACT: stop_condition tests-green declared but "
                     f"PHASE5_VERIFY_STATUS={phase5_status or 'none'}")
    # Exact match, not the substring test the Critical-Files loop below uses: an
    # evidence token is the machine-checked contract itself, so `bin/x` must not
    # be discharged by a diff that only touched `bin/xylophone`.
    for tok in plan.evidence:
        if not any(f == tok or f.endswith("/" + tok) for f in changed_files):
            items.append(f"UNMET-CONTRACT: evidence {tok} declared but never appeared in the diff")
    return items


def check(plan: PlanInfo, changed_files: list[str], diff_body: str = "",
          phase5_status: str | None = None) -> list[str]:
    """Returns unresolved `MISSING:` / `MISSING-FILE:` / `MISSING-METHOD:` /
    `UNMET-CONTRACT:` items (empty = clean).

    `UNMET-CONTRACT:` items are produced even when the plan has no Verification
    Matrix — a matrix-less doc/tooling plan is exactly what `evidence-present`
    exists for.

    Resolution (authoring the test / making the change / PR-comment noting the
    cut) is a downstream action; this function only detects.

    diff_body defaults to "" (fail-open): a missed caller silently no-ops the
    MISSING-METHOD loop instead of raising TypeError mid-run and aborting a live
    /post-plan. See plan Architectural trade-offs § conformance.check fail-open.
    """
    if not plan.found:
        return []
    items: list[str] = _contract_items(plan, changed_files, phase5_status)
    if not plan.has_matrix:
        return items
    joined = "\n".join(changed_files)
    for t in plan.planned_test_paths:
        if t not in joined:
            items.append(f"MISSING: {t} (matrix planned a test the diff never wrote)")
    for path, _annotation, exempt in plan.critical_files:
        if exempt:
            continue
        if path not in joined:
            items.append(f"MISSING-FILE: {path} (plan Critical File never appeared in the diff)")
    if diff_body:
        for m in plan.required_test_methods:
            if not re.search(rf"(function|def)\s+{re.escape(m)}\b", diff_body):
                items.append(f"MISSING-METHOD: {m} (plan required a test method the diff never wrote)")
    return items
