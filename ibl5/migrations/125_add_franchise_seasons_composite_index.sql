-- Add composite index for playoff/HEAT per-season stats JOIN
-- buildPerSeasonStatsQuery() JOINs ibl_franchise_seasons on
-- (franchise_id, season_ending_year). The existing unique key covers
-- (franchise_id, season_year) — not season_ending_year — so the optimizer
-- scans ~8 rows per join. The new index narrows that to a single-row lookup.
--
-- made idempotent 2026-06-05 (maintenance-27, backlog 15.22): `ADD INDEX IF NOT
-- EXISTS` (MariaDB 10.5.2+) makes re-applying this migration a no-op when the
-- index already exists. Safe to edit this applied migration: the runner records
-- it as run, so the body only matters on fresh installs / re-seeds.
ALTER TABLE ibl_franchise_seasons
    ADD INDEX IF NOT EXISTS idx_franchise_ending_year (franchise_id, season_ending_year);
