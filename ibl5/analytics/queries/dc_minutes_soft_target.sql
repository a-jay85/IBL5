-- dc_minutes_soft_target.sql: Actual minutes vs dc_minutes coaching target
-- Plan reference: dc_minutes exceeded in 12-63% of games (from 73 saved DCs in 2007).
-- This query uses 3,336 player-seasons with dc_minutes changes across 19 seasons.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/dc_minutes_soft_target.sql

.mode column
.headers on

SELECT '=== DC_Minutes vs Actual Minutes: Distribution by Target ===' AS '';

SELECT
    dc_minutes                              AS target,
    COUNT(*)                                AS obs,
    ROUND(AVG(minutes), 1)                  AS avg_actual,
    ROUND(MEDIAN(minutes), 1)               AS median_actual,
    ROUND(AVG(minutes) * 1.0 / dc_minutes, 2) AS adherence_ratio,
    COUNT(CASE WHEN minutes > dc_minutes THEN 1 END) AS n_over,
    ROUND(COUNT(CASE WHEN minutes > dc_minutes THEN 1 END) * 100.0 / COUNT(*), 1) AS pct_over,
    MAX(minutes) AS max_actual
FROM fact_player_sim
WHERE game_type = 1 AND dc_minutes > 0 AND minutes > 0
GROUP BY dc_minutes
HAVING COUNT(*) >= 50
ORDER BY dc_minutes;

SELECT '' AS '';
SELECT '=== Minutes Adherence by Era ===' AS '';

SELECT
    CASE
        WHEN season_year BETWEEN 1989 AND 1995 THEN '1989-1995'
        WHEN season_year BETWEEN 1996 AND 2001 THEN '1996-2001'
        WHEN season_year BETWEEN 2002 AND 2007 THEN '2002-2007'
    END AS era,
    ROUND(AVG(dc_minutes), 1) AS avg_target,
    ROUND(AVG(minutes), 1) AS avg_actual,
    ROUND(AVG(minutes) * 1.0 / NULLIF(AVG(dc_minutes), 0), 2) AS adherence,
    ROUND(COUNT(CASE WHEN minutes > dc_minutes THEN 1 END) * 100.0 / COUNT(*), 1) AS pct_over,
    COUNT(*) AS obs
FROM fact_player_sim
WHERE game_type = 1 AND dc_minutes > 0 AND minutes > 0
GROUP BY era
ORDER BY era;

SELECT '' AS '';
SELECT '=== Starters (dc_minutes >= 30) vs Bench (dc_minutes <= 15) ===' AS '';

SELECT
    CASE
        WHEN dc_minutes >= 30 THEN 'starter (30+)'
        WHEN dc_minutes >= 16 THEN 'rotation (16-29)'
        ELSE 'bench (1-15)'
    END AS role,
    COUNT(*) AS obs,
    ROUND(AVG(dc_minutes), 1) AS avg_target,
    ROUND(AVG(minutes), 1) AS avg_actual,
    ROUND(AVG(minutes) * 1.0 / NULLIF(AVG(dc_minutes), 0), 2) AS adherence,
    ROUND(COUNT(CASE WHEN minutes > dc_minutes THEN 1 END) * 100.0 / COUNT(*), 1) AS pct_over
FROM fact_player_sim
WHERE game_type = 1 AND dc_minutes > 0 AND minutes > 0
GROUP BY role
ORDER BY role;
