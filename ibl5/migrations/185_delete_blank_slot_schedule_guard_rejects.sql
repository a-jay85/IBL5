-- Remove schedule_guard_rejects rows logged for unused .sco game slots.
--
-- JSB pre-allocates 3,500 game slots per .sco; unused ones are all spaces and decode
-- to "<season ending year>-10-01, team 1 @ team 1, game 1". Since #2360, a
-- Preseason-phase import stopped exempting month 10, so every blank slot was rejected
-- as preseason_shift_not_in_schedule and logged here (2,000 rows from the
-- 08-09_02_preseason.zip backfill alone). BoxscoreProcessor now skips blank slots.
--
-- A team never plays itself, so the 1 @ 1 signature cannot match a real game.

DELETE FROM schedule_guard_rejects
WHERE reason = 'preseason_shift_not_in_schedule'
  AND visitor_teamid = 1
  AND home_teamid = 1
  AND game_of_that_day = 1
  AND game_date = STR_TO_DATE(CONCAT(season_year, '-10-01'), '%Y-%m-%d');
