---
description: Token-spend reduction backlog — resident-context diet, caching economics, output-spend guards, and LSP-first navigation for the Claude Code harness, with per-entry status.
last_verified: 2026-07-16
---

# Token-Spend Reduction Backlog

**Purpose:** Catalogue changes that reduce the token cost of every Claude Code session on this repo — smaller resident context per turn, fewer tokens re-cached per session, less output. Each open entry is a candidate for a `/plan` (repo entries) or a direct harness edit (⌂ entries).

**Origin:** Advisory sessions (2026-07-07): a 7-day transcript analysis (280 sessions; ~1.64B cache-read / 95.5M cache-write / 19.9M output tokens per week — cache reads + writes ≈ 70% of weighted spend) plus a harness-surface audit. Statuses verified against disk, `$HOME/.claude/settings.json`, and the automouse queue on 2026-07-07.

**Re-measure (2026-07-16):** after all 13 first-wave entries shipped, a fresh 7-day aggregation (392 transcripts, 334 sessions, 23,399 assistant calls) shows ~1.90B cache-read / 95.8M cache-write / 22.6M output per week — flat vs the origin week despite the diet, because work volume grew and the residual driver moved: it is no longer resident overhead (session-start context p50 ≈ 32K, p90 ≈ 41K) but **in-session context accretion** — the average API call now carries ~81K tokens of context, and 47.9% of all weekly cache-read is issued at ≥100K context. T14–T16 target that residual.

**Companion to** the other backlogs in [README.md](README.md); same status taxonomy.

---

## Taxonomy

