---
description: bin/db-sync-prod rejects a bare --print-plan instead of silently running the destructive prod sync, and the agent convention is recorded in .claude/rules/db-sync-prod-dry-run.md.
last_verified: 2026-09-21
---

> This ADR was drafted by the post-plan harness for this PR. A human must review and approve it before merging.

# ADR-0137: `bin/db-sync-prod` Rejects a Bare `--print-plan`

**Status:** Accepted
**Date:** 2026-09-21
**Deciders:** post-plan harness (auto-draft)

## Context

`bin/adr-check` flagged one decision-trigger surface on this branch: a new agent convention file at `.claude/rules/db-sync-prod-dry-run.md`. It carries `paths: "bin/db-sync-prod"`, so it attaches to an agent's context when that script is touched. The behaviour it records is a silent fall-through. `--print-plan` was parsed unconditionally and consumed only inside the `--snapshot` branch, so `bin/db-sync-prod --print-plan` printed no plan and ran the full production to local sync, dropping and reloading the target container's tables. The flag reads as a preview, and it made the run more dangerous than a bare invocation: the same `PRINT_PLAN` variable also skips the Homebrew keg-path preflight, so the destructive path executed without the PATH fix that resolves `mariadb-dump`.

## Decision

`bin/db-sync-prod` rejects `--print-plan` when `--snapshot` is absent. The guard sits immediately after argument parsing, prints an error naming `--snapshot`, and exits 1 before any preflight or dump work starts. `--print-plan` stays a planning flag for the snapshot load path only, and the production sync path has no dry-run mode. Enforcement is the guard itself plus `test_db_sync_prod_rejects_print_plan_without_snapshot` in `bin/test-phase-snapshot-detect`, which asserts both the exit code and the error text. The convention for agents is written down in `.claude/rules/db-sync-prod-dry-run.md`, which also lists the safe rehearsal paths.

## Alternatives Considered

- **Make the flag a real prod dry run.** Print the per-table dump passes the production sync would run. Rejected because: the plan depends on the live remote schema and on the target container's current tables, so a printed plan would be a guess, and a guess presented as a preview is the same hazard in a new shape.
- **Warn and continue.** Emit a stderr warning that `--print-plan` has no effect here, then run the sync. Rejected because: the drop and reload still happen. An operator reads that warning after the destructive work has already begun.
- **Rule doc only, no code guard.** Record the hazard in `.claude/rules/db-sync-prod-dry-run.md` and leave the script alone. Rejected because: the rule attaches when an agent edits `bin/db-sync-prod`, and the hazard fires on an invocation that edits nothing.
- **Accept the flag and exit without syncing.** Treat a bare `--print-plan` as a no-op success. Rejected because: it leaves an operator believing they previewed a plan that was never produced, and exit 0 hides the mistake from any wrapper script.

## Consequences

- Positive: an invocation that reads as a preview now exits 1 and names the flag it needs. Reaching the destructive production sync requires an invocation that carries no preview-shaped flag.
- Positive: the failure is covered by a regression test, so removing the guard turns `bin/test-phase-snapshot-detect` red.
- Positive: no caller changes. `bin/dev-up` and `bin/wt-up` invoke `bin/db-sync-prod` without `--print-plan`.
- Negative: the production path still has no way to rehearse. Operators fall back to loading a snapshot or targeting a throwaway worktree container, both of which leave the main stack's database alone.
- Negative: any external script that passed a bare `--print-plan` and relied on it being ignored now fails with exit 1. That break is the intended outcome, since the ignored flag was running a destructive sync.

## References

- `bin/db-sync-prod`: the guard, the usage header, and the `PRINT_PLAN` preflight skip it interacts with
- `bin/test-phase-snapshot-detect`: `test_db_sync_prod_rejects_print_plan_without_snapshot`
- `.claude/rules/db-sync-prod-dry-run.md`: the agent convention flagged by `bin/adr-check`
- `ibl5/docs/decisions/0127-shell-fail-open-probe.md`: the earlier fail-closed decision on the same script
- `ibl5/docs/decisions/README.md`: the decision-record policy `bin/adr-check` enforces
