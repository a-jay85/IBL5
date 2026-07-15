---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, plan decomposition/tier-routing machinery, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-07-15
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
| ⬜ Open | 7 |
| 📋 Planned | 2 |
| ◑ Partial | 3 |
| ✅ Implemented | 5 |
| 🚫 Declined | 0 |

---

## Entries

| # | Title | Status | Automouse | Effort |
|---|-------|--------|-----------|-------:|
| L1 | Plan dependency DAG | ⬜ Open | 🟦 | M |
| L2 | Per-plan circuit breaker | ✅ Implemented | — | S |
| L3 | Morning digest | ⬜ Open | 🟦 | S |
| L4 | Retro-miner | ⬜ Open | 🟥 | M |
| L5 | Master-canary between runs | ⬜ Open | 🟦 | M |
| L6 | Auto-update-branch unsticker | ✅ Implemented | — | S |
| L7 | Queue-add shift-left preflight | 📋 Planned | 🟦 | S |
| L8 | Failure self-heal / requeue | ✅ Implemented | — | M |
| L9 | JSB AutoResearch loop | ⬜ Open | 🟥 | L |
| L10 | Discord intake loop | ◑ Partial | 🟦 | L |
| L11 | Comprehension-debt digest | ⬜ Open | 🟦 | S |
| L12 | Autonomy contracts in plan frontmatter | ◑ Partial | 🟦 | M |
| L13 | Per-phase impl-model routing | ✅ Implemented | — | M |
| L14 | Escalate-on-retry (Sonnet-first, just-in-time Opus) | ✅ Implemented | — | S |
| L15 | Sonnet-recipe completeness lint | ⬜ Open | 🟦 | S |
| L16 | Context-budget gate v2 (work-size proxies + measured calibration) | 📋 Planned | 🟦 | M |
| L17 | Shared-context artifact for multi-plan splits | ✅ Implemented | — | S |
| L18 | Tier-default correction (`impl_model:` fails open to Opus) | ⬜ Open | 🟦 | S |

