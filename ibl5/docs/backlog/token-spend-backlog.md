---
description: Token-spend reduction backlog — resident-context diet, caching economics, output-spend guards, and LSP-first navigation for the Claude Code harness, with per-entry status.
last_verified: 2026-07-11
---

# Token-Spend Reduction Backlog

**Purpose:** Catalogue changes that reduce the token cost of every Claude Code session on this repo — smaller resident context per turn, fewer tokens re-cached per session, less output. Each open entry is a candidate for a `/plan` (repo entries) or a direct harness edit (⌂ entries).

**Origin:** Advisory sessions (2026-07-07): a 7-day transcript analysis (280 sessions; ~1.64B cache-read / 95.5M cache-write / 19.9M output tokens per week — cache reads + writes ≈ 70% of weighted spend) plus a harness-surface audit. Statuses verified against disk, `$HOME/.claude/settings.json`, and the automouse queue on 2026-07-07.

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
| ⬜ Open | 2 |
| 📋 Planned | 0 |
| ◑ Partial | 3 |
| ✅ Implemented | 7 |
| 🚫 Declined | 0 |

Archived entries (✅ Implemented): see [token-spend-backlog-archive.md](archive/token-spend-backlog-archive.md).

---

## Entries

| # | Title | Status | Locus | Effort |
|---|-------|--------|-------|-------:|
| T2 | Always-loaded context budget gate | ◑ Partial | repo | S |
| T4 | Driver-model downshift for babysitting loops | ⬜ Open | ⌂ | M |
| T5 | Memory/rules dedup lint | ⬜ Open | ⌂ | S |
| T7 | Resident-overlay diet (MEMORY.md + rules) | ◑ Partial | both | M |
| T9 | Lazy-load plan/post-plan skills | ◑ Partial | repo | M |

### T2 Always-loaded context budget gate
**Location:** `.claude/rules/*.md` (path-unscoped subset ≈ 19KB) + the memory index (`MEMORY.md`, ≈ 16KB) — together ~9K tokens on every request and every subagent spawn.
**Problem:** The always-loaded surface only ever grows; nothing pushes back. No CI check caps it (verified: no size/budget check in `.github/workflows/` or `bin/check-docs`).
**Suggested direction:** A small CI job (wired into the `gate` job's `needs:`, per house convention) failing when path-unscoped rules bytes or the MEMORY.md index exceed a budget. MEMORY.md lives outside the repo, so the gate checks rules in CI and the hook surface checks the index locally (pairs with T5).
**Risk if untouched:** Silent regrowth of the per-turn fixed tax that T7 pays down.
**Status (2026-07-11):** ◑ Partial — CI rules byte-budget gate shipped: `bin/check-rules-byte-budget` caps path-unscoped `.claude/rules/*.md` files at 5000 bytes, folded into the `static-guards` job (already in the `gate` `needs:`, so a regrowth fails the required gate). Residual: the local MEMORY.md index-budget hook (out-of-repo, pairs with T5) still deferred.

### T4 Driver-model downshift for babysitting loops
**Location:** Interactive workflow — CI-watching, merge-nudging, and re-run loops currently run in the main (Opus/Fable) session.
**Problem:** Babysitting phases need no frontier-model reasoning; every polling turn re-reads the full session context at the top tier.
**Suggested direction:** A Sonnet wrapper session (or `/loop`-driven routine) for watch/nudge phases, reserving the top-tier session for design and review. Partially substituted by L6/L8 in [loop-engineering-backlog.md](loop-engineering-backlog.md), which remove the need to babysit at all.
**Risk if untouched:** Top-tier token burn on mechanical polling.
**Status (2026-07-07):** ⬜ Open.

### T5 Memory/rules dedup lint
**Location:** `$HOME/.claude/hooks/memory-expiry.py` (SessionStart hook; currently expiry-only).
**Problem:** The "MEMORY.md never duplicates `.claude/rules/`" norm is manual discipline; overlap can silently re-grow in the always-loaded index.
**Suggested direction:** Add a similarity check (index hooks vs rule headings/bodies) to the existing hook, surfacing suspected duplicates the way expiry lines are surfaced.
**Risk if untouched:** Redundant always-loaded lines dilute recall and re-inflate the T7 surface.
**Status (2026-07-07):** ⬜ Open.

### T7 Resident-overlay diet (MEMORY.md + rules)
**Location:** Memory index `MEMORY.md` (~16KB, ~90 lines, 181 topic files behind it); `.claude/rules/agent-tiering.md` (~6.6KB, with an existing overflow file `.claude/rules/agent-tiering-detail.md`).
**Problem:** Every request and every subagent spawn carries the full overlay; on a typical ~35K-token request it is ~27% of the read.
**Suggested direction:** Prune index lines for finished pipelines and dated status that has expired; merge one-line variants; target ≤ 8KB for the index. Move remaining `agent-tiering.md` prose into the detail file, keeping the tier table + Explore rules. Pairs with E8 in [dev-efficiency-backlog.md](dev-efficiency-backlog.md) (each mechanical gate built lets a memory line retire) and is held in place afterwards by T2/T5.
**Risk if untouched:** A permanent per-turn tax that compounds across every subagent.
**Status (2026-07-11):** ◑ Partial — `agent-tiering.md` relocation now complete: its `## Skip the Agent` heuristic moved into `agent-tiering-detail.md` and the redundant Flat-fan-out / Context-economics / Prompt-style tail removed, leaving only the Tier table + Explore rules in the always-loaded file (down from ~5.9KB to under the 5000-byte T2 budget). Cross-refs in `work-triage.md` and `.claude/skills/plan/SKILL.md` repointed to the detail file. Residual: the full ≤8KB MEMORY.md index diet (out-of-repo, harness-side) still deferred.

### T9 Lazy-load plan/post-plan skills
**Location:** `.claude/skills/plan/SKILL.md` (~55KB ≈ 13K tokens) and `.claude/skills/post-plan/SKILL.md` (~68KB ≈ 17K tokens) — each a single file loaded whole at invocation and resident for the entire run.
**Problem:** A long post-plan run re-reads ~17K tokens every turn, plus a fresh cache write per nightly session.
**Suggested direction:** Thin orchestrator SKILL.md + per-phase reference files read on phase entry. Both restructures are planned: `$HOME/.claude/plans/lazy-load-plan-skill.md` and `$HOME/.claude/plans/lazy-load-post-plan-skill.md` (the post-plan one is in the automouse queue).
**Risk if untouched:** ~20K tokens of dead weight resident in every `/plan` and `/post-plan` run.
**Status (2026-07-11):** ◑ Partial — post-plan restructure shipped (#1389): `.claude/skills/post-plan/SKILL.md` cut from ~68KB to ~30KB, split into seven `_phase-*.md` reference files read on phase entry. Residual: the plan-skill restructure (`.claude/skills/plan/SKILL.md`, still ~48KB whole) is planned, not yet queued.

➜ T1 Automouse token ledger — ✅ Implemented (2026-07-09): see [archive](archive/token-spend-backlog-archive.md).

➜ T11 Tier-boundary plan splitting — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

➜ T12 Sonnet plan-architect for recipe-backed plans — ✅ Implemented (2026-07-11): see [archive](archive/token-spend-backlog-archive.md).

---

## Burn-down process

1. Repo entries: `/plan` or ad-hoc per the work-triage rule; ⌂ entries: edit the harness surface directly (exempt from the worktree rule).
2. After shipping, verify the effect with the T1 ledger/report once it exists — that's why T1 ranks first.
3. Update this doc's status; bump `last_verified` (CI enforces via `bin/check-docs`).
