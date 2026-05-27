-- Fix ibl_schedule.season_year to use ending-year convention.
--
-- ScheduleUpdater stored the calendar year for Oct-Dec games (e.g., 2025
-- for a Nov 2025 game in the 2025-2026 season). Every other table uses
-- the ending year (2026). Shift affected rows to match.

UPDATE ibl_schedule
   SET season_year = season_year + 1
 WHERE MONTH(game_date) >= 10;
