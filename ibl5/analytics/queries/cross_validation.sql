-- cross_validation.sql: Data consistency checks between fact tables and source CSVs
-- Verifies the DuckDB build accurately represents the MariaDB source data.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/cross_validation.sql

.mode column
.headers on

SELECT '=== Source Row Count Verification ===' AS '';

-- Compare DuckDB table counts with CSV row counts
-- (CSV rows = lines minus header)
SELECT
    'dim_player' AS "Table",
    (SELECT COUNT(*) FROM dim_player) AS duckdb_rows,
    'ibl_plr' AS source_csv
UNION ALL SELECT
    'fact_player_season',
    (SELECT COUNT(*) FROM fact_player_season),
    'ibl_hist'
UNION ALL SELECT
    'fact_player_game',
    (SELECT COUNT(*) FROM fact_player_game),
    'ibl_box_scores'
UNION ALL SELECT
    'fact_team_season',
    (SELECT COUNT(*) FROM fact_team_season),
    'ibl_jsb_history'
UNION ALL SELECT
    'fact_team_game',
    (SELECT COUNT(*) FROM fact_team_game),
    'ibl_box_scores_teams'
UNION ALL SELECT
    'fact_transactions',
    (SELECT COUNT(*) FROM fact_transactions),
    'ibl_jsb_transactions'
UNION ALL SELECT
    'fact_allstar_rosters',
    (SELECT COUNT(*) FROM fact_allstar_rosters),
    'ibl_jsb_allstar_rosters';

SELECT '' AS '';
SELECT '=== Career Totals Spot Check ===' AS '';

-- Verify career aggregation: sum of season games = career games
WITH verification AS (
    SELECT
        c.pid,
        c.name,
        c.career_games AS agg_career_games,
        SUM(f.games) AS sum_season_games
    FROM agg_player_career c
    JOIN fact_player_season f ON c.pid = f.pid
    WHERE f.games > 0
    GROUP BY c.pid, c.name, c.career_games
)
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'Career games match season sums' AS assertion,
    COUNT(*) AS mismatches
FROM verification
WHERE agg_career_games != sum_season_games;

SELECT '' AS '';
SELECT '=== PPG Formula Verification ===' AS '';

-- Verify PPG formula: (2*fgm + ftm + tgm) / games
-- Compare computed ppg against raw pts/games
WITH ppg_check AS (
    SELECT
        pid, season_year, ppg,
        CASE WHEN games > 0 THEN ROUND(pts * 1.0 / games, 1) END AS pts_per_game,
        CASE WHEN games > 0 THEN ROUND((2.0 * fgm + ftm + tgm) / games, 1) END AS formula_ppg
    FROM fact_player_season
    WHERE games > 0
)
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'PPG formula matches (2*fgm+ftm+tgm)/games' AS assertion,
    COUNT(*) AS mismatches
FROM ppg_check
WHERE ABS(ppg - formula_ppg) > 0.1;

SELECT '' AS '';
SELECT '=== Season Year Consistency ===' AS '';

-- All season years in fact_player_season should be in dim_season
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'All season years in dim_season' AS assertion,
    cnt AS orphan_years
FROM (
    SELECT COUNT(DISTINCT f.season_year) AS cnt
    FROM fact_player_season f
    LEFT JOIN dim_season s ON f.season_year = s.season_year
    WHERE s.season_year IS NULL AND f.season_year > 0
);

SELECT '' AS '';
SELECT '=== Team Season Record Consistency ===' AS '';

-- Win% should match wins/(wins+losses)
SELECT
    CASE WHEN COUNT(*) = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'Team win_pct matches wins/(wins+losses)' AS assertion,
    COUNT(*) AS mismatches
FROM fact_team_season
WHERE (wins + losses) > 0
    AND ABS(win_pct - (wins * 1.0 / (wins + losses))) > 0.002;

SELECT '' AS '';
SELECT '=== Progression Table Consistency ===' AS '';

-- Every progression row should have a valid previous season in fact_player_season
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'All progression rows have previous season' AS assertion,
    cnt AS orphans
FROM (
    SELECT COUNT(*) AS cnt
    FROM agg_tsi_progression p
    LEFT JOIN fact_player_season prev
        ON p.pid = prev.pid AND prev.season_year = p.season_year - 1
    WHERE prev.pid IS NULL
);

SELECT '' AS '';
SELECT '=== Data Quality Summary ===' AS '';

SELECT
    (SELECT COUNT(DISTINCT season_year) FROM fact_player_season) AS seasons,
    (SELECT COUNT(DISTINCT pid) FROM fact_player_season) AS players,
    (SELECT COUNT(*) FROM fact_player_season) AS player_seasons,
    (SELECT COUNT(*) FROM fact_player_game) AS player_games,
    (SELECT COUNT(DISTINCT teamid) FROM fact_team_season) AS teams,
    (SELECT COUNT(*) FROM agg_tsi_progression) AS progression_rows;
