-- Migration 160: Remove phantom playoff rows that ScheduleUpdater re-labelled from a
-- stale Schedule.htm JSB export.
--
-- Root cause: Schedule.htm and Standings.htm were tracked in git. The deploy workflow
-- runs `git reset --hard origin/production`, which clobbered JSB's freshly-written
-- exports with the stale 2006-07 copies committed to the repo. On the next sim import
-- ScheduleUpdater::insertPlayoffGamesFromScheduleHtm() parsed those stale rows and
-- wrote them into ibl_schedule with season_year set to the CURRENT ending year
-- (2008 at time of writing), producing phantom June games that never occurred.
--
-- Fix (Phase 2): a season guard was added to insertPlayoffGamesFromScheduleHtm()
-- that skips rows whose "Post N YYYY" label year does not match the current season's
-- ending year. Phase 4 untracked Schedule.htm and Standings.htm so git reset --hard
-- can never clobber a fresh export again. This migration removes any phantom rows
-- that already landed in ibl_schedule from the stale 2006-07 file.
--
-- Scope: rows with box_id < 100000 (played sentinel) in month 6 (June — playoff month
-- in the stale 2006-07 export) for the current season ending year. The sub-select
-- reads the live setting rather than hard-coding a year so the statement is safe to
-- re-run in any future season without modification.
--
-- Idempotent: a second run finds no matching rows (the DELETE removes them on the
-- first run) and returns affected_rows = 0.
--
-- Does NOT touch: unplayed playoff placeholders (box_id >= 100000), rows from other
-- seasons, or non-June rows. Regular-season games are in March–May; June is
-- exclusively playoff territory in the IBL calendar.

DELETE FROM ibl_schedule
 WHERE box_id < 100000
   AND MONTH(game_date) = 6
   AND season_year = (
         SELECT CAST(value AS UNSIGNED)
           FROM ibl_settings
          WHERE setting_key = 'Current Season Ending Year'
            AND league = 'ibl'
          LIMIT 1
       );
