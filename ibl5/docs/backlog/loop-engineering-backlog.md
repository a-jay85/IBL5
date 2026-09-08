---
description: Loop-engineering backlog — automouse queue robustness (dependency ordering, circuit breakers, canaries, self-healing), autonomous intake loops, plan decomposition/tier-routing machinery, and the human comprehension counter-loop, with per-entry status.
last_verified: 2026-09-08
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
| L1 | Plan dependency DAG | ✅ Implemented | — | M |
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
| L20 | post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6) | ✅ Implemented (2026-09-06) | — | M |
| L21 | Phase 5.0 parsers fail-open on an unclosed code fence (conformance check covers nothing) | ⬜ Open | 🟥 | S |
| L22 | Sweep queue-vs-review disposition gates across other skills/scripts | ✅ Implemented | — | S |
| L54 | Archive closure Status line can assert full sweep completion while named candidate sites lack documented dispositions, creating a permanently false record | ⬜ Open | 🟥 | S |
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
| L36 | `/post-plan` Phase 3 writes a hardcoded "covered by unit and E2E tests" clause into the PR body without checking the diff contains those test types | ✅ Implemented | — | S |
| L37 | PR body declares only the plan's named files; changes the plan never named ship undeclared, so a reviewer cannot separate intended scope from drift | ⬜ Open | 🟦 | S |
| L38 | Headless CI watcher killed: `local_bash` not awaited by wind-down sweep — phantom success under `claude -p` | ✅ Shipped #2026 | 🟦 | S |
| L39 | Autonomous PR body omits plan-deliverable moot-at-branch-cut explanation and asserts unchecked test coverage | ⬜ Open | 🟥 | S |
| L40 | Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8) | ✅ Shipped #2112 | 🟥 | S |
| L41 | Plan Verification Matrix rows can ship unrealised — nothing checks a plan's declared assertions against the tests actually delivered | ⬜ Open | 🟥 | S |
| L42 | Autonomous-loop PR ships stale line citations, undeclared plan substitution, unmentioned diff file, and duplicate backlog ID | ⬜ Open | 🟦 | S |
| L43 | Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit | ✅ Implemented | — | S |
| L44 | Upstream overlap silently drops a plan phase; Phase 2a pre-rebase artifact captures post-rebase state, making the drop undetectable | ✅ fixed this pass | 🟦 | S |
| L45 | `/pr-ready` Phase 2 squashes load-bearing commit boundaries when `auto_merge: false`; PR body SHAs go stale after force-push | ⬜ Open | 🟥 | S |
| L46 | Queued matrix-less plan with non-canonical `impl_model:` alias slips all pre-queue gates; runner disposes on first nightly run | ✅ Done | 🟦 | S |
| L47 | `/pr-ready` folds a recoverable pre-push-hook rebase rejection into the terminal `PUSH FAILED` verdict, stranding the Phase 6.5 remediation commit locally | ✅ Implemented | 🟥 | M |
| L48 | Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`, so they can diverge from `bin/plan-now`'s runtime slug derivation silently | ✅ Implemented (2026-09-04) | 🟦 | S |
| L49 | `/pr-ready` Phase 6.5 files backlog rows with non-canonical status glyphs and automouse values, making them invisible to open-work filters | ⬜ Open | 🟥 | S |
| L50 | `bin/pr-cycle` logs gate nominees as "excluded this run" but then orders and readies them (`--gate-edges /dev/null` re-admits every nominee) | ⬜ Open | 🟦 | S |
| L51 | Plan Phase 5 dry-run count propagated to archive only, not PR body; reviewer blast-radius instruction stale by ~23% | ⬜ Open | 🟦 | S |
| L52 | Test harness case comment over-claims assertion scope; adjacent cases leave `run_block` exit codes unchecked | ✅ fixed this pass | — | S |
| L53 | Phase 2 test code lost in branch rebuild — invisible because CI passed without the tests | ✅ fixed this pass | — | S |
| L55 | PR body mislabels Phase 6.5 remediation artifacts; new backlog entry inserted contextually collides with master's concurrent sequence advance | ✅ fixed this pass | 🟥 | S |
| L56 | PR #1900 Phase 6.5 — PR body misidentified ADR (0104→0114), omitted plan-mandated consent statement, undercounted test cases, contradicted pre-prod exception; all four fixed this pass | ⬜ Open | — | XS |
| L69 | `/pr-ready` re-ran Phase 6 Opus fidelity spawn on every cycle, even when tree SHA unchanged | ✅ Implemented | — | S |
| L57 | `bin/pr-ready-now:434` claims both `STOP:` and `PUSH FAILED` are matched as line prefixes, but only `STOP:` is anchored; `PUSH FAILED` uses unanchored `grep -qF`. Decide whether to anchor `PUSH FAILED` or correct the comment — a gate change needing its own verification, deliberately out of scope for L47. | ⬜ Open | 🟥 | S |
| L58 | Reconcile `~/claude-plans/pr-ready-dm-and-push-retry.md` with the `HOOK REJECTED` verdict: §6.1's *"`PUSH FAILED` is genuinely non-retriable"* is now scoped, and the `.claude/skills/pr-ready/scripts/push.sh` `shasum` pinned at Phase 6.6/8.3 is stale because this PR edited that file. Re-record the digest before executing that plan. | ⬜ Open | 🟦 | S |
| L59 | PR body coordinate citations (backlog row IDs, source line numbers) go stale after commits that renumber rows or shift code — no gate recomputes or validates them after push | ⬜ Open | 🟦 | S |
| L60 | Third recurrence of L59 class on PR #2083: body notes (c)/(d)/(e) cited L53/L54 after Phase 3 renaming to L57/L58; archive `(see L53)` also stale — both fixed this pass | ✅ fixed this pass | — | XS |
| L61 | Plan document mandated a string literal for the push.sh discriminator without cross-checking the hook source; initial implementation shipped dead recovery code | ✅ fixed this pass | — | XS |
| L62 | Phase 6 notes N-2 and N-3 — class n/a for both: N-2 (sixth file mandatory by backlog-housekeep, declared), N-3 (plan Verification Matrix rows cite wrong literal, outside repo scope) | ✅ fixed this pass | — | XS |
| L63 | PR #1899 Phase 6 notes — `fixed`+`terminal` skip and undeclared backlog addition | 📝 Note | — | XS |
| L64 | `fixed`+`terminal:true` in Gate-1 reject skips tier-climbing | ⬜ Open | — | XS |
| L65 | PR #1899 Phase 6.5 — backlog ID collisions from Phase 6.5 self-filing; three new entries collided with pre-existing IDs (L53→L63, L54→L64, E62→E68) | ✅ fixed this pass | — | S |
| L66 | PR #1899 Phase 6 plan-quality notes N-2/N-3/N-4 — class n/a; out-of-plan diff files (N-2), out-of-plan `timeout` fix (N-3), test-row renumbering consistent (N-4) | 📝 Note | — | XS |
| L67 | PR #2123 Phase 6.5 — stale hand-written Scope prose after remediation commit; VR matrix path note; row-8 tick vs. prior-run record | ⬜ Open | — | XS |
| L68 | `pr_manual_testing_clearance` callers in the ship pipeline pass only one argument; the keyword–file AND gate never fires at runtime | ⬜ Open | 🟦 | S |
| L70 | `pr-body-negative-claim-recheck.md` covers negative-claim-list re-reads but not Summary-prose re-reads when a post-review commit modifies a Summary-mentioned file | ⬜ Open | 🟦 | S |
| L71 | Autonomous-loop impl deviated from plan "exact content" recipe without declaring the deviation in the PR body | 📝 Note | — | XS |
| L72 | Loop-authored backlog entry cited unreachable squash-artifact SHA; archive entry missing blank line before GFM table | 📝 Note | — | XS |
| L73 | Forced-verification row in `_plan-verification.md` references lsof port guard deleted before shipping — row's live-instance check cannot self-verify | ⬜ Open | 🟥 | S |
| L74 | `write_canary_park_report()` glob-pipeline abort under `set -euo pipefail` (fixed); N1 declared-omission note (n/a) | ✅ Fixed | — | XS |
| L75 | `/plan` byte target derived without measuring the verbatim-protected floor — `_plan-verification.md` cap corrected to 21504 B | ⬜ Open | 🟦 | S |
| L76 | `bin/lib/plan-depends-on` fail-open on unreadable plan (fixed); portable self-heal cases placed behind macOS-only guard in test harness (fixed) | ✅ Fixed | — | S |
| L77 | PR body claimed "20 verification rows, V1–V20" when 12 matrix rows realized; `automouse-workflow.md` compression undeclared (both fixed in PR body) | ✅ Fixed | — | XS |
| L78 | PR #2178 fidelity-review notes (a–e): doc omissions in `automouse-workflow.md`, test-harness escape hatches, V17/V20 annotation mismatches, dead-code guard asymmetry — all non-blocking, none fixed this pass | ⬜ Open | 🟦 | S |
| L79 | `mtime_of()` in `bin/test-automouse-depends-on` used `stat -f %m \|\| stat -c %Y`; GNU stat `-f` exits 0 with filesystem verbatim output on Linux, blocking the `\|\|` fallback — V9 failed on Linux CI (fixed) | ✅ Fixed | — | XS |

### L1 Plan dependency DAG
➜ L1 Plan dependency DAG — ✅ Implemented (2026-09-08): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
➜ L20 post-plan body-rewrite clobbers `Depends-on:`, bypassing arm condition (6) — ✅ Implemented (2026-09-06): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L21 Phase 5.0 parsers fail-open on an unclosed code fence (conformance check covers nothing)
**Location:** `tools/postplan-harness/harness/planfile.py` — `parse_matrix` and `parse_critical_files` both route through `_strip_fenced`; see the `parse_matrix` docstring on branch `matrix-fence-strip` for the full exposition. `bin/check-plan:477` (`cf_fence_unbalanced`) and its gate comment at `bin/check-plan:470`.
**Problem:** `_strip_fenced` is a line-level fence state machine: when a code fence opener has no matching closer, every subsequent line is treated as inside the fence and is swallowed. Both `parse_matrix` and `parse_critical_files` call it, so on a plan with an unclosed fence they return empty collections (`planned_test_paths == []`, `critical_files == []`). Phase 5.0's conformance check then has nothing to evaluate and passes silently — arm condition (3) never fires. That is a fail-open: the plan→diff conformance check is skipped entirely, with no signal that anything was skipped. `bin/check-plan` gate `[F]` does reject unbalanced fences, but its own comment (`bin/check-plan:470`) states it "only ever sees newly-authored plans" — a plan authored before that gate landed can still reach Phase 5.0 undetected. Live exposure is currently zero: 1 of 260 plans in `~/claude-plans/` is unbalanced (`sonnet-recipe-completeness-lint.md`) and it already shipped, so this is latent, not actively firing.
**Suggested direction:** Have the harness report an unbalanced fence as `INDETERMINATE` rather than an empty list, so Phase 5.0 holds loudly instead of passing quietly — mirroring how condition (3) already treats unresolved items. An alternative is to run `cf_fence_unbalanced` from the post-plan path as a pre-parse guard. This changes a Phase 5.0 contract and wants a `/plan`; do not fix ad-hoc (`.claude/skills` ship-pipeline invariant per `.claude/rules/work-triage.md` § Ad-hoc safety mirror).
**Risk if untouched:** A malformed plan that slips past `bin/check-plan` [F] silently voids the conformance gate at ship time, with no indication that it did so.
**Status (2026-07-29):** ⬜ Open — 🟥 (ship-pipeline invariant; loop-machinery changes should default to `auto_merge: false`). (discovered 2026-07-29 during matrix-fence-strip; documented in `parse_matrix` docstring on that branch)

### L22 Sweep queue-vs-review disposition gates across other skills/scripts
➜ L22 Sweep queue-vs-review disposition gates across other skills/scripts — ✅ Implemented (2026-08-31): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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

➜ L36 `/post-plan` Phase 3 hardcoded "covered by unit and E2E tests" — ✅ Implemented (2026-08-31): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
➜ L40 Compiled post-plan harness crashes on any PR containing a binary file (`git diff` decoded as strict UTF-8) — ✅ Implemented (2026-09-05): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

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
| 2026-08-16 | #1897 | class: integer from $_GET used as a for-loop upper bound without a domain ceiling guard, enabling DoS via unbounded iteration until a dedicated fix closed it | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): plan introducing a loop with user-input bounds must assert over-horizon input is rejected before the loop begins | prior: -- |
| 2026-08-16 | #1901 | class: htmx request-lifecycle handlers (beforeRequest/afterRequest) that mutate DOM state leave that mutation serialized in the history cache; browser Back restores request-time snapshot, making the mutation permanent until a historyRestore handler repairs it | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced E2E triggers): plan adding/modifying htmx beforeRequest/afterRequest DOM mutations must require E2E coverage of browser Back behavior | prior: -- |
| 2026-08-19 | #1925 | class: queue enqueue operation inherits mtime from the queued file rather than stamping the ordering key at insertion time, silently misordering entries with old authoring dates | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an enqueue or requeue path in bin/automouse/queue must test back-of-queue placement with an ancient-mtime plan | prior: -- |
| 2026-08-19 | #1930 | class: a plan that stops ongoing data corruption in an import or upsert path ships without a compensating backfill migration for rows already corrupted before the fix | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan removing or modifying an importer or upsert path that was writing an incorrect value must verify a compensating backfill ships in the same PR or explicitly scope out already-corrupted rows in the plan | prior: -- |
| 2026-08-17 | #1900 | class: shell scripts use BSD-specific stat/date invocations without GNU/Linux fallbacks, failing on Linux CI but passing macOS dev — not caught by shellcheck | routed to: Rung 4 - new rule doc .claude/rules/shell-portability.md guiding dual-form stat/date patterns (stat -c %Y ... || stat -f %m ...) for cross-platform shell scripts | prior: -- |
| 2026-08-19 | #1923 | class: worktree batch-sync script uses git rebase instead of git merge on already-published branches, rewriting shared commit history | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a worktree batch-sync script must assert that a branch with already-published commits is handled without rewriting local commit history | prior: -- |
| 2026-08-21 | #1953 | class: cap-validation or salary-comparison logic selects a salary-basis column (current vs. next-year) without consulting the league phase, producing incorrect hard-cap outcomes during offseason | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying salary-comparison or cap-enforcement logic must carry verification rows for both the in-season path (advancesContractYears()=false, current_salary basis) and the offseason path (advancesContractYears()=true, next_year_salary basis) | prior: -- |
| 2026-08-22 | #1963 | class: a fail-closed validation gate on a store/import path is relaxed to warn-only before the compensating resolution path that makes relaxation safe is shipped, allowing uncorrectable orphaned rows to accumulate undetected | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan that relaxes a fail-closed guard on a store or import path (error → warn or removed) must verify that the compensating resolution path ships in the same PR or is already in prod, OR explicitly scope out orphan accumulation with a follow-up | prior: -- |
| 2026-08-23 | #1969 | class: an importer writes to a secondary tracking table but omits the corresponding write to the canonical flag column in the primary table — the secondary write satisfies the importer's narrow contract while the flag silently stays at its default | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an importer that writes to a secondary/audit table must carry an integration test verifying the canonical flag column in the primary table is also updated after a full import cycle | prior: -- |
| 2026-08-26 | #1996 | class: SQL table names in BaseMysqliRepository subclasses inserted without backtick quoting, silently bypassing the rewriteTableNames() invariant that all repository SQL be rewrite-eligible | routed to: Rung 1 - PHPStan rule over SQL string literals in BaseMysqliRepository subclasses, asserting all bare table-name identifiers are backtick-quoted in INSERT/UPDATE/DELETE statements | prior: -- |
| 2026-08-27 | #2002 | class: Phase 6 conflict-audit runtime dependency (/tmp/pr-ready-diff-pre-<N>.patch) deletable by bin/pr-ready-now:246 (rm -f /tmp/pr-ready-*-"${PR}".*), leaving the audit unable to verify conflict resolution without a reconstruction workaround | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan modifying the /pr-ready skill's tmp-file cleanup in bin/pr-ready-now must verify the Phase 6 conflict-audit path (/tmp/pr-ready-diff-pre-<N>.patch) is excluded from the cleanup glob | prior: -- |
| 2026-08-29 | #2023 | class: an unconditional detection check in an audit class is nested inside a fail-open guard conditioned on data availability, causing the check to silently skip when the guard condition is false instead of running independently | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a detection check in an audit class that has a fail-open guard must verify the check fires even when the guard-controlling condition is false (e.g., ScheduleReconciliationAudit with empty schedule index) | prior: -- |
| 2026-08-31 | #2039 | class: an awk filter in a skill file uses a reset pattern that fires after the set pattern on the same diff-header line, making the exclusion a no-op and silently passing all lines through | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying an awk filter in a skill or bin/ file must carry a CLI-executable smoke test that verifies the negative path (excluded content absent from output) and the positive path (non-excluded content present) | prior: -- |
| 2026-08-31 | #2043 | class: a skill or generator asserts a fact about its own output environment as a template constant — test types present, a tool exempt from a gate — with nothing checking the assertion holds after the diff that generates the output changes | routed to: Rung 2 - extended pr_manual_testing_clearance() in bin/lib/pr-armable.sh to fail closed when the PR body's tail clause names a test type absent from the changed-file list | prior: -- |
| 2026-08-31 | #2042 | class: CLI entrypoint accepts an unknown flag silently instead of erroring — Rung 1 rule delivered | routed to: Rung 1 complete — BanUnknownCliOptionRule added in ibl5/phpstan-rules/; fires on all getopt() calls; 2 existing callers (scripts/build-engine-bundle.php, scripts/runEngineShadow.php) baselined as temporary; closes class registered 2026-07-25 row (#1654 trigger, interim Rung 3 in #1668) | prior: #1354, #1496, #1654 |
| 2026-09-01 | #2054 | class: a two-phase CLI tool that collects human judgment for a set of items does not short-circuit when the set is empty, forcing an unnecessary second invocation and opening a failure window in the inter-invocation gap | routed to: Rung 4 - rule doc in .claude/rules/ stating that two-invocation CLI scripts must implement the trivial bypass when invocation 1 produces an empty judgment set | prior: -- |
| 2026-09-04 | #2087 | class: Shell script wrapper that cd's to its module root before invoking Python invalidates caller-provided relative path arguments, silently breaking callers that pass repo-relative paths | routed to: Rung 4 - .claude/rules/shell-wrapper-path-resolution.md | prior: -- |
| 2026-08-31 | #2040 | class: a backlog entry archived in the live file receives a table-row status flip but its section-body Status: line is left at the old open status — the two representations diverge until a follow-up fix commit corrects the section body | routed to: Rung 3 - forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan that archives a backlog entry (moves section body to archive/) must carry a verification step confirming the archived entry's Status: line is updated to ✅ Implemented | prior: -- |
| 2026-09-04 | #2092 | class: a plan-level portability claim for a shell script uses find -regex with \{n\} interval notation, verified only on macOS BSD find (where BRE supports \{n\}), not on Ubuntu GNU find (which uses emacs regex type by default and does not treat \{n\} as an interval) — the regex silently matches nothing in CI, causing the script to find no directories and skip its entire body without error | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan introducing a find -regex pattern claiming cross-platform portability between macOS and Ubuntu must carry a CI-run verification row demonstrating the regex matches on the Ubuntu runner, OR must use bash-level character-class and length filtering instead of find interval expressions | prior: -- |
| 2026-09-05 | #2117 | class: proc_open subprocess contract violations (unchecked proc_close exit, undrained stderr, NUL-unsafe delimiter) shipped undetected when a plan adds or modifies a proc_open call site without requiring subprocess contract verification | routed to: Rung 1 (partial, shipped in #2117) - BanProcOpenUncheckedExitRule in ibl5/phpstan-rules/ enforces checked proc_close exit; broader contract (stderr drain, NUL-delimiter correctness) routed to Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger) | prior: -- |
| 2026-09-05 | #2121 | class: new always-loaded rule doc committed to wrong directory tree during implementation — bin/check-rules-byte-budget scans only the correct $RULES_DIR, so the misplaced file passes the gate silently until manually relocated | routed to: Rung 4 - note in .claude/rules/doc-freshness.md clarifying always-loaded .claude/rules/*.md files must be created at the exact repo-root path, not inside any subdirectory (e.g. not ibl5/.claude/rules/) | prior: -- |
| 2026-09-05 | #2140 | class: a plan phase prescribes a specific numeric expected value for a phase-sensitive salary boundary case (e.g., cy=0) without tracing the resolver chain under each phase condition, producing an incorrect assertion that a later plan phase must overwrite | routed to: Rung 4 - new path-scoped rule doc .claude/rules/plan-phase-sensitive-expected-values.md: when a plan phase specifies an expected value for a characterization test involving resolveCurrentContractYear() or Season::advancesContractYears(), trace the resolver path under each phase condition to derive the value — domain intuition is insufficient for boundary cases where the dispatch chain collapses apparent differences | prior: -- |
| 2026-08-16 | #1899 | class: shell-function-as-timeout-argument — timeout(1) execvp()s its argument; wrapping a shell function name exits 127 at exec time, undetectable at plan-authoring time | routed to: Rung 4 - verification test at the execution site (row 9 of bin/test-bug-pipeline-hunt exercises run_under_starved_env+timeout under the credential-starved env); the exit-127 failure surfaced and fixed the argument order inline during implementation | prior: -- |
| 2026-08-21 | #1950 | class: a shell port-guard that pipes lsof output to grep -qv without stripping the column header always fires the "occupied by other process" branch regardless of actual port state, because the lsof header line never matches the process name | routed to: Rung 3 - new forced-trigger row in .claude/review-shared/_plan-verification.md (section: Forced integration-verification trigger): any plan adding or modifying a port-guard or process-detection guard that pipes lsof to a pattern filter must assert the port-free case exits with the expected free-port verdict (no false positive from the header line) | prior: -- |
| 2026-09-08 | #2174 | class: a shared plan-architect context file accumulates rationale content over successive plans with no byte-cap enforcement, silently growing the architect's cold-read token load beyond what it needs | routed to: Rung 2 - bin/test-architect-contract-split CI gate (wired in tests.yml) asserts both plan-architect context files stay within hard byte caps and that all operative content survived the split | prior: -- |
| 2026-09-08 | #2174 | class: a shell test harness under set -o pipefail uses printf-pipe-grep-q, causing grep's early exit (match found) to SIGPIPE printf and make pipefail report failure — passes on macOS, fails on Linux CI | routed to: Rung 4 - new lazy rule doc .claude/rules/shell-pipefail-grep.md (path-scoped to bin/ shell scripts, not resident) explaining the herestring fix | prior: -- |
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
| 5 | `ibl5/docs/backlog/loop-engineering-backlog.md` — iterative heading rename (L51→L53→L68) left stale L51 and L53 rows in the summary table; same-ID assertion would have caught this | yes | fixed this pass | fixed this pass (PR #2043) |

`prevention_ladder:`

- **rung 0 — already covered by an existing gate?** No gate re-validates hand-authored PR body line citations against post-rebase line numbers, and no gate checks sequential backlog ID uniqueness.
- **rung 1 — extend an existing gate?** **Yes — landing rung for the duplicate ID.** `bin/check-docs` already parses backlog tables; extending it to assert that each ID appears exactly once in its file's table is structurally the right host. Backlog-entry authoring (`_remediation.md`) should also instruct the author to grep for the proposed ID before writing. The PR-body citation gap is a harder problem (line numbers drift after rebase) and warrants a separate tracking note.
- **rung 2 — a rule doc?** Augment `ibl5/docs/backlog/loop-engineering-backlog.md`'s authoring notes (or a shared backlog-authoring companion) to include "grep for the ID first" before assigning.
- **rungs 3–5** — N/A. Line-number citations in free-form PR prose cannot be mechanically validated without reparsing the referenced file at the PR's HEAD.

`artifact destination:` `bin/check-docs` unique-ID extension (in-repo; ship-pipeline surface — wants a `/plan`). Authoring note: wherever `_remediation.md` instructs backlog-entry creation.


### L43 Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit
➜ L43 Autonomous-loop doc-fix PR body contains stale claims and inconsistent ADR authoring format after post-review commit — ✅ Implemented (2026-09-05, #2131): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L44 Upstream overlap silently drops a plan phase; Phase 2a pre-rebase artifact captures post-rebase state, making the drop undetectable

*(discovered 2026-09-02 during #1789)*

**class:** A git rebase that silently drops a plan-phase's implementation (via upstream overlap) leaves the branch with stale test assertions for the dropped code, and the Phase 2a pre-rebase artifact captures post-rebase state if a prior rebase already ran, making the drop undetectable.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/docfix-run` (dropped `bin/docfix-run:38-42` guard, superseded by PR #1861) + `bin/test-docfix-run` case 23 (stale assertion for 'API unreachable') | yes | yes | fixed this pass (test assertion corrected; PR body and ADR corrected) |

**prevention_ladder:**

- rung 0 — not covered by existing gate. No gate detects when a plan-phase's implementation is absent from the branch due to upstream overlap, and no gate checks whether the Phase 2a pre-rebase artifact predates the branch's latest reflog entry.
- rung 1 — extend Phase 2a to check whether `/tmp/pr-ready-diff-pre-<N>.patch` predates the branch's latest reflog entry; if so, recapture. This is the landing rung. Cheaper rungs are insufficient because the timing check is mechanical and can be automated.
- rung 2 — a rule doc noting that Phase 2a artifacts should be re-captured after any rebase. Insufficient alone: the artifact timestamp issue is not visible to the author.

Landing rung: 1 (extend Phase 2a capture to detect and correct post-rebase stale artifacts).

**artifact destination:** `.claude/skills/pr-ready/scripts/` (Phase 2a capture logic, in-repo)

**provenance:** (discovered 2026-09-02 during #1789)

**Status (2026-09-02):** ✅ fixed this pass (test assertion fixed; PR body and ADR corrected) — 🟦.

---

### L45 `/pr-ready` Phase 2 squashes load-bearing commit boundaries when `auto_merge: false`; PR body SHAs go stale after force-push

**class (Check 2 + Check 4):** A `/pr-ready` Phase 2 rebase delegate that applies a generic squash-is-cosmetic rule to a plan whose `auto_merge: false` flag signals a load-bearing commit boundary, silently voiding the merge gate (V-2c/V-4a/V-7a) and leaving PR body SHA citations pointing at pre-squash history that is no longer reachable from the pushed head.

**Check 3 — class: n/a:** three plan-undeclared docs (`codebase-map.md`, backlog archive, maintenance backlog) landed in the commit stack; informational finding, surfaced by Phase 5.9 files-changed comparison; no additional gate warranted beyond the existing Phase 5.9 diff.

**Check 5 — class: n/a:** the plan's V-4c/V-4d verification matrix contained a literal count that became stale after master advanced past the plan's authoring point; correctness of plan literals is the plan author's responsibility; no harness gate can distinguish intentional from accidental staleness in a plan literal.

**Occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/_rebase-and-conflicts.md` — Phase 2 delegate squash rule fires unconditionally regardless of `auto_merge` plan flag | yes (Check 2) | yes | fixed this pass (restored pre-squash stack; 11 commits rebased `--onto` new master; V-2c/V-4a verified) |
| 2 | PR body of #1797 — SHA citations pointed at `5bd71bc12` / `14b363829`, both unreachable from pushed head | yes (Check 4) | yes | fixed this pass (updated 3 SHA citations to `acbfff148a` / `09ee61e054`) |

**prevention_ladder:**

- rung 0 — not covered by an existing gate.
- rung 1 — extend `_rebase-and-conflicts.md` Phase 2 delegate to read `auto_merge:` from the plan file before squashing; if `false`, preserve individual commits. This is the landing rung for Check 2. Check 4 is self-corrected by Phase 6.5 PR body reconciliation (already implemented); no new rung needed.
- rung 2 — a rule doc noting that `auto_merge: false` signals a load-bearing commit boundary; Phase 2 must not squash. Insufficient alone: rule docs are not loaded during Sonnet delegation.
- rung 3 — not applicable (PHPStan cannot gate plan-file parsing).
- rung 4 — not applicable (CI cannot verify delegate behavior mid-run).
- rung 5 — not warranted (a push hook cannot recover a squash already committed).

Landing rung: 1 for Check 2 (extend `_rebase-and-conflicts.md`); rung 0 for Check 4 (Phase 6.5 already handles it). Check 3 and Check 5: `prevention_ladder: no gate warranted`.

**artifact destination:**
- Check 2: `.claude/skills/pr-ready/_rebase-and-conflicts.md` (in-repo)
- Check 4: `.claude/skills/pr-ready/SKILL.md` Phase 6.5 (already present; no new artifact)
- Check 3/5: `n/a — no gate`

**provenance:** (discovered 2026-09-02 during #1797)

---

➜ L46 Queued matrix-less plan with non-canonical `impl_model:` alias slips all pre-queue gates; runner disposes on first nightly run — ✅ Implemented (2026-09-05): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

---

➜ L47 `/pr-ready` hook rejection resilience — ✅ Implemented (2026-09-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

---

### L48 Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`
➜ L48 Planning pipeline prose coverage gap: code-block path expressions in `SKILL.md` are invisible to `bin/check-docs`, so they can diverge from `bin/plan-now`'s runtime slug derivation silently — ✅ Implemented (2026-09-04): see [loop-engineering-backlog-archive.md](archive/loop-engineering-backlog-archive.md).

### L49 `/pr-ready` Phase 6.5 files backlog rows with non-canonical status glyphs and automouse values

**class:** A `/pr-ready` Phase 6.5 remediation filing using non-canonical status glyphs (`🔵 filed`) and automouse values (`✗`) outside the documented five-glyph set, causing filed rows to be invisible to open-work filters and readers relying on the canonical taxonomy.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/maintenance-backlog.md:682` — row 15.31, Status and Automouse columns | yes | was live; fixed this pass | fixed this pass |
| 2 | `ibl5/docs/backlog/e2e-backlog.md:233` — row E15, Status and Automouse columns | yes | was live; fixed this pass | fixed this pass |
| 3 | `ibl5/docs/backlog/maintenance-backlog.md:28` — roll-up total not updated when row 15.31 was added | yes (related filing defect — stale count) | was live; fixed this pass | fixed this pass |

**prevention_ladder:**

- rung 0 — no existing gate validates status glyph values of new backlog rows.
- rung 1 — `bin/check-docs` could be extended to grep new `| <ID> |` rows added by the diff and validate Status and Automouse column values against the canonical set in `ibl5/docs/backlog/README.md`. Effort: S.
- rung 2 — add an explicit note to `.claude/skills/fix-and-prevent/_remediation.md` step 4 specifying the five canonical status glyphs (`⬜ Open`, `◑ Partial`, `📋 Planned`, `✅ Done`, `🚫 Declined`) and canonical automouse values (`🟩`/`🟦`/`🟨`/`🟥`/`—`). Cheaper than a CI gate and catches the defect at write time. Effort: XS.
- rung 3 — not applicable (markdown surface; PHPStan does not parse `.md` files).
- rung 4 — CI gate via extended `bin/check-docs`: possible but rung 2 is cheaper and faster.
- rung 5 — a new hook: not warranted per `meta-tooling-bar.md` (no distinct trigger event; rung 2 is the natural landing).

Landing rung: **2** — add an explicit note to `.claude/skills/fix-and-prevent/_remediation.md` step 4 before the "Bump that file's `last_verified:`" instruction, specifying canonical status glyphs and automouse values.

**artifact destination:** `.claude/skills/fix-and-prevent/_remediation.md` step 4 (in-repo)

**provenance:** (discovered 2026-09-04 during #1956)

---

### L50 `bin/pr-cycle` logs gate nominees as "excluded this run" but then orders and readies them

**class:** A log line that states a disposition the code does not apply — the worker prints `excluded this run (gate nominee, unjudged)` for every `### #N` nominee in `bin/pr-attack --gate-candidates` output, then calls `bin/pr-attack --work <WORK> --gate-edges /dev/null`, which is the *judged-empty* form: every nominee is re-admitted as orderable with no gate edges. The first live run (2026-09-05, `/tmp/pr-cycle-20260905-023625-80966.log`) printed seven "excluded" lines and then readied #2108, the first one on that list.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/pr-cycle` — the `excluded this run (gate nominee, unjudged)` echo inside the nominee loop, followed by the `--gate-edges /dev/null` re-run | yes | live — fires on every run with gate nominees | ⬜ Open |

**Why it matters:** The plan (`~/claude-plans/pr-cycle-driver.md`) said nominees are excluded for the run; the implementation orders them unjudged. Either is a defensible overnight policy — arming stays fail-closed in `bin/pr-triage`, and a gate PR merged out of order lands the affected PR in BLOCKED-CHECK for the human rather than merging it wrong. But the log must not lie: a reader debugging a surprising merge order will trust "excluded" and look elsewhere.

**Fix (pick one, S):**
- Reword to `ordered with no gate edges (gate nominee, unjudged)` and say so in the usage header — matches what the code does today; or
- Actually exclude: pass each nominee to `bin/pr-attack` as excluded (or filter them from `tried`/pick) so the log and behavior agree, at the cost of fewer merges per night.

The static-guard case in `bin/test-pr-cycle` should pin whichever wording lands, so the two cannot drift again.

**provenance:** (discovered 2026-09-05 during the first live `bin/pr-cycle --go` run, right after #2081 merged)

---

### L51 Plan Phase 5 dry-run count propagated to archive only, not PR body; reviewer blast-radius instruction stale by ~23%

*(discovered 2026-09-05 during #2108)*

**class:** A plan Phase 5 stated deliverable — recording the dry-run-measured blast-radius count in the PR body — propagated to the archive entry but not the PR body, leaving a reviewer-facing instruction citing the planning-time estimate (~772) rather than the measured figure (~626), a ~23% overstatement.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2108 body § "What this PR does to `gh-pages`" — both `~772` occurrences and the reviewer check instruction; plan `~/claude-plans/gh-pages-count-prune.md` Phase 5 states "record 626 in the archive" but does not explicitly say "update the PR body" | yes | was live; fixed this pass | fixed this pass (both occurrences updated to ~626 with measurement note; reviewer instruction now uses `<N>` placeholder) |

**prevention_ladder:**

- rung 0 — not covered. Phase 6 check 4 (`_plan-fidelity-review.md` 6d.4) catches this at review time, as it did here, but not at authoring time.
- rung 1 — extend Phase 5 of `/pr-ready` or the plan template to add an explicit instruction: "after recording the dry-run measurement, update the PR body in the same phase." No new gate required; the existing Phase 6 check 4a already flags a disagreement as blocking.
- rung 2 — a rule doc (or an addition to the plan template's Phase 5 dry-run section) stating that any plan phase that measures and records a value must propagate that value to the PR body before proceeding. Cheaper than rung 1 but advisory only.

Landing rung: **2** — add a sentence to the plan template's Phase 5 dry-run section stating that the measured count must be reflected in the PR body in the same phase, before CI is re-watched.

**artifact destination:** plan template or `.claude/skills/pr-ready/SKILL.md` Phase 5 prose (in-repo)

**provenance:** (discovered 2026-09-05 during #2108)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L52 Test harness case comment over-claims assertion scope; adjacent cases leave `run_block` exit codes unchecked

*(discovered 2026-09-05 during #2108)*

**class:** A test harness case comment asserts a behavioral property ("1 tracked removed") that the case's assertions do not verify; adjacent cases also capture `run_block` exit codes into a variable but do not assert them, so a non-zero exit silently masks the real cause.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | test-vr-pages-prune:134 — Case 4 comment said "1 tracked removed" but assertions only check rc=0, stray/ presence, and no fatal in stderr | yes | was live at discovery | moot — test-vr-pages-prune has since been retired on master; prune logic moved to `bin/prune-vr-galleries` / `bin/test-prune-eligibility` |
| 2 | test-vr-pages-prune:142 — `rc4=$?` captured but only asserted as `[ "$rc4" -eq 0 ]`; Cases 1, 2, 3 call `run_block` bare with no explicit exit-code capture | near-miss | was live at discovery | moot — same retired harness |

**prevention_ladder:**

- rung 0 — not covered. `shellcheck` does not validate assertion accuracy vs comment claims.
- rung 1 — a dedicated per-harness comment-vs-assertion lint: not warranted for a single 7-case harness.
- rung 2 — no rule doc warranted; the fix is inline and the occurrence is isolated.

Landing rung: **no gate warranted** — neither occurrence exists in the tree after test-vr-pages-prune was retired; the class is real but disproportionate to a standing gate, and Phase 4B structured code review (Agent E) already surfaces this class at review time.

**artifact destination:** n/a

**provenance:** (discovered 2026-09-05 during #2108)

**Status (2026-09-05):** ✅ moot — harness retired this PR.
### L54 Archive closure Status line can assert full sweep completion while named candidate sites lack documented dispositions

*(discovered 2026-09-05 via PR #2040 Phase 6 plan-intent fidelity review)*

**Location:** Any `loop-engineering-backlog-archive.md` entry whose body enumerates candidate sites for a multi-site sweep.

**Problem:** A Status line stamped ✅ Implemented after converting one site — while the body still names other sites as candidates requiring assessment — produces a permanently false closure. The archive is read-only after merge; a false closure there is uncorrectable without a follow-up PR.

**Suggested direction:** When archiving a multi-site sweep entry, the Status line must name every site listed in the body and state its disposition (converted, assessed/out-of-scope, or waived with reason). Add a check to `.claude/skills/fix-and-prevent/_remediation.md` step 4 noting this requirement before "Bump that file's `last_verified`".

**provenance:** (discovered 2026-09-05; PR #2040 L22 Status line claimed full sweep when gate-14, post-plan/SKILL.md, and bin/plan-now candidate dispositions were undocumented; corrected in same PR)

---

### L53 Phase 2 test code lost in branch rebuild — invisible because CI passed without the tests

*(discovered 2026-09-06 during #2141)*

**class:** A plan phase's test code that was implemented and committed was lost in a manual branch rebuild; the loss was invisible because CI passed — the missing tests had no footprint left to catch their own absence.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-automouse-queue` (Phase 2 rows 21-26 + row-15 tightening) | yes | yes | fixed this pass |

**prevention_ladder:**

- rung 0 — already covered: `/pr-ready` Phase 6 check 5 (Verification Matrix realisation) catches absent declared automated test paths, as demonstrated by finding F3 in this very run. Landing rung is **0 — already covered by existing gate.**
- rungs 1-5 — superseded by rung 0.

**artifact destination:** `.claude/skills/pr-ready/SKILL.md` Phase 6 (the gate that caught this)

**provenance:** (discovered 2026-09-06 during #2141)

**Status (2026-09-06):** ✅ fixed this pass — 🟦.

### L63 PR #1899 Phase 6 notes — `fixed`+`terminal` skip and undeclared backlog addition

**class:** two notes from Phase 6 review of #1899, both non-blocking.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/bug-pipeline-tick` — `terminal:true` check fires before a Gate-1 reject exits the tier-1 loop, sending the hunt to `give_up_needs_human` without climbing further tiers; the plan described rejection at tier 1 as never reaching a human directly (F3) | yes | live | 📝 Note — fail-safe direction; warrants its own plan |
| 2 | `ibl5/docs/backlog/loop-engineering-backlog.md` — PR #1899 added a shell-function-as-timeout-argument row but did not mention the backlog change in the body Scope prose (F6) | near-miss | resolved | 📝 Note — additive and doc-only |

**F3 detail:** A result carrying `verdict="fixed"` AND `terminal:true` that fails Gate 1 (`observed_before != reported`) triggers the `terminal` branch and calls `give_up_needs_human` immediately. The plan stated tier-1 rejections climb to tier 2 before giving up. The observed behavior is fail-safe — the hunter never ships a bad fix; a human receives the findings — but the escalation path is bypassed. Fixing it requires either clearing `terminal` before the reject exits, or adding a dedicated test row for this combination. Neither is a quick tweak; file as its own plan.

**F6 detail:** `ibl5/docs/backlog/loop-engineering-backlog.md` is listed in the `files-changed` block, but the Scope prose does not mention it. The change is additive and doc-only. Phase 5.9 already surfaces it via the files-changed block.

**prevention_ladder:**
- F3: no gate warranted — the combination of `fixed`+`terminal:true` being rejected by Gate 1 is not exercised in rows 50–55; fixing correctly requires a dedicated plan.
- F6: no gate warranted — additive backlog additions are already surfaced by Phase 5.9 files-changed; a rule requiring Scope prose for every backlog touch would be low-value.

`prevention_ladder: no gate warranted for either finding`

`artifact destination: n/a — no gate`

*(discovered 2026-09-05 during Phase 6 review of #1899)*

### L64 `fixed`+`terminal:true` in Gate-1 reject skips tier-climbing

**class:** a `verdict:"fixed"` result also carrying `terminal:true` that is rejected by Gate 1 (`observed_before != reported`) falls through to the `terminal` check at line 956 of `bin/bug-pipeline-tick`, skips the remaining model tiers, and hands directly to `give_up_needs_human` — contrary to the plan's stated intent that every Gate-1 reject climbs the full ladder before reaching a human.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/bug-pipeline-tick:943-958` | yes | yes | not fixed — filed |

**prevention_ladder:**
- rung 0 — no existing gate covers this; the test harness does not exercise `fixed`+`terminal:true` together.
- rung 1 — extend `bin/test-bug-pipeline-hunt`: a dedicated row asserting the combination climbs to sonnet/opus before landing on `give_up_needs_human`. Machine-verifiable.
- rung 2 — n/a (no rule doc needed; the existing plan prose already states the intent).
- rung 3–5 — n/a; a harness row (rung 1) is sufficient.

Landing rung: **rung 1** — a test row plus the matching `bin/bug-pipeline-tick` fix; warrants its own `/plan` to design the assertion and the guard change correctly. Rungs 2–5 not needed for this class.

**artifact destination:** `bin/test-bug-pipeline-hunt` (in-repo) + `bin/bug-pipeline-tick` (in-repo)

**provenance:** (discovered 2026-09-06 during Phase 6 review of #1899, surfaced by Phase 4B on 2026-08-16 at 75/100 sub-threshold)

---

### L68 `pr_manual_testing_clearance` callers in the ship pipeline pass only one argument; the keyword–file AND gate never fires at runtime

**class:** A gate predicate in `bin/lib/pr-armable.sh` extended with a second `changed_files` parameter and AND-semantics keyword enforcement, where every production caller in the ship pipeline still passes only the body argument, so the keyword–file gate is structurally unreachable at runtime.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/lib/pr-armable.sh` — OR-semantics accumulation in `pr_manual_testing_clearance` | yes | was live; fixed this pass (PR #2043) | fixed this pass |
| 2 | `.claude/skills/post-plan/_phase-6.5-arm-auto-merge.md` — all `pr_manual_testing_clearance` call sites pass only `$BODY` | yes | live | not fixed — filed |
| 3 | `.claude/skills/pr-ready/scripts/holds.sh:37` — `run_predicate "manual-testing-clearance" pr_manual_testing_clearance "$BODY"` passes only one arg | yes | live | not fixed — filed |
| 4 | `bin/pr-triage:288` — `pr_manual_testing_clearance "$BODY"` passes only one arg | yes | live | not fixed — filed |

**prevention_ladder:**

- rung 0 — no existing gate checks argument arity of sourced-shell-function calls.
- rung 1 — `bin/check-docs` or a dedicated `bin/test-pr-armable-wiring` (example) could grep each caller site and assert it passes two arguments; extend-before-add bar favors extending an existing test script if one already covers these callers. Effort: S.
- rung 2 — adding a note to `_phase-6.5-arm-auto-merge.md` naming the required second argument is cheaper but prose-only, so insufficient on its own for a headless caller that cannot read rules mid-run.
- rung 3 — not applicable (shell surface; PHPStan does not parse shell scripts).
- rung 4 — a CI gate that greps caller sites for the single-arg pattern after the function signature changes. Possible; rung 1 is the natural form for this repo.
- rung 5 — not warranted per `meta-tooling-bar.md`; rung 1 is the correct host.

Landing rung: **1** — extend the existing `ibl5/tests/Cli/PrArmableLibCliTest.php` or a shell-level wiring test to assert that each caller in `_phase-6.5-arm-auto-merge.md` and `scripts/holds.sh` passes the `changed_files` argument.

**artifact destination:** `ibl5/tests/Cli/PrArmableLibCliTest.php` (in-repo, extends existing test class) or a new `bin/test-pr-armable-wiring` (example) (in-repo shell test).

**provenance:** (discovered 2026-09-05 during #2043)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L57 `bin/pr-ready-now:434` claims both `STOP:` and `PUSH FAILED` are matched as line prefixes, but only `STOP:` is anchored

`STOP:` is checked with a line-anchored pattern; `PUSH FAILED` uses unanchored `grep -qF`, so a log line containing the substring anywhere (e.g. in quoted prose or a commented-out rule) would be classified as a hard-stop marker. Decide whether to anchor `PUSH FAILED` to the line start or correct the comment to reflect the current behaviour — this is a gate change needing its own verification, and was deliberately left out of scope for L47's implementation. See also the L47 archive body for context.

**Status (2026-09-04):** ⬜ Open — 🟥.

**provenance:** (discovered 2026-09-04 during L47 implementation)

---

### L58 Reconcile `~/claude-plans/pr-ready-dm-and-push-retry.md` — §6.1 scoped, Phase 6.6/8.3 `push.sh` shasum stale

**class:** A plan-doc (`~/claude-plans/pr-ready-dm-and-push-retry.md`) whose §6.1 prose claim (`PUSH FAILED` is genuinely non-retriable) and Phase 6.6/8.3 `shasum` pin for `.claude/skills/pr-ready/scripts/push.sh` both become stale when `push.sh` is edited in a sibling PR — non-discoverable at diff time because the plan file lives outside the repo.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/pr-ready-dm-and-push-retry.md` — §6.1 claim and Phase 6.6/8.3 `push.sh` shasum pin | yes | live | not fixed — filed (this entry) |

**prevention_ladder:** no gate warranted — the stale shasum pin fails closed (loud mismatch at run time, not silent loss); plan files live outside the repo at `~/claude-plans/` and are unreachable by CI or `bin/check-docs`. The durable fix is documented here: re-record the `.claude/skills/pr-ready/scripts/push.sh` digest before executing `pr-ready-dm-and-push-retry.md` Phase 6.6/8.3.

**artifact destination:** n/a — no gate

**provenance:** (discovered 2026-09-04 during #2083; row dropped by the 2026-09-04 18:12:52 rebase onto `0e71e6f4b`, restored 2026-09-05)

**Status (2026-09-06):** ⬜ Open — 🟦.

---

### L55 PR body mislabels Phase 6.5 remediation artifacts; new backlog entry inserted contextually collides with master's concurrent sequence advance

*(discovered 2026-09-06 during #2040)*

**class:** A Phase 6.5 remediation that (a) inserts a new backlog entry adjacent to a thematically related entry rather than at the end of the numeric sequence, producing an ID collision when master concurrently assigns the same number; and (b) describes the new entry in the PR body Scope prose with the wrong artifact form and wrong defect class, making the actual artifacts invisible to a reviewer reading the body.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/loop-engineering-backlog.md:52` (L51 contextual insert, renumbered L54) | yes | yes | fixed this pass |
| 2 | `#2040 PR body Scope prose` (L51 labeled as class-registry row with wrong defect class) | yes | yes | fixed this pass |

**prevention_ladder:**
- rung 0 — `bin/check-numbering` (existing CI gate) already caught the ID collision (finding a). Phase 6 check 4 (existing gate) already caught the body misdescription (finding b). Landing rung is **0 — already covered by existing gates.**
- rungs 1-5 — superseded by rung 0. Note: `_remediation.md` step 4 already says "read the last row's ID and increment"; a more explicit position instruction (end of sequence, not mid-table) would reinforce it without a new gate.

**artifact destination:** n/a — no gate

**provenance:** (discovered 2026-09-06 during #2040)

**Status (2026-09-06):** ✅ fixed this pass — 🟥.

---

### L59 PR body coordinate citations go stale after commits that renumber backlog rows or shift source-file lines

**class:** A PR body prose claim that cites a coordinate (backlog row ID or source-file line number) that a post-body commit changes — no gate recomputes these citations, and no rule names them as a re-read trigger alongside the negative-claim list.

**occurrence table:**

| # | Finding | Same class? | Live? | Status |
|---|---------|-------------|-------|--------|
| 1 | F1 — `#2083` body notes (c)/(d)/(e) cited `L51`/`L52` after rows were renumbered to `L53`/`L54`; archive cross-ref `(see L51)` was also stale | yes | fixed | fixed this pass (`gh pr edit` + archive file) |
| 2 | F2 — `#2083` body cited `push.sh:55`/`:41`/`:59-63` after hunk B shifted them to `:56`/`:42`/`:60-62` | yes | fixed | fixed this pass (`gh pr edit`) |

**prevention_ladder:**

- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` exists and fires on negative-scope claims after every commit. It does not cover positive citations (row IDs, line numbers). Not covered for this class.
- rung 1 — extend `pr-body-negative-claim-recheck.md`: add a parallel "positive-cite re-check" trigger that re-reads any `(see L<N>)` or `:NN` line citation in the body after any commit that renumbers rows or touches the cited file. Landing rung for the F1 sub-class.
- rung 2 — a companion rule doc reminding authors to re-verify prose line citations after any commit that adds or removes lines in the cited region. Landing rung for the F2 sub-class (automated verification would require parsing arbitrary prose numbers, which is higher cost than a rule).
- rung 3 — PHPStan rule: not applicable (shell and markdown, no PHP).
- rung 4 — CI gate: a gate could scan PR body for `:<N>` patterns and cross-check against live file line counts, but false-positive risk on prose text is high and the cost exceeds the benefit for an infrequent class.
- rung 5 — new hook: not warranted; `pr-body-negative-claim-recheck.md` is the natural host for extension.

Landing rung: **1** for backlog ID citations (extend `pr-body-negative-claim-recheck.md`); **2** for line-number citations (rule doc).

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` (extension); optionally a companion rule for line citations.

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #2083; second recurrence of F1 class on this same PR)

**Status (2026-09-06):** ⬜ Open — 🟦.

---

### L70 `pr-body-negative-claim-recheck.md` covers negative-claim-list re-reads but not Summary-prose re-reads when a post-review commit modifies a Summary-mentioned file

*(discovered 2026-09-05 during #2131)*

**class:** A post-review commit that modifies a file already named in the PR body's `## Summary` section silently invalidates Summary-section prose without triggering a targeted re-read. The existing rule (`pr-body-negative-claim-recheck.md`) already re-reads the negative-claim list on every commit; the uncovered gap is Summary-section prose — nothing cross-references the Summary-mentioned file set to trigger a re-read there.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2131 — a post-review commit modified `ibl5/docs/backlog/loop-engineering-backlog.md` (named in `## Summary`); no mechanism triggered a re-read of Summary-section prose, leaving the requirement (b) bullet inaccurate until caught by Phase 6 plan-fidelity review | yes | yes — not prevented by existing rule | ⬜ Open |

**prevention_ladder:**

- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` exists and fires on every commit, but it is a behavioral prompt only — it carries no parse of the Summary to derive a file-set trigger. Phase 6 catches this class at review time (as it did here), but not at commit-push time.
- rung 1 — extend `pr-body-negative-claim-recheck.md` to add requirement (b): when a post-review commit modifies a file named in `## Summary`, the rule fires a targeted re-read of **Summary-section prose** (the negative-claim list is already covered by the existing rule). Landing rung.
- rung 2 — a separate rule doc is not warranted; requirement (b) belongs as a named clause in the existing rule.

Landing rung: **1** (extend `pr-body-negative-claim-recheck.md` to add the Summary-file cross-reference trigger for Summary-prose re-reads as requirement (b)).

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` (in-repo)

**provenance:** (discovered 2026-09-05 during #2131)

**Status (2026-09-05):** ⬜ Open — 🟦.

---

### L71 Autonomous-loop impl deviated from plan "exact content" recipe without declaring the deviation

*(discovered 2026-09-06 during #2131)*

**class:** An autonomous-loop implementation that delivers a rule doc at significant compression relative to the plan's stated "exact content" recipe without recording the deviation or its rationale in the PR body, leaving reviewers unable to assess whether the compression was intentional.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2131 — `.claude/rules/pr-body-claims.md` shipped at ~1.2 KB against a plan recipe of ~4.5 KB; the `## What triggered this rule` section (both PR #2059 failure narratives) was dropped without declaration in the PR body | yes | yes | not fixed — filed |

**prevention_ladder:**
- rung 0 — no existing gate compares shipped rule doc content against plan recipe content.
- rung 1 — extending an existing gate: not feasible; plan recipes are free-form Markdown with no canonical diff surface.
- rung 2 — a rule doc requiring that any deviation from a plan's "exact content" recipe be declared in the PR body Scope with a one-line rationale. Landing rung.
- rungs 3–5 — not applicable; the surface is PR body authoring, not PHP code or CI.

Landing rung: **2** — a rule doc under `.claude/rules/` requiring declared deviations from "exact content" plan recipes. Zero gate overhead; surfaced at the authoring step where the cost of a miss is lowest.

**artifact destination:** `.claude/rules/` (new file, in-repo)

**provenance:** (discovered 2026-09-06 during #2131)

**Status (2026-09-06):** 📝 Note — not fixed; deviation is non-regression (enforcement norm intact); prevention filed.

---

### L72 Loop-authored backlog entry cited unreachable squash-artifact SHA; archive entry missing blank line before GFM table

*(discovered 2026-09-06 during #2131)*

**class:** An autonomous-loop-authored backlog entry that cites a commit SHA that is a squash artifact and will be unreachable on `master` after merge; and a companion archive entry whose `**occurrence table:**` heading is not followed by a blank line, causing GFM to render the table as prose.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/loop-engineering-backlog.md` L70 — cited `0ca67f8b4` (squash artifact; unreachable post-merge) | yes | fixed this pass | fixed this pass |
| 2 | `ibl5/docs/backlog/archive/loop-engineering-backlog-archive.md` L43 entry — `**occurrence table:**` not followed by blank line; GFM table renders as prose | yes | fixed this pass | fixed this pass |

**prevention_ladder:**
- rung 0 — no existing gate validates that SHAs cited in backlog entries resolve to reachable ancestors of HEAD.
- rung 1 — extending `bin/check-docs`: could warn on commit-SHA tokens in backlog prose whose SHA is not a reachable ancestor of HEAD. Feasible; low false-positive risk.
- rung 2 — a rule doc: "when filing a backlog entry, cite the PR number rather than a commit SHA; if a SHA is essential, confirm it is a reachable ancestor of HEAD at filing time." Cheaper and sufficient. Landing rung.
- rungs 3–5 — not applicable; the surface is backlog authoring, not PHP code or CI.

Landing rung: **2** — rule doc under `.claude/rules/` (or addendum to `.claude/rules/pr-body-claims.md` since both govern PR/backlog authoring quality).

**artifact destination:** `.claude/rules/` (addendum or new file, in-repo)

**provenance:** (discovered 2026-09-06 during #2131)

**Status (2026-09-06):** 📝 Note — fixed this pass (SHA replaced, blank line added); prevention filed.

---

## Burn-down process

1. Pick an entry; `/plan` it. Loop-machinery changes should default to `auto_merge: false` — a bug here costs whole nights.
2. Ship the measurement half first (T1, L3) so later entries' effects are visible.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).

### L56 PR body inaccuracies on PR #1900 (findings 4A/4B/4C/4D)

**class:** A PR body written before or shortly after a renumber commit landed, and never re-read after that commit, leaving an incorrect ADR reference, a missing plan-mandated consent statement for a destructive operation, an understated test-coverage claim, and a manual-testing declaration that contradicted the plan's own pre-prod exception.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #1900 body — "ADR-0104" should be "ADR-0114" (4A) | yes | yes | fixed this pass |
| 2 | PR #1900 body — missing "ships uninstalled; arming is reviewer's call" statement (4B) | yes | yes | fixed this pass |
| 3 | PR #1900 body — "14-case harness" understated (FB-1..FB-6 + S-1..S-11 omitted) (4C) | yes | yes | fixed this pass |
| 4 | PR #1900 body — "No manual testing needed" contradicted plan's pre-prod exception (4D) | yes | yes | fixed this pass |

**prevention_ladder:**
- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` covers stale negative-scope claims (finding 4D is adjacent to this class but is a contradicted exception, not a negative scope claim per se). Finding 4A (positive ADR reference) and 4B (missing mandated statement) are not covered by any existing rule.
- rung 2 — extend or add to `.claude/rules/`: (a) re-read ADR references after any renumber commit; (b) verify plan-mandated body statements are present before the PR is opened (destructive operations and scheduling-slice pre-prod exceptions each mandate a specific body sentence in this plan class).
- **landing rung:** rung 2 — two rule-doc additions or one combined rule covering ADR-reference re-check and plan-mandated-statement verification. Zero gate overhead; surfaced at the authoring step where the cost of a miss is lowest.

`artifact destination: this entry`

*(discovered 2026-09-05 during PR #1900 Phase 6 plan-intent fidelity review)*

### L69 `/pr-ready` re-ran Phase 6 Opus fidelity spawn on every cycle, even when tree SHA unchanged

**class:** A fail-open absence of a skip predicate in the `/pr-ready` Phase 6 orchestration: the Opus fidelity reviewer was spawned unconditionally on every cycle, even when the post-rebase `HEAD^{tree}` was byte-identical to the `**Reviewed tree:**` SHA recorded in the sticky verdict comment from the prior run. No new signal was produced; the spawn cost was paid in full.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/skills/pr-ready/SKILL.md` Phase 6 — no skip gate before the fidelity reviewer spawn | yes | yes (pre-fix) | fixed this pass |

**fix shipped (PR #2158):** Added a fail-closed skip predicate `skip-review.sh` invoked as Phase 6 step 0. It compares `HEAD^{tree}` to the `**Reviewed tree:**` SHA on line 1 of the sticky verdict comment. On a match with no conflicts, it writes carry-forward files for the prior digest and emits `SKIP-REVIEW <sha>`, authorising Phase 6 to omit the spawn. Every failure path emits `RUN-REVIEW <reason>` and exits 0 — unavailability degrades to full review, never to a halted run. `_phase7-verdict.md` updated with the body-order contract (`**Reviewed tree:**` as line 1) and skip-path composition table. `bin/test-pr-ready-skip` added (6 predicate cases + mutation check); companion assertions in `bin/test-pr-ready-now`; `bin/test-pr-cycle` fixture proving the Reviewed-tree line is invisible to `_digest_labels`.

**prevention_ladder:**
- rung 0 — no existing rule covered this class.
- **landing rung:** shipped directly — the predicate, its test harness, and the SKILL.md wiring are all in this PR. No further prevention gate is needed; the harness is the gate.

`artifact destination: .claude/skills/pr-ready/scripts/skip-review.sh, .claude/skills/pr-ready/SKILL.md, .claude/skills/pr-ready/_phase7-verdict.md, bin/test-pr-ready-skip`

*(discovered during Phase 6 cost review; shipped 2026-09-06)*

---

### L60 Third recurrence of stale coordinate citation (L59 class): PR body notes cited L53/L54 after Phase 3 renaming to L57/L58; archive cross-ref also stale

**class:** A PR body coordinate citation (backlog row L-number) not updated after a Phase 3 rebase renumbering caused the cited IDs to change, with the same stale reference appearing in the archive file that recorded the resolved row.

**occurrence table:**

| # | Finding | Same class? | Live? | Status |
|---|---------|-------------|-------|--------|
| 1 | F-1 — `#2083` body notes (c)/(d)/(e) cited L53/L54 after Phase 3 renamed branch rows to L57/L58 | yes | yes | fixed this pass (`gh pr edit`) |
| 2 | F-2 — archive `loop-engineering-backlog-archive.md:199` `(see L53)` should be `(see L57)` | yes | yes | fixed this pass |

Third recurrence of this class on #2083 (previous two: L53/L54→L51/L52 renaming and push.sh line-shift; both fixed in a prior remediation pass, recorded in L59). Prevention gate tracked in L59; this entry records the occurrence only.

**prevention_ladder:** rung 0 — L59 is the filed prevention item for exactly this class; gate not yet built. No additional prevention warranted here beyond L59.

**artifact destination:** n/a — prevention is L59.

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #2083, third occurrence)

---

### L61 Plan document mandated wrong string literal for push.sh discriminator; initial implementation shipped dead recovery code

**class:** A plan document that quoted a string literal to match at runtime without cross-checking the source that emits it, causing the initial implementation to ship a `case` arm that can never fire.
### L62 Phase 6 notes N-2 and N-3 — plan quality issues, class n/a for both

**class:** n/a — N-2 (sixth file mandatory by backlog-housekeep, declared in PR body, deviation is correct); N-3 (plan Verification Matrix rows cite the wrong literal string, but the matrix lives outside the repo and is not modified in in-PR mode).

**occurrence table:**

| # | Finding | Same class? | Live? | Status |
|---|---------|-------------|-------|--------|
| 1 | N-2 — sixth file (`loop-engineering-backlog-archive.md`) beyond plan's five-file bound; deviation mandatory and declared | class n/a | n/a | not fixed — no fix needed |
| 2 | N-3 — four Verification Matrix rows cite the old discriminator literal; matrix is outside the repo | class n/a | n/a | not fixed — outside repo scope; plan hygiene only |

**prevention_ladder:** no gate warranted — N-2's deviation is correct behavior (backlog-housekeep mandates the archive); N-3 is stale plan documentation in an out-of-repo file, acceptable given that the tests still pass and the correct behavior shipped.

**artifact destination:** n/a — no gate.

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #2083, N-2 and N-3)

---

### L65 PR #1899 Phase 6.5 — backlog ID collisions from Phase 6.5 self-filing

**class:** A Phase 6.5 self-filing pass assigned backlog IDs (L53, L54, E62) that were already in use in the same files; the collisions broke `bin/check-numbering` and corrupted the pre-existing L53 entry by splicing the new heading into the middle of its body.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `ibl5/docs/backlog/loop-engineering-backlog.md` — new L53/L54 collided with pre-existing L53/L54 | yes | no | fixed this pass (renamed L63/L64) |
| 2 | `ibl5/docs/backlog/dev-efficiency-backlog.md` — new E62 collided with pre-existing E62 | yes | no | fixed this pass (renamed E68) |

**prevention_ladder:**
- rung 0 — no existing gate catches this at filing time; `bin/check-numbering` catches it at CI but only after the commit lands.
- rung 1 — extend `bin/check-numbering` with a pre-file check or add a helper that reads the highest ID before filing, so Phase 6.5 self-filing always uses the next available ID.
- rung 2 — add a rule to `.claude/skills/pr-ready/_phase65-remediation.md` requiring the next available ID to be computed from `grep "^### [EL][0-9]"` before writing any new entry.

Landing rung: **rung 2** — a rule doc change; warrants a prose edit to `.claude/skills/pr-ready/_phase65-remediation.md` or a dedicated `/plan`.

**artifact destination:** `.claude/skills/pr-ready/_phase65-remediation.md` (rule addition)

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #1899, B-1/B-2)

---

### L66 PR #1899 Phase 6 plan-quality notes N-2/N-3/N-4 — class n/a for all three

**class:** n/a — N-2 (two backlog docs in diff not named by plan — additive and declared via Phase 5.9 files-changed block); N-3 (out-of-plan `timeout` argument fix in `bin/bug-pipeline-tick` — beneficial, not a regression); N-4 (test-row labels renumbered post-impl consistent throughout the test harness).

**occurrence table:**

| # | Finding | Same class? | Live? | Status |
|---|---------|-------------|-------|--------|
| 1 | N-2 — `ibl5/docs/backlog/loop-engineering-backlog.md` and `ibl5/docs/backlog/dev-efficiency-backlog.md` in diff but not named in plan | class n/a | n/a | not fixed — no fix needed; Phase 5.9 surfaced them |
| 2 | N-3 — `bin/bug-pipeline-tick` `timeout` argument fix shipped out-of-plan | class n/a | n/a | not fixed — fix is correct; no action needed |
| 3 | N-4 — test-row labels renumbered post-impl (rows 50-55→50-56) | class n/a | n/a | not fixed — renumbering consistent; no action needed |

**prevention_ladder:** no gate warranted — N-2 is already surfaced by Phase 5.9 files-changed; N-3/N-4 are beneficial and self-consistent out-of-plan touches.

**artifact destination:** n/a — no gate.

**provenance:** (discovered 2026-09-06 during /pr-ready Phase 6 review of #1899, N-2/N-3/N-4)
### L67 PR #2123 Phase 6.5 — stale Scope prose, VR matrix note, row-8 record note

**class:** a hand-written Scope prose block in a PR body not re-read after a post-review remediation commit changes file count and entry IDs; combined with two matrix notes (class: n/a) that resolve once sibling findings are fixed.

**occurrence table:**
| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2123 body — Scope prose | yes | fixed this pass | fixed this pass |
| 2 | `.claude/rules/pr-body-negative-claim-recheck.md` scopes to negative-claim lists only | near-miss | yes | not fixed — filed |

**prevention_ladder:**
- rung 0 — `.claude/rules/pr-body-negative-claim-recheck.md` covers negative-claim list staleness, not general Scope prose; not already covered.
- rung 1 — extend that rule to cover all hand-written Scope prose after a remediation commit; this is the landing rung.
- rung 2 — n/a; the rule doc is the mechanism.
- rungs 3–5 — n/a; the check is prose comprehension, not mechanical.
Landing: rung 1 — extend `.claude/rules/pr-body-negative-claim-recheck.md` to require re-reading ALL hand-written Scope / diff-stat prose (not just negative-claim lists) after any commit.

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` (in-repo, present in every worktree's checkout).

**F7 note (class: n/a):** Matrix row 5's declared path `ibl5/tests/e2e/smoke/visual-regression.spec.ts` is absent from the diff. Expected: the plan declares this suite to run against existing baselines; a clean VR run produces no diff by construction. prevention_ladder: no gate warranted — expected-absent path for a run-not-ship matrix row.

**F8 note (class: n/a):** Row 8 tick `[x]` in PR body conflicts with the E62 record (which logged the row as unticked from the prior review). Resolves when E62 is renumbered to E70 and its content reflects the current run. prevention_ladder: no gate warranted — one-off artifact of an ID collision that is fixed in E70.

*(discovered 2026-09-06 during /pr-ready Phase 6 review of #2123)*

---

### L73 Forced-verification row in `_plan-verification.md` references lsof port guard deleted before shipping

**class:** A forced-verification row in `.claude/review-shared/_plan-verification.md` whose described guard (lsof port guard) no longer exists in the diff when the PR was reviewed, leaving a trigger pattern with no live instance to self-verify — the class of "body written → guard deleted → body row now asserts something absent."

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `.claude/review-shared/_plan-verification.md` (lsof port-guard row, added in the Phase 9 retrospective commit) — the lsof guard it describes was removed before shipping | yes — same class as `pr-body-negative-claim-recheck.md` | live | not fixed — filed |

**Why it matters:** The forced-verification row existed to enforce that lsof-style port guards are reviewed on any PR introducing them. When the guard itself was removed from the diff, the row became a phantom trigger — it neither protects against a guard-in-PR nor signals its own obsolescence. The pr-body-negative-claim-recheck.md rule covers PR body negative claims; this class applies to `_plan-verification.md` trigger rows, which are structurally identical in their failure mode.

**Fix:** When a retrospective commit adds a `_plan-verification.md` row that references a guard, a cross-check should confirm the guard exists in the current diff before the row is committed. Alternatively, extend `pr-body-negative-claim-recheck.md` to name `_plan-verification.md` rows as a second surface where the "negative claim vs. actual diff" check applies.

**prevention_ladder:**
- rung 0 — not covered; `_plan-verification.md` rows are not checked against the current diff.
- rung 1 — cannot extend an existing gate to catch this (no gate reads `_plan-verification.md` content against the live diff).
- rung 2 — a rule doc noting that `_plan-verification.md` rows describing deleted guards must be removed or retargeted at time of deletion; `.claude/rules/pr-body-negative-claim-recheck.md` partially covers this class (the failure mode is identical); extending that rule's scope to `_plan-verification.md` rows is the landing rung.
- rung 3/4/5 — no PHPStan or CI check can validate markdown-prose alignment with a live diff.
- **landing rung: rung 2** — extend `pr-body-negative-claim-recheck.md` scope to cover `_plan-verification.md` rows; no gate warranted.

**artifact destination:** `.claude/rules/pr-body-negative-claim-recheck.md` — in-repo (extend scope to `_plan-verification.md` rows)

**provenance:** (discovered 2026-09-05 during #1950 Phase 6.5 review)

---

### L74 `write_canary_park_report()` glob-pipeline abort under `set -euo pipefail` (fixed); N1 declared-omission note

**class:** A glob-expansion pipeline in a `set -euo pipefail` command substitution (`ls "$QUEUE_DIR"/*.md`) that exits non-zero when the target directory is empty, which under the runner's `set -euo pipefail` can abort the enclosing function — fixed by replacing `ls` with `find`, which exits 0 on empty.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/automouse/run:1009` — `remaining=$(ls "$QUEUE_DIR"/*.md 2>/dev/null \| wc -l \| tr -d ' ')` | yes | live but currently unreachable (dispatch guard guarantees non-empty queue at this call site) | fixed this pass |

**prevention_ladder:**
- rung 0 — not covered by any existing gate.
- rung 1 — no existing gate checks ls-vs-find glob patterns in shell scripts.
- rung 2 — a rule note ("prefer `find` over glob-`ls` in `set -euo pipefail` subshells") is possible but the fix is a local one-liner; the rule would govern an unusually narrow pattern.
- rung 3/4/5 — shellcheck SC2012 flags this class; wiring shellcheck to CI requires a new workflow step and justification under the meta-tooling bar.
- **landing rung: no gate warranted** — the class is narrow (glob-ls in an assignment subshell under set -euo pipefail), the fix is trivially local, and shellcheck CI integration is a separate decision under the meta-tooling bar.

**artifact destination:** n/a — no gate

**provenance:** (discovered 2026-09-07 during #2161 Phase 6.5 review)

**N1 note (class: n/a):** Plan Phase 3 item 2 (mark backlog item L5 done in-repo) was withdrawn before this PR: commit `3cb8e15f3` removed the branch's backlog edits after L5 was migrated to IBL5-backlog issue #107 (closed 2026-09-07T20:05:56Z). The omission is declared in the PR body under `## Backlog migration`. No in-repo artifact exists to fix or gate. prevention_ladder: no gate warranted — one-off migration artifact, already handled.

*(discovered 2026-09-07 during /pr-ready Phase 6.5 review of #2161)*

---

### L75 `/plan` byte target derived without measuring the verbatim-protected floor

**class:** A `/plan` architect derives a file-size target by inventorying the *movable* rationale in the source file, but no `/plan` phase requires measuring the complementary quantity — the **floor** of content the same plan requires to stay verbatim. A target set below that floor is unreachable no matter how well the implementation executes, and the miss surfaces only at implementation time.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `~/claude-plans/architect-contract-rules-detail-split-shared-context.md` — inventory sized the movable rationale correctly (`## Required format` claimed 11,172 B, measures 11,197 B in `bin/fixtures/plan-verification-presplit.md` counting its `###` subsections) and that rationale was duly moved (block now 4,952 B; file 29,191 B → 20,474 B). But the plan never measured the verbatim-protected residue: it put `## Forced integration-verification trigger` at 5,647 B when it measures 7,237 B, and that section must stay verbatim. The 1,590 B underestimate of protected content exceeds the 1,018 B by which the split file misses the 19,456 B hard cap | yes (target derived without a protected-floor measurement) | live | not fixed — filed; cap corrected to 21,504 B in `bin/test-architect-contract-split` with an inline measurement citation |

**Why it matters:** When the target sits below the verbatim floor, the implementation has only two exits: correct the cap after the fact (what happened here) or reach the cap by deleting operative content. The byte-count gate (`bin/test-architect-contract-split` assertion 4) cannot tell those apart on size alone — assertions 1–3 (no-loss, residency, orphan pointers) are the real discriminator. Sizing only the movable side of the split hides the constraint that actually binds.

**Fix:** The `/plan` split-authoring workflow should require the architect to measure **both** sides from the live file before declaring a byte target: the movable rationale *and* the sum of every section the plan's own Approach marks stays-verbatim. The target must be ≥ the protected floor plus the non-movable remainder. Both are CLI-measurable, e.g. `awk '/^## Forced integration-verification trigger/{f=1} f && /^## / && !/^## Forced/{exit} f{print}' _plan-verification.md | wc -c`.

**prevention_ladder:**
- rung 0 — not covered; no plan phase measures the verbatim-protected floor.
- rung 1 — no existing gate compares a plan's declared byte target against the protected residue of the target file.
- rung 2 — a note in `.claude/skills/plan/_architect-contract.md` requiring that any byte-reduction target be accompanied by a CLI-measured protected-floor figure from the live file. Low friction, no new gate.
- rung 3/4/5 — cannot be mechanized: which sections are "stays verbatim" is declared in plan prose outside the repo, and plan content is not parsed by any CI gate.
- **landing rung: rung 2** — add a protected-floor measurement requirement to the plan-authoring contract; no CI gate warranted.

**artifact destination:** `.claude/skills/plan/_architect-contract.md` — byte-reduction recipe section, or wherever split-file targets are specified.

**provenance:** (discovered 2026-09-08 during architect-contract-rules-detail-split)

### L76 `bin/lib/plan-depends-on` fail-open on unreadable plan; portable self-heal cases behind macOS guard

**class:** A `bin/lib/` dependency evaluator that suppresses `awk` errors with `2>/dev/null`, so an unreadable plan file produces empty output — the key-absent fast-path then returns `met` rather than `unresolvable`, inverting the stated contract. A companion defect: a test harness places its portable verification cases (V6–V9) inside a macOS-only OS guard, so the Linux CI step never exercises them.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/lib/plan-depends-on:42` (pre-fix) — `awk '…' "$plan_file" 2>/dev/null` silently returns empty on EACCES/ENOENT; key-absent fast-path promoted empty to `met` | yes | live | fixed this pass |
| 2 | `bin/test-automouse-depends-on:436` (pre-fix) — Section 3 (V6–V9 self-heal cases) inside `if [ "$(uname)" = Darwin ]` guard; V9 used BSD-only `stat -f %m` | yes | live | fixed this pass |

**prevention_ladder:**
- rung 0 — no existing gate checks for `2>/dev/null` on a user-supplied path argument.
- rung 1 — no existing gate covers OS-guard placement in test harnesses.
- rung 2 — a note in `.claude/rules/automouse-workflow.md` that `plan_depends_on_status` must fail-closed (return `unresolvable`) on any file it cannot read; and that self-heal cases must not sit inside an OS guard because `bin/automouse/self-heal` itself is portable.
- rung 3/4/5 — no PHPStan/CI gate can mechanize OS-guard placement or awk-suppress scope.
- **landing rung: rung 2** — add prose note to the rule doc; no new gate warranted.

**artifact destination:** `.claude/rules/automouse-workflow.md` — `depends_on:` hold gate section.

**provenance:** (discovered 2026-09-08 during PR #2178 fidelity review)

### L77 PR body claimed unrealized verification-row count; `automouse-workflow.md` compression undeclared

**class:** A PR body that states a verification-row count higher than the number of matrix rows realized in the harness, and that silently compresses a rules file without disclosing the byte-cap trade-off — giving reviewers a false picture of test coverage and scope.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2178 Summary — "20 verification rows, V1–V20" when only V1–V11 and V14 realized; V12–V13 and V15–V20 absent from harness | yes | live | fixed this pass (PR body updated) |
| 2 | PR #2178 Summary — `.claude/rules/automouse-workflow.md` listed as "documents the `depends_on:` key" with no mention of the byte-cap compression that deleted the `$1.82→$12.86` cost example | yes | live | fixed this pass (PR body updated) |

**prevention_ladder:**
- rung 0 — no existing gate cross-checks PR body row counts against the realized test cases.
- rung 1 — no gate owns PR body accuracy for autonomous-loop runs.
- rung 2 — the existing `.claude/rules/pr-body-claims.md` and `.claude/rules/pr-body-negative-claim-recheck.md` rules cover cited figures and negative claims; extend them to require that any rules-file edit that compresses content discloses the byte-cap reason in the PR body.
- rung 3/4/5 — not mechanizable.
- **landing rung: rung 2** — add a note to `.claude/rules/pr-body-claims.md` that a rules-file edit driven by the byte cap must name the cap in the PR body.

**artifact destination:** `.claude/rules/pr-body-claims.md` — add a row to the Application table covering rules-file byte-cap compression.

**provenance:** (discovered 2026-09-08 during PR #2178 fidelity review)

---

### L78 PR #2178 fidelity-review notes (a–e): doc omissions, test-harness escape hatches, annotation mismatches, dead-code guard asymmetry

**class:** Coverage gaps left unfixed in a nightly-pipeline PR: doc omissions in a rules file, escape hatches in the test harness that suppress failure output for a known-broken row, annotation mismatches between the plan matrix and harness realisation, and a dead-code guard added asymmetrically — each individually non-blocking but collectively leaving regression paths open.

**occurrence table:**

| # | File:line | Note | Same class? | Live? | Status |
|---|-----------|------|-------------|-------|--------|
| 1 | `.claude/rules/automouse-workflow.md` — `paths:` not widened to include `bin/lib/plan-depends-on`; Phase 7 items 7.2 (queue layout line) and 7.4 (self-heal paragraph) absent; "symlink mtime preserved" and "scan before skipped/ guard" facts documented nowhere | note (a) | yes | live | not fixed — filed |
| 2 | `bin/test-automouse-depends-on` — V2/V4/V5 declare `.lock`-absent assertions; no `.lock` assertion exists in the harness; the Automouse Hold Justification's "each has a verification row" claim is therefore false for this bug shape | note (b) | yes | live | not fixed — filed |
| 3 | `bin/test-automouse-depends-on` — V14 escape hatch: hardcoded `# V14 WARN: … not counted as a failure here`; masks a genuine future regression locally on macOS | note (c) | yes | live | not fixed — filed |
| 4 | `bin/test-automouse-depends-on` / plan matrix — V17 and V20 carry "(wired in this PR)" annotation in the matrix but appear nowhere in the harness; V19 likewise has no realisation in the diff | note (d) | yes | live | not fixed — filed |
| 5 | `bin/automouse/run:1161` — `case "${DEPENDS_HELD:- }" in …` guard is dead code; `DEPENDS_HELD=" "` at top-level line 546 executes unconditionally before both call sites (1161, 1268); the `:- ` form is asymmetric with the sibling `CAP_DEFERRED` line one row above | note (e) | yes | live | not fixed — filed |

**prevention_ladder:**
- rung 0 — not covered by any existing gate.
- rung 1 — no existing gate checks rule-file `paths:` widening completeness, harness escape-hatch prose, or plan-matrix annotation fidelity.
- rung 2 — a note in `automouse-workflow.md`'s `depends_on:` section that `paths:` must list all helper scripts; and a test-authoring norm (e.g. in `.claude/rules/bin-help-span-and-secondary-assertions.md` or a new rule) that every declared secondary token and every "(wired in this PR)" annotation must have a corresponding `want` or `assert` call. Rung 2 is the landing rung for all five occurrences.
- rung 3/4/5 — not mechanizable: PHPStan/CI cannot validate prose annotations against test implementations.
- **landing rung: rung 2** — prose notes in the relevant rule docs; no new gate warranted.

**artifact destination:** `.claude/rules/automouse-workflow.md` (occurrence 1), `.claude/rules/bin-help-span-and-secondary-assertions.md` or a new harness-annotation rule doc (occurrences 2–4)

**provenance:** (discovered 2026-09-08 during PR #2178 fidelity review, notes a–e)

---

### L79 `mtime_of()` GNU stat `-f` exits 0 with wrong output — Linux CI V9 failure

**class:** A portable-stat helper that uses `stat -f %m || stat -c %Y` fails silently on Linux because GNU `stat -f` (filesystem-stat mode) exits 0 but produces verbose block/inode output, never triggering the `||` fallback — a platform-divergence defect that passes all macOS development tests but fails Linux CI.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | `bin/test-automouse-depends-on:56` — `mtime_of() { stat -f %m "$1" 2>/dev/null \|\| stat -c %Y "$1" 2>/dev/null; }` — V9 "plan mtime changed" FAIL on Linux CI (run 34272784060 job 102218662249) | yes | live | fixed (commit f2584c8e1, PR #2178 Phase 7) |

**prevention_ladder:**
- rung 0 — no gate checks portable-stat helpers for Linux/BSD divergence.
- rung 1 — ShellCheck does not catch this pattern; `-f` is a valid flag on both platforms with different semantics.
- rung 2 — norm: portable-mtime helpers must use `uname` branching, not `|| fallback`, because GNU `stat -f` exits 0 with wrong output. Add a note to `.claude/rules/shell-pipefail-grep.md` or a new rule covering portable-stat pitfalls.
- **landing rung: rung 2** — a one-sentence authoring norm; no new gate warranted.

**artifact destination:** `.claude/rules/shell-pipefail-grep.md` — add a note on `stat -f` portability: use `uname` branching, not `||` fallback.

**provenance:** (discovered 2026-09-08 during PR #2178 Phase 7 CI monitoring)

---

### L80 PR #2181 Phase 5.5 fidelity-review — Changes list omits subset of changed behaviors

**class:** an autonomous-authored PR body Changes list that omits a subset of changed code behaviors within the modified file, causing the body to under-describe the diff even though no claim is contradicted.

**occurrence table:**

| # | File:line | Same class? | Live? | Status |
|---|-----------|-------------|-------|--------|
| 1 | PR #2181 `## Changes` — two `digest: unavailable` fallback lines at `bin/pr-cycle:852–854` re-prefixed with `- ` bullet but not mentioned in the Changes list | yes | fixed (PR body updated in Phase 5.5 remediation) | fixed this pass |

**prevention_ladder:**
- rung 0 — no gate checks that a PR body Changes list enumerates all changed code behaviors.
- rung 1 — not decidable by static analysis: a PHPStan rule cannot compare prose list completeness to diff content.
- rung 2 — not a CI-gate candidate: any gate here would require semantic understanding of both the diff and the prose.
- rung 3 — not a forced-trigger candidate: the trigger table checks for test coverage patterns, not PR body completeness.
- rung 4 — a reminder in an always-loaded rule or in the fidelity-review prompt: "When authoring the Changes list, enumerate every distinct behavior change, not just the primary one; the fallback/edge-case branches of the changed function count."
- **landing rung: rung 4** — a note in the fidelity-review prompt or Phase 5.5 review criteria (check 4 already catches contradicted claims; an addendum covering omissions would prevent this without adding a new gate).

**artifact destination:** `.claude/skills/pr-ready/_plan-fidelity-review.md` — a note under check 4 that the review should flag a Changes list that omits behaviors changed in the diff (even non-contradicting omissions), so future plan-blind PRs enumerate fallback/edge-case branches alongside primary behaviors.

**provenance:** (discovered 2026-09-08 during PR #2181 Phase 5.5 fidelity review, Note 1)
