---
description: Index of shared library files sourced by bin/ scripts.
last_verified: 2026-08-23
---

# bin/lib — Shared Library Files

Sourced (not executed directly) by scripts in `bin/` and `bin/automouse/`. Each file provides a focused set of helper functions or data for a specific concern.

| File | Purpose |
|------|---------|
| `automouse-escalate-model` | Resolve the automouse impl model for the current attempt, escalating to Opus on the final retry |
| `automouse-reorder-router.php` | PHP router spawned by `bin/automouse/queue-reorder-ui`; serves the drag-and-drop queue reorder UI and applies reorders via `bin/automouse/queue reorder` |
| `automouse-stream-filter.sh` | Filter for `claude -p --output-format stream-json` NDJSON; emits per-phase log lines (tool:/exit:/COMPACTION:) and maintains heartbeat |
| `bug-pipeline-gh.sh` | Best-effort GitHub issue-tracking seam for the autonomous bug pipeline (§3f) |
| `bug-pipeline-test-stubs.sh` | Shared stub scaffolding for `bin/test-bug-pipeline-*` harnesses |
| `db-helpers.sh` | Shared database helper functions for Docker MariaDB interactions (password-warning suppression, exec wrappers, and `db_resolve_target` / `db_container_running` — the main-stack-vs-worktree-container routing used by `ibl5/bin/db-query`) |
| `docfix-dm.sh` | Compose the docs-refreshed Discord DM for a docfix PR; holds the numeric-input, OPEN-state, and `docs-stale-refresh-` head-ref guards lifted out of `docs-refreshed-notify.yml` so they are exercisable by `bin/test-docfix-run` |
| `git-helpers.sh` | Shared git-layout helpers: canonical repo root resolution and related utilities |
| `human-signoff-classifier.sh` | Single source of truth for the feature-PR human sign-off classifier (ADR-0062), sourced by both the workflow and its regression harness |
| `plan-model-tier` | Validate a raw `impl_model:` value against the accepted whitelist and classify it (`absent`/`opus-tier`/`sonnet-tier`/`haiku-tier`); shared by `plan-impl-model` and `plan-model-consistency` |
| `plan-impl-model` | Resolve the automouse impl-agent model for a given plan file; rejects any value outside the `plan-model-tier` whitelist (exit 1, one line on stderr) instead of defaulting to Opus |
| `plan-model-consistency` | Shared `impl_model` ↔ Verification-Matrix consistency check, invoked by `bin/check-plan` gate `[13]` and by the `bin/automouse/queue` add-time backstop |
| `post-review-findings.sh` | Convert a JSON findings array into resolvable inline GitHub review threads or a fallback issue comment; sourced by `/post-plan` Phase 4D, `/pr-review`, and `/security-audit` |
| `pr-armable.sh` | Shared auto-merge "live hold" predicate for `/post-plan` Phase 6.5 arming conditions; sourced by `bin/pr-triage` and `/post-plan` |
| `sim-recap-exemplar.txt` | Exemplar sim-recap text used as a style reference by the sim-recap prompt |
| `wt-guards.sh` | Shared worktree safety guards for scripts that modify or remove worktrees |
