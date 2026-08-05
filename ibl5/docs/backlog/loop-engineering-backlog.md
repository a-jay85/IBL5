---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, plan decomposition/tier-routing machinery, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-08-05
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
| L9 | JSB AutoResearch loop | ✅ Implemented | — | L |
| L10 | Discord intake loop | ◑ Partial | 🟦 | L |
| L11 | Comprehension-debt digest | ⬜ Open | 🟦 | S |
| L12 | Autonomy contracts in plan frontmatter | ◑ Partial | 🟦 | M |
| L13 | Per-phase impl-model routing | ✅ Implemented | — | M |
| L14 | Escalate-on-retry (Sonnet-first, just-in-time Opus) | ✅ Implemented | — | S |
| L15 | Sonnet-recipe completeness lint | ✅ Implemented | — | S |
| L16 | Context-budget gate v2 (work-size proxies + measured calibration) | 📋 Planned | 🟦 | M |
| L17 | Shared-context artifact for multi-plan splits | ✅ Implemented | — | S |
| L18 | Tier-default correction (`impl_model:` fails open to Opus) | ✅ Implemented | — | S |
| L19 | Weekly product-analytics review | ⬜ Open | 🟦 | M |
| L20 | post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6) | ⬜ Open | 🟥 | M |
| L21 | Phase 5.0 parsers fail-open on an unclosed code fence (conformance check covers nothing) | ⬜ Open | 🟥 | S |
| L22 | Sweep queue-vs-review disposition gates across other skills/scripts | ⬜ Open | 🟦 | S |
| L23 | sim-recap degraded path emits no Discord signal; qctx() failure ships roster-blind with CI green | 📋 Planned | 🟦 | S |
| L24 | Phase 5.0 conformance is path-level only; planned method names absent from diff pass undetected | 📋 Planned | 🟥 | S |
| L25 | CI-wiring gap: matrix CLI-executable rows may live in jobs the PR's own path filters never trigger | 📋 Planned | 🟥 | S |
| L26 | Gate 15 never examines silent-fallback paths when the hold is security-justified | 📋 Planned | 🟥 | S |
| L27 | /post-plan should sweep Out-of-Scope deferral phrases and open a backlog entry per hit | ⬜ Open | 🟥 | S |

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
**Suggested direction:** Between plans, gate on `bin/check-master-ci-green` plus a cheap local smoke (main-stack curl); on red, park the queue rather than continue. (Adjacent: `$HOME/claude-plans/pr-canary-fast-conflict-signal.md` covers the PR-level pre-merge signal.)
**Risk if untouched:** One bad merge converts the rest of the night into cascading noise.
**Status (2026-07-07):** ⬜ Open — 🟦.

### L6 Auto-update-branch unsticker
**Location:** `.github/workflows/update-behind-prs.yml` — scheduled every 15 min; calls the GitHub `update-branch` API for armed auto-merge PRs stuck BEHIND master. ADR-0081 records the CI_PAT token strategy, merge-vs-rebase decision, and loop-safety design.
**Status (2026-07-10):** ✅ Implemented — merged PR #1390.

### L7 Queue-add shift-left preflight
**Location:** `bin/automouse-queue` `add` runs zero preflight (verified); staleness is caught only at 2am by the impl agent, then self-heal requeues (L8). Plan: `$HOME/claude-plans/staleness-guard-fp-fix-and-queue-check.md` (not yet queued).
**Problem:** A stale anchor costs a night when it could be fixed in 30 seconds at queue-add time, while a human is at the keyboard.
**Suggested direction (per the plan):** Run `bin/check-plan` + `bin/check-plan-staleness` at add time; also fixes known staleness-check false positives.
**Risk if untouched:** Recurring burned queue slots for trivially-fixable staleness.
**Status (2026-07-07):** 📋 Planned — not queued. 🟦.

