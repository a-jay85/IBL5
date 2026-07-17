---
description: Historical archive: completed token-spend reduction entries, extracted from token-spend-backlog.md.
last_verified: 2026-07-16
---

# Token-Spend Reduction Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../token-spend-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

### T1 Automouse token ledger
**Location:** `bin/lib/automouse-stream-filter` (parses `total_cost_usd` + token counts from the stream-json `result` event); `bin/automouse-run` (appends a per-plan, per-phase row to `automouse/reports/YYYY-MM-DD-costs.md`).
**Problem (was):** The nightly queue was pure spend mechanism with only partial measurement: per-phase cost rows existed, but there was no tier breakdown, no weekly aggregate, and no equivalent report for interactive sessions (the origin 7-day analysis was done by hand).
**Status (2026-07-09):** ✅ Implemented — `bin/lib/automouse-stream-filter` now emits `cache_write` on the exit line; `bin/automouse-run` cost rows carry Model + Tier columns and a per-phase-rebuilt `## Weekly aggregate (last 7 days)` section (cost-by-tier + tokens-by-phase); new `bin/token-report` runs the interactive-session token analysis on demand.

### T2 Always-loaded context budget gate
**Location:** `bin/check-rules-byte-budget` (CI per-file rules cap) + `$HOME/.claude/hooks/memory-expiry.py` (SessionStart MEMORY.md byte-budget check).
**Problem (was):** The always-loaded surface (path-unscoped `.claude/rules/*.md` + the `MEMORY.md` index) only ever grew; nothing pushed back, and no CI or hook check capped it.
**Status (2026-07-14):** ✅ Implemented — CI rules byte-budget gate shipped first (`bin/check-rules-byte-budget`, 5000-byte per-file cap, folded into the `static-guards` job). Residual local MEMORY.md index-budget check now closed: `memory-expiry.py` warns when the index exceeds an 8192-byte budget (ratcheted to hold the T7 diet; index was dieted to ~6.1KB the same day). Pairs with T5 (same hook).

### T3 Wire PHP LSP + LSP-first rule
**Location:** `.claude/rules/lsp-first.md`; intelephense via the php-lsp plugin.
**Problem (was):** Symbol navigation ran as grep-and-read-whole-file chains — the largest read-token sink in a 275K-line PHP codebase.
**Status (2026-07-07):** ✅ Implemented — intelephense wired and the LSP-first rule shipped (measured ~10–30× cheaper than the grep-and-read path on `CsrfGuard::validateSubmittedToken`: ~320 tokens vs ~3–10K). Sub-item **SessionStart index warm-up: 🚫 Declined** — a `--stdio` server can't be reached by a shell hook (verified 2026-07-07); the tool blocks until indexed and the rule carries retry-once guidance instead.

### T5 Memory/rules dedup lint
**Location:** `$HOME/.claude/hooks/memory-expiry.py` (SessionStart hook).
**Problem (was):** The "MEMORY.md never duplicates `.claude/rules/`" norm was manual discipline; overlap could silently re-grow in the always-loaded index.
**Status (2026-07-14):** ✅ Implemented — added a dedup check to the SessionStart hook: each index line is scored against every rule's signature (description + headings) and near-exact overlaps surface for retirement. Thresholds (overlap-coef ≥0.6 ∧ shared ≥5 ∧ Jaccard ≥0.35) were calibrated on the real corpus — silent on the current clean index (top coincidental overlap oc 0.50), fires on a genuine duplicate (oc 1.0). Pairs with T2 (same hook).

### T6 Re-cap runtime context window
**Location:** `$HOME/.claude/settings.json` — `autoCompactWindow: 200000`.
**Problem (was):** Uncapped sessions reached 400K+ context; every turn of such a session re-reads the whole window from cache, and reasoning quality degrades well before that size.
**Status (2026-07-07):** ✅ Implemented — capped at 200K (120K was tried and thrashed — back-to-back compactions — in both interactive and headless runs; 200K is the measured compromise).

