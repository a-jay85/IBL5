"""Phase 6.5 — the sixteen ported arming conditions as pure, typed functions.

The numbers track the SKILL's condition numbers, not this list's position. The set is
now {1..16} with no gaps: condition (11) shells out to
`bin/lib/pr-armable.sh::pr_unresolved_findings_hold` for unresolved review-thread
findings, condition (14) reads the run-local conflict-resolved flag, condition (15)
probes for already-red CI checks at arm time, and condition (16) checks the
pre-push local meta-check gate.

Faithful port of .claude/skills/post-plan/_phase-6.5-arm-auto-merge.md +
bin/lib/pr-armable.sh. Historically each condition was a separate model-driven
Bash turn; here the whole AND-of-not-blocked set is one deterministic pass.

Fail-closed: any indeterminate input BLOCKS (a false HOLD costs one manual
merge; a false ARM ships unreviewed code).
"""
from __future__ import annotations

import os

import re
from dataclasses import dataclass, field
from typing import Callable, Optional

from .state import ArmDecision, Classification, ConditionResult, Finding

FEAT_RE = re.compile(r"^feat(\([^)]*\))?!?:", re.IGNORECASE)
GOLDEN_PATH = "engine/internal/sim/testdata/golden.json"
SENTINEL_RE = re.compile(r"^\s*No manual testing needed", re.IGNORECASE)
DEP_LINE = re.compile(r"^\s*depends-on:", re.IGNORECASE)

# The harness's own row shape, shared with manual_rows.render_rows
# (`- [ ] **Row {n}** — …`), manual-rows.sh's `^\- \[[ x]\] \*\*` filter, and
# tick-rows.sh's `- [ ] **<id>**` substitution. Deliberately NOT "any - [x]":
# unrelated ticked bullets appear in real bodies and must not clear the gate.
ROW_CHECKBOX_RE = re.compile(r"^- \[([ x])\] \*\*[^*]+\*\*")

# Condition (9) deterministic hold-trigger surface (the enumerable subset; the
# bounded LLM verdict may ADD holds on top — never release one).
DESTRUCTIVE_SQL = re.compile(
    r"^\+.*\b(DROP\s+TABLE|DROP\s+COLUMN|ALTER\s+TABLE\s+\S+\s+RENAME|RENAME\s+COLUMN|TRUNCATE)\b",
    re.IGNORECASE | re.MULTILINE,
)


def _manual_section(body: str) -> list[str] | None:
    """Extract lines in the `## Manual Testing` window. Returns None when the heading is absent."""
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
        return None
    return section


def manual_testing_clearance(body: str) -> str:
    """pr_manual_testing_clearance port: CLEARED / HELD / UNKNOWN.

    Scan window: from `^## Manual Testing` to the next `^## ` line. Any body
    section the runner appends must therefore either sit BEFORE this heading or
    emit no `^#`-anchored line; classify.upsert_manual_confirmation does the
    former and classify._neutralize_headings the latter. Pinned by
    tests/test_hold_justification.py.
    """
    section = _manual_section(body)
    if section is None:
        return "UNKNOWN"
    for l in section:
        if SENTINEL_RE.match(l):
            return "CLEARED"
    return "HELD"


def meta_checks_clearance(flag_path: str, post_pr_rc: int) -> str:
    """Three-state clearance for condition (16): pre-push flag file or post-pr run.

    Flag file checked first — a pre-push failure outranks a passing post-pr run.
    """
    if os.path.exists(flag_path):
        return "HELD"
    if post_pr_rc == 0:
        return "CLEARED"
    if post_pr_rc == 1:
        return "HELD"
    return "UNKNOWN"


def all_rows_ticked(body: str) -> bool:
    """True only when the Manual Testing window holds at least one harness-shaped
    checkbox row and every one of them is ticked."""
    section = _manual_section(body)
    if section is None:
        return False
    marks = [m.group(1) for m in (ROW_CHECKBOX_RE.match(l) for l in section) if m]
    return bool(marks) and all(c == "x" for c in marks)


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
    fidelity_verdict: Optional[str] = None    # Phase 5.5 verdict word; None = never ran (blocks)
    degraded_agents: list[str] = field(default_factory=list)   # unparseable review agents
    plan_slug_drift: str = ""                 # plan adopted by slug drift -> hold
    unresolved_findings: Optional[list[str]] = None   # None = never consulted -> BLOCKS
    fidelity_verdict_2: Optional[str] = None          # re-review verdict word
    fidelity_tree_2: Optional[str] = None             # REVIEWED_TREE read off verdict 2
    current_tree: str = ""                            # git rev-parse HEAD^{tree}
    conflict_resolved: Optional[bool] = None          # None = never consulted -> BLOCKS
    failed_checks: list[str] = field(default_factory=list)  # checks in gh's fail bucket at arm time
    meta_checks_status: str = "CLEARED"               # pre-push meta-check gate; "HELD"/"UNKNOWN" blocks


def select_fidelity_verdict(v1, v2, tree2, current_tree):
    """Skill parity: verdict 2 is tree-gated, verdict 1 is NOT.

    Verdict 1 is deliberately not tree-gated. Phase 6 commits between 5.5 and 6.5 on the
    ordinary path, so gating it would newly block every PR that arms today. A verdict 2
    whose REVIEWED_TREE is absent or mismatched is stale and falls back to verdict 1.
    """
    if v2 and tree2 and tree2 == current_tree:
        return v2, "verdict-2"
    return v1, "verdict-1"