### L8 Failure self-heal / requeue
**Location:** `bin/automouse-run` + `bin/automouse-self-heal`.
**Status (2026-07-07):** ✅ Implemented, multi-layer — environmental failures (rate-limit/auth/stall) refund the attempt and stop the run with the queue intact; genuine failures increment a per-plan attempts counter, parking to `skipped/` after 3; staleness skips write a sidecar that `automouse-self-heal` re-checks and requeues at next run start; already-merged plans move to `done/`. (Covers the original "failure-as-tuning-signal" suggestion's requeue half; feeding the failure note back into the retry's context remains a possible refinement under L4.)

### L9 JSB AutoResearch loop
➜ L9 JSB AutoResearch loop — ✅ Implemented (2026-07-23): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
✅ Implemented (2026-07-15) — see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L16 Context-budget gate v2 (work-size proxies + measured calibration)
**Location:** `bin/check-plan` gate `[C]` (≥ 500 lines OR ≥ 12 numbered phases — thresholds hand-set once from the 2026-07-07 automouse-corpus audit); the T1 per-phase cost rows carry no peak-context column.
**Problem:** Two blind spots. (1) Plan size ≠ work size: a 100-line plan phase saying "sweep every call site" triggers a marathon implementation the gate can't see, while a reference-heavy plan false-trips and gets papered over with a `context-budget:` marker. (2) No feedback loop: nothing re-checks the thresholds as plan style evolves, so the gate drifts from the dumb-zone reality it proxies.
**Suggested direction:** (a) Add work-size proxies — Verification-Matrix row count, Critical-Files change-target count, and sweep-verb detection ("all call sites", "every occurrence") in a phase without a delegation packet. (b) Log peak context tokens per impl run into the T1 ledger (the stream-json usage events already carry them) and add a report correlating plan proxies against measured peaks — recalibrate thresholds from data, and flag any run breaching ~150K as a Step 2.5 split miss for the retro.
**Risk if untouched:** Dumb-zone breaches keep happening under the gate's radar, and the thresholds stay a one-shot guess.
**Status (2026-07-14):** 📋 Planned — plan slug `context-budget-gate-v2`; ships check-plan [C] proxy counts, [W] sweep-verb advisory, stream-filter peak_ctx tracking, and costs.md Peak Ctx column.