### T7 Resident-overlay diet (MEMORY.md + rules)
**Location:** Memory index `MEMORY.md` (was 18.1KB, ~90 lines, 191 topic files behind it); `.claude/rules/agent-tiering.md`.
**Problem (was):** Every request and every subagent spawn carried the full overlay; on a typical ~35K-token request it was ~27% of the read.
**Status (2026-07-14):** ✅ Implemented in two parts. **(1) Rules half (2026-07-11):** `agent-tiering.md` prose relocated into `agent-tiering-detail.md` — its `## Skip the Agent` heuristic moved and the redundant Flat-fan-out / Context-economics / Prompt-style tail removed, leaving only the Tier table + Explore rules (down from ~5.9KB to under the 5000-byte T2 budget); cross-refs in `work-triage.md` and `.claude/skills/plan/SKILL.md` repointed. **(2) Index half (2026-07-14):** MEMORY.md dieted from 18.1KB → ~6.1KB (well under the ≤8KB target). Key finding — recall surfaces topic files by their `description:` frontmatter **independent** of the index (70 of 190 topic files already lived unindexed), so the diet is **lossless de-residenting**, not deletion: only *preventive* memories (must fire before a path — user profile, guardrails, "don't re-audit" pipeline status, dated ⏰ reminders) stay resident; *reactive* ones (DB/testing/E2E/domain/CI-gotcha/refactoring/web-server/tooling references) moved out, files intact, recalled on demand. Added `description:` frontmatter to `migrations.md` (it had none) so it recalls after de-residenting. Held in place afterwards by T2/T13 (rules budget) and the deferred T5 index-dedup hook.

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

### T13 Aggregate always-loaded rules budget
**Location:** `bin/check-rules-byte-budget` + `.claude/rules/*.md` (path-unscoped subset).
**Problem (was):** T2's shipped gate capped each path-unscoped rules file at 5000 bytes but capped neither the file COUNT nor the TOTAL — the always-loaded surface could regrow one new 4.9KB file at a time without the CI gate firing.
**Status (2026-07-14):** ✅ Implemented (discovered 2026-07-14 during token-spend-triage) — extended `bin/check-rules-byte-budget` with a `RULES_TOTAL_BUDGET` (default 30,000 bytes) aggregate cap for path-unscoped rules. At implementation the 9 path-unscoped files totalled 24,523 bytes; the cap gives ~22% headroom and fires before a second unchecked file can land. Wire stays in the existing `static-guards` CI job — no new gate or script added (extend-before-add).

### T4 Driver-model downshift for babysitting loops
**Location:** Interactive workflow — CI-watching, merge-nudging, and re-run loops (once run in the main Opus/Fable session).
**Problem (was):** Babysitting phases need no frontier-model reasoning, yet every polling turn re-read the full session context at the top tier.
**Status (2026-07-14):** ✅ Implemented — **resolved by substitution, not a new build; residual measured empty.** Three shipped changes already removed the target: L6 (auto-update-branch unsticker) + L8 (failure self-heal/requeue) in [loop-engineering-backlog.md](../loop-engineering-backlog.md) removed nightly babysitting, and `/post-plan` CI-watch already runs detached on Sonnet 4.6 (`workflow-continuity.md`). A 7-day transcript scan (219 sessions, top-tier interactive turns only, sub-agents excluded) confirmed the residual — ad-hoc manual babysitting in an Opus/Fable session — is effectively **zero**: **0 dedicated babysitting sessions** (none dominated by polling; busiest was 18 poll turns buried in 888), poll turns were **0.8%** of top-tier turns, and the only nonzero bucket ($11/wk opus→sonnet delta) was **interleaved** polling threaded through active design/PR-stack sessions — **not addressable**, since a separate Sonnet loop would re-pay its own cache reads and lose the loaded design context (near-zero, possibly negative, real savings). If a dedicated poll loop ever recurs, the `/loop` skill already covers it with zero new infrastructure. The one-off audit ran against `~/.claude/projects/*/*.jsonl` transcripts (per-turn `message.model` + `message.usage`); methodology is captured in this note, not shipped as a script (meta-tooling bar).

