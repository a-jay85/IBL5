---
description: Token-spend reduction backlog — resident-context diet, caching economics, output-spend guards, and LSP-first navigation for the Claude Code harness, with per-entry status.
last_verified: 2026-07-17
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
| ⬜ Open | 0 |
| 📋 Planned | 0 |
| ◑ Partial | 0 |
| ✅ Implemented | 16 |
| 🚫 Declined | 0 |

Archived entries (✅ Implemented): see [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md).

---

## Entries

First-wave entries (T1–T13) are ✅ Implemented — see the dated pointers below and [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md). T14–T16 are the second wave, from the 2026-07-16 re-measure.

**T14** ✅ Implemented 2026-07-16 — archived in [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md).

**T16** ✅ Implemented 2026-07-17 — archived in [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md).

---

➜ T4 Driver-model downshift for babysitting loops — ✅ Implemented (2026-07-14): resolved by substitution (L6+L8+post-plan-Sonnet+`/loop`); residual measured empty. See [archive](archive/token-spend-backlog-archive.md).

➜ T13 Aggregate always-loaded rules budget — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).
➜ T15 Read-payload accretion guard — ✅ Implemented (2026-07-16): see [archive](archive/token-spend-backlog-archive.md).

➜ T7 Resident-overlay diet (MEMORY.md + rules) — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T1 Automouse token ledger — ✅ Implemented (2026-07-09): see [archive](archive/token-spend-backlog-archive.md).

➜ T2 Always-loaded context budget gate — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T5 Memory/rules dedup lint — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T9 Lazy-load plan/post-plan skills — ✅ Implemented (2026-07-14): see [archive](archive/token-spend-backlog-archive.md).

➜ T11 Tier-boundary plan splitting — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

➜ T12 Sonnet plan-architect for recipe-backed plans — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

**Token-relevant entries tracked elsewhere:**
- L18 (tier-default correction — `impl_model:` fails open to Opus) — [loop-engineering-backlog.md](loop-engineering-backlog.md). ✅ Implemented (2026-07-16). Supersedes the former L2 token-budget-breaker residual, closed 2026-07-15 as empirically refuted (PR #1477 closed unmerged): impl spend doesn't predict postplan spend, and the measured waste is tier misallocation (~82% of impl spend is Opus; ~$27/week routed to Opus purely by a missing `impl_model:` field), not plan length.
- L15 (Sonnet-recipe completeness lint) — [loop-engineering-backlog.md](loop-engineering-backlog.md). ✅ Implemented (2026-07-15).
- L16 (context-budget gate v2) — [loop-engineering-backlog.md](loop-engineering-backlog.md). 📋 Planned.
- E8 (memory-lines→gates umbrella, pairs with T7) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md). ◑ Partial as of 2026-07-14; one item remains (free-agents teamid write guard).
- E6 (diff-scoped PHPStan wrapper) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md): every agent verify iteration (local + automouse) drops from O(project) to O(diff) analysis — fewer polling turns and less output per loop. (Cross-referenced 2026-07-14, token-spend sweep.) ✅ Implemented (2026-07-14) — shipped in PR #1362.
- E3 (PHPStan result-cache in CI) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md). ✅ Implemented (2026-07-03). shorter CI runs → fewer CI-watch polling turns per PR. (Cross-referenced 2026-07-14, token-spend sweep.)
- E4 (flake-quarantine ledger) — [dev-efficiency-backlog.md](dev-efficiency-backlog.md): removes flake-triggered re-run cycles and lost nightly-queue runs. (Cross-referenced 2026-07-14, token-spend sweep.)
- 5.1 (build IBLbot TypeScript on the CI runner) — [ci-backlog.md](ci-backlog.md): each droplet-side build failure (2 to date) costs manual-intervention sessions plus CI-watch re-run cycles. (Cross-referenced 2026-07-14, token-spend sweep.)

---

## Burn-down process

1. Repo entries: `/plan` or ad-hoc per the work-triage rule; ⌂ entries: edit the harness surface directly (exempt from the worktree rule).
2. After shipping, verify the effect with the T1 ledger/report once it exists — that's why T1 ranks first.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
