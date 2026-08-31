---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, plan decomposition/tier-routing machinery, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-09-02
---

# Loop-Engineering Backlog

**Purpose:** Catalogue changes that make the autonomous loops (automouse nightly queue, PR lifecycle, intake pipelines) more self-healing, better-measured, and safer to leave unattended. Each open entry is a candidate for a `/plan`.

**Origin:** Advisory sessions (2026-07-07): an automouse pipeline audit plus a research synthesis (Cherny's loop-engineering stages, Osmani's autonomy contracts / comprehension debt, Karpathy's verification-first autonomy). Statuses verified against `bin/automouse/run`, `bin/automouse/queue`, `bin/automouse/self-heal`, and the live queue on 2026-07-07.

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
| L33 | CLI entrypoints accept unknown flags silently; no static rule enforces argv option allowlisting | ✅ Implemented | — | S |
| L34 | `bin/pr-ready-now` has no working stop path; `launchctl bootout` orphans the session and corrupts slot accounting | ✅ Implemented | — | S |
| L35 | automouse: cap-timeout kill (exit 143) misclassified as genuine plan failure, burns attempt | ⬜ Open | 🟥 | S |
| L36 | `/post-plan` Phase 3 writes a hardcoded "covered by unit and E2E tests" clause into the PR body without checking the diff contains those test types | ⬜ Open | 🟥 | S |
| L37 | PR body declares only the plan's named files; changes the plan never named ship undeclared, so a reviewer cannot separate intended scope from drift | ⬜ Open | 🟦 | S |
| L38 | Headless CI watcher killed: `local_bash` not awaited by wind-down sweep — phantom success under `claude -p` | ✅ Shipped #2026 | 🟦 | S |
| L39 | Autonomous PR body omits plan-deliverable moot-at-branch-cut explanation and asserts unchecked test coverage | ⬜ Open | 🟥 | S |
| L40 | Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8) | ⬜ Open | 🟥 | S |
| L41 | Plan Verification Matrix rows can ship unrealised — nothing checks a plan's declared assertions against the tests actually delivered | ⬜ Open | 🟥 | S |
| L42 | Autonomous-loop PR ships stale line citations, undeclared plan substitution, unmentioned diff file, and duplicate backlog ID | ⬜ Open | 🟦 | S |

