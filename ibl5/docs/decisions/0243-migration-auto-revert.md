---
description: The migration runner stores a migration's down SQL and checksum when it applies the up, and undoes a reverted data-changing migration from that stored copy on the next deploy.
last_verified: 2026-11-12
---

# ADR-0243: Undo a reverted data-changing migration from stored down SQL on the next deploy

**Status:** Accepted
**Date:** 2026-11-12
**Deciders:** ajaynicolas

## Context

A `git revert` deletes a migration file but cannot undo the data change the migration already made on production. Migrations 205 and 212 rewrote existing rows, and a revert of either left the rewritten values in place. The runner is forward-only, and its tracking row stays behind with no file to match. The destructive scan already asks for a backup table and a hand-run undo script, but nothing runs that script on a deploy.

## Decision

When the runner applies a `.sql` migration that has a `NNN_name.down.sql` partner, it stores the down text and the SHA-256 of the up file in the new `migrations.down_sql` and `migrations.up_sha256` columns. On a later deploy, `undoVanished()` in `ibl5/classes/Migration/MigrationRunner.php` finds applied rows whose file is gone from the checkout and that have stored down SQL. It runs those down texts newest first, each in one transaction with the delete of its `migrations` row, before it looks for pending files. `ibl5/bin/migrate` calls it inside its existing `try`, so a deploy that only reverts still reaches it.

The undo fails closed. It runs only when every row from the oldest candidate to the newest is itself a vanished row with stored down SQL and a stored checksum. It stops the deploy and changes nothing when a later migration's file still exists, when a vanished row in the run has no stored down SQL, or when a vanished row's checksum matches a current file (a rename).

Down files hold only `INSERT`, `UPDATE` and `DELETE`, because DDL commits implicitly and could not roll back with the delete of the tracking row. Three places enforce this: the destructive scan trigger `down-not-dml`, a check in `readDownPartner()` before the up runs, and a check in `undoVanished()` before the stored text runs. The scan trigger `down-file-missing` requires a down file for a new data-rewrite migration. Down files restore from backup tables, so rows come back with their original primary keys.

## Alternatives Considered

- **Checkout read.** Read the down file from the checkout at undo time. Rejected because the revert deletes it along with the up file.
- **Schema rollback.** Undo DDL changes too. Rejected because DDL cannot run inside a transaction, so a failed undo could leave a half-reverted schema.
- **Column on `schema_migrations`.** Store the down text in the Docker and CI tracking table. Rejected because that table belongs to `bin/db-migrate`, which never undoes anything. Production tracks migrations in `migrations`.

## Consequences

- Positive: reverting a data-changing PR restores its data on the next deploy, with no hand-run script.
- Positive: a stored copy and a checksum make the undo independent of the checkout and safe against renames.
- Positive: `bin/predeploy-db-snapshot` now also dumps when a deploy deletes a migration file, so a revert deploy has a backup.
- Negative: an applied migration that has a down file must never be renamed, renumbered or squashed. The runner stops on a byte-identical rename but cannot tell an edited rename or a squash from a revert.
- Negative: a down file cannot hold a `;` inside a string literal, and it restores only InnoDB tables.
- Negative: two new scan triggers, `down-file-missing` and `down-not-dml`, add review cost to data-rewrite migrations.

## References

- [`ibl5/classes/Migration/MigrationRunner.php`](../../classes/Migration/MigrationRunner.php): `getVanishedUndoPlan()`, `undoVanished()` and `readDownPartner()`.
- [`ibl5/bin/migrate`](../../bin/migrate): runs the undo pass before the pending set.
- [`ibl5/migrations/213_add_down_sql_to_migrations_table.sql`](../../migrations/213_add_down_sql_to_migrations_table.sql): adds the two columns.
- [`bin/lib/destructive-migration-scan.py`](../../../bin/lib/destructive-migration-scan.py): the `down-file-missing` and `down-not-dml` triggers.
- [`bin/predeploy-db-snapshot`](../../../bin/predeploy-db-snapshot): the wider snapshot trigger.
- [`ibl5/tests/DatabaseIntegration/MigrationDownSqlIntegrationTest.php`](../../tests/DatabaseIntegration/MigrationDownSqlIntegrationTest.php): up, down, compare for every paired migration.
- [`.github/workflows/deploy-rehearsal.yml`](../../../.github/workflows/deploy-rehearsal.yml): the fake-revert step.
- [`ibl5/migrations/README.md`](../../migrations/README.md): author guide, Down files and automatic undo.
