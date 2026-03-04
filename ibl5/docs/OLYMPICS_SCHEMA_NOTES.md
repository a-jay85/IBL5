# Olympics Schema Notes

## Table Pairs and `LeagueContext::getTableName()` Mapping

When `LeagueContext::isOlympics()` is true, the following IBL table names are resolved to their Olympics equivalents:

| IBL Table | Olympics Table |
|-----------|---------------|
| `ibl_standings` | `ibl_olympics_standings` |
| `ibl_team_info` | `ibl_olympics_team_info` |
| `ibl_box_scores` | `ibl_olympics_box_scores` |
| `ibl_box_scores_teams` | `ibl_olympics_box_scores_teams` |
| `ibl_schedule` | `ibl_olympics_schedule` |
| `ibl_power` | `ibl_olympics_power` |
| `ibl_league_config` | `ibl_olympics_league_config` |

Tables not in this mapping are returned unchanged by `getTableName()`.

## How Table Resolution Works

1. `LeagueContext` detects the current league from `$_GET['league']`, `$_SESSION['current_league']`, or `$_COOKIE['ibl_league']` (in priority order).
2. `BaseMysqliRepository` accepts an optional `?LeagueContext` constructor parameter and provides `protected function resolveTable(string $iblTableName): string`.
3. Repositories resolve table names in their constructors (stored as properties) and use those properties in SQL queries.
4. Repositories that don't receive a `LeagueContext` default to IBL table names (backward compatible).

## Intentional Schema Differences

### Write-path tables (identical schemas)

Both leagues use the same JSB simulation engine, so write-path tables share identical column structures. The same INSERT/UPDATE queries work for both. Migration 043 aligned Olympics tables with IBL, and migration 044 added missing `clinchedLeague` and changed `conference`/`division` from ENUM to VARCHAR(32).

### Read-path tables (different schemas)

| IBL Table | Olympics Equivalent | Notes |
|-----------|-------------------|-------|
| `ibl_hist` | `ibl_olympics_stats` | Different column sets; queried by dedicated methods |
| `ibl_plr` | None | Player file data is IBL-only |
| `ibl_awards` | None | IBL-only awards |
| `ibl_settings` | None | Global site settings |
| `ibl_sim_dates` | None | Global sim calendar |
| `ibl_team_offense_stats` | None | IBL-only aggregated stats |
| `ibl_team_defense_stats` | None | IBL-only aggregated stats |
| `ibl_banners` | None | IBL-only championship banners |
| `ibl_gm_tenures` | None | IBL-only GM history |

## Olympics-Only Tables (No IBL Equivalent)

| Table | Purpose |
|-------|---------|
| `ibl_olympics_stats` | Per-game player statistics for Olympics |

## IBL-Only Modules (Disabled in Olympics Context)

These modules are gated by `LeagueContext::isModuleEnabled()` and return false for Olympics:

- Draft
- FreeAgency
- Trading
- Waivers
- Voting

## Pipeline Behavior in Olympics Context

When `updateAllTheThings.php` runs with `?league=olympics`:

- **Skipped steps:** ParsePlayerFile, ResetExtensionAttempts, ExtendDepthCharts, ProcessAllStarGames
- **League-aware steps:** All updaters receive `LeagueContext` and resolve tables accordingly
- **Shared steps:** StandingsUpdater, PowerRankingsUpdater, ScheduleUpdater, BoxscoreRepository, LeagueConfigRepository all use resolved table names

## Migration History

| Migration | Changes |
|-----------|---------|
| 043 | Olympics schema alignment — created Olympics tables matching IBL structure |
| 044 | Added `clinchedLeague` to `ibl_olympics_standings`; changed `conference`/`division` from ENUM to VARCHAR(32) on both standings tables |

## Convention for Future Changes

When adding a new table that should have an Olympics equivalent:

1. Create both tables in a migration file
2. Add the mapping to `LeagueContext::TABLE_MAP`
3. Use `$this->resolveTable('ibl_new_table')` in any repository that queries it