def conflict_flag_path(branch: str, tmp_dir: str = "/tmp") -> str:
    """The Python spelling of the skill's `rev-parse --abbrev-ref HEAD | tr '/:' '--'` key."""
    return os.path.join(tmp_dir,
                        "postplan-conflict-resolved-" + branch.translate(str.maketrans("/:", "--")))


def evaluate(inp: ArmInputs) -> ArmDecision:
    cs: list[ConditionResult] = []

    clearance = manual_testing_clearance(inp.pr_body)
    # Widened for the harness's deterministic tick pass: a section whose every
    # harness-shaped row is ticked clears, alongside the sentinel. Gated on HELD
    # so UNKNOWN (no `## Manual Testing` heading at all) still holds.
    ticked_clear = clearance == "HELD" and all_rows_ticked(inp.pr_body)
    cond1_held = clearance != "CLEARED" and not ticked_clear
    cs.append(ConditionResult(1, "manual-testing-clearance", cond1_held,
                              f"state={clearance}" if cond1_held else ""))

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
    if inp.degraded_agents:
        all_holds = all_holds + ["review unavailable: " + ", ".join(inp.degraded_agents)]
    cs.append(ConditionResult(9, "pr-time-safety-verdict", bool(all_holds), "; ".join(all_holds)))

    pipe = "pipeline-authored" in inp.pr_labels
    cs.append(ConditionResult(10, "pipeline-authored-floor", pipe,
                              "pipeline-authored label present" if pipe else ""))

    # Condition (11) — unresolved review-thread findings scored >= 80. The shell-out
    # lives in adapters/ghad.py::unresolved_findings; this grades its output.
    uf = inp.unresolved_findings
    if uf is None:
        r = "unresolved review-thread state not consulted — fail-closed"
    elif "unresolved-findings-api-error" in uf:
        r = "cannot verify review-thread state (GitHub API error) — fail-closed"
    elif "unresolved-findings-cap" in uf:
        r = "review-thread list hit the 100-thread page cap — fail-closed"
    elif uf:
        r = f"{len(uf)} unresolved review finding(s) scored >= 80: " + " ".join(uf)
    else:
        r = ""
    cs.append(ConditionResult(11, "unresolved-scored-findings", bool(r), r))

    # Condition (13). The number tracks the skill's condition number, not this list's
    # position: (11) now lives directly above and (12) is plan-intent fidelity. Do not
    # renumber this to 11 or 12.
    cs.append(ConditionResult(13, "plan-slug-drift", bool(inp.plan_slug_drift),
                              f"plan '{inp.plan_slug_drift}' adopted by slug drift — "
                              "confirm it is this branch's plan"
                              if inp.plan_slug_drift else ""))

    # Condition (12) — NOT (11). The number tracks the skill's condition number, not
    # this list's position: the skill's condition (11) (unresolved review-thread
    # findings) reads the GitHub review-thread API and is not ported here. The
    # harness's condition (13) above is a different condition — master's
    # plan-slug-drift hold (PR #2164) — and does not correspond to the skill's
    # (11). Do not renumber this to 11.
    # Fail-closed and additive: this can only add a hold. `None` means Phase 5.5 never
    # ran, which is indeterminate, not clean.
    selected, source = select_fidelity_verdict(inp.fidelity_verdict, inp.fidelity_verdict_2,
                                               inp.fidelity_tree_2, inp.current_tree)
    fid = (selected or "").strip()
    fid_ok = fid in ("READY", "READY WITH NOTES")
    cs.append(ConditionResult(12, "plan-fidelity-verdict", not fid_ok,
                              f"fidelity verdict={selected!r} (source: {source}); "
                              "need READY or READY WITH NOTES"
                              if not fid_ok else ""))

    # Condition (14) — this BRANCH carries a /post-plan auto-resolved rebase conflict.
    # The flag outlives the run that wrote it: SKILL.md Phase 2 creates it and nothing
    # ever deletes it, so an earlier skill run on this branch still holds here.
    if inp.conflict_resolved is None:
        r = "conflict-resolved flag not consulted — fail-closed"
    elif inp.conflict_resolved:
        r = "this branch carries a /post-plan auto-resolved rebase conflict — a human reads the resolution"
    else:
        r = ""
    cs.append(ConditionResult(14, "conflict-auto-resolved", bool(r), r))

    # Condition (15) — checks already in gh's `fail` bucket on the PR's current head.
    # FAIL-OPEN ON PENDING CHECKS: `probe_failed_checks` returning [] means "no failure
    # proven at arm time", never "all checks are green". A check that has not yet
    # reported is invisible to the probe and therefore clears (15). This condition
    # catches fast-failing checks that are already red when the arming decision runs —
    # it does NOT wait for slow checks to settle (that is Phase 7's job). An empty
    # `failed_checks` list is a deliberate pass-through, not a green signal: the only
    # correct way to clear a real hold is to fix CI and re-run, never to assume
    # absence of a probe result means success. Additive: can only ADD a hold, never
    # release one.
    cs.append(ConditionResult(15, "red-ci-check", bool(inp.failed_checks),
                              ", ".join(inp.failed_checks)))

    # Condition (16) — pre-push meta-check gate flag or post-pr run failure.
    mc = inp.meta_checks_status
    mc_blocked = mc != "CLEARED"
    cs.append(ConditionResult(16, "meta-checks", mc_blocked,
                              f"state={mc}" if mc_blocked else ""))

    return ArmDecision(armed=not any(c.blocked for c in cs), conditions=cs)
