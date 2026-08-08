---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, plan decomposition/tier-routing machinery, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-08-08
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
| L7 | Queue-add shift-left preflight | ✅ Implemented | — | S |
| L8 | Failure self-heal / requeue | ✅ Implemented | — | M |
| L9 | JSB AutoResearch loop | ✅ Implemented | — | L |
| L10 | Discord intake loop | ✅ Implemented | — | L |
| L11 | Comprehension-debt digest | ⬜ Open | 🟦 | S |
| L12 | Autonomy contracts in plan frontmatter | ◑ Partial | 🟦 | M |
| L13 | Per-phase impl-model routing | ✅ Implemented | — | M |
| L14 | Escalate-on-retry (Sonnet-first, just-in-time Opus) | ✅ Implemented | — | S |
| L15 | Sonnet-recipe completeness lint | ✅ Implemented | — | S |
| L16 | Context-budget gate v2 (work-size proxies + measured calibration) | ✅ Implemented | — | M |
| L17 | Shared-context artifact for multi-plan splits | ✅ Implemented | — | S |
| L18 | Tier-default correction (`impl_model:` fails open to Opus) | ✅ Implemented | — | S |
| L19 | Weekly product-analytics review | ⬜ Open | 🟦 | M |
| L20 | post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6) | ⬜ Open | 🟥 | M |
| L21 | Phase 5.0 parsers fail-open on an unclosed code fence (conformance check covers nothing) | ⬜ Open | 🟥 | S |
| L22 | Sweep queue-vs-review disposition gates across other skills/scripts | ⬜ Open | 🟦 | S |
| L23 | sim-recap degraded path emits no Discord signal; qctx() failure ships roster-blind with CI green | 📋 Planned | 🟦 | S |
| L24 | Phase 5.0 conformance is path-level only; planned method names absent from diff pass undetected | ✅ Implemented | — | S |
| L25 | CI-wiring gap: matrix CLI-executable rows may live in jobs the PR's own path filters never trigger | ✅ Implemented | — | S |
| L26 | Gate 15 never examines silent-fallback paths when the hold is security-justified | ✅ Implemented | — | S |
| L27 | /post-plan should sweep Out-of-Scope deferral phrases and open a backlog entry per hit | ⬜ Open | 🟥 | S |
| L28 | Compiled harness misses arm condition (11); primary engine enforces 10 of 11, drift is silent | ⬜ Open | 🟥 | S |
| L29 | `bin/plan-now` launchd labels collide at second granularity; concurrent fires silently lose runs | ⬜ Open | 🟦 | S |
| L30 | Concurrent `automouse-run` sessions corrupt the shared cost report (lost rows, duplicated weekly aggregate) | ⬜ Open | 🟦 | S |
| L31 | One shared daily log per calendar day: concurrent runners cross-read each other's cost, stall-kill, and env-stop signals | ⬜ Open | 🟥 | M |
| L32 | Concurrent `bin/wt-new` on the shared main checkout can lose a queued plan to a `skipped/` disposition | ⬜ Open | 🟥 | S |

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

➜ L7 Queue-add shift-left preflight — ✅ Implemented (2026-06-27): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L8 Failure self-heal / requeue
**Location:** `bin/automouse-run` + `bin/automouse-self-heal`.
**Status (2026-07-07):** ✅ Implemented, multi-layer — environmental failures (rate-limit/auth/stall) refund the attempt and stop the run with the queue intact; genuine failures increment a per-plan attempts counter, parking to `skipped/` after 3; staleness skips write a sidecar that `automouse-self-heal` re-checks and requeues at next run start; already-merged plans move to `done/`. (Covers the original "failure-as-tuning-signal" suggestion's requeue half; feeding the failure note back into the retry's context remains a possible refinement under L4.)

### L9 JSB AutoResearch loop
➜ L9 JSB AutoResearch loop — ✅ Implemented (2026-07-23): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

➜ L10 Discord intake loop — ✅ Implemented (2026-07-11): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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

