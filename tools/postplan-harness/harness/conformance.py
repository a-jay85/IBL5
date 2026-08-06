"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

import re

from .state import PlanInfo


def check(plan: PlanInfo, changed_files: list[str], diff_body: str = "") -> list[str]:
    """Returns unresolved `MISSING:` / `MISSING-FILE:` / `MISSING-METHOD:` items (empty = clean).

    Resolution (authoring the test / making the change / PR-comment noting the
    cut) is a downstream action; this function only detects.

    diff_body defaults to "" (fail-open): a missed caller silently no-ops the
    MISSING-METHOD loop instead of raising TypeError mid-run and aborting a live
    /post-plan. See plan Architectural trade-offs § conformance.check fail-open.
    """
    if not plan.found or not plan.has_matrix:
        return []
    items: list[str] = []
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
