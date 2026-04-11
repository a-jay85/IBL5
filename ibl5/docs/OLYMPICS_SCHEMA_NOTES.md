---
description: Schema notes for Olympics event data.
last_verified: 2026-04-11
---

# Olympics Schema Notes

## Table Pairs and `LeagueContext::getTableName()` Mapping

When `LeagueContext::isOlympics()` is true, the following IBL table names are resolved to their Olympics equivalents:

| IBL Table | Olympics Table | Purpose |
|-----------|---------------|---------|
| `ibl_box_scores` | `ibl_olympics_box_scores` | Player box scores |
| `ibl_box_scores_teams` | `ibl_olympics_box_scores_teams` | Team box scores |
| `ibl_schedule` | `ibl_olympics_schedule` | Game schedule |
| `ibl_standings` | `ibl_olympics_standings` | Team standings |
| `ibl_power` | `ibl_olympics_power` | Power rankings |
| `ibl_team_info` | `ibl_olympics_team_info` | Team metadata |
| `ibl_league_config` | `ibl_olympics_league_config` | League configuration from .lge |
| `ibl_plr` | `ibl_olympics_plr` | Player data from .plr |
| `ibl_hist` | `ibl_olympics_hist` | Career/historical stats from .car/.plr |
| `ibl_jsb_history` | `ibl_olympics_jsb_history` | Season W-L records from .his |
| `ibl_jsb_transactions` | `ibl_olympics_jsb_transactions` | Transactions from .trn |
| `ibl_rcb_alltime_records` | `ibl_olympics_rcb_alltime_records` | All-time records from .rcb |
| `ibl_rcb_season_records` | `ibl_olympics_rcb_season_records` | Season records from .rcb |

Tables not in this mapping are returned unchanged by `getTableName()`.

## How Table Resolution Works

1. `LeagueContext` detects the current league from `$_GET['league']`, `$_SESSION['current_league']`, or `$_COOKIE['ibl_league']` (in priority order).
2. `BaseMysqliRepository` accepts an optional `?LeagueContext` constructor parameter and provides `protected function resolveTable(string $iblTableName): string`.
3. Repositories resolve table names in their constructors (stored as properties) and use those properties in SQL queries.
4. Repositories that don't receive a `LeagueContext` default to IBL table names (backward compatible).

### Shared Tables

- `ibl_sim_dates` — shared between IBL and Olympics. Olympics games occur in August (no overlap with IBL Oct-Jun season).

### Not Mapped

- `ibl_jsb_allstar_rosters` / `ibl_jsb_allstar_scores` — Olympics has no All-Star Weekend.
- `ibl_olympics_stats` — different schema from `ibl_hist`, populated by a different mechanism.

## File Prefix Convention

JSB engine files use different prefixes per league:

| League | Prefix | Example |
|--------|--------|---------|
| IBL | `IBL5` | `IBL5.lge`, `IBL5.plr`, `IBL5.sco` |
| Olympics | `Olympics` | `Olympics.lge`, `Olympics.plr`, `Olympics.sco` |

`LeagueContext::getFilePrefix()` returns the correct prefix. All file path construction in the pipeline uses this method.

## Olympics Date Mapping

All Olympics game dates map to **August** of the ending year:

- `.sch` dates: `DateParser::extractDate()` maps to `Season::IBL_OLYMPICS_MONTH` (8)
- `.sco` dates: `Boxscore::fillGameInfo()` overrides month to August when `$league === 'olympics'`

This ensures no date overlap with IBL games (October-June).

## Intentional Schema Differences

### Write-path tables (identical schemas)

Both leagues use the same JSB simulation engine, so write-path tables share identical column structures. The same INSERT/UPDATE queries work for both.

### Read-path tables (different schemas)

| IBL Table | Olympics Equivalent | Notes |
|-----------|-------------------|-------|
| `ibl_hist` | `ibl_olympics_stats` | Different column sets; queried by dedicated methods |
| `ibl_awards` | None | IBL-only awards |
| `ibl_settings` | None | Global site settings |
| `ibl_sim_dates` | None (shared) | Global sim calendar |
| `ibl_team_offense_stats` | None | IBL-only aggregated stats |
| `ibl_team_defense_stats` | None | IBL-only aggregated stats |
| `ibl_banners` | None | IBL-only championship banners |
| `ibl_gm_tenures` | None | IBL-only GM history |

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

## Backfill Instructions

To import historical Olympics data:

```
updateAllTheThings.php?league=olympics&season_year=2003
```

1. Copy Olympics files to `ibl5/`: `Olympics.{lge,plr,sco,sch,car,trn,his,rcb}`
2. Ensure `ibl_olympics_team_info` is populated (seed from league_config data)
3. Run the pipeline URL above
4. Verify data in Olympics tables
5. Remove copied Olympics files from `ibl5/`

## Migration History

| Migration | Changes |
|-----------|---------|
| 043 | Olympics schema alignment — created Olympics tables matching IBL structure |
| 044 | Olympics JSB tables — plr, hist, jsb_history, jsb_transactions, rcb_alltime_records, rcb_season_records |
| 045 | Align Olympics table schemas — added missing columns (wins/losses, generated columns, SOS, UUID trigger) |

## Convention for Future Changes

When adding a new table that should have an Olympics equivalent:

1. Create both tables in a migration file
2. Add the mapping to `LeagueContext::TABLE_MAP`
3. Use `$this->resolveTable('ibl_new_table')` in any repository that queries it
