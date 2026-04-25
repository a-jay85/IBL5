-- 01_dimensions.sql: Dimension tables loaded from CSV exports
-- Run: duckdb ibl_analytics.duckdb < schema/01_dimensions.sql

-- dim_player: Player master with computed TSI fields
CREATE OR REPLACE TABLE dim_player AS
SELECT
    TRY_CAST(pid AS INTEGER) AS pid,
    name,
    TRY_CAST(age AS INTEGER) AS age,
    TRY_CAST(peak AS INTEGER) AS peak,
    TRY_CAST(talent AS INTEGER) AS talent,
    TRY_CAST(skill AS INTEGER) AS skill,
    TRY_CAST(intangibles AS INTEGER) AS intangibles,
    (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) AS tsi_sum,
    CASE
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 6  THEN 'low'
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 9  THEN 'mid'
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 12 THEN 'high'
        ELSE 'elite'
    END AS tsi_band,
    TRY_CAST(retired AS INTEGER) AS retired,
    TRY_CAST(exp AS INTEGER) AS exp,
    -- Current contract salary (cy indicates current year of contract)
    CASE TRY_CAST(cy AS INTEGER)
        WHEN 1 THEN TRY_CAST(salary_yr1 AS INTEGER) WHEN 2 THEN TRY_CAST(salary_yr2 AS INTEGER) WHEN 3 THEN TRY_CAST(salary_yr3 AS INTEGER)
        WHEN 4 THEN TRY_CAST(salary_yr4 AS INTEGER) WHEN 5 THEN TRY_CAST(salary_yr5 AS INTEGER) WHEN 6 THEN TRY_CAST(salary_yr6 AS INTEGER)
        ELSE 0
    END AS current_salary,
    -- Migration 114: MariaDB column renamed tid → teamid.
    TRY_CAST(teamid AS INTEGER) AS teamid,
    TRY_CAST(draftround AS INTEGER) AS draftround,
    TRY_CAST(draftyear AS INTEGER) AS draftyear,
    TRY_CAST(draftpickno AS INTEGER) AS draftpickno
FROM read_csv('data/ibl_plr.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- dim_team: Team master
CREATE OR REPLACE TABLE dim_team AS
SELECT
    TRY_CAST(teamid AS INTEGER) AS teamid,
    team_name,
    team_city,
    color1,
    color2
FROM read_csv('data/ibl_team_info.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='')
WHERE TRY_CAST(teamid AS INTEGER) IS NOT NULL;

-- dim_season: Distinct season years from ibl_hist
CREATE OR REPLACE TABLE dim_season AS
SELECT DISTINCT
    TRY_CAST(year AS INTEGER) AS season_year,
    -- Season label: "06-07" format (season spans Oct of prior year to Jun of this year)
    LPAD(TRY_CAST((TRY_CAST(year AS INTEGER) - 1) % 100 AS VARCHAR), 2, '0') || '-' ||
    LPAD(TRY_CAST(TRY_CAST(year AS INTEGER) % 100 AS VARCHAR), 2, '0') AS season_label
FROM read_csv('data/ibl_hist.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='')
WHERE TRY_CAST(year AS INTEGER) IS NOT NULL AND TRY_CAST(year AS INTEGER) > 0
ORDER BY season_year;

-- dim_franchise_seasons: Historical team identity tracking
CREATE OR REPLACE TABLE dim_franchise_seasons AS
SELECT
    TRY_CAST(franchise_id AS INTEGER) AS franchise_id,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    TRY_CAST(season_ending_year AS INTEGER) AS season_ending_year,
    team_city,
    team_name
FROM read_csv('data/ibl_franchise_seasons.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- Snapshot of TSI per season (Phase 2 dependent — load if available)
CREATE OR REPLACE TABLE dim_player_snapshot AS
SELECT
    TRY_CAST(pid AS INTEGER) AS pid,
    name,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    snapshot_phase,
    -- Migration 114: MariaDB column renamed tid → teamid.
    TRY_CAST(teamid AS INTEGER) AS teamid,
    TRY_CAST(age AS INTEGER) AS age,
    pos,
    TRY_CAST(peak AS INTEGER) AS peak,
    TRY_CAST(talent AS INTEGER) AS talent,
    TRY_CAST(skill AS INTEGER) AS skill,
    TRY_CAST(intangibles AS INTEGER) AS intangibles,
    (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) AS tsi_sum,
    CASE
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 6  THEN 'low'
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 9  THEN 'mid'
        WHEN (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) <= 12 THEN 'high'
        ELSE 'elite'
    END AS tsi_band,
    TRY_CAST(r_fga AS INTEGER) AS r_fga, TRY_CAST(r_fgp AS INTEGER) AS r_fgp,
    TRY_CAST(r_fta AS INTEGER) AS r_fta, TRY_CAST(r_ftp AS INTEGER) AS r_ftp,
    -- Migration 114: MariaDB columns renamed r_tga → r_3ga, r_tgp → r_3gp.
    TRY_CAST(r_3ga AS INTEGER) AS r_3ga, TRY_CAST(r_3gp AS INTEGER) AS r_3gp,
    TRY_CAST(r_orb AS INTEGER) AS r_orb, TRY_CAST(r_drb AS INTEGER) AS r_drb,
    TRY_CAST(r_ast AS INTEGER) AS r_ast, TRY_CAST(r_stl AS INTEGER) AS r_stl,
    -- Migration 113: MariaDB column renamed r_to → r_tvr.
    TRY_CAST(r_tvr AS INTEGER) AS r_tvr,  TRY_CAST(r_blk AS INTEGER) AS r_blk,
    TRY_CAST(r_foul AS INTEGER) AS r_foul
FROM read_csv('data/ibl_plr_snapshots.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='')
WHERE snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
QUALIFY ROW_NUMBER() OVER (
    PARTITION BY TRY_CAST(pid AS INTEGER), TRY_CAST(season_year AS INTEGER)
    ORDER BY CASE snapshot_phase
        WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2
        WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4
        WHEN 'heat-lb' THEN 5 END
) = 1;

-- dim_sim_dates: Global simulation date windows (698 sims across 19 seasons)
-- Maps PLB per-season sim_number to date ranges via season offset calculation.
-- Migration 113: MariaDB columns renamed `Start Date` → start_date, `End Date` → end_date.
CREATE OR REPLACE TABLE dim_sim_dates AS
SELECT
    TRY_CAST("Sim" AS INTEGER)        AS global_sim,
    TRY_CAST(start_date AS DATE)      AS sim_start_date,
    TRY_CAST(end_date   AS DATE)      AS sim_end_date
FROM read_csv('data/ibl_sim_dates.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='')
WHERE TRY_CAST("Sim" AS INTEGER) IS NOT NULL;

-- Summary
SELECT 'dim_player' AS table_name, COUNT(*) AS row_count FROM dim_player
UNION ALL SELECT 'dim_team', COUNT(*) FROM dim_team
UNION ALL SELECT 'dim_season', COUNT(*) FROM dim_season
UNION ALL SELECT 'dim_franchise_seasons', COUNT(*) FROM dim_franchise_seasons
UNION ALL SELECT 'dim_player_snapshot', COUNT(*) FROM dim_player_snapshot
UNION ALL SELECT 'dim_sim_dates', COUNT(*) FROM dim_sim_dates
ORDER BY table_name;