### L1 Plan dependency DAG
**Location:** `bin/automouse/queue` — queue order is symlink mtime (`ls -1tr`); `bin/automouse/queue-reorder-ui` re-touches mtimes by hand. No `depends_on` anywhere (verified).
**Problem:** mtime order is a proxy, not a guarantee: a plan whose prerequisite PR hasn't merged can run anyway and fail or build on the wrong base.
**Suggested direction:** `depends_on:` frontmatter (plan slug or PR#); the queue holds/skips a plan whose prerequisite isn't merged, self-healing it back in once it is (L8 already has the requeue machinery).
**Risk if untouched:** Dependency hazards in every multi-plan program (observed hazard class in the 11-plan queue).
**Status (2026-07-07):** ⬜ Open — 🟦.

### L2 Per-plan circuit breaker
➜ L2 Per-plan circuit breaker — ✅ Implemented (2026-07-15): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L3 Morning digest
**Location:** `bin/automouse/run` writes per-run reports (`done`/`skipped`/`env-stop`/`error`) plus a daily costs table; nothing aggregates or notifies (verified).
**Problem:** Overnight outcomes are read by manually trawling `reports/` and `gh pr list`.
**Suggested direction:** One morning Discord DM aggregating merged / held / failed / parked + spend, reusing the existing `notify-discord` composite; replaces per-run pings rather than adding to them.
**Risk if untouched:** Slow human catch-up every morning; parked plans linger unnoticed.
**Status (2026-07-07):** ⬜ Open — 🟦 (notify surface).

### L4 Retro-miner
**Location:** Post-plan retrospectives accumulate as static per-run reports; nothing mines them (verified).
**Problem:** The learning loop is manual — recurring failure patterns become rules/memory only when a human notices.
**Suggested direction:** Weekly cron that clusters retrospectives and proposes rule/memory edits **as a PR** — the human reviews the proposed norm, never auto-applies.
**Risk if untouched:** Repeat failures that a rule would have prevented; lessons decay in unread reports.
**Status (2026-07-27):** ⬜ Open — 🟥 (rule authoring is judgment; the miner only drafts).
**Provenance (2026-07-27):** A manual mine of accumulated retrospectives surfaced one recurring pattern — repo-path references rotting inside source-file comments, invisible to the markdown-only dead-ref gate — and it shipped as the `bin/check-docs` source-comment scan (branch `check-docs-source-comment-refs`). One pattern, found by hand, in one pass: the item stays open precisely because that mine was manual.

### L5 Master-canary between runs
**Location:** `bin/automouse/run` refreshes master between plans (fetch + `--ff-only` merge) but runs no health check; `bin/check-master-ci-green` exists as a building block.
**Problem:** After an overnight auto-merge, the next plan builds on the new master with no smoke check — a poisoned master cascades failures through every remaining plan.
**Suggested direction:** Between plans, gate on `bin/check-master-ci-green` plus a cheap local smoke (main-stack curl); on red, park the queue rather than continue. (Adjacent: `$HOME/claude-plans/pr-canary-fast-conflict-signal.md` covers the PR-level pre-merge signal.)
**Risk if untouched:** One bad merge converts the rest of the night into cascading noise.
**Status (2026-07-07):** ⬜ Open — 🟦.

### L6 Auto-update-branch unsticker
**Location:** `.github/workflows/update-behind-prs.yml` — scheduled every 15 min; calls the GitHub `update-branch` API for every open non-draft PR stuck BEHIND master (scope widened from armed-only by #1936), debounced on an hour of master quiet. ADR-0081 records the CI_PAT token strategy, merge-vs-rebase decision, loop-safety design, and the debounce amendment.
**Status (2026-07-10):** ✅ Implemented — merged PR #1390.

➜ L7 Queue-add shift-left preflight — ✅ Implemented (2026-06-27): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L8 Failure self-heal / requeue
**Location:** `bin/automouse/run` + `bin/automouse/self-heal`.
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
**Location:** `ibl5/migrations/154_create_ibl_events.sql` + `ibl5/classes/EventLog/` write request events to `ibl_events` (PR #1425); nothing reads them. Closest existing shape is `.github/workflows/log-review.yml` + `bin/log-fetch-prod` — a Sunday cron that SSHes to prod, aggregates, and DMs the owner via `.github/actions/notify-discord`. That pipeline is pure shell/awk with **no LLM step**, and there is no Claude-in-CI pattern anywhere in the repo (verified 2026-07-24: every `claude -p` — `bin/docfix-run`, `bin/post-plan-now`, `bin/automouse/run` — is detached launchd on the owner's Mac, on the subscription).
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

➜ L23 sim-recap degraded path emits no Discord signal; qctx() failure ships roster-blind with CI green — ✅ Implemented (2026-08-14): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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

**Superseded by:** #2034 — PID suffix (`-$$`) appended to `$TS` in both `bin/plan-now` and `bin/post-plan-now`; backstop guard added in `bin/plan-now`; `bin/test-plan-now` extended with collision regression. Collision class resolved.

> **L30–L32 share an origin** *(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*. The finding that prompted them: **plan-level work isolation is sound, shared bookkeeping is not.** The atomic-`mkdir` claim lock in `bin/automouse/run` `claim_next_plan()` guarantees exactly-once execution, and `bin/test-automouse-concurrency` verifies it end to end (run 2026-08-08 — all four assertions pass: every plan executed once, phases genuinely overlapped across two runner PIDs, queue fully drained, no leftover `.lock`/`.attempts`). The TTL-steal branch cannot misfire on a healthy sibling either: `LOCK_TTL_SECS=9000` against hard per-phase caps `MAX_IMPL_SECS=3600` + `MAX_PP_SECS=3600` leaves ~30 min of slack, and the lock is claimed once per plan. What that test asserts nothing about — and what L30–L32 cover — is every piece of state the runners *share*: the per-day cost report, the per-day log, and the main checkout.

### L30 Concurrent `automouse-run` sessions corrupt the shared cost report (lost rows, duplicated weekly aggregate)
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/automouse/run` — `record_cost()` and its helpers `strip_weekly_section()` / `regenerate_weekly_section()`, writing `$NIGHTLY_DIR/reports/YYYY-MM-DD-costs.md`. Test host to extend: `bin/test-automouse-concurrency` (already launches two real runners against a temp `NIGHTLY_DIR` with a stubbed `claude`; it currently asserts nothing about the cost report).
**Problem:** Every runner appends its per-phase row to the *same* `reports/YYYY-MM-DD-costs.md` via an unsynchronized three-step read-modify-write: `strip_weekly_section` rewrites the file through a `mktemp` + `mv`, the row is appended, then `regenerate_weekly_section` rewrites it again. Two runners interleaving that sequence lose whichever row landed in the copy the other's `mv` replaced. **Confirmed empirically**, not inferred: a two-runner drain of four fake plans (eight phases) produced **seven rows** — the `| fake-plan-3.md | impl |` row vanished — and the file carried **two `## Weekly aggregate` sections**, each with its own "Cost by tier" and "Tokens by phase" tables. The duplicate section self-heals on the next append (`strip_weekly_section` stops at the first `^## Weekly aggregate`), but **the lost row is permanent**.
**Suggested direction:** Serialize the report update — an `mkdir`-based mutex around the whole strip/append/regenerate sequence reuses the primitive `claim_next_plan()` already trusts, needs no new dependency, and is the smallest change; an append-only per-phase row plus a regenerate-on-read aggregate is the larger alternative if the RMW is worth removing outright. Either way add a `bin/test-automouse-concurrency` assertion that the row count equals `2 × plans`, and that exactly one `## Weekly aggregate` section survives.
**Risk if untouched:** Silent under-reporting of token spend on any day two runners overlap, and the damage is not confined to that day — `regenerate_weekly_section` re-ingests the prior six days' reports, so one corrupted day biases a week of aggregates. These are the numbers spend audits are read off; a missing row looks exactly like a cheaper night.
**Closes gap:** measurement integrity under concurrency — the loss is currently invisible in every artifact.
**Status (2026-08-08):** ⬜ Open — 🟦 (design resolved, machine-verifiable in an existing zero-token test; loop-machinery default is human-merge).

### L31 One shared daily log per calendar day: concurrent runners cross-read each other's cost, stall-kill, and env-stop signals
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/automouse/run` — `LOG="$LOG_DIR/$(date +%Y-%m-%d).log"`, and its four `tail -n +"$((log_before + 1))" "$LOG"` consumers: cost / `peak_ctx` extraction in `record_cost()`, the limit/auth signature scan in `should_env_stop()`, the `STALL-KILL` grep, and the failure-report excerpt.
**Problem:** All concurrent runners append to a single log file named only by calendar date, and each consumer reads "everything after my saved line offset" — which under concurrency includes a sibling's lines. Four consequences, all analytic (the shared-log path was reasoned from source, not isolated in a repro, unlike L30): a phase can bill itself the sibling's `cost=` / `peak_ctx=` because both greps take the *last* match in the window; `should_env_stop()` can fire on a rate-limit or auth signature the *sibling* emitted, aborting a perfectly healthy runner's loop; the `STALL-KILL` grep can likewise attribute the sibling's watchdog kill; and a skip report embeds the last 20 lines of a window that may belong to a different plan entirely, misleading whoever debugs the skip. The env-stop case is wasteful rather than destructive — it breaks the loop but leaves the queue intact by design — but it converts a sibling's transient into this runner's lost night.
**Suggested direction:** Give each runner its own log sink — `logs/YYYY-MM-DD-<pid>.log`, or a per-run subdirectory — so `log_before` offsets index only that runner's own output; keep a combined view by concatenating on read rather than on write. Note this touches breaker semantics (`should_env_stop` / `should_impl_env_stop`), which `bin/test-automouse-env-breaker` locks; that test and `bin/test-automouse-concurrency` are the two hosts a fix must satisfy. Anything downstream that assumes one log per day (report readers, the archival sweep in `archive_stale`, the `Check logs` quick-reference in `.claude/rules/automouse-workflow.md`) has to move with it — which is why this is M, not S.
**Risk if untouched:** Per-phase cost figures are unreliable on any overlapped day (compounding L30), and a single runner hitting a usage limit can stop a healthy sibling mid-queue. Neither failure announces itself: the env-stop writes a normal-looking `env-stop` report naming the wrong cause.
**Closes gap:** signal isolation — no consumer can currently tell its own output from a sibling's.
**Status (2026-08-08):** ⬜ Open — 🟥 (changes a fail-closed breaker's input surface; not automouse-safe).

### L32 Concurrent `bin/wt-new` on the shared main checkout can lose a queued plan to a `skipped/` disposition
*(discovered 2026-08-08 while checking whether two automouse agents can run simultaneously without interfering)*
**Location:** `bin/wt-new` — the `git fetch` / `merge --ff-only` / `worktree add` sequence run against the shared main checkout; consumed by `bin/automouse/prompt-impl` § Step 3 ("If bin/wt-new fails, write an error report …, move the plan symlink to `$NIGHTLY_DIR/skipped/`, and STOP"), whose fast no-handoff exit `should_impl_env_stop()` classifies as a *deliberate* disposition per `.claude/rules/automouse-workflow.md` § Guards.
**Problem:** Two concurrent implementation agents both create their worktree from the one main checkout, and on a shared launchd trigger they arrive within minutes of each other. Git takes its own `index.lock` / `worktree` locks, so the collision surfaces as a **loud failure of one invocation**, not corruption — but the pipeline's handling of that failure is the problem: a `wt-new` failure is grouped with stale-plan / ambiguity / missing-info as a deliberate skip, so a *transient* lock contention moves the plan to `skipped/` permanently and the loop moves on. The plan does not retry and does not carry a `.staleness` sidecar, so `bin/automouse/self-heal` will not recover it either. **Unverified:** this path is not exercised by `bin/test-automouse-concurrency`, which stubs `claude` and therefore never creates a real worktree — the classification is read from source, the collision itself is not reproduced.
**Suggested direction:** Reproduce first — drive two real `bin/wt-new` invocations at the shared checkout concurrently and capture the actual exit status and stderr; the fix depends on whether git fails cleanly or partially. Then either serialize worktree creation behind a lock in `bin/wt-new`, or (better, since it also covers unrelated transients) split the `wt-new` failure out of the deliberate-disposition bucket so it refunds the attempt like an environmental failure instead of consuming the plan. The second option changes breaker classification, so it belongs in `should_impl_env_stop()` with a matching `bin/test-automouse-env-breaker` case.
**Risk if untouched:** A queued plan can be permanently skipped by a race that has nothing to do with the plan, with a report that names `wt-new` failure rather than contention. Low probability per run, but the consequence — a silently dropped unit of work — is the worst in this group.
**Closes gap:** disposition correctness — a transient must not be spent as a verdict.
**Status (2026-08-08):** ⬜ Open — 🟥 (touches impl-disposition classification; reproduce before designing).

### L33 CLI entrypoints accept unknown flags silently; no static rule enforces argv option allowlisting
➜ L33 CLI entrypoints accept unknown flags silently — ✅ Implemented (2026-08-30): PR #2042. see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).


### L34 `bin/pr-ready-now` has no working stop path; `launchctl bootout` orphans the session and corrupts slot accounting
➜ L34 `bin/pr-ready-now` has no working stop path — ✅ Implemented (2026-08-25): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L35 automouse: cap-timeout kill (exit 143) misclassified as genuine plan failure, burns attempt
*(discovered 2026-08-23 during pr-firstpass-loop)*
**Location:** `bin/automouse/run` — `MAX_IMPL_SECS=3600` (line 462), macOS timeout shim (lines 371–413) that SIGTERMs the child at the cap, and `should_impl_env_stop()` (line 902) which decides whether a failed impl refunds the attempt (environmental) or burns it (genuine failure).
**Problem:** A SIGTERM from the cap fires exit code 143 (128+15). `should_impl_env_stop()` env-stops only on: a limit/auth string matched by `phase_env_error()`, a watchdog `STALL-KILL` marker matched by `phase_stalled()`, or a sub-minute "fast" exit. A cap-timeout SIGTERM matches none of those, writes no handoff file, and leaves the plan in `queue/` — so it falls through as a "Genuine impl failure" and increments the attempt counter. A plan too large to complete in one hour burns all 3 attempts and is poison-pilled into `skipped/`, even though each attempt committed real progress. Observed 2026-08-23: attempt 1 ran 19:03:54–20:03:55 (3601 s); attempt 2 ran 20:45:40–21:45:40 (3600 s); both logged `claude exited code=143 (impl phase)` then `Genuine impl failure ... (attempt N/3)`. Log: `~/.claude/projects/-Users-ajaynicolas-GitHub-IBL5/automouse/logs/2026-08-23.log`.
**Suggested direction:** Teach the impl-failure path to recognise a cap-timeout — e.g. record the phase's elapsed seconds and treat "elapsed >= MAX_IMPL_SECS and exit code 143" as a distinct outcome that refunds the attempt (or at minimum does not count it toward the poison-pill), so out-of-time is not conflated with broken. `should_impl_env_stop()` is locked by `bin/test-automouse-env-breaker`; any change must extend that test.
**Risk if untouched:** Every plan that routinely exceeds MAX_IMPL_SECS is eventually poison-pilled into `skipped/` rather than retried with a smaller scope or re-queued.
**Note:** `bin/automouse/run` is a ship-pipeline surface; route through `/plan`, not ad-hoc.
**Status (2026-08-24):** ⬜ Open — 🟥 (ship-pipeline surface; loop-machinery changes should default to `auto_merge: false`).

### L36 `/post-plan` Phase 3 writes a hardcoded "covered by unit and E2E tests" clause into the PR body without checking the diff contains those test types
*(discovered 2026-08-25 during #1969)*

**class:** a skill or generator asserts a fact about its own environment — test coverage present, a tool exempt from a gate — as a template constant or a hand-written invariant, with nothing checking that the assertion is still true. Two live instances found this pass: a PR-body clause naming test types the diff does not contain, and a skill invariant declaring `Monitor` exempt from the worktree command-substitution gate when it is not.

**Location:** `.claude/skills/post-plan/SKILL.md` line 121 — "If the matrix has zero truly-manual rows (or the plan says 'All verification is automated'), write: `No manual testing needed — all changes are covered by unit and E2E tests.`"

**Problem:** The clause is a template constant, not a claim derived from the diff. PR #1969 added PHPUnit unit and `DatabaseIntegration` tests and **no** Playwright E2E spec, yet the body asserted E2E coverage. The failing half is only the tail clause: the `No manual testing needed` prefix is a load-bearing machine sentinel that `bin/lib/pr-armable.sh:59` prefix-matches (`^[[:space:]]*No manual testing needed`) to clear auto-merge condition (1), so the sentinel itself must survive any fix. `.claude/skills/plan/SKILL.md:288` already names this exact sentence as a known plan-side failure mode ("Silence is not coverage"), but names it only as a *plan matrix* defect — nothing checks the generated PR body against the realized diff.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1969 body § Manual Testing (generated from `.claude/skills/post-plan/SKILL.md:121`) | yes | yes | fixed this pass (body prose corrected in-PR) |
| 2 | `.claude/skills/post-plan/SKILL.md:121` (the generator itself) | yes | yes | not fixed — filed (ship-pipeline surface; wants a `/plan`) |
| 3 | `.claude/skills/post-plan/SKILL.md:276` — fallback clause says "automated tests", type-agnostic | near-miss | yes | not fixed — correct as written; names no specific type |
| 4 | `.claude/skills/plan/SKILL.md:288` | near-miss | yes | not fixed — this is the documented warning, not an occurrence |
| 5 | `.claude/skills/pr-ready/SKILL.md` — Invariants § "**Exempt:** any command passed to `Monitor`, which is not gated" | yes | yes | **already fixed on master** — independently found and shipped the same day as [E14](dev-efficiency-backlog.md) via #1991, which deleted the clause. Measured false here first (2026-08-25: the inline watcher was refused with "too complex to verify that it stays inside the worktree" and had to be written to a `/tmp` script), then confirmed resolved on rebase. Retained as evidence the class generalises beyond the `post-plan` generator. |

**prevention ladder:**
- rung 0 — already covered? No. `bin/lib/pr-armable.sh` prefix-matches the sentinel and never reads the tail clause; `bin/check-docs` does not read PR bodies at all.
- rung 1 — extend an existing gate? **Yes — this is the landing rung.** `bin/lib/pr-armable.sh` already parses the `## Manual Testing` section and already owns the Manual-Testing clearance predicate; extending `pr_manual_testing_clearance` (or adding a sibling predicate beside it) to reject a tail clause naming a test type absent from the PR's changed-file list reuses the existing parse and the existing `ibl5/tests/Cli/PrArmableLibCliTest.php` harness.
- rung 2 — a rule doc? Insufficient alone: `.claude/skills/plan/SKILL.md:288` already *is* prose guidance against this exact sentence and it did not prevent the occurrence.
- rung 3 — a PHPStan rule? N/A — the artifact is a GitHub PR body, not PHP.
- rung 4 — a CI gate? Overkill given rung 1 lands it, and a CI job cannot see the body on a `pull_request` event without an extra API call.
- rung 5 — a new hook? Overkill; rung 1 is strictly cheaper.

Rung 1 does not require the `.claude/rules/meta-tooling-bar.md` extend-before-add conditions (those bind rungs 3–5), and it *is* the extend-before-add outcome those conditions push toward.

**artifact destination:** `bin/lib/pr-armable.sh` (in-repo, appears in the PR diff), locked by `ibl5/tests/Cli/PrArmableLibCliTest.php`. A companion one-line correction to the template at `.claude/skills/post-plan/SKILL.md:121` ships with it. Occurrence 5 needs no artifact — #1991 already deleted the clause (dev-efficiency E14, ✅ Implemented 2026-08-25). Two independent discoveries of the same class on the same day is itself the argument for rung 1: prose asserting an environment fact is not self-checking, so it drifts silently until something trips over it.

**Suggested direction:** Change the `.claude/skills/post-plan/SKILL.md:121` template to a type-agnostic clause ("all changes are covered by automated tests", matching line 276), and extend `pr_manual_testing_clearance` to fail closed when the tail clause names a test type with no corresponding file in the PR's changed-file list.

**Risk if untouched:** Every plan whose matrix has zero `Truly-manual` rows ships a PR body asserting E2E coverage it may not have. A reviewer who trusts the sentence skips exactly the verification the plan deferred, and the false claim is the *positive clearance signal* auto-merge arming reads — so the sentence that is wrong is also the one that unblocks the merge.

**Note:** `.claude/skills/post-plan/SKILL.md` and `bin/lib/pr-armable.sh` are ship-pipeline surfaces; route through `/plan`, not ad-hoc.

**Status (2026-08-25):** ⬜ Open — 🟥 (ship-pipeline surface; touches the auto-merge clearance predicate).

### L37 PR body declares only the plan's named files; changes the plan never named ship undeclared

*(discovered 2026-08-26 during #1996)*

**class:** a change lands in a PR that no plan phase asked for and no hand-written PR-body prose declares, so the only record of it is the machine-generated files-changed list — which names the path but never says what changed or why, leaving a reviewer unable to separate intended scope from drift.

**Location:** PR #1996 body § Summary (generated by `.claude/skills/post-plan/SKILL.md` Phase 3) vs. `~/claude-plans/boxscore-schedule-guard.md` § Critical Files.

**Problem:** `/post-plan` Phase 3 writes the Summary bullets from the **plan**, and Phase 5.0 checks conformance in one direction only — that every *declared* artifact exists in the diff. Nothing checks the other direction: a file in the diff that the plan never named and the body never mentions. `/pr-ready` runtime Phase 5.9 then regenerates the files-changed block from `git diff --name-status`, which makes the *path* visible but is explicitly excluded from the Phase 6d.4 body-claim check as generated data — so an undeclared change is simultaneously present in the body and invisible as a claim.

The live instance was not cosmetic. `ibl5/classes/Updater/ScheduleUpdater.php` gained a new `\RuntimeException` that aborts the schedule rebuild during the `Playoffs` phase when `Schedule.htm` is missing, unreadable, or all-stale. It is well-tested and, on inspection, correct — but it changes what an unattended league-operator updater run does, it is not in the plan's Critical Files, and no prose in the PR said so. A reviewer reading the Summary would not know to look at it.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/classes/Updater/ScheduleUpdater.php:180` and `:205` — new `Playoffs`-phase `\RuntimeException`, in no plan phase | yes | yes | fixed this pass (declared in the PR body § Scope beyond the plan's named files) |
| 2 | `.claude/rules/codebase-map.md`, `bin/lib/db-helpers.sh`, `bin/lib/git-helpers.sh`, `ibl5/tests/DatabaseIntegration/SchemaInvariantTest.php` — undeclared but mechanical (map rows, a shellcheck directive, a table registration) | yes | yes | fixed this pass (same body section) |
| 3 | `.claude/skills/post-plan/_phase-5-final-verification.md` — the conformance check itself, declared-artifact direction only | yes | yes | not fixed — filed (ship-pipeline surface; wants a `/plan`) |
| 4 | `.claude/skills/pr-ready/_plan-fidelity-review.md` 6d.3 — already asks the reviewer for this, at review time | near-miss | yes | not fixed — correct as written; it is the human backstop this entry wants to precede, not an occurrence |
| 5 | [L36](#l36-post-plan-phase-3-writes-a-hardcoded-covered-by-unit-and-e2e-tests-clause-into-the-pr-body-without-checking-the-diff-contains-those-test-types) — the same body-vs-diff surface in the opposite direction (body asserts what the diff lacks) | near-miss | yes | not fixed — separately filed; see the ladder below on why these should land together |

**prevention ladder:**
- rung 0 — already covered? No. `/post-plan` Phase 5.0 checks declared → diff and never diff → declared; `bin/check-docs` does not read PR bodies; `bin/lib/pr-armable.sh` parses only § Manual Testing.
- rung 1 — extend an existing gate? **Yes — this is the landing rung.** `/post-plan` Phase 5.0 already parses the plan's `## Critical Files` list and already has the PR's changed-file list in hand. Adding the reverse assertion — every changed path is either in Critical Files, matches a declared test path, or is named in a body prose section — reuses both inputs and needs no new parse. It should ship with L36's `bin/lib/pr-armable.sh` work: same surface, same test harness, opposite direction, and building them separately means touching the ship pipeline twice.
- rung 2 — a rule doc? Insufficient alone. `.claude/skills/pr-ready/_plan-fidelity-review.md` 6d.3 already *is* prose instructing a reviewer to catch this, and it caught it only at `/pr-ready` time — after the PR was open, reviewed, and CI-green.
- rung 3 — a PHPStan rule? N/A — the artifact is a GitHub PR body and a plan markdown file, not PHP.
- rung 4 — a CI gate? Rejected. A `pull_request`-event job cannot see the plan at all: plans live at `~/claude-plans/`, outside the repo and off the runner.
- rung 5 — a new hook? Overkill, and it would violate `.claude/rules/meta-tooling-bar.md` — there **is** a host to extend (rung 1), so the extend-before-add bar fails its first condition.

Rung 1 does not require the `meta-tooling-bar.md` extend-before-add conditions (those bind rungs 3–5), and it is the outcome those conditions push toward.

**artifact destination:** `.claude/skills/post-plan/_phase-5-final-verification.md` (in-repo, appears in the PR diff), with the executable half beside L36's in `bin/lib/pr-armable.sh` and locked by `ibl5/tests/Cli/PrArmableLibCliTest.php`.

**Suggested direction:** In `/post-plan` Phase 5.0, after the declared → diff pass, run the reverse: for each path in `git diff --name-only origin/master...HEAD`, require it to appear in the plan's `## Critical Files`, in a declared test path, or in a body prose section. Report the remainder as an `UNDECLARED` list and require Phase 3 to write a `## Scope beyond the plan's named files` section naming each one before the body is considered complete. Allowlist the machine-generated files-changed block so it cannot self-satisfy the check.

**Risk if untouched:** The undeclared change is the one nobody reads. Phase 4B structured review scores the diff without the plan, and the fidelity review is the only pass that holds the two side by side — so an undeclared behavior change in an unattended pipeline reaches `READY` on the strength of a Summary that never mentions it. Here the change was correct; the class does not guarantee that.

**Note:** `.claude/skills/post-plan/` and `bin/lib/pr-armable.sh` are ship-pipeline surfaces; route through `/plan`, not ad-hoc. Ship with L36.

**Status (2026-08-26):** ⬜ Open — 🟦 (ship-pipeline surface, but additive: the check only reports, it removes no existing assertion).

### L39 Autonomous PR body omits plan-deliverable moot-at-branch-cut explanation and asserts unchecked test coverage

*(discovered 2026-08-31 during #2046)*

**class (finding 1):** an autonomous PR body does not explain why the plan's sole deliverable is absent from the diff when that deliverable was already completed by a prior PR before the branch was cut, leaving a reader with an unexplained plan-to-diff gap.

**class (finding 4):** a PR-body Manual Testing section asserts automated test coverage when the diff adds no test and no pre-existing test covers the new behavior — an additional occurrence of [L36](#l36-post-plan-phase-3-writes-a-hardcoded-covered-by-unit-and-e2e-tests-clause-into-the-pr-body-without-checking-the-diff-contains-those-test-types)'s class.

**Location (finding 1):** PR #2046 body § Summary — no mention of issue #2035, ADR-0074, or the prior refresh (#2036). The autonomous run's plan records the deliverable but nothing in the pipeline checks whether the deliverable was already done before the branch was cut.

**Location (finding 4):** PR #2046 body § Manual Testing — "No manual testing needed — verified by automated tests." `git diff --name-only` against `ibl5/tests` returns nothing; `PHPUnit Tests` is `SKIPPED` in CI. Occurrence 2 of L36's class; prior occurrence was PR #1969.

**Finding 1 fix (this PR):** Added an explanatory bullet to PR body Summary noting that ADR-0074 was already refreshed by #2036 before branch cut.

**Finding 4 fix (this PR):** Rewrote Manual Testing from "verified by automated tests" to a truthful manual-verification statement. The "No manual testing needed" sentinel prefix was preserved for `bin/lib/pr-armable.sh:59`.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2046 body § Summary — plan deliverable absent from diff with no explanation | finding 1 | yes | fixed this pass (explanatory bullet added) |
| 2 | PR #2046 body § Manual Testing — "verified by automated tests" with no test in diff | finding 4 (L36 class) | yes | fixed this pass (section rewritten) |
| 3 | .claude/skills/post-plan/SKILL.md:121 — template generator for the "verified by automated tests" clause | finding 4 source | yes | not fixed — filed as L36 (open) |
| 4 | /post-plan pipeline — no step checks whether the plan's deliverable was already done before branch cut | finding 1 source | yes | not fixed — filed here as L39 |

**prevention ladder (finding 1):**
- rung 0 — already covered? No. `/post-plan` Phase 5.0 checks declared-artifact → diff; no step checks whether a plan-named deliverable is absent from the diff without a declared descope or pre-emption reason.
- rung 1 — extend an existing gate? Yes — this is the landing rung. L37 proposes extending `/post-plan` Phase 5.0 to check diff → declared. Finding 1 wants the symmetric check: any plan-declared deliverable with no diff footprint must carry a body prose explanation. Ships with L36 and L37 (same surface, same test harness, complementary directions).
- rungs 2–5 — N/A; rung 1 is the landing rung.

**prevention ladder (finding 4):** Already covered by L36's ladder (rung 1: extend `bin/lib/pr-armable.sh`). This is occurrence 2; per the class registry anti-recurrence lever, recurrence confirms L36 should move up the burn-down queue.

**artifact destination:** .claude/skills/post-plan/_phase-5-final-verification.md and bin/lib/pr-armable.sh (in-repo). Ships with L36 and L37.

**provenance:** (discovered 2026-08-31 during #2046)

---
### L41 Plan Verification Matrix rows can ship unrealised — nothing checks a plan's declared assertions against the tests actually delivered
*(discovered 2026-08-25 during #1966)*

**class:** a plan declares a per-row verification assertion in its Verification Matrix, the implementation delivers the *file* that row names but not the *assertion*, and no gate compares the two — so the plan reads as fully verified while a named assertion has zero footprint in the diff.

**Location:** `.claude/skills/post-plan/SKILL.md` Phase 5.0 (declared-artifact conformance). It confirms every artifact the plan declared exists in the diff; it never reads the Verification Matrix's per-row assertion text.

**Problem:** `~/claude-plans/plan-now-dup-guard.md` declared 19 CLI-executable matrix rows. Rows 10 and 12 named assertions that the delivered `bin/test-plan-now` did not contain — row 10 ("a custom `PLAN_NOW_DUP_CHECK` seam that exits 3 declines, and its marker reaches both stdout and the DM log") had no case at all, and row 12 ("benign re-queue emits no `automouse-queue FAILED` at **either** call site") was implemented for the normal path only, because `run_case_recovery` never forwarded `QUEUE_ALREADY` to the second call site at `bin/plan-now:583`. Both rows named `bin/test-plan-now`, which *is* in the diff, so Phase 5.0's artifact check passed on both. The suite was green throughout: a matrix row with no case cannot fail. This is the mirror image of [L36](#l36-post-plan-phase-3-writes-a-hardcoded-covered-by-unit-and-e2e-tests-clause-into-the-pr-body-without-checking-the-diff-contains-those-test-types) — there a *generated* coverage claim outran the diff; here a *hand-declared* one did.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-plan-now` — Matrix row 10, live seam exits 3 | yes | yes | fixed this pass (pre-flight case 11b added) |
| 2 | `bin/test-plan-now:242` — Matrix row 12, call site #2 (`bin/plan-now:583`) | yes | yes | fixed this pass (`run_case_recovery` forwards `QUEUE_ALREADY`; case 13b added) |
| 3 | `bin/test-plan-now` case 13 — the plan's `want_no … "automouse-queue FAILED"` assertion omitted | yes | yes | fixed this pass |
| 4 | `~/claude-plans/plan-now-dup-guard.md` Phase 6 item 5 (a genuinely new ancient-mtime plan lands last, both sort keys strictly newer) | near-miss | yes | not fixed — goal already met by pre-existing `bin/test-automouse-queue` rows 17/18, which still run; the substituted row 8d is additive |
| 5 | `.claude/skills/post-plan/SKILL.md` Phase 5.0 (the gate itself) | yes | yes | not fixed — filed; ship-pipeline surface, wants a `/plan` |

**prevention ladder:**
- rung 0 — already covered? No. Phase 5.0 checks declared **artifacts**, not declared **assertions**; `bin/check-plan` validates the plan's own shape *before* implementation and never sees the diff.
- rung 1 — extend an existing gate? **Yes — this is the landing rung.** Phase 5.0 already locates the plan and parses its declared-artifact list. Extending it to enumerate the Verification Matrix rows and require, per row, either a matching assertion string in the diff or an explicit waiver line in the PR body reuses that existing parse and the existing plan-lookup path.
- rung 2 — a rule doc? Insufficient alone: `.claude/skills/plan/SKILL.md` already carries the "silence is not coverage" warning and this still shipped.
- rung 3 — a PHPStan rule? N/A — the artifacts are Markdown and Bash.
- rung 4 — a CI gate? Structurally ruled out: plans live at `~/claude-plans/<branch>.md`, **outside** the repo (ADR-0046 / `workflow-continuity.md`), so a CI runner cannot read the plan being conformed against. The check has to run where the plan is readable — the `/post-plan` session.
- rung 5 — a new hook? Overkill, and it fails the first `.claude/rules/meta-tooling-bar.md` condition: there **is** a host to extend (Phase 5.0), so add-new is not warranted.

**artifact destination:** `.claude/skills/post-plan/SKILL.md` Phase 5.0 (in-repo; appears in the PR diff). Not out-of-repo.

**Suggested direction:** In Phase 5.0, after the artifact check, walk the Verification Matrix row by row and emit a `MATRIX-ROW UNREALISED: <row> — <assertion>` line for any row whose named assertion has no footprint in the diff. Report, do not block: some rows are legitimately satisfied by a pre-existing test (occurrence 4 above), so the output belongs in the PR body as a reviewer checklist rather than as a hard gate.

**Risk if untouched:** A plan's matrix is the artifact reviewers trust when deciding a PR is verified. A row that was never implemented is indistinguishable from one that passes — both are silent — so the plan's own count of verification rows overstates the real coverage, and the specific assertion the planner thought worth naming is the one nobody runs.

**Status (2026-08-25):** ⬜ Open — 🟥 (ship-pipeline surface: `.claude/skills/post-plan/SKILL.md`).

---


### L40 Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8)

*(discovered 2026-09-01 while shipping #2056, whose diff contained the binary artifact `ibl5/data/finals2008-g4.rec`)*

**Location:** `tools/postplan-harness/harness/adapters/gitad.py:18` — `_run()` calls `subprocess.run([...], capture_output=True, text=True)` with no `errors=` argument. The crash surfaces at `gitad.py:42` (`diff_vs_base`), which shells out to `git diff <merge-base>`. Same unguarded `text=True` at six other call sites: `gitad.py:85`, `gitad.py:88`, `ciwatch.py:71`, `llm.py:68`, `ghad.py:116`, `verify.py:42`. (`llm.py:58` is the only place in the harness that passes `errors=` at all.) Regression-test host: `tools/postplan-harness/tests/test_gitad_live.py`.

**Problem:** `text=True` decodes the child's stdout as strict UTF-8. `git diff` emits raw bytes for a binary file, so any diff touching one raises `UnicodeDecodeError` and kills the compiled harness *before any phase runs*. Observed 2026-09-01 at 18:19: `UnicodeDecodeError: 'utf-8' codec can't decode byte 0x9e in position 23462: invalid start byte`, traceback `gitad.py:42 diff_vs_base` → `gitad.py:17 _run`. This is not a corner case — it fires on **every** PR whose diff contains a binary file, which is the entire boxscore-restore class of work (`.rec` artifacts) plus any image, font, or fixture blob.

The failure is quiet because the two-engine design absorbs it: the harness exits non-zero, `should_fallback()` returns true for any rc except 0 and 3, and `bin/post-plan-now` silently hands off to the slower Sonnet `/post-plan` skill. Work still completes, so nothing alarms — but the run costs ~40 min instead of a few, and the fallback agent reasons without the harness's guardrails. In #2056 it invented a `@codeCoverageIgnore` annotation with zero precedent anywhere in the repo, which did not clear the coverage gate anyway (83.98% → 84.24%, minimum 84.46%); the fix had to be reverted by hand and replaced with the repo's actual precedent (lowering `coverage-baseline.json`, as in #2001 and #2022).

**Suggested direction:** Add `errors="replace"` to `_run()` in `gitad.py` — mojibake in a diff string the harness only pattern-matches over is strictly better than a crash. Then sweep the other six `text=True` sites for the same guard, since `git log`, `gh` output, and CI logs can all carry non-UTF-8 bytes. Regression test in `tests/test_gitad_live.py`: create a temp repo, commit a file containing byte `0x9e`, and assert `diff_vs_base()` returns a string rather than raising.

**Risk if untouched:** Every binary-touching PR silently loses the fast, guardrailed engine and falls through to an unconstrained agent — the expensive path, taken invisibly, with lower-quality output. Because the fallback usually *succeeds*, there is no signal that the primary engine has been dead for that whole class of PR.

**Status (2026-09-01):** ⬜ Open — 🟥 (self-contained fix in a dev-tooling adapter; no user-facing surface, no gate weakened).

---

## Class registry

Append-only. One line per **class of defect**, written by `/post-plan` Phase 9 when a run routes a
learning up the escalation ladder. Never edit or delete a line — the value is in the history.

The `prior:` field is the anti-recurrence lever: when a new line's class matches an existing one,
record the earlier PR numbers there. A non-empty `prior:` means the class has recurred, and recurrence
is the signal that the previously chosen rung was too weak — escalate one rung rather than re-routing
to the same place. This is a **prompted** loop, not an automated one: nothing scans this table on a
schedule, and no gate fails on it.

The table is fenced and every path inside it is written bare — no backticks, no link syntax. The
bare paths are what keep a row naming a destination that does not exist yet from failing the
dead-reference check; the fence alone would not, since the checker does not strip fenced blocks. Do
not add backticks or markdown links to a row.

```
| Date       | PR   | Class                                                                 | Routed to rung                                                                                          | Prior          |
| ---------- | ---- | --------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | -------------- |
| 2026-07-25 | #1633 | class: env/supervisor precondition unverified before a long-running job | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-07-25 | --   | class: shared-context wire contract between two scripts is unasserted   | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-07-25 | #1654 | class: CLI entrypoint accepts an unknown flag silently instead of erroring | routed to: Rung 1 - PHPStan rule over argv option parsing, queued as L33 in this backlog, not yet built; interim Rung 3 backstop shipped in #1668 (section: Forced integration-verification trigger). A fourth occurrence forces the Rung 1 rule. | prior: #1354, #1496 |
| 2026-08-10 | #1834 | class: app-generated file read by an updater is force-tracked via .gitignore negation, so deploy git-reset clobbers live data with stale committed content | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): updater/importer that reads a generated file from a repo-relative path must assert git ls-files exits nonzero for that path | prior: -- |
| 2026-08-14 | #1880 | class: gate escape path conditioned on a git-range query silently blocks when the range is empty (first-branch-commit), with no null fallback | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an escape path in a CI check gate that calls a git-range helper must test the empty-range (no-prior-commits-on-branch) scenario | prior: -- |
| 2026-08-16 | #1901 | class: htmx request-lifecycle handlers (beforeRequest/afterRequest) that mutate DOM state leave that mutation serialized in the history cache; browser Back restores request-time snapshot, making the mutation permanent until a historyRestore handler repairs it | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced E2E triggers): plan adding/modifying htmx beforeRequest/afterRequest DOM mutations must require E2E coverage of browser Back behavior | prior: -- |
| 2026-08-19 | #1925 | class: queue enqueue operation inherits mtime from the queued file rather than stamping the ordering key at insertion time, silently misordering entries with old authoring dates | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an enqueue or requeue path in bin/automouse/queue must test back-of-queue placement with an ancient-mtime plan | prior: -- |
| 2026-08-19 | #1930 | class: a plan that stops ongoing data corruption in an import or upsert path ships without a compensating backfill migration for rows already corrupted before the fix | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan removing or modifying an importer or upsert path that was writing an incorrect value must verify a compensating backfill ships in the same PR or explicitly scope out already-corrupted rows in the plan | prior: -- |
| 2026-08-21 | #1953 | class: cap-validation or salary-comparison logic selects a salary-basis column (current vs. next-year) without consulting the league phase, producing incorrect hard-cap outcomes during offseason | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying salary-comparison or cap-enforcement logic must carry verification rows for both the in-season path (advancesContractYears()=false, current_salary basis) and the offseason path (advancesContractYears()=true, next_year_salary basis) | prior: -- |
| 2026-08-22 | #1963 | class: a fail-closed validation gate on a store/import path is relaxed to warn-only before the compensating resolution path that makes relaxation safe is shipped, allowing uncorrectable orphaned rows to accumulate undetected | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan that relaxes a fail-closed guard on a store or import path (error → warn or removed) must verify that the compensating resolution path ships in the same PR or is already in prod, OR explicitly scope out orphan accumulation with a follow-up | prior: -- |
| 2026-08-23 | #1969 | class: an importer writes to a secondary tracking table but omits the corresponding write to the canonical flag column in the primary table — the secondary write satisfies the importer's narrow contract while the flag silently stays at its default | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an importer that writes to a secondary/audit table must carry an integration test verifying the canonical flag column in the primary table is also updated after a full import cycle | prior: -- |
| 2026-08-26 | #1996 | class: SQL table names in BaseMysqliRepository subclasses inserted without backtick quoting, silently bypassing the rewriteTableNames() invariant that all repository SQL be rewrite-eligible | routed to: Rung 1 - PHPStan rule over SQL string literals in BaseMysqliRepository subclasses, asserting all bare table-name identifiers are backtick-quoted in INSERT/UPDATE/DELETE statements | prior: -- |
| 2026-08-27 | #2002 | class: Phase 6 conflict-audit runtime dependency (/tmp/pr-ready-diff-pre-<N>.patch) deletable by bin/pr-ready-now:246 (rm -f /tmp/pr-ready-*-"${PR}".*), leaving the audit unable to verify conflict resolution without a reconstruction workaround | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan modifying the /pr-ready skill's tmp-file cleanup in bin/pr-ready-now must verify the Phase 6 conflict-audit path (/tmp/pr-ready-diff-pre-<N>.patch) is excluded from the cleanup glob | prior: -- |
| 2026-08-29 | #2023 | class: an unconditional detection check in an audit class is nested inside a fail-open guard conditioned on data availability, causing the check to silently skip when the guard condition is false instead of running independently | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a detection check in an audit class that has a fail-open guard must verify the check fires even when the guard-controlling condition is false (e.g., ScheduleReconciliationAudit with empty schedule index) | prior: -- |
| 2026-08-31 | #2042 | class: CLI entrypoint accepts an unknown flag silently instead of erroring — Rung 1 rule delivered | routed to: Rung 1 complete — BanUnknownCliOptionRule added in ibl5/phpstan-rules/; fires on all getopt() calls; 2 existing callers (scripts/build-engine-bundle.php, scripts/runEngineShadow.php) baselined as temporary; closes class registered 2026-07-25 row (#1654 trigger, interim Rung 3 in #1668) | prior: #1354, #1496, #1654 |
| 2026-09-01 | #2054 | class: a two-phase CLI tool that collects human judgment for a set of items does not short-circuit when the set is empty, forcing an unnecessary second invocation and opening a failure window in the inter-invocation gap | routed to: Rung 4 - rule doc in .claude/rules/ stating that two-invocation CLI scripts must implement the trivial bypass when invocation 1 produces an empty judgment set | prior: -- |
```

---


---

### L42 Autonomous-loop PR ships stale line citations, undeclared plan substitution, unmentioned diff file, and duplicate backlog ID

*(discovered 2026-09-01 during #1966)*

**class:** an autonomous-loop run authors or squash-rebases a PR body containing hand-written line citations, plan-substitution declarations, and a scope file list — none of which are re-validated after the commit history changes; and a sequential backlog ID is assigned without checking the preceding entry for duplication.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1966 body — `bin/plan-now:449` (should be `:453`) and `bin/plan-now:583` (should be `:587`) post-rebase | yes | yes | fixed this pass (via `gh pr edit`) |
| 2 | PR #1966 body — plan-item 5 substituted but PR body carries no substitution declaration | yes | yes | fixed this pass (via `gh pr edit`) |
| 3 | PR #1966 body — `ibl5/docs/backlog/loop-engineering-backlog.md` modified in diff but absent from Scope section | yes | yes | fixed this pass (via `gh pr edit`) |
| 4 | `ibl5/docs/backlog/loop-engineering-backlog.md` — second `L39` entry created without checking that `L39` already existed | yes | fixed this pass | fixed this pass |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No gate re-validates hand-authored PR body line citations against post-rebase line numbers, and no gate checks sequential backlog ID uniqueness.
- **rung 1 — extend an existing gate?** **Yes — landing rung for the duplicate ID.** `bin/check-docs` already parses backlog tables; extending it to assert that each ID appears exactly once in its file's table is structurally the right host. Backlog-entry authoring (`_remediation.md`) should also instruct the author to grep for the proposed ID before writing. The PR-body citation gap is a harder problem (line numbers drift after rebase) and warrants a separate tracking note.
- **rung 2 — a rule doc?** Augment `ibl5/docs/backlog/loop-engineering-backlog.md`'s authoring notes (or a shared backlog-authoring companion) to include "grep for the ID first" before assigning.
- **rungs 3–5** — N/A. Line-number citations in free-form PR prose cannot be mechanically validated without reparsing the referenced file at the PR's HEAD.

`artifact destination:` `bin/check-docs` unique-ID extension (in-repo; ship-pipeline surface — wants a `/plan`). Authoring note: wherever `_remediation.md` instructs backlog-entry creation.


## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
