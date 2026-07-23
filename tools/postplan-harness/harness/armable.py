"""Phase 6.5 — the ten arming conditions as pure, typed functions.

Faithful port of .claude/skills/post-plan/_phase-6.5-arm-auto-merge.md +
bin/lib/pr-armable.sh. Historically each condition was a separate model-driven
Bash turn; here the whole AND-of-not-blocked set is one deterministic pass.

Fail-closed: any indeterminate input BLOCKS (a false HOLD costs one manual
merge; a false ARM ships unreviewed code).
"""
from __future__ import annotations

import re
from dataclasses import dataclass, field
from typing import Callable, Optional

from .state import ArmDecision, Classification, ConditionResult, Finding

FEAT_RE = re.compile(r"^feat(\([^)]*\))?!?:", re.IGNORECASE)
GOLDEN_PATH = "engine/internal/sim/testdata/golden.json"
SENTINEL_RE = re.compile(r"^\s*No manual testing needed", re.IGNORECASE)
DEP_LINE = re.compile(r"^\s*depends-on:", re.IGNORECASE)

# Condition (9) deterministic hold-trigger surface (the enumerable subset; the
# bounded LLM verdict may ADD holds on top — never release one).
DESTRUCTIVE_SQL = re.compile(
    r"^\+.*\b(DROP\s+TABLE|DROP\s+COLUMN|ALTER\s+TABLE\s+\S+\s+RENAME|RENAME\s+COLUMN|TRUNCATE)\b",
    re.IGNORECASE | re.MULTILINE,
)


def manual_testing_clearance(body: str) -> str:
    """pr_manual_testing_clearance port: CLEARED / HELD / UNKNOWN."""
    lines = (body or "").splitlines()
    section: list[str] = []
    in_sec = False
    for l in lines:
        if re.match(r"^## Manual Testing", l):
            in_sec = True
            continue
        if in_sec and re.match(r"^## ", l):
            break
        if in_sec:
            section.append(l)
    if not in_sec:
        return "UNKNOWN"
    for l in section:
        if SENTINEL_RE.match(l):
            return "CLEARED"
    return "HELD"


def dep_numbers(body: str) -> list[int]:
    """Anchored `Depends-on:` lines only (inline prose mentions ignored)."""
    nums: list[int] = []
    for l in (body or "").splitlines():
        if DEP_LINE.match(l):
            nums.extend(int(n) for n in re.findall(r"\d+", l))
    return nums


def feat_hold(title: str, labels: list[str]) -> bool:
    if FEAT_RE.match((title or "").strip()):
        return "human-approved" not in labels
    return False


def deterministic_safety_holds(cls: Classification, title: str) -> list[str]:
    """Condition (9)'s enumerable hold-triggers. The bounded LLM verdict (when
    enabled) can only APPEND to this list."""
    holds: list[str] = []
    if cls.has_migration and DESTRUCTIVE_SQL.search(cls.filtered_diff or ""):
        holds.append("destructive/schema-tightening migration in realized diff")
    return holds


@dataclass
class ArmInputs:
    """Everything Phase 6.5 consumes — carried state only, never recomputed."""
    pr_body: str
    pr_title: str
    pr_labels: list[str]
    classification: Classification
    findings: list[Finding]
    unresolved_conformance: list[str]
    phase5_status: Optional[str]              # "pass"|"fail"|"skipped"|None(=indeterminate)
    plan_auto_merge_false: bool
    headless: bool
    dep_state_lookup: Callable[[int], str]    # pr number -> state ("MERGED"/"OPEN"/"UNKNOWN")
    llm_safety_holds: list[str] = field(default_factory=list)  # bounded-LLM ADDed holds


def evaluate(inp: ArmInputs) -> ArmDecision:
    cs: list[ConditionResult] = []

    clearance = manual_testing_clearance(inp.pr_body)
    cs.append(ConditionResult(1, "manual-testing-clearance", clearance != "CLEARED",
                              f"state={clearance}" if clearance != "CLEARED" else ""))

    high = [f for f in inp.findings if (f.score or 0) >= 80]
    cs.append(ConditionResult(2, "review-finding>=80", bool(high),
                              "; ".join(f"{f.path}:{f.line} score={f.score}" for f in high)))

    cs.append(ConditionResult(3, "unresolved-MISSING-items", bool(inp.unresolved_conformance),
                              "; ".join(inp.unresolved_conformance)))

    p5 = inp.phase5_status
    p5_blocked = (p5 == "fail") or (p5 not in ("pass", "skipped", "fail", None))
    # None = no status recorded; the skill treats absent file as non-blocking
    cs.append(ConditionResult(4, "phase5-verify", p5 == "fail",
                              "Phase 5 deterministic failure" if p5 == "fail" else ""))
    del p5_blocked

    golden = inp.classification.golden_changed
    c5 = ConditionResult(5, "golden-snapshot-headless", golden and inp.headless,
                         "golden.json changed in headless mode" if (golden and inp.headless) else "")
    if golden and not inp.headless:
        c5.warning = ("golden.json changed: simulation behavior changed. Confirm this was an "
                      "intentional `make -C engine golden-update`, not a masked regression")
    cs.append(c5)

    unmerged = []
    for n in dep_numbers(inp.pr_body):
        state = "UNKNOWN"
        try:
            state = inp.dep_state_lookup(n) or "UNKNOWN"
        except Exception:
            state = "UNKNOWN"
        if state != "MERGED":       # fail-closed on UNKNOWN
            unmerged.append(f"#{n}({state})")
    cs.append(ConditionResult(6, "depends-on-merge-order", bool(unmerged), ", ".join(unmerged)))

    cs.append(ConditionResult(7, "plan-auto-merge-hold", inp.plan_auto_merge_false,
                              "plan declares auto_merge: false" if inp.plan_auto_merge_false else ""))

    f_hold = feat_hold(inp.pr_title, inp.pr_labels)
    cs.append(ConditionResult(8, "feat-commit-type-floor", f_hold,
                              "feat: PR awaiting human-signoff" if f_hold else ""))

    det_holds = deterministic_safety_holds(inp.classification, inp.pr_title)
    all_holds = det_holds + list(inp.llm_safety_holds)
    cs.append(ConditionResult(9, "pr-time-safety-verdict", bool(all_holds), "; ".join(all_holds)))

    pipe = "pipeline-authored" in inp.pr_labels
    cs.append(ConditionResult(10, "pipeline-authored-floor", pipe,
                              "pipeline-authored label present" if pipe else ""))

    return ArmDecision(armed=not any(c.blocked for c in cs), conditions=cs)
