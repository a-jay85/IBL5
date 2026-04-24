-- simulation_calibration_by_era.sql: Per-season team-level calibration targets
-- Feeds Phase 2E of the simulation engine plan.
-- Plan concern: 2007 ratings are dramatically inflated vs historical.
-- This query produces per-season baselines to validate formula generalization.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/simulation_calibration_by_era.sql

.mode column
.headers on

SELECT '=== Per-Season Team-Level Calibration Targets ===' AS '';

SELECT
    tg.season_year,
    COUNT(*) / 2 AS team_games,
    ROUND(AVG(tg.total_points), 1)                AS avg_team_pts,
    ROUND(AVG(tg.visitor_q1 + tg.visitor_q2 + tg.visitor_q3 + tg.visitor_q4
            + tg.home_q1 + tg.home_q2 + tg.home_q3 + tg.home_q4), 1) AS avg_reg_pts,
    ROUND(AVG(CASE WHEN tg.visitor_ot > 0 OR tg.home_ot > 0 THEN 1.0 ELSE 0.0 END) * 100, 1)
                                                  AS ot_pct,
    ROUND(AVG(CASE WHEN tg.home_q1 + tg.home_q2 + tg.home_q3 + tg.home_q4 + tg.home_ot
                      > tg.visitor_q1 + tg.visitor_q2 + tg.visitor_q3 + tg.visitor_q4 + tg.visitor_ot
              THEN 1.0 ELSE 0.0 END) * 100, 1)   AS home_win_pct
FROM fact_team_game tg
WHERE tg.game_type = 1
GROUP BY tg.season_year
ORDER BY tg.season_year;

SELECT '' AS '';
SELECT '=== Per-Season Player Shooting Targets (MIN >= 10) ===' AS '';

SELECT
    season_year,
    COUNT(*) AS player_games,
    ROUND(SUM(fg2_made) * 100.0 / NULLIF(SUM(fg2_att), 0), 1) AS league_2fg_pct,
    ROUND(SUM(fg3_made) * 100.0 / NULLIF(SUM(fg3_att), 0), 1) AS league_3fg_pct,
    ROUND(SUM(ft_made) * 100.0 / NULLIF(SUM(ft_att), 0), 1)   AS league_ft_pct,
    ROUND(AVG(fg2_att + fg3_att), 1)                            AS avg_fga,
    ROUND(AVG(ft_att), 1)                                       AS avg_fta,
    ROUND(AVG(ast), 1)                                          AS avg_ast,
    ROUND(AVG(stl), 1)                                          AS avg_stl,
    ROUND(AVG(blk), 1)                                          AS avg_blk,
    ROUND(AVG(tov), 1)                                          AS avg_tov,
    ROUND(AVG(pf), 1)                                           AS avg_pf
FROM fact_player_game
WHERE game_type = 1 AND minutes >= 10
GROUP BY season_year
ORDER BY season_year;

SELECT '' AS '';
SELECT '=== Per-Season Rating Averages (from PLR snapshots) ===' AS '';

SELECT
    season_year,
    COUNT(*) AS players,
    ROUND(AVG(r_fgp), 1) AS avg_r_fgp,
    ROUND(AVG(r_3gp), 1) AS avg_r_3gp,
    ROUND(AVG(r_fga), 1) AS avg_r_fga,
    ROUND(AVG(r_3ga), 1) AS avg_r_3ga,
    ROUND(AVG(r_blk), 1) AS avg_r_blk,
    ROUND(AVG(r_tvr), 1) AS avg_r_tvr,
    ROUND(AVG(r_foul), 1) AS avg_r_foul,
    ROUND(AVG(tsi_sum), 1) AS avg_tsi
FROM (
    SELECT *
    FROM fact_plr_snapshots
    WHERE snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
    QUALIFY ROW_NUMBER() OVER (
        PARTITION BY pid, season_year
        ORDER BY CASE snapshot_phase
            WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2
            WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4
            WHEN 'heat-lb' THEN 5 END
    ) = 1
)
GROUP BY season_year
ORDER BY season_year;
