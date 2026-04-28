-- Add composite index for playoff/HEAT per-season stats JOIN
-- buildPerSeasonStatsQuery() JOINs ibl_franchise_seasons on
-- (franchise_id, season_ending_year). The existing unique key covers
-- (franchise_id, season_year) — not season_ending_year — so the optimizer
-- scans ~8 rows per join. The new index narrows that to a single-row lookup.
ALTER TABLE ibl_franchise_seasons
    ADD INDEX idx_franchise_ending_year (franchise_id, season_ending_year);
