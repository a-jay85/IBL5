-- dc_oi_volume_effect.sql: Offensive instruction effect on shot volume and scoring
-- Plan reference: dc_oi=+2 adds ~3.2 FGA per-36, validated from 25 players in 2007.
-- This query uses 2,537 player-seasons with dc_oi changes across 19 seasons.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/dc_oi_volume_effect.sql

.mode column
.headers on

SELECT '=== DC_OI Volume Effect: Cross-Sectional by r_fga Tier ===' AS '';

WITH sim_stats AS (
    SELECT
        pid, season_year, sim_number, dc_oi, r_fga,
        ROUND(SUM(fg2_att + fg3_att) * 36.0 / NULLIF(SUM(minutes), 0), 2) AS fga_per_36,
        ROUND(SUM(points) * 36.0 / NULLIF(SUM(minutes), 0), 2)            AS pts_per_36,
        ROUND(SUM(ast) * 36.0 / NULLIF(SUM(minutes), 0), 2)               AS ast_per_36,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1 AND minutes > 0
    GROUP BY pid, season_year, sim_number, dc_oi, r_fga
)
SELECT
    CASE
        WHEN r_fga < 33 THEN 'low_fga'
        WHEN r_fga < 66 THEN 'mid_fga'
        ELSE 'high_fga'
    END AS rating_tier,
    dc_oi,
    COUNT(*) AS n,
    ROUND(AVG(fga_per_36), 2) AS mean_fga_36,
    ROUND(AVG(pts_per_36), 2) AS mean_pts_36,
    ROUND(AVG(ast_per_36), 2) AS mean_ast_36
FROM sim_stats
WHERE games >= 2 AND total_min >= 20
GROUP BY rating_tier, dc_oi
HAVING COUNT(*) >= 10
ORDER BY rating_tier, dc_oi;

SELECT '' AS '';
SELECT '=== DC_OI Within-Player Paired Effect ===' AS '';
SELECT '    Same player, consecutive sims, different dc_oi' AS '';

WITH player_sim_stats AS (
    SELECT
        pid, season_year, sim_number, dc_oi,
        ROUND(SUM(fg2_att + fg3_att) * 36.0 / NULLIF(SUM(minutes), 0), 2) AS fga_per_36,
        ROUND(SUM(points) * 36.0 / NULLIF(SUM(minutes), 0), 2)            AS pts_per_36,
        ROUND(SUM(ast) * 36.0 / NULLIF(SUM(minutes), 0), 2)               AS ast_per_36,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1
    GROUP BY pid, season_year, sim_number, dc_oi
),
sim_pairs AS (
    SELECT
        a.pid, a.season_year,
        b.dc_oi - a.dc_oi AS oi_delta,
        b.fga_per_36 - a.fga_per_36 AS delta_fga,
        b.pts_per_36 - a.pts_per_36 AS delta_pts,
        b.ast_per_36 - a.ast_per_36 AS delta_ast
    FROM player_sim_stats a
    JOIN player_sim_stats b
        ON a.pid = b.pid AND a.season_year = b.season_year
        AND b.sim_number = a.sim_number + 1
        AND a.dc_oi != b.dc_oi
    WHERE a.games >= 2 AND b.games >= 2
      AND a.total_min >= 20 AND b.total_min >= 20
)
SELECT
    oi_delta AS oi_change,
    COUNT(*) AS n,
    ROUND(AVG(delta_fga), 2) AS delta_fga_36,
    ROUND(AVG(delta_pts), 2) AS delta_pts_36,
    ROUND(AVG(delta_ast), 2) AS delta_ast_36
FROM sim_pairs
GROUP BY oi_delta
ORDER BY oi_delta;
