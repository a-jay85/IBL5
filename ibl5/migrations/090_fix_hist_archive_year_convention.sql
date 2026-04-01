-- Fix ibl_hist_archive.year to use ending-year convention.
--
-- The archive stored the season's starting year (e.g., 2001 for the "01-02"
-- season). Every other table uses the ending year (2002 for "01-02"):
--   - ibl_box_scores.season_year (generated column)
--   - ibl_plr_snapshots.season_year
--   - ibl_franchise_seasons.season_ending_year
--
-- This mismatch caused the ibl_hist VIEW (migration 088) to produce duplicate
-- rows: the NOT EXISTS check compared starting year != ending year, so archive
-- rows were never filtered out even when box_scores covered the same season.

-- ORDER BY year DESC avoids unique key (pid, name, year) collisions:
-- higher years shift first so they don't block lower years moving up.
UPDATE ibl_hist_archive SET `year` = `year` + 1 ORDER BY `year` DESC;
