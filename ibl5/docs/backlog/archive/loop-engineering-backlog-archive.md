---
description: Historical archive: completed autonomous-loop engineering entries, extracted from loop-engineering-backlog.md.
last_verified: 2026-07-11
---

# Autonomous-Loop Engineering Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../loop-engineering-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### L17 Shared-context artifact for multi-plan splits
**Location:** `/plan` Step 2.5 multi-PR path (`.claude/skills/plan/SKILL.md`) — Steps 3–5 run once per unit, each plan fully self-contained; the Discord bug pipeline hand-rolled a shared-context spec file to avoid exactly this.
**Problem (was):** When a task splits into N plans, each plan-architect run and each implementation session re-derives the shared orientation (blast radius, patterns, front-loaded decisions) independently — N× the exploration spend — and each plan re-inlines the shared background, inflating it toward gate `[C]`. This is a tax on splitting, i.e. a disincentive against the very decomposition the context-budget gate demands.
**Suggested direction (was):** Formalize the pattern the Discord pipeline improvised: when Step 2.5 splits, persist Step 2's exploration pointers (`path:line` + load-bearing fact, never file bodies) plus recorded Step 3.5 decisions once to `$HOME/.claude/plans/<program>-shared-context.md`; each split plan references it instead of restating it. Plans get smaller, and each architect run becomes targeted confirmation instead of re-exploration.
**Risk if untouched (was):** Splitting stays expensive, so plans skew large — working against L16/T11.
**Status (2026-07-11):** ✅ Implemented — formalizes the shared-context artifact in `/plan`: on a Step 2.5 split, SKILL.md Steps 2/2.5/3/3.5/5 seed `$HOME/.claude/plans/<program>-shared-context.md` once and each unit references it instead of restating shared background, with matching guidance in `_architect-contract.md`. L16 and T11 remain open.
