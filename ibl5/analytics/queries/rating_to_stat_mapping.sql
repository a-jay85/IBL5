-- rating_to_stat_mapping.sql: Rating-to-stat correlations by era
-- Tests whether r_fgp -> 2FG%, r_ast -> AST etc. relationships are stable
-- across dramatically different rating distributions (r_fga drifted +27.6 from 1989-2007).
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/rating_to_stat_mapping.sql

.mode column
.headers on

SELECT '=== Rating -> Stat Correlations (Pearson r, Regular Season, MIN >= 10) ===' AS '';

SELECT
    CASE
        WHEN season_year BETWEEN 1989 AND 1995 THEN '1989-1995'
        WHEN season_year BETWEEN 1996 AND 2001 THEN '1996-2001'
        WHEN season_year BETWEEN 2002 AND 2007 THEN '2002-2007'
    END AS era,
    COUNT(*) AS n,
    ROUND(CORR(r_fgp, CASE WHEN fg2_att > 0 THEN fg2_made * 100.0 / fg2_att END), 3)
        AS "r_fgp->2FG%",
    ROUND(CORR(r_fga, fg2_att + fg3_att), 3)  AS "r_fga->FGA",
    ROUND(CORR(r_3gp, CASE WHEN fg3_att > 0 THEN fg3_made * 100.0 / fg3_att END), 3)
        AS "r_3gp->3FG%",
    ROUND(CORR(r_ast, ast), 3)                 AS "r_ast->AST",
    ROUND(CORR(r_orb, orb), 3)                 AS "r_orb->ORB",
    ROUND(CORR(r_drb, drb), 3)                 AS "r_drb->DRB",
    ROUND(CORR(r_stl, stl), 3)                 AS "r_stl->STL",
    ROUND(CORR(r_blk, blk), 3)                 AS "r_blk->BLK",
    ROUND(CORR(r_tvr, tov), 3)                 AS "r_tvr->TOV"
FROM fact_player_sim
WHERE game_type = 1 AND minutes >= 10
GROUP BY era
ORDER BY era;

SELECT '' AS '';
SELECT '=== Per-Season Calibration Targets ===' AS '';

SELECT
    season_year,
    COUNT(DISTINCT box_score_id) AS games,
    ROUND(AVG(points), 1)       AS avg_pts,
    ROUND(AVG(CASE WHEN fg2_att > 0 THEN fg2_made * 100.0 / fg2_att END), 1) AS avg_2fg_pct,
    ROUND(AVG(CASE WHEN fg3_att > 0 THEN fg3_made * 100.0 / fg3_att END), 1) AS avg_3fg_pct,
    ROUND(AVG(CASE WHEN ft_att > 0 THEN ft_made * 100.0 / ft_att END), 1)    AS avg_ft_pct,
    ROUND(AVG(r_fgp), 1)       AS avg_r_fgp,
    ROUND(AVG(r_fga), 1)       AS avg_r_fga,
    ROUND(AVG(r_3gp), 1)       AS avg_r_3gp
FROM fact_player_sim
WHERE game_type = 1 AND minutes >= 10
GROUP BY season_year
ORDER BY season_year;