### L17 Shared-context artifact for multi-plan splits
✅ Implemented (2026-07-11) — see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L18 Tier-default correction (`impl_model:` fails open to Opus)
➜ L18 Tier-default correction — ✅ Implemented (2026-07-16): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L19 Weekly product-analytics review
**Location:** `ibl5/migrations/154_create_ibl_events.sql` + `ibl5/classes/EventLog/` write request events to `ibl_events` (PR #1425); nothing reads them. Closest existing shape is `.github/workflows/log-review.yml` + `bin/log-fetch-prod` — a Sunday cron that SSHes to prod, aggregates, and DMs the owner via `.github/actions/notify-discord`. That pipeline is pure shell/awk with **no LLM step**, and there is no Claude-in-CI pattern anywhere in the repo (verified 2026-07-24: every `claude -p` — `bin/docfix-run`, `bin/post-plan-now`, `bin/automouse-run` — is detached launchd on the owner's Mac, on the subscription).
**Problem:** Real-user behavior is captured but never reviewed, so product decisions stay unmeasured. Same class as L4 (retro-miner) and L11 (comprehension-debt digest) — a weekly agent digests accumulated evidence and proposes improvements — but sourced from user analytics rather than the repo's own output.
**Suggested direction:** `bin/events-fetch-prod` (example) mirroring `bin/log-fetch-prod`: SSH → SQL aggregate over the segmented event view → ~1–2K-token digest; then a scheduled Claude run that reads the digest and drafts recommendations, delivered as a Discord DM. Scheduling fork to resolve at plan time: **launchd on the Mac** (free on the subscription, matches the three existing `claude -p` runners, only fires when the Mac is awake — `caffeinate -s` is how the others handle that) vs **GitHub Actions + `ANTHROPIC_API_KEY`** (always runs; new secret, and the first API-metered surface in the repo). Launchd is the presumptive default.
**Blocked by:** the `ibl_events` enrichment work planned 2026-07-24 (no PR number yet at filing time; traffic segmentation + `session_id` + `http_status` + domain events + route-name canonicalization). Prod measurement on 2026-07-24 over 2026-07-14→24: 13,885 rows, of which only **545 authenticated** across 17 GMs (~3 events/GM/day); the rest is unattributable — no username and no session id, so it can't be tied to a person or a visit. Fired today the review would read bare pageviews and report that GMs look at team pages. Wants 3–4 weeks of enriched data first.
**Risk if untouched:** Analytics accrue as write-only overhead — the exact failure mode [ADR-0016](../decisions/0016-remove-duckdb-analytics.md) removed a previous analytics layer for.
**Status (2026-07-24):** ⬜ Open — 🟦 (notify surface; human reads the recommendations, nothing auto-applies). (discovered 2026-07-24 during PR #1425 analytics review)

### L20 post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6)
**Location:** `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` (arm condition 6: `depends-on-merge-order`) and `.claude/skills/post-plan/SKILL.md:93` (prescribing `Depends-on: #<n>` as the alternative to `--base` stacking in this squash-merge repo).
**Problem:** Arm condition (6) reads the live PR body via `gh pr view` and refuses to arm auto-merge until every PR named on a `Depends-on: #<n>` line is merged. `SKILL.md:93` prescribes `Depends-on:` as the correct alternative to `--base` when the repo squash-merges (a squash collapses the parent's commits, so a stacked child's branch carries pre-squash commits that conflict on auto-retarget). Observed 2026-07-29 on PR #1734 (`fence-parity-guard`): `Depends-on: #1715` was added as line 1 of the body. A later post-plan run rewrote that PR body wholesale; `gh pr view 1734 --json body` then returned a body starting `## Summary` with no `Depends-on:` line, so condition (6) evaluated `blocked=False` and #1734 armed and merged ahead of its declared dependency (commit `1b8249f4f7a651fb78b8e8bc3d60b7af25b460a4`). Effect was harmless this time only because the branch already contained #1715's commits. The structural problem: the same pipeline that reads the `Depends-on:` marker also overwrites the text carrying it — the prescribed alternative to `--base` is silently unreliable as a dependency declaration.
**Suggested direction:** (a) Make body rewrites preserve/re-emit any existing `Depends-on:` lines before overwriting. (b) Move the dependency declaration somewhere the pipeline does not overwrite (a label, or plan frontmatter `depends_on:` — see **L1**, which proposes exactly this field for queue ordering). (c) Have condition (6) read from a source other than the mutable PR body. This needs design; do not pick a direction ad-hoc (touches a `.claude/skills` ship-pipeline invariant per `.claude/rules/work-triage.md` § Ad-hoc safety mirror — wants a `/plan`).
**Blocked by:** peer session active on branch `postplan-arm-unresolved-findings`; coordinate before touching arm conditions to avoid duplicating work.
**Risk if untouched:** Silent merge-order violations in future stacked-plan programs where the parent branch is not yet in the child's commit history.
**Status (2026-07-29):** ⬜ Open — 🟥 (ship-pipeline invariant; loop-machinery changes should default to `auto_merge: false`). (discovered 2026-07-29 during PR #1734 fence-parity-guard)

### L21 Phase 5.0 parsers fail-open on an unclosed code fence (conformance check covers nothing)
**Location:** `tools/postplan-harness/harness/planfile.py` — `parse_matrix` and `parse_critical_files` both route through `_strip_fenced`; see the `parse_matrix` docstring on branch `matrix-fence-strip` for the full exposition. `bin/check-plan:477` (`cf_fence_unbalanced`) and its gate comment at `bin/check-plan:470`.
**Problem:** `_strip_fenced` is a line-level fence state machine: when a code fence opener has no matching closer, every subsequent line is treated as inside the fence and is swallowed. Both `parse_matrix` and `parse_critical_files` call it, so on a plan with an unclosed fence they return empty collections (`planned_test_paths == []`, `critical_files == []`). Phase 5.0's conformance check then has nothing to evaluate and passes silently — arm condition (3) never fires. That is a fail-open: the plan→diff conformance check is skipped entirely, with no signal that anything was skipped. `bin/check-plan` gate `[F]` does reject unbalanced fences, but its own comment (`bin/check-plan:470`) states it "only ever sees newly-authored plans" — a plan authored before that gate landed can still reach Phase 5.0 undetected. Live exposure is currently zero: 1 of 260 plans in `~/claude-plans/` is unbalanced (`sonnet-recipe-completeness-lint.md`) and it already shipped, so this is latent, not actively firing.
**Suggested direction:** Have the harness report an unbalanced fence as `INDETERMINATE` rather than an empty list, so Phase 5.0 holds loudly instead of passing quietly — mirroring how condition (3) already treats unresolved items. An alternative is to run `cf_fence_unbalanced` from the post-plan path as a pre-parse guard. This changes a Phase 5.0 contract and wants a `/plan`; do not fix ad-hoc (`.claude/skills` ship-pipeline invariant per `.claude/rules/work-triage.md` § Ad-hoc safety mirror).
**Risk if untouched:** A malformed plan that slips past `bin/check-plan` [F] silently voids the conformance gate at ship time, with no indication that it did so.
**Status (2026-07-29):** ⬜ Open — 🟥 (ship-pipeline invariant; loop-machinery changes should default to `auto_merge: false`). (discovered 2026-07-29 during matrix-fence-strip; documented in `parse_matrix` docstring on that branch)

### L22 Sweep queue-vs-review disposition gates across other skills/scripts
**Location:** `.claude/skills/` — every other skill or script carrying `--queue` vs `--implement` (or equivalent queue-vs-human-review) disposition guidance. `.claude/skills/plan-prompt/SKILL.md` Step 5 item 2 is already converted (this entry's originating PR). Candidates to enumerate: `.claude/skills/plan/SKILL.md` (Step 4 gate 14 and its `auto_merge` guidance), `.claude/skills/post-plan/SKILL.md` (the Phase 6.5 arm conditions), and `bin/plan-now`'s disposition coda. **Peer conflict:** `.claude/skills/plan/SKILL.md` was owned by branch `plan-frontmatter-scaffold-strip` during the originating PR's implementation window, so the sweep is deferred until that branch merges.
**Problem:** The blast-radius predicate now governing `/plan-prompt`'s gate — reach for `--implement` if and only if the work triggers `plan-architect-xhigh` — applies equally to every other queue-vs-review gate in the pipeline. Those gates still key on subjective self-assessment (novelty, felt scope, drafting-session confidence), which is unfalsifiable and fires hardest exactly when the deciding session has the least information. Left alone, each gate re-accretes its own carve-outs on its next edit and the pipeline ends up holding several mutually inconsistent answers to one question.
**Suggested direction:** Once `plan-frontmatter-scaffold-strip` has merged, enumerate every disposition gate under `.claude/skills/` and `bin/`, apply the same three-trigger predicate (security surface or trust boundary; destructive or schema-tightening migration; `.claude/skills` ship-pipeline invariant — authoritative in `.claude/rules/agent-tiering.md` § Tiers), and ship it as one `chore:` PR. **Explicit non-target:** `.claude/rules/work-triage.md` § Ad-hoc safety mirror is deliberately out of scope — it answers a different question (should this work be planned at all, where a UI/UX or subjective-judgment surface is a legitimate trigger), not whether a human reads the plan before it implements. Do not fold it in.
**Risk if untouched:** The predicate lives in one skill while its peers keep subjective carve-outs, so the queue-vs-review decision stays inconsistent across the pipeline and each gate drifts independently.
**Status (2026-07-30):** ⬜ Open — 🟦 (the design is already resolved by the originating PR, so the sweep itself is mechanical; human-merge per this doc's burn-down default for loop-machinery changes). (discovered 2026-07-30 during plan-prompt-blast-radius-disposition)

### L23 sim-recap degraded path emits no Discord signal; qctx() failure ships roster-blind with CI green
*(discovered 2026-07-31 during #1753)*
**Location:** `bin/sim-recap-tick` (calls `qctx()`; on failure logs `WARNING` to launchd only and continues with `{}`); `ibl5/classes/Discord/Discord.php` (Discord class surface); `bin/bug-pipeline-tick` + `bin/lib/bug-pipeline-gh.sh` (existing pattern to copy).
**Problem:** When `qctx()` fails, the recap ships roster-blind. CI stays green — the fix can no-op in prod indefinitely with no visible signal. Only a human reading launchd logs would notice. Also: Block 8's "authoritative" header always emits followed by bare `{}`, so the documented roster-blind mode (Block 8 omitted) is unreachable in prod.
**Suggested direction:** Emit a Discord signal on `qctx()` failure, copying the `bin/bug-pipeline-tick` + `bin/lib/bug-pipeline-gh.sh` pattern. Also decide Block 8's empty-`{}` behavior at plan time.
**Risk if untouched:** A qctx() failure in prod is undetectable until a GM notices the recap is wrong — the exact failure mode PR #1753 was written to fix.
**Closes gap:** #9 from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Status (2026-07-31):** 📋 Planned — `~/claude-plans/sim-recap-degraded-discord-alert.md` (written 2026-07-31). Not yet implemented. 🟦.

### L24 Phase 5.0 conformance is path-level only; planned method names absent from diff pass undetected
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/_phase-5-final-verification.md` — greps for the test *file path* from the Verification Matrix (`grep -qF "$T" /tmp/post-plan-changed-$PPID`), not individual method names. `.claude/review-shared/_plan-verification.md`.
**Problem:** When an implementer substitutes weaker test methods for plan-specified ones, Phase 5.0 passes — the file path appeared in the diff. This is how 4 of the 5 plan-specified test cases in PR #1753 were replaced without triggering a `MISSING:` signal. Sibling entry L21 covers the fail-open from unclosed fences; this entry covers the fail-open from path-only conformance.
**Suggested direction:** Extend Phase 5.0 to extract planned test method names from the matrix and phase bodies and emit `MISSING:` for any method absent from the diff. *Confirmed uncovered by #1665/#1667/#1668/#1714 (dedup 2026-07-31).* Route: one `/plan` with L25/L26 (C2/C3), `plan-architect-xhigh` (ship-pipeline invariant), `auto_merge: false`.
**Risk if untouched:** Any future implementer can substitute weaker tests than the plan specified; CI stays green and post-plan conformance passes silently.
**Closes gap:** root cause of gaps #6 and #7 (and the general weakened-test class) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-07-31):** 📋 Planned — `~/claude-plans/plan-c-verification-conformance.md` (written 2026-07-31; covers L24+L25+L26 as one PR). 🟥.

### L25 CI-wiring gap: matrix CLI-executable rows may live in jobs the PR's own path filters never trigger
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/_phase-5-final-verification.md`; `.github/workflows/tests.yml` path-filter coupling (see ci-backlog 6.1 for the structural fix).
**Problem:** A `CLI-executable / post-impl` matrix row can be "wired into CI" on paper but in a job whose path filter the PR never triggers. Neither gate 15 nor gate 16 asks whether the CI job actually fires on the PR's changed paths. Matrix rows 11 and 20 in PR #1753 were one-shot commands that provided zero permanent regression protection.
**Suggested direction:** Add a residual check: for each `CLI-executable` matrix row, either (a) the row's CI job is triggered by the PR's path filter, or (b) the row is explicitly marked `one-shot`. *Dedup completed 2026-07-31: uncovered by #1668/#1665/#1667/#1714 — #1668 executes CLI matrix cells locally once more but covers neither half of the CI-wiring question.* Route: one `/plan` with L24/L26 (C1/C3), `plan-architect-xhigh`, `auto_merge: false`.
**Risk if untouched:** A test that appears in a CI job but whose job is never triggered by the PR's path filters provides zero coverage — indistinguishable from a wired test until examined.
**Closes gap:** #4 (meta-tooling half, complementary to ci-backlog 6.1) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-07-31):** 📋 Planned — `~/claude-plans/plan-c-verification-conformance.md` (written 2026-07-31; covers L24+L25+L26 as one PR). 🟥.

### L26 Gate 15 never examines silent-fallback paths when the hold is security-justified
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/plan/SKILL.md` Step 4 gate 15 (loud-failure signal lever list); the gate currently fires only when the hold is justified by a *verification gap*.
**Problem:** Gate 15 includes "a loud-failure signal replacing a silent fallback" in its lever list. It did not engage for PR #1753 because the hold was justified on gate-14b security-surface grounds — not a verification gap. The `qctx()` failure → `WARNING` → `{}` → roster-blind-recap path was never examined. The detectability concern is orthogonal to what justifies the hold.
**Suggested direction:** Extend gate 15 (do not add a new gate — meta-tooling-bar.md extend-before-add). Add a named trigger: a new silent-fallback / degraded path in a synchronous sim path or a `bin/*-tick` script requires a loud signal (Discord), independent of what justifies the hold. *Confirmed uncovered by #1665/#1667/#1668/#1714 (dedup 2026-07-31).* Route: one `/plan` with L24/L25 (C1/C2), `plan-architect-xhigh`, `auto_merge: false`.
**Risk if untouched:** Future silent-fallback paths in ship-adjacent code will not be examined at plan time when the plan hold is security-motivated rather than verification-gap-motivated.
**Closes gap:** #9 (meta-tooling — prevents future versions of this class) from `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`
**Dedup:** reconciled 2026-07-31 — uncovered by #1668 / #1665 / #1667 / #1714.
**Status (2026-07-31):** 📋 Planned — `~/claude-plans/plan-c-verification-conformance.md` (written 2026-07-31; covers L24+L25+L26 as one PR). 🟥.

### L27 /post-plan should sweep Out-of-Scope deferral phrases and open a backlog entry per hit
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/SKILL.md`; `.claude/skills/post-plan/_phase-5-final-verification.md` (candidate phase to extend).
**Problem:** 40 of 275 plans in `~/claude-plans/` contain deferral phrases ("file it separately", "its own plan", "separate PR"). Nothing captures any of them. PR #1753's own `## Out of Scope` deferred two items (B2 and a stale ADR citation) that would have evaporated without the manual audit that produced `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`.
**Suggested direction:** Post-plan sweeps the merged plan's `## Out of Scope` section for deferral phrases and opens a backlog entry (or stamped TODO in the appropriate backlog file) per hit — turning a deferral into a tracked row automatically rather than relying on a human to remember. `plan-architect-xhigh` (ship-pipeline invariant), `auto_merge: false`. Dedup resolved 2026-07-31; the C plan (`plan-c-verification-conformance`) does NOT cover this, so it stays standalone.
**Risk if untouched:** Every plan that uses `## Out of Scope` continues to evaporate its deferrals; 40 existing plans already have untracked items.
**Closes gap:** meta-tracking — prevents the D-class failure (deferral evaporation) across all future plans
**Status (2026-07-31):** ⬜ Open — not covered by the C plan; needs its own `/plan`. 🟥.

---

## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
