# Olympics Schema Notes

## Table Pairs

Olympics tables mirror their IBL counterparts. The pipeline writes to the correct table based on `LeagueContext`.

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

## Backfill Instructions

To import historical Olympics data:

```
updateAllTheThings.php?league=olympics&season_year=2003
```

1. Copy Olympics files to `ibl5/`: `Olympics.{lge,plr,sco,sch,car,trn,his,rcb}`
2. Ensure `ibl_olympics_team_info` is populated (the `.lge` import step handles this)
3. Run the pipeline URL above
4. Verify data in Olympics tables
5. Remove copied Olympics files from `ibl5/`

## Migrations

| Migration | Tables Created |
|-----------|---------------|
| `043_align_olympics_schemas.sql` | box_scores, box_scores_teams, schedule, standings, power, team_info, league_config |
| `044_olympics_jsb_tables.sql` | plr, hist, jsb_history, jsb_transactions, rcb_alltime_records, rcb_season_records |
