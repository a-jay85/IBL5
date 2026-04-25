---
description: Rationale for removing the 9998/9999 preseason sentinel year and using real season years instead, with a cleanup pipeline step for the Preseason→Regular Season transition.
last_verified: 2026-04-25
---

# ADR-0011: Remove Preseason Sentinel Year (9998/9999)

**Status:** Accepted
**Date:** 2026-04-25

## Context

The update pipeline (`updateAllTheThings.php`) shares a single `Season` object across all steps. During Preseason, `ScheduleUpdater::extractDate()` mutated `$season->beginningYear` to 9998 and `$season->endingYear` to 9999. Because all downstream steps (standings, boxscores, JSB imports, power rankings) share the same `Season` reference, this mutation corrupted every subsequent step:

- **Standings**: `fetchTeamMap()` queries `ibl_league_config WHERE season_ending_year = 9999` — finds nothing, inserts nothing.
- **Boxscores**: games stored with dates in `9998-11-XX` to `9999-05-XX` — invisible to season-year-filtered queries.
- **JSB imports**: career stats, trade history, and records written with `season_year = 9999`.
- **Front page**: sim dates intentionally skipped during Preseason, so chunk leaders show last season's data.
- **Team accomplishments**: awards inserted for year 9999 ("9999 IBL Champion Clippers").
- **Schedule**: `.sch` file present in backup archives but never extracted (`ExtractFromBackupStep::EXTENSIONS` omitted `'sch'`).

## Decision

1. **Delete `Season::IBL_PRESEASON_YEAR`** and all 9998/9999 override logic. Preseason now uses the real season years from `ibl_settings`.

2. **Add `CleanupPreseasonDataStep`** to the update pipeline. On the first Regular Season sim (detected by: phase is Regular Season, no sim dates exist for the current season, and preseason boxscores are present), this step deletes preseason data from `ibl_box_scores`, `ibl_box_scores_teams`, `ibl_team_awards`, `ibl_jsb_history`, `ibl_jsb_transactions`, `ibl_rcb_season_records`, and `ibl_plr_snapshots`. The Regular Season `.sco` file re-imports all played games.

3. **Extend `deletePreseasonBoxScores()`** to accept `int $seasonBeginningYear` and cover November through December (preseason games extend into December).

4. **Add `'sch'` to `ExtractFromBackupStep::EXTENSIONS`** so the schedule file is extracted from backup archives.

5. **One-time migration** (`121_remove_preseason_sentinel_data.sql`) purges any existing year-9999 data.

## Consequences

- Tables that auto-overwrite each run (`ibl_schedule`, `ibl_standings`, `ibl_power`) need no cleanup.
- `ibl_sim_dates` is already protected — `BoxscoreProcessor::updateSimDates()` skips writes during Preseason.
- Preseason boxscores share the same `game_type` (1) and `season_year` as Regular Season games. The cleanup step runs before boxscore processing, so there is no collision window.
- 8 production files and 5 test files updated. The `IBL_PRESEASON_YEAR` constant is fully removed.

## Enforcement

- Destructive migration (`121`) triggers this ADR requirement.
- New pipeline step (`CleanupPreseasonDataStep`) is covered by unit tests.
