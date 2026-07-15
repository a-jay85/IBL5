---
description: Historical archive: completed autonomous-loop engineering entries, extracted from loop-engineering-backlog.md.
last_verified: 2026-07-15
---

# Autonomous-Loop Engineering Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../loop-engineering-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### L14 Escalate-on-retry (Sonnet-first, just-in-time Opus)
**Location:** `bin/automouse-run` — `MAX_ATTEMPTS=3`, every attempt at the same `impl_model`; genuine failures park the plan in `skipped/` after 3.
**Problem (was):** `impl_model: sonnet` adoption is throttled by its downside: a plan that turns out to need judgment burns all three attempts on the same model, then a queue slot. The rational response is conservative labeling — Opus-by-default — which is the exact spend the marker exists to avoid.
**Suggested direction (was):** On a genuine (non-environmental) failure of a Sonnet-model plan, escalate the final retry to Opus, feeding the prior attempt's failure report into the retry context. Cheap plans stay cheap; hard plans get Opus exactly when the evidence demands it. Once proven, this makes Sonnet-first safe enough to consider as the *default* for unmarked plans, inverting today's Opus-by-omission.
**Risk if untouched (was):** Gate 13(b)'s Sonnet default stays capped at "obviously mechanical" plans; every borderline plan pre-commits to Opus.
**Status (2026-07-11):** ✅ Implemented — a genuine (non-environmental) failure of any non-Opus plan escalates ONLY its final retry to Opus, fed the prior attempt's capped `.failure` report; policy in ADR-0085. Pairs with T1/T11 in [token-spend-backlog.md](token-spend-backlog.md).

### L17 Shared-context artifact for multi-plan splits
**Location:** `/plan` Step 2.5 multi-PR path (`.claude/skills/plan/SKILL.md`) — Steps 3–5 run once per unit, each plan fully self-contained; the Discord bug pipeline hand-rolled a shared-context spec file to avoid exactly this.
**Problem (was):** When a task splits into N plans, each plan-architect run and each implementation session re-derives the shared orientation (blast radius, patterns, front-loaded decisions) independently — N× the exploration spend — and each plan re-inlines the shared background, inflating it toward gate `[C]`. This is a tax on splitting, i.e. a disincentive against the very decomposition the context-budget gate demands.
**Suggested direction (was):** Formalize the pattern the Discord pipeline improvised: when Step 2.5 splits, persist Step 2's exploration pointers (`path:line` + load-bearing fact, never file bodies) plus recorded Step 3.5 decisions once to `$HOME/.claude/plans/<program>-shared-context.md`; each split plan references it instead of restating it. Plans get smaller, and each architect run becomes targeted confirmation instead of re-exploration.
**Risk if untouched (was):** Splitting stays expensive, so plans skew large — working against L16/T11.
**Status (2026-07-11):** ✅ Implemented — formalizes the shared-context artifact in `/plan`: on a Step 2.5 split, SKILL.md Steps 2/2.5/3/3.5/5 seed `$HOME/.claude/plans/<program>-shared-context.md` once and each unit references it instead of restating shared background, with matching guidance in `_architect-contract.md`. L16 and T11 remain open.

### L13 Per-phase impl-model routing
**Location:** `bin/automouse-run` (single `--model` per plan, resolved once by `bin/lib/plan-impl-model`); plans already label every phase Sonnet / Haiku / self per `.claude/skills/plan/_architect-contract.md` § Agent-tiering guidance — nothing consumes those labels at run time (verified 2026-07-08).
**Problem (was):** Model selection is whole-run: a mixed plan runs every mechanical phase at the top tier, and the only relief is a tier-boundary split (T11 in [token-spend-backlog.md](../token-spend-backlog.md)), which can't reach plans whose judgment and mechanical phases interleave.
**Suggested direction (was):** Make the in-plan tier labels binding rather than advisory: the impl orchestrator MUST delegate a Sonnet/Haiku-labeled phase as a sub-agent per its delegation packet (packet-carrying phases were already bound from 2026-06-07; the residual gap was bare sub-tier labels carrying no packet). Bulk spend moves down-tier AND out of the orchestrator's context — dumb-zone relief and tier savings from the same change. A runner-driven per-phase `claude -p` sequence with a state handoff is the heavier fallback if in-run delegation proves unreliable.
**Risk if untouched (was):** Per-phase tiering stays a plan-authoring ritual with no runtime effect; mixed plans pay top-tier for mechanical sweeps.
**Status (2026-07-11):** ✅ Implemented — in-plan sub-tier labels are now binding: `.claude/skills/plan/_architect-contract.md` requires a `### Delegate` packet or an explicit `(inline — …)` marker on every below-run-model phase, `bin/check-plan` gate `[T]` enforces it, and `bin/automouse-prompt-impl` binds each case at run time. Packet-carrying phases were already bound (2026-06-07); this closes the bare-label gap. The heavier runner-driven per-phase `claude -p` fallback was not needed.

### L2 Per-plan circuit breaker
**Location:** `bin/automouse-run` — per-phase `timeout` caps (`MAX_IMPL_SECS`/`MAX_PP_SECS` = 3600s), outer `MAX_ELAPSED` ≈ 4h45m, `MAX_ATTEMPTS=3` then the plan is parked in `skipped/` with a report.
**Problem (was):** One runaway plan could eat the night.
**Residual (token-budget breaker) — ✖ Won't do, empirically refuted.** Built as PR #1477 (`MAX_PLAN_COST_USD`, default $5.00, parks the plan in `skipped/` before postplan), then **closed unmerged 2026-07-15**. Measured against 47 `exit:` lines (2026-07-07→07-15):
- **Keys on the wrong variable.** Impl cost does not predict postplan cost — the most expensive impl in the dataset (`ibl6-retirement-1-boxscore-php-port`, $18.72) had nearly the *cheapest* postplan ($1.95), while the three most expensive postplans ($5.68, $5.41, $4.22) all rode cheap impls that never trip the cap. Correlation across 17 paired plans r ≈ 0.08 (noisy at n=17; the inversions are the robust signal).
- **Destroys completed work.** The breaker is gated on `$HANDOFF_FILE`, so it fires *only on impls that succeeded*, then `mv`s the plan away and `rm`s the handoff. At $5.00 it would discard ~$83.49 of working implementation across 7 of 28 runs to avoid postplans averaging ~$3.35; recovery means re-running impl, spending model-hours twice to save a notional figure once.
- **Wrong unit.** automouse runs on subscription auth (bare `claude -p`, no `ANTHROPIC_API_KEY`), so `cost=$X` is an API list-price equivalent, not spend. Weekly automouse load is ~10.4 model-hours — a small fraction of a Max 5x weekly cap. Long overnight impls are *desirable* use of otherwise-idle budget, not waste.
- **Doesn't bound the real constraint.** A per-plan cap can't bound a queue total: on 2026-07-11 the queue ran 5.8 model-hours (breaching a 5-hour session window) and this breaker would have fired once, saving ~14 minutes.

**Superseded by:** L18 (tier-default correction) — the measured waste is tier misallocation, not plan length.
**Status (2026-07-15):** ✅ Implemented — wall-clock + attempts breakers live and sufficient; the token-cap residual is closed as refuted (above), not deferred. Surfaced L18 as the real measured cost driver. PR #1481.
