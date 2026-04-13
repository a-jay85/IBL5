---
description: Replace ibl_hist TEMPTABLE VIEW with a materialized table refreshed by the update pipeline
last_verified: 2026-04-13
---

# ADR-0006: Materialize ibl_hist VIEW

## Status

Accepted

## Context

The `ibl_hist` VIEW uses `ALGORITHM = TEMPTABLE` because it contains a `ROW_NUMBER()` window function for deduplication. MariaDB cannot merge TEMPTABLE views into outer queries, so every `SELECT ... FROM ibl_hist WHERE pid = ?` materializes the entire result set (~12K+ rows), computes the window function, builds a temp table, and only then applies the `WHERE` filter.

With 380+ queries per production log cycle hitting this VIEW, the repeated full-table materialization is the single largest query performance bottleneck. No predicate pushdown, no index usage, no caching — every query pays the full cost.

## Decision

Replace the `ibl_hist` VIEW with a real InnoDB table that is refreshed by the update pipeline (`updateAllTheThings.php`) after every sim run.

The pipeline also gains a `SnapshotPlrStep` that creates mid-season snapshots in `ibl_plr_snapshots` on every run, so that `ibl_hist` includes current-season stats. Previously, snapshots were only created at end-of-season.

### Migration

- Drop the VIEW and `vw_career_totals`
- Create the `ibl_hist` TABLE with indexes on `(pid, year)`, `(teamid, year)`, `(year)`, `(name)`
- Populate from the same ROW_NUMBER query
- Recreate `vw_career_totals` as a VIEW over the real table

### Refresh mechanism

A `RefreshIblHistStep` runs as the final pipeline step: `DELETE FROM ibl_hist` + `INSERT INTO ibl_hist SELECT ...` inside a transaction. This runs after `SnapshotPlrStep` and `EndOfSeasonImportStep` so all new data is picked up.

## Consequences

- **Queries use indexes.** `WHERE pid = ?` is an index seek, not a full temp-table scan.
- **`vw_career_totals` is faster.** It reads from a real indexed table instead of materializing the TEMPTABLE on every access.
- **Data is point-in-time.** `ibl_hist` reflects the state at last pipeline run. Staleness window is bounded to one sim cycle.
- **Test fixture change.** `DatabaseTestCase::insertHistRow()` now inserts directly into the `ibl_hist` table instead of `ibl_plr_snapshots`.
- **`EndOfSeasonImportStep` loses PLR snapshot responsibility.** That is now handled by `SnapshotPlrStep` which auto-detects the phase (mid-season vs end-of-season) based on champion status.
