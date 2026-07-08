---
description: Historical archive: completed token-spend reduction entries, extracted from token-spend-backlog.md.
last_verified: 2026-07-07
---

# Token-Spend Reduction Backlog — Archive

Read-only historical record of ✅ Implemented entries. For OPEN items see ../token-spend-backlog.md. Not governed by bin/check-docs (historical dead refs tolerated).

---

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
