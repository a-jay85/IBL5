---
description: Migration authoring rules — numbering via bin/next-migration, the Olympics schema-parity allowlist, how to read prod's applied-migration state, and the integration-test sweep a view→table materialization requires.
paths: "ibl5/migrations/**"
last_verified: 2026-09-16
---

# Migration Rules

## Numbering

Never hand-pick a migration number — run `bin/next-migration`. This is the same
id-collision class as E2E seed `pid`s and fixture ids: a number you *assume* is free
may already be taken, and the collision surfaces as an unrelated-looking failure.
General id rule and the `pid` precedent: `.claude/rules/database-access.md`.

## Olympics schema parity

When a migration adds a column to an `ibl_olympics_<table>` that has no matching column
in `ibl_<table>`, add it to `ALLOWED_OLYMPICS_ONLY_COLUMNS` in
`tests/DatabaseIntegration/OlympicsSchemaParityTest.php` **in the same commit**.

The parity test asserts no Olympics table carries a column absent from its IBL
counterpart except those explicitly allowlisted. A missing entry fails the `Database
Integration Tests` and `Infection PHP` CI jobs in the coverage-generation step (not in
mutation scoring), so the failure does not read as a parity problem. Shape:
`'ibl_olympics_team_info' => ['is_real_team']` (PR #864, migration 131).

## Reading prod's applied-migration state

To check whether a migration is live on production, query prod's **`migrations`** table
(Laravel-style: `id`, `migration`, `batch`) — e.g.
`SELECT * FROM migrations WHERE migration LIKE '133%'`. Production has **no**
`schema_migrations` table.

`bin/db-sync-prod` uses `schema_migrations` for its own *local* replay bookkeeping. Its
console output ("Backfilling schema_migrations from prod dump…", "Replaying migrations
not yet applied to prod…") describes the local sync's tracking, not prod's state. A
freshly-synced local DB can show a migration "applied just now" that has in fact been on
prod for weeks — that trap produced a wrong "migration 133 isn't deployed" conclusion
(it was) and derailed a root-cause analysis. Never infer prod state from
`schema_migrations` or from the replay log; query prod directly. Read-only query pattern
and the known db-sync-prod table-name inconsistency: `.claude/rules/database-access.md`.

## View → materialized-table conversions need a test sweep

When converting a CTE-based view into a thin pass-through over a materialized table (the
`RefreshIblHistStep` / `RefreshPlayoffSeriesResultsStep` pattern), integration tests that
insert into the **old view's source tables** and assert against the view name break
silently: the materialized table is only populated by its refresh step or by a direct
insert, so it stays empty. A local DB without the CI seed can mask this; only seeded CI
surfaces it.

Before pushing such a migration, run
`grep -rn "<view_name>" ibl5/tests/DatabaseIntegration/` and resolve every test that
inserts into one of the view's source tables (e.g. `ibl_box_scores_teams` for
`vw_playoff_series_results`). Each must either seed the materialized table via a fixture
helper, or invoke the refresh step inside the test. Plan the sweep as part of the
migration, not as a CI fixup.