➜ L16 Context-budget gate v2 — ✅ Implemented (2026-07-15): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
➜ L24 Phase 5.0 conformance is path-level only — ✅ Implemented (2026-08-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L25 CI-wiring gap: matrix CLI-executable rows may live in jobs the PR's own path filters never trigger
➜ L25 CI-wiring gap: matrix CLI-executable rows in jobs the PR's own path filters never trigger — ✅ Implemented (2026-08-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L26 Gate 15 never examines silent-fallback paths when the hold is security-justified
➜ L26 Gate 15 never examines silent-fallback paths when hold is security-justified — ✅ Implemented (2026-08-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L27 /post-plan should sweep Out-of-Scope deferral phrases and open a backlog entry per hit
*(discovered 2026-07-31 during #1753)*
**Location:** `.claude/skills/post-plan/SKILL.md`; `.claude/skills/post-plan/_phase-5-final-verification.md` (candidate phase to extend).
**Problem:** 40 of 275 plans in `~/claude-plans/` contain deferral phrases ("file it separately", "its own plan", "separate PR"). Nothing captures any of them. PR #1753's own `## Out of Scope` deferred two items (B2 and a stale ADR citation) that would have evaporated without the manual audit that produced `$HOME/claude-plans/sim-recap-testing-gaps-breakdown.md`.
**Suggested direction:** Post-plan sweeps the merged plan's `## Out of Scope` section for deferral phrases and opens a backlog entry (or stamped TODO in the appropriate backlog file) per hit — turning a deferral into a tracked row automatically rather than relying on a human to remember. `plan-architect-xhigh` (ship-pipeline invariant), `auto_merge: false`. Dedup resolved 2026-07-31; the C plan (`plan-c-verification-conformance`) does NOT cover this, so it stays standalone.
**Risk if untouched:** Every plan that uses `## Out of Scope` continues to evaporate its deferrals; 40 existing plans already have untracked items.
**Closes gap:** meta-tracking — prevents the D-class failure (deferral evaporation) across all future plans
**Status (2026-07-31):** ⬜ Open — not covered by the C plan; needs its own `/plan`. 🟥.

### L28 Compiled harness misses arm condition (11); primary engine enforces 10 of 11, drift is silent
*(discovered 2026-08-07 during PR #1733 review)*
**Location:** `tools/postplan-harness/harness/armable.py` — `evaluate()` appends `ConditionResult`s 1–10 then returns `armed=not any(c.blocked …)`. Engine selection: `bin/post-plan-now:133-140` runs the compiled harness as the **primary** engine (`./run isolated <root> --live`) and falls back to the `/post-plan` skill only on failure. Skill-side source of truth: `bin/lib/pr-armable.sh` `pr_unresolved_findings_hold` + `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` condition (11). Stale doc sites that state the old denominator: `tools/postplan-harness/README.md:25` (the row sits under **"Owned by code (deterministic)"**, so it now asserts something false), `tools/postplan-harness/README.md:42`, `tools/postplan-harness/runner.py:5`, `tools/postplan-harness/bench/make_report.py:207` and `:218`.
**Problem:** PR #1733 added arm condition (11) — an unresolved GitHub review thread carrying `<!-- score: N -->` with N >= 80 blocks auto-merge — to the skill path only, and its plan listed the harness mirror as out of scope. The result is an engine split on a fail-closed gate: the path that actually runs (the harness) enforces 10 of the 11 conditions, and `ArmInputs` carries no review-thread field at all — condition (2) is fed from `inp.findings`, i.e. only findings **this** run scored. So an unresolved >= 80 finding left by an earlier run or by a standalone `/pr-review` / `/security-audit` contributes no hold to the harness's arming decision. The harness does arm: `gh pr merge --squash --auto` is one of the six allowlisted `--live` mutations (`tools/postplan-harness/README.md` § Safety model / Installation). Worse, the drift is **silent**: `tools/postplan-harness/tests/test_armable.py` enumerates conditions by name with no count assertion, so nothing fails when the harness falls behind the skill — verified 2026-08-07 (`grep` for `len(`/`== 10`/`count` over that file returns nothing). This is an instance of the exact failure class **L27** proposes to automate away: an `## Out of Scope` deferral with no tracking row. It is filed by hand because L27 is not built yet.
**Suggested direction:** Port `pr_unresolved_findings_hold` into `armable.py` as condition (11), fed by a carried input (the harness's `ArmInputs` is "carried state only, never recomputed" — the thread fetch belongs in the adapter layer, not in `evaluate()`), fail-closed on API error and on a full unpaginated page exactly as the shell predicate is. Add a **count assertion** to `tests/test_armable.py` so the next skill-side condition cannot drift in silently — that guard, not the port, is the durable half. Sweep the five stale "ten arming conditions" doc sites in the same PR. This touches a `.claude/skills`/ship-pipeline invariant, so `plan-architect-xhigh` and `auto_merge: false` per `.claude/rules/work-triage.md` § Ad-hoc safety mirror; do not fix ad-hoc.
**Risk if untouched:** The gate PR #1733 shipped is inert on the path that runs. On the harness path an unresolved >= 80 finding contributes no hold, so a PR that otherwise clears conditions 1–10 can arm — the case condition (11) was added to catch. Not yet observed in a live run; the verified fact is the absent condition, not a completed bad merge. No test or doc reports the gap.
**Status (2026-08-07):** ⬜ Open — 🟥 (ship-pipeline invariant; loop-machinery changes should default to `auto_merge: false`).

### L29 `bin/plan-now` launchd labels collide at second granularity; concurrent fires silently lose runs
*(discovered 2026-08-08 while firing three context-residency plans back to back)*
**Location:** `bin/plan-now` — the run-ID derivation that produces the launchd label, the `~/Library/LaunchAgents/com.ibl5.plan-now-<id>.plist`, and the `/tmp/plan-now-<id>.{sh,prompt,log}` triple. `bin/test-plan-now` is the existing test host to extend.
**Problem:** The run ID is a `YYYYMMDD-HHMMSS` timestamp, so two `bin/plan-now` invocations inside the same wall-clock second derive the *same* label and the *same* four file paths. Observed 2026-08-08 00:17:37: three invocations issued from one shell `for` loop all produced `com.ibl5.plan-now-20260808-001737`. Each overwrote the previous run's plist, `.sh`, `.prompt`, and `.log`; only one job appeared in `launchctl list`, running the last-written prompt. The failure is **silent and not fail-safe** — the CLI printed its normal success banner (`disposition: queue`, log path, label) three times, so all three runs looked launched while two had been destroyed before exec. The near-miss is already visible in the on-disk history: `~/Library/LaunchAgents/` holds `com.ibl5.plan-now-20260729-182758`, `-182759`, `-182800`, `-182801`, `-182802`, `-182803` — six runs across six consecutive seconds, one collision away from the same loss.
**Suggested direction:** Make the run ID collision-proof rather than merely unlikely — append the shell PID (`$$`) or a monotonic suffix, or probe and increment until the label is free and the four paths are unclaimed. Prefer a check that fails loudly over one that silently reuses. Extend `bin/test-plan-now` (which already stubs the model call through `PLAN_NOW_CLAUDE` / `PLAN_NOW_PLANS_DIR` and spawns no launchd job) with a regression that two same-second invocations yield two distinct IDs and two surviving prompt files.
**Risk if untouched:** Any batch fire — a multi-plan blast, an intake loop, a retry burst — silently drops all but one run while reporting every one as launched. The loss is invisible until someone counts jobs in `launchctl list`, and the discarded runs leave no artifact to recover from.
**Status (2026-08-08):** ⬜ Open — 🟦 (automouse-safe, human-merge per this doc's loop-machinery default; the design is resolved and `bin/test-plan-now` already exists to pin it).

> **L30–L32 share an origin** *(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*. The finding that prompted them: **plan-level work isolation is sound, shared bookkeeping is not.** The atomic-`mkdir` claim lock in `bin/automouse-run` `claim_next_plan()` guarantees exactly-once execution, and `bin/test-automouse-concurrency` verifies it end to end (run 2026-08-08 — all four assertions pass: every plan executed once, phases genuinely overlapped across two runner PIDs, queue fully drained, no leftover `.lock`/`.attempts`). The TTL-steal branch cannot misfire on a healthy sibling either: `LOCK_TTL_SECS=9000` against hard per-phase caps `MAX_IMPL_SECS=3600` + `MAX_PP_SECS=3600` leaves ~30 min of slack, and the lock is claimed once per plan. What that test asserts nothing about — and what L30–L32 cover — is every piece of state the runners *share*: the per-day cost report, the per-day log, and the main checkout.

### L30 Concurrent `automouse-run` sessions corrupt the shared cost report (lost rows, duplicated weekly aggregate)
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/automouse-run` — `record_cost()` and its helpers `strip_weekly_section()` / `regenerate_weekly_section()`, writing `$NIGHTLY_DIR/reports/YYYY-MM-DD-costs.md`. Test host to extend: `bin/test-automouse-concurrency` (already launches two real runners against a temp `NIGHTLY_DIR` with a stubbed `claude`; it currently asserts nothing about the cost report).
**Problem:** Every runner appends its per-phase row to the *same* `reports/YYYY-MM-DD-costs.md` via an unsynchronized three-step read-modify-write: `strip_weekly_section` rewrites the file through a `mktemp` + `mv`, the row is appended, then `regenerate_weekly_section` rewrites it again. Two runners interleaving that sequence lose whichever row landed in the copy the other's `mv` replaced. **Confirmed empirically**, not inferred: a two-runner drain of four fake plans (eight phases) produced **seven rows** — the `| fake-plan-3.md | impl |` row vanished — and the file carried **two `## Weekly aggregate` sections**, each with its own "Cost by tier" and "Tokens by phase" tables. The duplicate section self-heals on the next append (`strip_weekly_section` stops at the first `^## Weekly aggregate`), but **the lost row is permanent**.
**Suggested direction:** Serialize the report update — an `mkdir`-based mutex around the whole strip/append/regenerate sequence reuses the primitive `claim_next_plan()` already trusts, needs no new dependency, and is the smallest change; an append-only per-phase row plus a regenerate-on-read aggregate is the larger alternative if the RMW is worth removing outright. Either way add a `bin/test-automouse-concurrency` assertion that the row count equals `2 × plans`, and that exactly one `## Weekly aggregate` section survives.
**Risk if untouched:** Silent under-reporting of token spend on any day two runners overlap, and the damage is not confined to that day — `regenerate_weekly_section` re-ingests the prior six days' reports, so one corrupted day biases a week of aggregates. These are the numbers spend audits are read off; a missing row looks exactly like a cheaper night.
**Closes gap:** measurement integrity under concurrency — the loss is currently invisible in every artifact.
**Status (2026-08-08):** ⬜ Open — 🟦 (design resolved, machine-verifiable in an existing zero-token test; loop-machinery default is human-merge).

### L31 One shared daily log per calendar day: concurrent runners cross-read each other's cost, stall-kill, and env-stop signals
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/automouse-run` — `LOG="$LOG_DIR/$(date +%Y-%m-%d).log"`, and its four `tail -n +"$((log_before + 1))" "$LOG"` consumers: cost / `peak_ctx` extraction in `record_cost()`, the limit/auth signature scan in `should_env_stop()`, the `STALL-KILL` grep, and the failure-report excerpt.
**Problem:** All concurrent runners append to a single log file named only by calendar date, and each consumer reads "everything after my saved line offset" — which under concurrency includes a sibling's lines. Four consequences, all analytic (the shared-log path was reasoned from source, not isolated in a repro, unlike L30): a phase can bill itself the sibling's `cost=` / `peak_ctx=` because both greps take the *last* match in the window; `should_env_stop()` can fire on a rate-limit or auth signature the *sibling* emitted, aborting a perfectly healthy runner's loop; the `STALL-KILL` grep can likewise attribute the sibling's watchdog kill; and a skip report embeds the last 20 lines of a window that may belong to a different plan entirely, misleading whoever debugs the skip. The env-stop case is wasteful rather than destructive — it breaks the loop but leaves the queue intact by design — but it converts a sibling's transient into this runner's lost night.
**Suggested direction:** Give each runner its own log sink — `logs/YYYY-MM-DD-<pid>.log`, or a per-run subdirectory — so `log_before` offsets index only that runner's own output; keep a combined view by concatenating on read rather than on write. Note this touches breaker semantics (`should_env_stop` / `should_impl_env_stop`), which `bin/test-automouse-env-breaker` locks; that test and `bin/test-automouse-concurrency` are the two hosts a fix must satisfy. Anything downstream that assumes one log per day (report readers, the archival sweep in `archive_stale`, the `Check logs` quick-reference in `.claude/rules/automouse-workflow.md`) has to move with it — which is why this is M, not S.
**Risk if untouched:** Per-phase cost figures are unreliable on any overlapped day (compounding L30), and a single runner hitting a usage limit can stop a healthy sibling mid-queue. Neither failure announces itself: the env-stop writes a normal-looking `env-stop` report naming the wrong cause.
**Closes gap:** signal isolation — no consumer can currently tell its own output from a sibling's.
**Status (2026-08-08):** ⬜ Open — 🟥 (changes a fail-closed breaker's input surface; not automouse-safe).

### L32 Concurrent `bin/wt-new` on the shared main checkout can lose a queued plan to a `skipped/` disposition
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/wt-new` — the `git fetch` / `merge --ff-only` / `worktree add` sequence run against the shared main checkout; consumed by `bin/automouse-prompt-impl` § Step 3 ("If bin/wt-new fails, write an error report …, move the plan symlink to `$NIGHTLY_DIR/skipped/`, and STOP"), whose fast no-handoff exit `should_impl_env_stop()` classifies as a *deliberate* disposition per `.claude/rules/automouse-workflow.md` § Guards.
**Problem:** Two concurrent implementation agents both create their worktree from the one main checkout, and on a shared launchd trigger they arrive within minutes of each other. Git takes its own `index.lock` / `worktree` locks, so the collision surfaces as a **loud failure of one invocation**, not corruption — but the pipeline's handling of that failure is the problem: a `wt-new` failure is grouped with stale-plan / ambiguity / missing-info as a deliberate skip, so a *transient* lock contention moves the plan to `skipped/` permanently and the loop moves on. The plan does not retry and does not carry a `.staleness` sidecar, so `bin/automouse-self-heal` will not recover it either. **Unverified:** this path is not exercised by `bin/test-automouse-concurrency`, which stubs `claude` and therefore never creates a real worktree — the classification is read from source, the collision itself is not reproduced.
**Suggested direction:** Reproduce first — drive two real `bin/wt-new` invocations at the shared checkout concurrently and capture the actual exit status and stderr; the fix depends on whether git fails cleanly or partially. Then either serialize worktree creation behind a lock in `bin/wt-new`, or (better, since it also covers unrelated transients) split the `wt-new` failure out of the deliberate-disposition bucket so it refunds the attempt like an environmental failure instead of consuming the plan. The second option changes breaker classification, so it belongs in `should_impl_env_stop()` with a matching `bin/test-automouse-env-breaker` case.
**Risk if untouched:** A queued plan can be permanently skipped by a race that has nothing to do with the plan, with a report that names `wt-new` failure rather than contention. Low probability per run, but the consequence — a silently dropped unit of work — is the worst in this group.
**Closes gap:** disposition correctness — a transient must not be spent as a verdict.
**Status (2026-08-08):** ⬜ Open — 🟥 (touches impl-disposition classification; reproduce before designing).

---

## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
