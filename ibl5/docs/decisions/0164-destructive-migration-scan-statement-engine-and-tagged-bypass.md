---
description: bin/check-destructive-migrations scans whole SQL statements (and .php migrations) through a python3 engine, adds four data-loss triggers, and requires bypass markers to name the triggers they suppress.
last_verified: 2026-10-03
---

# ADR-0164: Statement-level destructive migration scan with tagged bypass markers

**Status:** Accepted
**Date:** 2026-10-03
**Deciders:** automouse implementation run

## Context

`bin/check-destructive-migrations` (PR [#725](https://github.com/a-jay85/IBL5/pull/725), extended by PR [#2597](https://github.com/a-jay85/IBL5/pull/2597)) matched migration text line by line in bash. A multi-line `ALTER TABLE` whose `DROP COLUMN` sat on its own line passed. So did a `DELETE` or `UPDATE` with no `WHERE`, a type narrowing, and a drop-and-recreate of an existing table. The production `MigrationRunner` executes `.php` migrations and CI never applies them, yet the scan ignored them entirely. One untagged marker (`-- destructive-migration: reason`) suppressed every trigger in a file or PR, so a reviewer could not tell which destructive action had been accepted.

## Decision

The scan is a statement-level engine in python3 (`bin/lib/destructive-migration-scan.py`, stdlib only, 3.8 syntax) behind the unchanged bash entry point. A statement is scanned when any of its lines is added, and its text comes from the post-image. The triggers are the eight existing ones plus `delete-no-where`, `update-no-where`, `narrow-type` and `drop-recreate-existing`.

- `narrow-type` reads the old type from `ibl5/docs/schema/current-schema.sql`, then from a same-file definition. When the dump is present and lacks the column, the trigger fires as unresolved. When no dump exists (a scratch repo), only same-file definitions resolve and an unknown column stays silent.
- `drop-recreate-existing` treats a table as existing when the schema dump, the baseline, or an earlier-numbered migration creates it.
- `.php` migrations are scanned heuristically: string literals, heredocs, nowdocs, and dotted concatenation.
- Bypass markers must name the triggers they suppress (`-- destructive-migration[tag,tag]: reason`, PR body `<!-- destructive-migration[tag]: reason -->`). An untagged marker is an error in `--staged` and `--since` modes. The `--full-scan` audit mode honours it so the historical corpus stays reproducible.
- A missing python3 exits 2 and fails CI. The pre-commit hook skips loudly on a developer box without python3.

Stricter-only behaviour is enforced by three checks. `ibl5/tests/Cli/CheckDestructiveMigrationsCharacterizationTest.php` passed on the old engine and still passes. A pinned full-scan golden (`ibl5/tests/Cli/fixtures/destructive-migration-full-scan.golden`) fixes the corpus result. A superset check proves every old-engine hit is still reported.

## Alternatives Considered

- Multi-line regexes in the bash engine. Rejected because comment and string handling across lines needs a real tokenizer, which bash cannot express maintainably.
- A full SQL parser library. Rejected because it adds a dependency to CI and every developer box for a gate that needs only statement boundaries and a few clause shapes.
- Untagged markers kept valid. Rejected because one marker then hides every later destructive statement in the same file or PR.

## Consequences

- Positive: multi-line statements, missing `WHERE` clauses, type narrowing, recreate-of-existing and `.php` migrations are caught before merge.
- Positive: a reviewer sees exactly which trigger each marker accepts.
- Negative: future FK backfills written as `UPDATE ... JOIN ... SET` without `WHERE` need an `[update-no-where]` marker.
- Negative: `narrow-type` reports a false positive when the dump lags master by one regen run. The tagged marker clears it.
- Negative: the PR-body marker still needs `--bypass-from-stdin`, unchanged.

## References

- `bin/check-destructive-migrations`
- `bin/lib/destructive-migration-scan.py`
- `bin/pre-commit-hook`
- `ibl5/migrations/README.md`
- `ibl5/tests/Cli/CheckDestructiveMigrationsCliTest.php`
- `ibl5/tests/Cli/PreCommitHookDestructiveScanTest.php`
