-- stat_production_development.sql: DC settings -> stat production -> rating development
-- TSI Progression Finding 6: AST/g -> d_AST has 1.93 spread.
-- This query tests whether DC changes (dc_bh -> more AST, dc_oi -> more FGA)
-- create measurable signals in next-season rating development.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/stat_production_development.sql

.mode column
.headers on

SELECT '=== DC_BH -> Season AST Production -> Next-Season AST Rating Change ===' AS '';
SELECT '    Tests: does higher dc_bh -> more AST/g -> better d_AST next year?' AS '';

-- Step 1: Compute season-level stats per player from fact_player_sim, grouped by dc_bh
WITH season_stats AS (
    SELECT
        pid, season_year,
        -- Use the most common dc_bh for the season (mode)
        MODE(dc_bh) AS primary_dc_bh,
        SUM(ast) * 1.0 / NULLIF(COUNT(CASE WHEN minutes > 0 THEN 1 END), 0) AS ast_per_game,
        SUM(fg2_att + fg3_att) * 1.0 / NULLIF(COUNT(CASE WHEN minutes > 0 THEN 1 END), 0) AS fga_per_game,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1 AND minutes > 0
    GROUP BY pid, season_year
),
-- Step 2: Get next-season rating changes from PLR snapshots
rating_deltas AS (
    SELECT
        curr.pid,
        curr.season_year,
        next.r_ast - curr.r_ast AS d_ast,
        next.r_fgp - curr.r_fgp AS d_fgp,
        next.r_ftp - curr.r_ftp AS d_ftp,
        curr.tsi_sum
    FROM fact_plr_snapshots curr
    JOIN fact_plr_snapshots next
        ON curr.pid = next.pid
        AND next.season_year = curr.season_year + 1
    WHERE curr.snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
      AND next.snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
    QUALIFY ROW_NUMBER() OVER (PARTITION BY curr.pid, curr.season_year ORDER BY
        CASE curr.snapshot_phase WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2 WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4 WHEN 'heat-lb' THEN 5 END,
        CASE next.snapshot_phase WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2 WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4 WHEN 'heat-lb' THEN 5 END
    ) = 1
)
SELECT
    ss.primary_dc_bh AS dc_bh,
    CASE
        WHEN ss.ast_per_game < 2 THEN '<2 APG'
        WHEN ss.ast_per_game < 4 THEN '2-4 APG'
        ELSE '4+ APG'
    END AS ast_tier,
    COUNT(*) AS n,
    ROUND(AVG(ss.ast_per_game), 2) AS avg_ast_pg,
    ROUND(AVG(rd.d_ast), 2) AS next_d_ast,
    ROUND(AVG(rd.d_fgp), 2) AS next_d_fgp,
    ROUND(AVG(rd.tsi_sum), 1) AS avg_tsi
FROM season_stats ss
JOIN rating_deltas rd ON ss.pid = rd.pid AND ss.season_year = rd.season_year
WHERE ss.games >= 20 AND ss.total_min >= 500
GROUP BY dc_bh, ast_tier
HAVING COUNT(*) >= 10
ORDER BY dc_bh, ast_tier;

SELECT '' AS '';
SELECT '=== DC_OI -> Season FGA Production -> Next-Season FGP Change ===' AS '';

WITH season_stats AS (
    SELECT
        pid, season_year,
        MODE(dc_oi) AS primary_dc_oi,
        SUM(fg2_att + fg3_att) * 1.0 / NULLIF(COUNT(CASE WHEN minutes > 0 THEN 1 END), 0) AS fga_per_game,
        SUM(minutes) AS total_min,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE game_type = 1 AND minutes > 0
    GROUP BY pid, season_year
),
rating_deltas AS (
    SELECT
        curr.pid, curr.season_year,
        next.r_fgp - curr.r_fgp AS d_fgp,
        next.r_fga - curr.r_fga AS d_fga,
        curr.tsi_sum
    FROM fact_plr_snapshots curr
    JOIN fact_plr_snapshots next
        ON curr.pid = next.pid AND next.season_year = curr.season_year + 1
    WHERE curr.snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
      AND next.snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
    QUALIFY ROW_NUMBER() OVER (PARTITION BY curr.pid, curr.season_year ORDER BY
        CASE curr.snapshot_phase WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2 WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4 WHEN 'heat-lb' THEN 5 END,
        CASE next.snapshot_phase WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2 WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4 WHEN 'heat-lb' THEN 5 END
    ) = 1
)
SELECT
    ss.primary_dc_oi AS dc_oi,
    CASE
        WHEN ss.fga_per_game < 7 THEN '<7 FGA/g'
        WHEN ss.fga_per_game < 12 THEN '7-12 FGA/g'
        ELSE '12+ FGA/g'
    END AS fga_tier,
    COUNT(*) AS n,
    ROUND(AVG(ss.fga_per_game), 2) AS avg_fga_pg,
    ROUND(AVG(rd.d_fgp), 2) AS next_d_fgp,
    ROUND(AVG(rd.d_fga), 2) AS next_d_fga,
    ROUND(AVG(rd.tsi_sum), 1) AS avg_tsi
FROM season_stats ss
JOIN rating_deltas rd ON ss.pid = rd.pid AND ss.season_year = rd.season_year
WHERE ss.games >= 20 AND ss.total_min >= 500
GROUP BY dc_oi, fga_tier
HAVING COUNT(*) >= 10
ORDER BY dc_oi, fga_tier;