### T9 Lazy-load plan/post-plan skills
**Location:** `.claude/skills/plan/SKILL.md` (~55KB ≈ 13K tokens) and `.claude/skills/post-plan/SKILL.md` (~68KB ≈ 17K tokens) — each a single file loaded whole at invocation and resident for the entire run.
**Problem (was):** A long post-plan run re-reads ~17K tokens every turn, plus a fresh cache write per nightly session.
**Status (2026-07-14):** ✅ Implemented — both restructures shipped. Post-plan (#1389, merged 2026-07-09): `.claude/skills/post-plan/SKILL.md` cut from ~68KB to ~30KB, split into seven `_phase-*.md` reference files read on phase entry. Plan-skill (#1363, merged 2026-07-08): `.claude/skills/plan/SKILL.md` Step 3 slimmed (Regions A/B removed, contract pointer wired), extracting the architect contract into on-demand `.claude/skills/plan/_architect-contract.md`. The two restructures were deliberately different shapes: plan-skill did not mirror post-plan's per-phase orchestrator split — instead it moved the architect contract into the plan-architect subagent's throwaway context, since the orchestrator's Steps 1–4 are too interdependent/reasoning-heavy to split. That contract extraction was the complete intended optimization for plan-skill, not a partial step toward a fuller split — both halves of T9 are done.

### T14 In-session context-ceiling nudge (dumb-zone spend tax)
**Locus:** ⌂ harness-local. **Effort:** S–M.
**Location:** No surface owns it today. T6 caps the window at 200K (`autoCompactWindow`), and the context-dumb-zone memory says "keep peak ~100K" — but nothing fires *during* a session as context crosses 100K, so the band between 100K and the 200K compaction cap is entirely unguarded.
**Problem (was):** Measured 2026-07-16 (7-day window): **47.9% of all weekly cache-read (~911M of 1.90B tokens) is issued at ≥100K context**; 26.9% at ≥125K; 256 calls ran at 200–225K. The fat tail is extreme — the top 15 sessions carry 34% of weekly reads, with the top two at ~98M and ~89M cache-read across ~900 calls each. Every call in a bloated session re-reads the whole window, so a session that should have split at 100K pays roughly double per remaining call — and reasons worse while doing it (the dumb-zone the memory warns about). The work-triage rule already names the cause (mechanical work staying inline on the main thread); what's missing is the in-the-moment signal.
**Suggested direction (was):** Extend an existing hook surface rather than add one (meta-tooling-bar): the PostToolUse/PreToolUse guard family (e.g. `$HOME/.claude/hooks/output-guard.sh`) can read the last `usage` block from the session transcript and emit a one-line advisory when context first crosses ~100K and again at ~125K — "context ≥100K: delegate remaining mechanical work (work-triage § execution routing), or split/hand off the session." Warn-only, once per threshold, skip subagents/headless (automouse peak-context is L16's territory). Verify effect via `bin/token-report` bucket distribution after two weeks.
**Risk if untouched (was):** The single largest residual spend class (~half of weekly cache-read) stays invisible at the moment it's created; T6's 200K cap only bounds the worst case.
**Provenance:** discovered 2026-07-16 during the post-backlog re-measure (advisory session).
**Status (2026-07-16):** ✅ Implemented — context-ceiling hook added to `~/.claude/hooks/output-guard.sh`; warns at 100K and 125K tokens, once per threshold per session; skips subagents and headless runs. 10 new tests added (24–33) to `~/.claude/hooks/test-output-guard.sh`. Plan: `$HOME/.claude/plans/t14-context-ceiling-nudge.md`.

### T15 Read-payload accretion guard
**Location:** `$HOME/.claude/hooks/output-guard.sh` (Check E, PreToolUse warn-only); `~/GitHub/IBL5/.claude/settings.local.json` (new `"Read"` matcher entry).
**Problem (was):** Read results injected 17.3M chars (~4.3M tokens) into contexts over 7 days — 67% of all tool-result bytes. A full-file Read early in a long session is the compounding version of the T10 problem: its tokens are re-billed as cache-read on every subsequent call. The Read tool supports `offset`/`limit` but nothing pushed back on a no-limit Read of a large file.
**Status (2026-07-16):** ✅ Implemented — `output-guard.sh` extended with Check E: PreToolUse warn when a Read call targets a file over 500 lines with no `offset`/`limit` parameter. Advisory names the LSP-first rule and Explore sub-agent delegation as cheaper paths. Warn-only; skips subagents (their contexts are discarded at SubagentStop). Wired via a new `"Read"` matcher entry in `settings.local.json`. Harness extended with 6 new tests (18–23). Plan: `$HOME/.claude/plans/t15-read-payload-accretion-guard.md`.

### T16 Poll-shaped Bash round-trips → background/Monitor routing
**Location:** `.claude/rules/work-triage.md` (new "Execution routing: repeat-polling is a spend bug" section).
**Problem (was):** Measured 2026-07-16: **329 `gh pr`/`gh run` calls plus ~120 nightly-queue status checks in 7 days**, largely poll loops. At the measured ~81K-token average context per call, each poll is a full-window cache re-read just to ask "is it done yet" — order of 30M+ cache-read tokens/week spent on waiting. The harness already had cheap substitutes (`run_in_background`+Monitor, `/loop` self-pacing, ScheduleWakeup), but adoption was norm-level only and poll patterns persisted in transcripts.
**Status (2026-07-17):** ✅ Implemented — cheapest lever shipped via PR #1492: "Execution routing: repeat-polling is a spend bug" section added to `work-triage.md`, naming `run_in_background`+Monitor and matched-interval ScheduleWakeup as the required substitutes, with the spend math (eight 60s checks ≈ 650K tokens vs ~81K for one deferred check). Optional escalation (output-guard.sh advisory on repeated `gh pr checks`/`gh run watch` calls within a session) deferred; if poll loops persist after two weeks, surface as a new entry.
