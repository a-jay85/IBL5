-- 01_dimensions.sql: Dimension tables loaded from CSV exports
-- Run: duckdb ibl_analytics.duckdb < schema/01_dimensions.sql

-- dim_player: Player master with computed TSI fields
CREATE OR REPLACE TABLE dim_player AS
SELECT
    pid,
    name,
    age,
    peak,
    talent,
    skill,
    intangibles,
    (talent + skill + intangibles)::INTEGER AS tsi_sum,
    CASE
        WHEN (talent + skill + intangibles) <= 6  THEN 'low'
        WHEN (talent + skill + intangibles) <= 9  THEN 'mid'
        WHEN (talent + skill + intangibles) <= 12 THEN 'high'
        ELSE 'elite'
    END AS tsi_band,
    retired,
    exp,
    -- Current contract salary (cy indicates current year of contract)
    CASE cy
        WHEN 1 THEN cy1 WHEN 2 THEN cy2 WHEN 3 THEN cy3
        WHEN 4 THEN cy4 WHEN 5 THEN cy5 WHEN 6 THEN cy6
        ELSE 0
    END AS current_salary,
    tid,
    draftround,
    draftyear,
    draftpickno
FROM read_csv('data/ibl_plr.csv', delim='\t', header=true, all_varchar=true,
    columns={
        'pid': 'INTEGER', 'name': 'VARCHAR', 'age': 'INTEGER', 'peak': 'INTEGER',
        'talent': 'INTEGER', 'skill': 'INTEGER', 'intangibles': 'INTEGER',
        'retired': 'INTEGER', 'exp': 'INTEGER', 'tid': 'INTEGER',
        'cy': 'INTEGER', 'cy1': 'INTEGER', 'cy2': 'INTEGER', 'cy3': 'INTEGER',
        'cy4': 'INTEGER', 'cy5': 'INTEGER', 'cy6': 'INTEGER',
        'draftround': 'INTEGER', 'draftyear': 'INTEGER', 'draftpickno': 'INTEGER'
    },
    null_padding=true, ignore_errors=true);

-- dim_team: Team master
CREATE OR REPLACE TABLE dim_team AS
SELECT
    teamid,
    team_name,
    team_city,
    color1,
    color2
FROM read_csv('data/ibl_team_info.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true)
WHERE CAST(teamid AS INTEGER) IS NOT NULL;

-- dim_season: Distinct season years from ibl_hist
CREATE OR REPLACE TABLE dim_season AS
SELECT DISTINCT
    CAST(year AS INTEGER) AS season_year,
    -- Season label: "06-07" format (season spans Oct of prior year to Jun of this year)
    LPAD(CAST((CAST(year AS INTEGER) - 1) % 100 AS VARCHAR), 2, '0') || '-' ||
    LPAD(CAST(CAST(year AS INTEGER) % 100 AS VARCHAR), 2, '0') AS season_label
FROM read_csv('data/ibl_hist.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true)
WHERE CAST(year AS INTEGER) > 0
ORDER BY season_year;

-- dim_franchise_seasons: Historical team identity tracking
CREATE OR REPLACE TABLE dim_franchise_seasons AS
SELECT
    CAST(franchise_id AS INTEGER) AS franchise_id,
    CAST(season_year AS INTEGER) AS season_year,
    CAST(season_ending_year AS INTEGER) AS season_ending_year,
    team_city,
    team_name
FROM read_csv('data/ibl_franchise_seasons.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- Snapshot of TSI per season (Phase 2 dependent — load if available)
CREATE OR REPLACE TABLE dim_player_snapshot AS
SELECT
    CAST(pid AS INTEGER) AS pid,
    name,
    CAST(season_year AS INTEGER) AS season_year,
    snapshot_phase,
    CAST(tid AS INTEGER) AS tid,
    CAST(age AS INTEGER) AS age,
    pos,
    CAST(peak AS INTEGER) AS peak,
    CAST(talent AS INTEGER) AS talent,
    CAST(skill AS INTEGER) AS skill,
    CAST(intangibles AS INTEGER) AS intangibles,
    (CAST(talent AS INTEGER) + CAST(skill AS INTEGER) + CAST(intangibles AS INTEGER))::INTEGER AS tsi_sum,
    CASE
        WHEN (CAST(talent AS INTEGER) + CAST(skill AS INTEGER) + CAST(intangibles AS INTEGER)) <= 6  THEN 'low'
        WHEN (CAST(talent AS INTEGER) + CAST(skill AS INTEGER) + CAST(intangibles AS INTEGER)) <= 9  THEN 'mid'
        WHEN (CAST(talent AS INTEGER) + CAST(skill AS INTEGER) + CAST(intangibles AS INTEGER)) <= 12 THEN 'high'
        ELSE 'elite'
    END AS tsi_band,
    CAST(r_2ga AS INTEGER) AS r_2ga, CAST(r_2gp AS INTEGER) AS r_2gp,
    CAST(r_fta AS INTEGER) AS r_fta, CAST(r_ftp AS INTEGER) AS r_ftp,
    CAST(r_3ga AS INTEGER) AS r_3ga, CAST(r_3gp AS INTEGER) AS r_3gp,
    CAST(r_orb AS INTEGER) AS r_orb, CAST(r_drb AS INTEGER) AS r_drb,
    CAST(r_ast AS INTEGER) AS r_ast, CAST(r_stl AS INTEGER) AS r_stl,
    CAST(r_to AS INTEGER)  AS r_to,  CAST(r_blk AS INTEGER) AS r_blk,
    CAST(r_foul AS INTEGER) AS r_foul
FROM read_csv('data/ibl_plr_snapshots.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true)
WHERE snapshot_phase = 'preseason';

-- Summary
SELECT 'dim_player' AS table_name, COUNT(*) AS row_count FROM dim_player
UNION ALL SELECT 'dim_team', COUNT(*) FROM dim_team
UNION ALL SELECT 'dim_season', COUNT(*) FROM dim_season
UNION ALL SELECT 'dim_franchise_seasons', COUNT(*) FROM dim_franchise_seasons
UNION ALL SELECT 'dim_player_snapshot', COUNT(*) FROM dim_player_snapshot
ORDER BY table_name;
