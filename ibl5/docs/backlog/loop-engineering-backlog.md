---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-07-07
---

# Loop-Engineering Backlog

**Purpose:** Catalogue changes that make the autonomous loops (automouse nightly queue, PR lifecycle, intake pipelines) more self-healing, better-measured, and safer to leave unattended. Each open entry is a candidate for a `/plan`.

**Origin:** Advisory sessions (2026-07-07): an automouse pipeline audit plus a research synthesis (Cherny's loop-engineering stages, Osmani's autonomy contracts / comprehension debt, Karpathy's verification-first autonomy). Statuses verified against `bin/automouse-run`, `bin/automouse-queue`, `bin/automouse-self-heal`, and the live queue on 2026-07-07.

**Companion to** the other backlogs in [README.md](README.md); same status taxonomy.

---

## Taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

**Automouse-readiness** (for items not ✅/🚫): same glyphs as [`ci-backlog.md`](ci-backlog.md) — 🟩 auto-mergeable · 🟦 automouse-safe, human-merge · 🟨 conditional · 🟥 not automouse-safe. (Ironic but real: changes to the loop machinery itself mostly want a human merge — a bug here burns whole nights.)

**Effort scale:** **S** — single PR, < 1 day. **M** — multi-step plan, 1–3 days. **L** — platform shift, likely needs an ADR.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 6 |
| 📋 Planned | 2 |
| ◑ Partial | 3 |
| ✅ Implemented | 1 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| L1 | Plan dependency DAG | ⬜ Open | 🟦 | M |
| L2 | Per-plan circuit breaker | ◑ Partial | 🟦 | S |
| L3 | Morning digest | ⬜ Open | 🟦 | S |
| L4 | Retro-miner | ⬜ Open | 🟥 | M |
| L5 | Master-canary between runs | ⬜ Open | 🟦 | M |
| L6 | Auto-update-branch unsticker | 📋 Planned | 🟦 | S |
| L7 | Queue-add shift-left preflight | 📋 Planned | 🟦 | S |
| L8 | Failure self-heal / requeue | ✅ Implemented | — | M |
| L9 | JSB AutoResearch loop | ⬜ Open | 🟥 | L |
| L10 | Discord intake loop | ◑ Partial | 🟦 | L |
| L11 | Comprehension-debt digest | ⬜ Open | 🟦 | S |
| L12 | Autonomy contracts in plan frontmatter | ◑ Partial | 🟦 | M |

### L1 Plan dependency DAG
**Location:** `bin/automouse-queue` — queue order is symlink mtime (`ls -1tr`); `bin/automouse-queue-reorder-ui` re-touches mtimes by hand. No `depends_on` anywhere (verified).
**Problem:** mtime order is a proxy, not a guarantee: a plan whose prerequisite PR hasn't merged can run anyway and fail or build on the wrong base.
**Suggested direction:** `depends_on:` frontmatter (plan slug or PR#); the queue holds/skips a plan whose prerequisite isn't merged, self-healing it back in once it is (L8 already has the requeue machinery).
**Risk if untouched:** Dependency hazards in every multi-plan program (observed hazard class in the 11-plan queue).
**Status (2026-07-07):** ⬜ Open — 🟦.

### L2 Per-plan circuit breaker
**Location:** `bin/automouse-run` — per-phase `timeout` caps (`MAX_IMPL_SECS`/`MAX_PP_SECS` = 3600s), outer `MAX_ELAPSED` ≈ 4h45m, `MAX_ATTEMPTS=3` then the plan is parked in `skipped/` with a report.
**Problem (was):** One runaway plan could eat the night.
**Suggested direction (residual):** Add a token-budget breaker alongside the wall-clock one — the cost data is already parsed per phase (T1 in [token-spend-backlog.md](token-spend-backlog.md)); breach parks the plan as needs-human and continues the queue.
**Risk if untouched:** A plan can stay under the time cap while burning an outsized token budget.
**Status (2026-07-07):** ◑ Partial — wall-clock + attempts breakers live; token cap absent. 🟦.

### L3 Morning digest
**Location:** `bin/automouse-run` writes per-run reports (`done`/`skipped`/`env-stop`/`error`) plus a daily costs table; nothing aggregates or notifies (verified).
**Problem:** Overnight outcomes are read by manually trawling `reports/` and `gh pr list`.
**Suggested direction:** One morning Discord DM aggregating merged / held / failed / parked + spend, reusing the existing `notify-discord` composite; replaces per-run pings rather than adding to them.
**Risk if untouched:** Slow human catch-up every morning; parked plans linger unnoticed.
**Status (2026-07-07):** ⬜ Open — 🟦 (notify surface).

### L4 Retro-miner
**Location:** Post-plan retrospectives accumulate as static per-run reports; nothing mines them (verified).
**Problem:** The learning loop is manual — recurring failure patterns become rules/memory only when a human notices.
**Suggested direction:** Weekly cron that clusters retrospectives and proposes rule/memory edits **as a PR** — the human reviews the proposed norm, never auto-applies.
**Risk if untouched:** Repeat failures that a rule would have prevented; lessons decay in unread reports.
**Status (2026-07-07):** ⬜ Open — 🟥 (rule authoring is judgment; the miner only drafts).

### L5 Master-canary between runs
**Location:** `bin/automouse-run` refreshes master between plans (fetch + `--ff-only` merge) but runs no health check; `bin/check-master-ci-green` exists as a building block.
**Problem:** After an overnight auto-merge, the next plan builds on the new master with no smoke check — a poisoned master cascades failures through every remaining plan.
**Suggested direction:** Between plans, gate on `bin/check-master-ci-green` plus a cheap local smoke (main-stack curl); on red, park the queue rather than continue. (Adjacent: `$HOME/.claude/plans/pr-canary-fast-conflict-signal.md` covers the PR-level pre-merge signal.)
**Risk if untouched:** One bad merge converts the rest of the night into cascading noise.
**Status (2026-07-07):** ⬜ Open — 🟦.

### L6 Auto-update-branch unsticker
**Location:** Plan: `$HOME/.claude/plans/update-behind-armed-prs.md` (queued) — a workflow calling GitHub's update-branch API for armed-auto-merge PRs stuck BEHIND master. No automation on disk yet (verified).
**Problem:** Armed auto-merge silently never fires when the branch falls behind; unsticking is a recurring manual morning ritual.
**Status (2026-07-07):** 📋 Planned — queued. 🟦.

### L7 Queue-add shift-left preflight
**Location:** `bin/automouse-queue` `add` runs zero preflight (verified); staleness is caught only at 2am by the impl agent, then self-heal requeues (L8). Plan: `$HOME/.claude/plans/staleness-guard-fp-fix-and-queue-check.md` (not yet queued).
**Problem:** A stale anchor costs a night when it could be fixed in 30 seconds at queue-add time, while a human is at the keyboard.
**Suggested direction (per the plan):** Run `bin/check-plan` + `bin/check-plan-staleness` at add time; also fixes known staleness-check false positives.
**Risk if untouched:** Recurring burned queue slots for trivially-fixable staleness.
**Status (2026-07-07):** 📋 Planned — not queued. 🟦.

### L8 Failure self-heal / requeue
**Location:** `bin/automouse-run` + `bin/automouse-self-heal`.
**Status (2026-07-07):** ✅ Implemented, multi-layer — environmental failures (rate-limit/auth/stall) refund the attempt and stop the run with the queue intact; genuine failures increment a per-plan attempts counter, parking to `skipped/` after 3; staleness skips write a sidecar that `automouse-self-heal` re-checks and requeues at next run start; already-merged plans move to `done/`. (Covers the original "failure-as-tuning-signal" suggestion's requeue half; feeding the failure note back into the retry's context remains a possible refinement under L4.)

### L9 JSB AutoResearch loop
**Location:** JSB sim engine + RE distribution targets; instrumentation groundwork exists (`$HOME/.claude/plans/jsb-l1-gate1-counterfactual-instrument.md` and siblings).
**Problem:** Engine-parameter tuning is human-paced despite having exactly what a self-improvement loop needs: an objective metric (simulated stat distributions vs real targets).
**Suggested direction:** An eval harness that perturbs engine params in a worktree, sims N seasons, scores distribution error, keeps only improvements, and logs each trial — overnight, hundreds of trials. Wants an ADR (metric definition, param search space, acceptance rule).
**Risk if untouched:** RE convergence stays bottlenecked on human iteration bandwidth.
**Status (2026-07-07):** ⬜ Open — 🟥 (the loop design itself is the judgment).

### L10 Discord intake loop
**Location:** `bin/bug-pipeline-tick`, `bin/bug-pipeline-cron-setup`, `bin/bug-pipeline-classify-prompt`, `bin/bug-pipeline-gather-prompt` (live); remainder of the 6-PR Discord bug pipeline program per its shared-context spec.
**Problem (was):** Bug reports ended at a human reading Discord.
**Status (2026-07-07):** ◑ Partial — gather/classify/tick machinery merged and cron-installable; the residual program (hunter stages) is tracked in its own pipeline, not re-planned here. Human checkpoints (plan review + `feat:` signoff gate) stay in place by design. 🟦.

### L11 Comprehension-debt digest
**Location:** No weekly merged-diff digest exists (verified — automouse reports are per-run, not per-week).
**Problem:** Nightly auto-merges mean human reading no longer scales with merge velocity; agent-made decisions can land unseen.
**Suggested direction:** Weekly scheduled agent digests the week's merged diffs into a short architecture-delta brief: what changed conceptually, new patterns introduced, anything decided without human eyes.
**Risk if untouched:** Comprehension debt — review capacity silently decouples from output.
**Status (2026-07-07):** ⬜ Open — 🟦.

### L12 Autonomy contracts in plan frontmatter
**Location:** Exists today as single bits and gates: `auto_merge: false` frontmatter, plan gate 14 (security/UI/schema surfaces), the `feat:` human-signoff required check.
**Problem:** The autonomy level of a plan is inferred at ship time from scattered signals rather than declared at plan time as a structured contract.
**Suggested direction:** Per-plan `stop_condition` / `evidence` frontmatter fields that post-plan verification checks mechanically — goal, scope, stop condition, evidence, per the autonomy-contract framing.
**Risk if untouched:** Autonomy increases (L1–L8) without a matching declared-contract surface.
**Status (2026-07-07):** ◑ Partial — the one-bit levers + gates exist; structured contract fields don't. 🟦.

---

## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
