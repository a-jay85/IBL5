-- clutch_playoff_gradient.sql: Clutch rating x game type stat differentials
-- Uses exact game-time ratings from canonical HEAT-phase PLR snapshots (not current ibl_plr proxy).
-- Plan reference: Clutch=1 scoring -6.7% in playoffs, Clutch=3 +3.3%.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/clutch_playoff_gradient.sql

.mode column
.headers on

SELECT '=== Clutch Rating: Regular Season vs Playoff Performance ===' AS '';
SELECT '    Exact game-time ratings from PLR snapshots' AS '';

WITH phase_stats AS (
    SELECT
        clutch,
        game_type_label,
        COUNT(DISTINCT pid || '-' || season_year) AS player_seasons,
        COUNT(*) AS games,
        ROUND(AVG(points), 2)                   AS avg_pts,
        ROUND(AVG(ast), 2)                      AS avg_ast,
        ROUND(AVG(stl), 2)                      AS avg_stl,
        ROUND(AVG(blk), 2)                      AS avg_blk,
        ROUND(AVG(orb), 2)                      AS avg_orb,
        ROUND(AVG(pf), 2)                       AS avg_pf,
        ROUND(AVG(tov), 2)                      AS avg_tov,
        ROUND(SUM(fg2_made + fg3_made) * 100.0 / NULLIF(SUM(fg2_att + fg3_att), 0), 1)
                                                AS fg_pct
    FROM fact_player_sim
    WHERE clutch IS NOT NULL AND minutes >= 10
    GROUP BY clutch, game_type_label
)
SELECT * FROM phase_stats
WHERE game_type_label IN ('regular', 'playoffs')
ORDER BY clutch, game_type_label;

SELECT '' AS '';
SELECT '=== Clutch Playoff Differential (playoff - regular) ===' AS '';

WITH phase_stats AS (
    SELECT
        clutch,
        game_type,
        ROUND(AVG(points), 3)                   AS avg_pts,
        ROUND(SUM(fg2_made + fg3_made) * 100.0 / NULLIF(SUM(fg2_att + fg3_att), 0), 2)
                                                AS fg_pct,
        ROUND(AVG(ast), 3)                      AS avg_ast,
        ROUND(AVG(stl), 3)                      AS avg_stl,
        ROUND(AVG(blk), 3)                      AS avg_blk,
        COUNT(*) AS games
    FROM fact_player_sim
    WHERE clutch IS NOT NULL AND minutes >= 10
    GROUP BY clutch, game_type
)
SELECT
    r.clutch,
    r.games AS reg_games,
    p.games AS playoff_games,
    ROUND(p.avg_pts - r.avg_pts, 2)   AS delta_pts,
    ROUND(p.fg_pct - r.fg_pct, 2)     AS delta_fg_pct,
    ROUND(p.avg_ast - r.avg_ast, 2)   AS delta_ast,
    ROUND(p.avg_stl - r.avg_stl, 2)   AS delta_stl,
    ROUND(p.avg_blk - r.avg_blk, 2)   AS delta_blk
FROM phase_stats r
JOIN phase_stats p ON r.clutch = p.clutch
WHERE r.game_type = 1 AND p.game_type = 2
ORDER BY r.clutch;
