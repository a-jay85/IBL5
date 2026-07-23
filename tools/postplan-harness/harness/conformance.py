"""Phase 5.0 — plan→test and plan→file (Critical Files) conformance. Pure port of
_phase-5-final-verification.md's two check loops."""
from __future__ import annotations

from .state import PlanInfo


def check(plan: PlanInfo, changed_files: list[str]) -> list[str]:
    """Returns unresolved `MISSING:` / `MISSING-FILE:` items (empty = clean).

    Resolution (authoring the test / making the change / PR-comment noting the
    cut) is a downstream action; this function only detects.
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
    return items
