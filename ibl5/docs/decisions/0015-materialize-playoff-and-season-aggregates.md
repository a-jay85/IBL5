---
description: Materialize vw_playoff_series_results and team season win/loss records into refresh-on-pipeline tables for indexed lookups
last_verified: 2026-04-28
---

# ADR-0015: Materialize Playoff Series and Team Season Aggregate Tables

## Status

Accepted

## Context

The team page sidebar runs three queries per render that each scan `ibl_box_scores_teams` (~45K+ rows) end-to-end:

1. `getPlayoffResults()` reads `vw_playoff_series_results`, a multi-CTE view with two window functions over every playoff game (3,150+ rows). The view name is referenced from 7+ PHP call-sites and 2 dependent SQL views (`vw_team_awards`, `vw_franchise_summary`).
2. `getRegularSeasonHistory()` and `getHEATHistory()` build a CTE per call against the entire `ibl_box_scores_teams` table and only filter by team in the outer query — predicate pushdown does not happen because of the per-game row dedupe inside the CTE.

The fixes for Issue 1 must avoid touching the 7+ call-sites; the fixes for 2/3 can be a clean SELECT against a materialized table.

## Decision

Adopt the same materialization pattern established by ADR-0006 (`ibl_hist`):

- **Playoff series:** drop `vw_playoff_series_results`, create real table `ibl_playoff_series_results` populated by the existing CTE, recreate `vw_playoff_series_results` as a thin `SELECT *` pass-through. Dependent views (`vw_team_awards`, `vw_franchise_summary`) bind to the same name and need no changes beyond regeneration.
- **Team season records:** create `ibl_team_season_records` keyed on `(team_id, year, game_type)` with `game_type` matching `ibl_box_scores_teams`'s discriminator (1=regular, 3=HEAT). `getRegularSeasonHistory()` and `getHEATHistory()` become single-row indexed lookups.
- **Refresh:** two new pipeline steps (`RefreshPlayoffSeriesResultsStep`, `RefreshTeamSeasonRecordsStep`) run inside `if (!$isOlympics)` between `ProcessAllStarGamesStep` and `ParseJsbFilesStep`. Each runs `DELETE` + `INSERT ... SELECT` inside a transaction so the table is never empty on error.

Push the team filter into `getPlayoffResults()` SQL using `WHERE pr.winner = ? OR pr.loser = ?`; the materialized table has indexes on both columns, so this becomes an indexed lookup instead of returning every series and filtering in PHP.

## Consequences

- **Sidebar SQL goes from full-table scans to indexed lookups** — the dominant per-render cost on the team page.
- **Staleness window = one sim cycle**, identical to `ibl_hist` (ADR-0006).
- **Test fixture changes:** `DatabaseTestCase` gains `insertPlayoffSeriesResultRow()` and `insertTeamSeasonRecordRow()` helpers; `testGetPlayoffResultsReturnsPlayoffSeriesData` and `testGetRegularSeasonHistoryDependsOnBoxscoreView` insert directly into the new tables instead of relying on view materialization.
- **`getPlayoffResults()` signature changes** to `getPlayoffResults(string $teamName)` — caller in `TeamService::preparePlayoffData()` updated, dead PHP-side filter removed.
- **The `ibl_team_win_loss` and `ibl_heat_win_loss` views remain** — `FranchiseHistoryRepository`, `SeasonArchiveRepository`, `MaintenanceRepository`, and `RecordHoldersRepository` still consume them. Materializing those is a separate effort.
