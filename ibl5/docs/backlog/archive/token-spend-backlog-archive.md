---
description: Historical archive: completed token-spend reduction entries, extracted from token-spend-backlog.md.
last_verified: 2026-07-14
---

# Token-Spend Reduction Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../token-spend-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### T1 Automouse token ledger
**Location:** `bin/lib/automouse-stream-filter` (parses `total_cost_usd` + token counts from the stream-json `result` event); `bin/automouse-run` (appends a per-plan, per-phase row to `automouse/reports/YYYY-MM-DD-costs.md`).
**Problem (was):** The nightly queue was pure spend mechanism with only partial measurement: per-phase cost rows existed, but there was no tier breakdown, no weekly aggregate, and no equivalent report for interactive sessions (the origin 7-day analysis was done by hand).
**Status (2026-07-09):** ✅ Implemented — `bin/lib/automouse-stream-filter` now emits `cache_write` on the exit line; `bin/automouse-run` cost rows carry Model + Tier columns and a per-phase-rebuilt `## Weekly aggregate (last 7 days)` section (cost-by-tier + tokens-by-phase); new `bin/token-report` runs the interactive-session token analysis on demand.

### T3 Wire PHP LSP + LSP-first rule
**Location:** `.claude/rules/lsp-first.md`; intelephense via the php-lsp plugin.
**Problem (was):** Symbol navigation ran as grep-and-read-whole-file chains — the largest read-token sink in a 275K-line PHP codebase.
**Status (2026-07-07):** ✅ Implemented — intelephense wired and the LSP-first rule shipped (measured ~10–30× cheaper than the grep-and-read path on `CsrfGuard::validateSubmittedToken`: ~320 tokens vs ~3–10K). Sub-item **SessionStart index warm-up: 🚫 Declined** — a `--stdio` server can't be reached by a shell hook (verified 2026-07-07); the tool blocks until indexed and the rule carries retry-once guidance instead.

### T6 Re-cap runtime context window
**Location:** `$HOME/.claude/settings.json` — `autoCompactWindow: 200000`.
**Problem (was):** Uncapped sessions reached 400K+ context; every turn of such a session re-reads the whole window from cache, and reasoning quality degrades well before that size.
**Status (2026-07-07):** ✅ Implemented — capped at 200K (120K was tried and thrashed — back-to-back compactions — in both interactive and headless runs; 200K is the measured compromise).

### T8 Re-enable adaptive thinking
**Location:** `$HOME/.claude/settings.json`.
**Problem (was):** `CLAUDE_CODE_DISABLE_ADAPTIVE_THINKING=1` forced full thinking (billed as output — the most expensive class) on every turn.
**Status (2026-07-07):** ✅ Implemented — the env var is gone from settings; easy turns skip thinking again.

### T10 Tool-output token guards
**Location:** `$HOME/.claude/hooks/output-guard.sh` (PreToolUse, warn-only), extending the `ci-log-guard.sh` family.
**Problem (was):** Unbounded Bash output (`cat`, unbounded `git log`/`find`, full Playwright runs) lands in context once and is re-billed as a cache read every remaining turn.
**Status (2026-07-07):** ✅ Implemented — guards the four measured worst categories (scoped from 27,899 transcript Bash calls); warns with the bounded alternative; skips subagents. Plan archive: `$HOME/.claude/plans/output-guard-hook.md`.

### T11 Tier-boundary plan splitting
**Location:** `.claude/skills/plan/SKILL.md` — Step 2.5 (split criteria) and Step 4 gate 13 (`impl_model` criterion).
**Problem (was):** Gate 13's Sonnet criterion is all-or-nothing per plan: a single judgment phase or `Truly-manual` row drops the *entire* implementation to Opus (~1.7× per-token) even when the other 90% of phases are mechanical. Step 2.5's split criteria never considered the model-tier boundary, so mixed plans were never restructured to isolate the Opus-forcing part.
**Status (2026-07-11):** ✅ Implemented — added the Step 2.5 **Tier-boundary separability** split criterion and paired Step 4 gate 13 to prefer a tier-boundary split over dropping a mixed plan to Opus (`.claude/skills/plan/SKILL.md`); batched with T12.

### T12 Sonnet plan-architect for recipe-backed plans
**Location:** `.claude/agents/plan-architect.md` (pins `model: opus`); `/plan` Step 3 spawns it for every plan.
**Problem (was):** Every plan paid an Opus-xhigh architect run even when the design was already resolved upstream — a backlog entry naming the recipe, the files, and the pattern to copy (the marker-swap / mechanical-sweep class). Composing a plan from a pre-resolved recipe is mechanical composition, not novel design.
**Status (2026-07-11):** ✅ Implemented — added `.claude/agents/plan-architect-sonnet.md` and a recipe-backed Sonnet branch in `/plan` Step 3's ordered tier selection (xhigh > recipe-backed Sonnet > Opus default), with the `agent-tiering.md` Opus (delegated) row updated to match; rollout monitored via the T1 tier ledger.

### T9 Lazy-load plan/post-plan skills
**Location:** `.claude/skills/plan/SKILL.md` (~55KB ≈ 13K tokens) and `.claude/skills/post-plan/SKILL.md` (~68KB ≈ 17K tokens) — each a single file loaded whole at invocation and resident for the entire run.
**Problem (was):** A long post-plan run re-reads ~17K tokens every turn, plus a fresh cache write per nightly session.
**Status (2026-07-14):** ✅ Implemented — both restructures shipped. Post-plan (#1389, merged 2026-07-09): `.claude/skills/post-plan/SKILL.md` cut from ~68KB to ~30KB, split into seven `_phase-*.md` reference files read on phase entry. Plan-skill (#1363, merged 2026-07-08): `.claude/skills/plan/SKILL.md` Step 3 slimmed (Regions A/B removed, contract pointer wired), extracting the architect contract into on-demand `.claude/skills/plan/_architect-contract.md`. The two restructures were deliberately different shapes: plan-skill did not mirror post-plan's per-phase orchestrator split — instead it moved the architect contract into the plan-architect subagent's throwaway context, since the orchestrator's Steps 1–4 are too interdependent/reasoning-heavy to split. That contract extraction was the complete intended optimization for plan-skill, not a partial step toward a fuller split — both halves of T9 are done.
