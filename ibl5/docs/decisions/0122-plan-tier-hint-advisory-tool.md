---
description: bin/plan-tier-hint is an advisory architect-tier pre-check for /plan Step 3; bin/test-plan-tier-hint is its test harness plus static drift assertions across the five trigger copies.
last_verified: 2026-09-07
owner: ajaynicolas
---

# ADR-0122: Advisory `bin/plan-tier-hint` for /plan Step 3 Tier Routing

**Status:** Accepted
**Date:** 2026-09-07
**Deciders:** ajaynicolas

## Context

The `/plan` Step 3 routing decision — whether to escalate to `plan-architect-xhigh`, route to `plan-architect-sonnet`, or use the default `plan-architect` — keys on a behaviour-led test: does the diff cause an input previously rejected by an executable gate to now pass? The canonical checklist in `.claude/skills/plan/SKILL.md` Step 3 check 1 is authoritative, but applying it requires judgment. The same trigger definition is replicated across five documents (`.claude/skills/plan/SKILL.md`, `.claude/skills/plan-prompt/SKILL.md`, `.claude/rules/agent-tiering.md`, `.claude/rules/work-triage.md`, and `.claude/agents/plan-architect-xhigh.md`), creating a drift risk when any copy is updated.

## Decision

Add `bin/plan-tier-hint` as an advisory (non-gating) pre-check. Given a task description and an optional comma-separated file list, it applies the behaviour-led trigger definition and prints one of `xhigh`, `default`, or `sonnet` to stdout; `--explain` appends the matching reason. The script exits 0 in all three cases. Nothing consumes its exit status as a gate — the canonical checklist and the orchestrating session own the final call.

Add `bin/test-plan-tier-hint` as the test harness, covering 15 fixture cases that pin the decision procedure (a gate path alone never escalates; a gate path plus a weakening verb does; directory membership is never the trigger). The harness also carries static drift assertions that verify the five copies of the trigger still agree on the critical phrases.

## Alternatives Considered

- **Make `bin/plan-tier-hint` a hard gate** — block `/plan` from proceeding until the hint confirms a tier. Rejected: the checklist requires judgment that keyword matching cannot substitute for; the orchestrating session must own the final call.
- **No tool at all** — rely solely on the canonical prose checklist. Rejected: a mechanical second opinion reduces misroutes where the trigger boundary is ambiguous, and the drift assertions are only possible via a test harness.

## Consequences

- Positive: the orchestrating session can get a mechanical second opinion on a tier call before spawning the architect, reducing misroutes at the behaviour/directory boundary.
- Positive: static drift assertions in `bin/test-plan-tier-hint` catch divergence across the five trigger copies in CI.
- Negative: adds two new `bin/` scripts (131 and 161 lines respectively) to the meta-tooling surface.

## References

- `bin/plan-tier-hint` — the advisory tool
- `bin/test-plan-tier-hint` — the test harness and static drift assertions
- `.claude/skills/plan/SKILL.md` — canonical Step 3 check 1 checklist (authoritative)
- `.claude/rules/agent-tiering.md` — Tiers table, points at the canonical check
- `.claude/rules/work-triage.md` — safety mirror, behaviour-led gate trigger wording
- `.claude/skills/plan-prompt/SKILL.md` — plan-prompt tier-routing section
- `.claude/agents/plan-architect-xhigh.md` — agent def description
