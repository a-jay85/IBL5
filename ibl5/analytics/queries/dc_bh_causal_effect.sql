-- dc_bh_causal_effect.sql: Within-player paired analysis of ball-handling instruction
-- Tests whether dc_bh changes cause measurable stat changes (AST, TOV, FGA).
-- Plan reference: dc_bh validated at +0.5-0.7 AST/48 per step from 73 saved DCs.
-- This query uses 1,911 player-seasons with dc_bh changes across 19 seasons.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/dc_bh_causal_effect.sql

.mode column
.headers on

SELECT '=== DC_BH Causal Effect: Within-Player Paired Analysis ===' AS '';
SELECT '    Same player, consecutive sims, different dc_bh' AS '';

-- Aggregate stats per player-sim from fact_player_sim
WITH player_sim_stats AS (
    SELECT
        pid, season_year, sim_number, dc_bh, r_ast,
        ROUND(SUM(ast) * 48.0 / NULLIF(SUM(minutes), 0), 2)  AS ast_per_48,
        ROUND(SUM(tov) * 48.0 / NULLIF(SUM(minutes), 0), 2)  AS tov_per_48,
        ROUND(SUM(fg2_att + fg3_att) * 48.0 / NULLIF(SUM(minutes), 0), 2) AS fga_per_48,
        ROUND(SUM(points) * 48.0 / NULLIF(SUM(minutes), 0), 2) AS pts_per_48,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1
    GROUP BY pid, season_year, sim_number, dc_bh, r_ast
),
-- Build pairs: same player, consecutive sims, different dc_bh
sim_pairs AS (
    SELECT
        a.pid, a.season_year,
        a.dc_bh AS bh_before, b.dc_bh AS bh_after,
        b.dc_bh - a.dc_bh AS bh_delta,
        a.r_ast,
        a.ast_per_48 AS ast_before, b.ast_per_48 AS ast_after,
        a.tov_per_48 AS tov_before, b.tov_per_48 AS tov_after,
        a.fga_per_48 AS fga_before, b.fga_per_48 AS fga_after
    FROM player_sim_stats a
    JOIN player_sim_stats b
        ON a.pid = b.pid
        AND a.season_year = b.season_year
        AND b.sim_number = a.sim_number + 1
        AND a.dc_bh != b.dc_bh
    WHERE a.games >= 2 AND b.games >= 2
      AND a.total_min >= 20 AND b.total_min >= 20
)
SELECT
    bh_delta                                  AS bh_change,
    COUNT(*)                                  AS n_pairs,
    ROUND(AVG(ast_after - ast_before), 2)     AS delta_ast_48,
    ROUND(AVG(tov_after - tov_before), 2)     AS delta_tov_48,
    ROUND(AVG(fga_after - fga_before), 2)     AS delta_fga_48
FROM sim_pairs
GROUP BY bh_delta
ORDER BY bh_delta;

SELECT '' AS '';
SELECT '=== DC_BH Effect by r_ast Tier ===' AS '';
SELECT '    Plan finding: effect scales with r_ast (negligible at <25, strongest at 30-60)' AS '';

WITH player_sim_stats AS (
    SELECT
        pid, season_year, sim_number, dc_bh, r_ast,
        ROUND(SUM(ast) * 48.0 / NULLIF(SUM(minutes), 0), 2) AS ast_per_48,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1
    GROUP BY pid, season_year, sim_number, dc_bh, r_ast
)
SELECT
    CASE
        WHEN r_ast < 25 THEN '0-24'
        WHEN r_ast < 50 THEN '25-49'
        ELSE '50+'
    END AS r_ast_tier,
    dc_bh,
    COUNT(*) AS n,
    ROUND(AVG(ast_per_48), 2) AS mean_ast_48
FROM player_sim_stats
WHERE games >= 2 AND total_min >= 20
GROUP BY r_ast_tier, dc_bh
HAVING COUNT(*) >= 10
ORDER BY r_ast_tier, dc_bh;