**Status** — canonical five-glyph set: see [README.md § Status taxonomy](README.md#status-taxonomy).

**Locus** — where the change lives:
- **⌂ harness-local** — files under `$HOME/.claude/` (settings, hooks, memory). Exempt from the worktree rule; edited in place, no PR, not automouse-shippable.
- **repo** — normal worktree → PR path.

**Effort scale:** **S** — one sitting. **M** — multi-step, 1–3 days. **L** — restructure, likely needs an ADR.

---

## Roll-up

| Status | Count |
|--------|------:|
| ⬜ Open | 3 |
| 📋 Planned | 0 |
| ◑ Partial | 0 |
| ✅ Implemented | 13 |
| 🚫 Declined | 0 |

Archived entries (✅ Implemented): see [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md).

---

## Entries

First-wave entries (T1–T13) are ✅ Implemented — see the dated pointers below and [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md). T14–T16 are the second wave, from the 2026-07-16 re-measure.

### T14 In-session context-ceiling nudge (dumb-zone spend tax)
**Locus:** ⌂ harness-local. **Effort:** S–M.
**Location:** No surface owns it today. T6 caps the window at 200K (`autoCompactWindow`), and the context-dumb-zone memory says "keep peak ~100K" — but nothing fires *during* a session as context crosses 100K, so the band between 100K and the 200K compaction cap is entirely unguarded.
**Problem:** Measured 2026-07-16 (7-day window): **47.9% of all weekly cache-read (~911M of 1.90B tokens) is issued at ≥100K context**; 26.9% at ≥125K; 256 calls ran at 200–225K. The fat tail is extreme — the top 15 sessions carry 34% of weekly reads, with the top two at ~98M and ~89M cache-read across ~900 calls each. Every call in a bloated session re-reads the whole window, so a session that should have split at 100K pays roughly double per remaining call — and reasons worse while doing it (the dumb-zone the memory warns about). The work-triage rule already names the cause (mechanical work staying inline on the main thread); what's missing is the in-the-moment signal.
**Suggested direction:** Extend an existing hook surface rather than add one (meta-tooling-bar): the PostToolUse/PreToolUse guard family (e.g. `$HOME/.claude/hooks/output-guard.sh`) can read the last `usage` block from the session transcript and emit a one-line advisory when context first crosses ~100K and again at ~125K — "context ≥100K: delegate remaining mechanical work (work-triage § execution routing), or split/hand off the session." Warn-only, once per threshold, skip subagents/headless (automouse peak-context is L16's territory). Verify effect via `bin/token-report` bucket distribution after two weeks.
**Risk if untouched:** The single largest residual spend class (~half of weekly cache-read) stays invisible at the moment it's created; T6's 200K cap only bounds the worst case.
**Provenance:** discovered 2026-07-16 during the post-backlog re-measure (advisory session).
**Status (2026-07-16):** ⬜ Open.

### T15 Read-payload accretion guard
**Locus:** ⌂ harness-local. **Effort:** S.
**Location:** `$HOME/.claude/hooks/output-guard.sh` guards unbounded **Bash** output (T10) — the **Read** tool is unguarded.
**Problem:** Measured 2026-07-16: Read results injected **17.3M chars (~4.3M tokens) into contexts over 7 days — 67% of all tool-result bytes** (next largest: Bash at 8.0M). A full-file Read early in a long session is the compounding version of the T10 problem: its tokens are re-billed as cache-read on every subsequent call, so a 5K-token Read at call 50 of a 500-call session costs ~2.25M cache-read tokens by session end. The Read tool supports `offset`/`limit` and the harness guidance says "read only the part you need," but nothing pushes back on a no-limit Read of a large file.
**Suggested direction:** Extend `$HOME/.claude/hooks/output-guard.sh` (same family as T10, an extend not an add): PreToolUse warn when a Read call targets a file over ~500 lines with no `offset`/`limit` — advisory names the LSP-first rule (symbol questions) and Explore/fork delegation (multi-file surveys) as the cheaper paths. Warn-only; skip subagents (their contexts are discarded, which is exactly where big reads *should* go).
**Risk if untouched:** The dominant tool-result payload keeps feeding T14's accretion; the two entries compound.
**Provenance:** discovered 2026-07-16 during the post-backlog re-measure (advisory session).
**Status (2026-07-16):** ⬜ Open.

### T16 Poll-shaped Bash round-trips → background/Monitor routing
**Locus:** ⌂ harness-local (hook or rule line). **Effort:** S.
**Problem:** Measured 2026-07-16: **329 `gh pr`/`gh run` calls plus ~120 nightly-queue status checks in 7 days**, largely poll loops (run, read, run again). At the measured ~81K-token average context per call, each poll is a full-window cache re-read just to ask "is it done yet" — order of 30M+ cache-read tokens/week spent on waiting. The harness already has the cheap substitutes (`run_in_background` + Monitor re-invokes on completion; `/loop` self-pacing with matched intervals; ScheduleWakeup's own guidance says one ~480s check beats eight 60s ones), but adoption is norm-level and the poll pattern persists in transcripts.
**Suggested direction:** Cheapest lever first (meta-tooling-bar): a rule-line in an existing always-loaded rule — "repeat-polling an external state (CI, deploy, queue) from the main thread is a spend bug; route it through `run_in_background`+Monitor or a matched-interval `/loop`." If transcripts still show poll loops after two weeks, escalate to an `$HOME/.claude/hooks/output-guard.sh` advisory on a repeated identical `gh pr checks`/`gh run watch`-class command within a session. Verify via `bin/token-report` call-count trend.
**Risk if untouched:** Steady ~2%-of-weekly-reads tax, and each poll turn also re-invokes the model for zero-information output.
**Provenance:** discovered 2026-07-16 during the post-backlog re-measure (advisory session).
**Status (2026-07-16):** ⬜ Open.

---

➜ T4 Driver-model downshift for babysitting loops — ✅ Implemented (2026-07-14): resolved by substitution (L6+L8+post-plan-Sonnet+`/loop`); residual measured empty. See [archive](archive/token-spend-backlog-archive.md).

➜ T13 Aggregate always-loaded rules budget — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T7 Resident-overlay diet (MEMORY.md + rules) — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T1 Automouse token ledger — ✅ Implemented (2026-07-09): see [archive](archive/token-spend-backlog-archive.md).

➜ T2 Always-loaded context budget gate — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T5 Memory/rules dedup lint — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T9 Lazy-load plan/post-plan skills — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T11 Tier-boundary plan splitting — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

➜ T12 Sonnet plan-architect for recipe-backed plans — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

**Token-relevant entries tracked elsewhere:**
- L18 (tier-default correction — `impl_model:` fails open to Opus) — [loop-engineering-backlog.md](loop-engineering-backlog.md). Supersedes the former L2 token-budget-breaker residual, closed 2026-07-15 as empirically refuted (PR #1477 closed unmerged): impl spend doesn't predict postplan spend, and the measured waste is tier misallocation (~82% of impl spend is Opus; ~$27/week routed to Opus purely by a missing `impl_model:` field), not plan length.
- L15 (Sonnet-recipe completeness lint) — [loop-engineering-backlog.md](loop-engineering-backlog.md).
- L16 (context-budget gate v2) — [loop-engineering-backlog.md](loop-engineering-backlog.md).
- E8 (memory-lines→gates umbrella, pairs with T7) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md). ◑ Partial as of 2026-07-14; one item remains (free-agents teamid write guard).
- E6 (diff-scoped PHPStan wrapper) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md): every agent verify iteration (local + automouse) drops from O(project) to O(diff) analysis — fewer polling turns and less output per loop. (Cross-referenced 2026-07-14, token-spend sweep.) ✅ Implemented (2026-07-14) — shipped in PR #1362.
- E3 (PHPStan result-cache in CI) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md): shorter CI runs → fewer CI-watch polling turns per PR. (Cross-referenced 2026-07-14, token-spend sweep.)
- E4 (flake-quarantine ledger) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md): removes flake-triggered re-run cycles and lost nightly-queue runs. (Cross-referenced 2026-07-14, token-spend sweep.)
- 5.1 (build IBLbot TypeScript on the CI runner) — [ci-backlog.md](ci-backlog.md): each droplet-side build failure (2 to date) costs manual-intervention sessions plus CI-watch re-run cycles. (Cross-referenced 2026-07-14, token-spend sweep.)

---

## Burn-down process

1. Repo entries: `/plan` or ad-hoc per the work-triage rule; ⌂ entries: edit the harness surface directly (exempt from the worktree rule).
2. After shipping, verify the effect with the T1 ledger/report once it exists — that's why T1 ranks first.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