### L1 Plan dependency DAG
**Location:** `bin/automouse-queue` — queue order is symlink mtime (`ls -1tr`); `bin/automouse-queue-reorder-ui` re-touches mtimes by hand. No `depends_on` anywhere (verified).
**Problem:** mtime order is a proxy, not a guarantee: a plan whose prerequisite PR hasn't merged can run anyway and fail or build on the wrong base.
**Suggested direction:** `depends_on:` frontmatter (plan slug or PR#); the queue holds/skips a plan whose prerequisite isn't merged, self-healing it back in once it is (L8 already has the requeue machinery).
**Risk if untouched:** Dependency hazards in every multi-plan program (observed hazard class in the 11-plan queue).
**Status (2026-07-07):** ⬜ Open — 🟦.

### L2 Per-plan circuit breaker
➜ L2 Per-plan circuit breaker — ✅ Implemented (2026-07-15): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
**Location:** `.github/workflows/update-behind-prs.yml` — scheduled every 15 min; calls the GitHub `update-branch` API for armed auto-merge PRs stuck BEHIND master. ADR-0081 records the CI_PAT token strategy, merge-vs-rebase decision, and loop-safety design.
**Status (2026-07-10):** ✅ Implemented — merged PR #1390.

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

### L13 Per-phase impl-model routing
✅ Implemented (2026-07-11) — see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L14 Escalate-on-retry (Sonnet-first, just-in-time Opus)
✅ Implemented (2026-07-11) — see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L15 Sonnet-recipe completeness lint
**Location:** `bin/check-plan` — gates cover matrix presence, forbidden tokens, staleness, and size; none check *recipe completeness*. Gate 13 judges Sonnet-eligibility by verification (a machine check fails on a wrong edit) only.
**Problem:** "Sonnet-capable" has two halves and only one is enforced: verifiable, but not *specified* — a Sonnet plan whose phases lack edit anchors, reuse notes, or self-verify commands passes `bin/check-plan`, then flails at 2am on judgment calls the plan never resolved. Those burned attempts read as model failure and push labeling back toward Opus.
**Suggested direction:** A new gate for `impl_model: sonnet` plans: every numbered phase that edits an existing file must carry an edit-anchor cue (quoted snippet per the architect contract), and every delegation packet a Self-verify line; violations name the phase. Heuristic by nature, so support a clearing marker mirroring the existing `no-adr:` / `context-budget:` pattern.
**Risk if untouched:** Sonnet-labeled plans fail for specification gaps, mis-attributed to model capability.
**Status (2026-07-08):** ⬜ Open — 🟦.

### L16 Context-budget gate v2 (work-size proxies + measured calibration)
**Location:** `bin/check-plan` gate `[C]` (≥ 500 lines OR ≥ 12 numbered phases — thresholds hand-set once from the 2026-07-07 automouse-corpus audit); the T1 per-phase cost rows carry no peak-context column.
**Problem:** Two blind spots. (1) Plan size ≠ work size: a 100-line plan phase saying "sweep every call site" triggers a marathon implementation the gate can't see, while a reference-heavy plan false-trips and gets papered over with a `context-budget:` marker. (2) No feedback loop: nothing re-checks the thresholds as plan style evolves, so the gate drifts from the dumb-zone reality it proxies.
**Suggested direction:** (a) Add work-size proxies — Verification-Matrix row count, Critical-Files change-target count, and sweep-verb detection ("all call sites", "every occurrence") in a phase without a delegation packet. (b) Log peak context tokens per impl run into the T1 ledger (the stream-json usage events already carry them) and add a report correlating plan proxies against measured peaks — recalibrate thresholds from data, and flag any run breaching ~150K as a Step 2.5 split miss for the retro.
**Risk if untouched:** Dumb-zone breaches keep happening under the gate's radar, and the thresholds stay a one-shot guess.
**Status (2026-07-14):** 📋 Planned — plan slug `context-budget-gate-v2`; ships check-plan [C] proxy counts, [W] sweep-verb advisory, stream-filter peak_ctx tracking, and costs.md Peak Ctx column.

### L17 Shared-context artifact for multi-plan splits
✅ Implemented (2026-07-11) — see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L18 Tier-default correction (`impl_model:` fails open to Opus)
**Location:** `bin/lib/plan-impl-model` — resolves a plan's impl model from line-1-anchored `impl_model:` frontmatter. Its fallthrough is `*) echo "claude-opus-4-8" ;; # opus, empty, garbled, or unknown → safe default`. `bin/lib/automouse-escalate-model` already escalates any non-Opus base → Opus on the final attempt (ADR-0085); `bin/check-plan` gate `[T]` enforces per-phase sub-tier labels but does **not** require the top-level `impl_model:` field.
**Problem:** The default is the *most expensive* model, so a plan that omits `impl_model:` silently buys Opus at ~5.4× Sonnet's per-run cost. Measured 2026-07-07→07-15 (28 impl runs): Opus 13 runs / $99.11 total / $7.62 avg vs Sonnet 15 runs / $21.33 / $1.42 avg — Opus is **82% of impl spend**. Two plans reached the queue with no `impl_model:` field at all and were silently routed to Opus: `ibl6-retirement-1-boxscore-php-port` ($18.72 — the single most expensive run in the dataset) and `mobile-target-size-a11y-sitewide` ($7.38 + $0.94 retry). Both are mechanical work (a PHP port; a sitewide a11y sweep) — textbook Sonnet jobs. That's **~$27, ~15% of the week, spent on Opus because a YAML field was absent** — nobody ever made a tier decision.
**Suggested direction:** Invert the fallthrough so the ladder starts at its bottom rung, since the Opus safety net already exists above it. Either (a) **fail closed** — `bin/check-plan` rejects a plan with no `impl_model:`, forcing an explicit tier decision at authoring time; or (b) **fail cheap** — default to `claude-sonnet-4-6` and let `automouse-escalate-model` promote to Opus on the final attempt. (b) is self-correcting and needs no new gate: a wrongly-Sonnet'd plan costs ~$1.42 + $7.62 ≈ $9 worst case versus $7.62 guaranteed today, while every correctly-Sonnet'd plan drops from $7.62 to $1.42. Prefer (b), optionally with (a)'s lint as a nudge. Keep `garbled/unknown → Opus` distinct from `missing → Sonnet`; only the *absent* case should fail cheap.
**Risk if untouched:** Every plan authored without the field silently bills top-tier for mechanical work — the exact failure `.claude/rules/agent-tiering.md` ("never default to Opus") forbids for sub-agents, reproduced in the runner's own default.
**Provenance:** Surfaced 2026-07-15 while reviewing L2/PR #1477; the cost telemetry gathered to refute the token-budget breaker located the real waste here.
**Status (2026-07-15):** ⬜ Open — 🟦.

---

## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
