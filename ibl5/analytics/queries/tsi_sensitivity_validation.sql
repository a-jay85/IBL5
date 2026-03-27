-- tsi_sensitivity_validation.sql: Cross-validate TSI progression analysis
-- Finding 5: TSI sensitivity is rating-specific (FTP 13.3, TVR 12.5, BLK 1.2).
-- Original analysis used current ibl_plr TSI as proxy. This query uses exact
-- per-season TSI from PLR snapshots for higher precision.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/tsi_sensitivity_validation.sql

.mode column
.headers on

SELECT '=== TSI Sensitivity by Rating at Peak (YTP = 0, FGP 40-55) ===' AS '';
SELECT '    Using exact per-season TSI from PLR snapshots' AS '';

-- Year-over-year deltas with exact TSI from snapshots
WITH player_year AS (
    SELECT
        f.pid,
        f.season_year,
        f.r_2gp AS fgp, f.r_ftp AS ftp, f.r_tvr AS tvr,
        f.r_3gp AS tgp, f.r_ast AS ast, f.r_stl AS stl,
        f.r_blk AS blk, f.r_orb AS orb, f.r_drb AS drb,
        f.r_3ga AS tga, f.r_2ga AS fga, f.r_fta AS fta,
        COALESCE(s.tsi_sum, p.tsi_sum) AS tsi_sum,
        COALESCE(s.age, p.age - ((SELECT MAX(season_year) FROM dim_season) - f.season_year)) AS est_age,
        COALESCE(s.peak, p.peak) AS peak
    FROM fact_player_season f
    LEFT JOIN dim_player p ON f.pid = p.pid
    LEFT JOIN fact_plr_snapshots s ON f.pid = s.pid AND f.season_year = s.season_year
        AND s.snapshot_phase = 'heat-end'
    WHERE f.games > 0 AND f.r_2gp BETWEEN 40 AND 55
),
deltas AS (
    SELECT
        curr.pid, curr.season_year,
        curr.tsi_sum,
        curr.est_age - curr.peak AS ytp,
        CASE WHEN curr.tsi_sum <= 9 THEN 'low (3-9)' ELSE 'high (10-15)' END AS tsi_group,
        curr.fgp - prev.fgp AS d_fgp,
        curr.ftp - prev.ftp AS d_ftp,
        curr.tvr - prev.tvr AS d_tvr,
        curr.tgp - prev.tgp AS d_tgp,
        curr.ast - prev.ast AS d_ast,
        curr.stl - prev.stl AS d_stl,
        curr.blk - prev.blk AS d_blk,
        curr.orb - prev.orb AS d_orb,
        curr.drb - prev.drb AS d_drb,
        curr.fga - prev.fga AS d_fga,
        curr.tga - prev.tga AS d_tga,
        curr.fta - prev.fta AS d_fta
    FROM player_year curr
    JOIN player_year prev ON curr.pid = prev.pid AND curr.season_year = prev.season_year + 1
    WHERE curr.peak IS NOT NULL AND curr.peak > 0
)
SELECT
    tsi_group,
    COUNT(*) AS n,
    ROUND(AVG(d_fgp), 2) AS d_FGP,
    ROUND(AVG(d_ftp), 2) AS d_FTP,
    ROUND(AVG(d_tvr), 2) AS d_TVR,
    ROUND(AVG(d_tgp), 2) AS d_3GP,
    ROUND(AVG(d_ast), 2) AS d_AST,
    ROUND(AVG(d_stl), 2) AS d_STL,
    ROUND(AVG(d_blk), 2) AS d_BLK,
    ROUND(AVG(d_orb), 2) AS d_ORB,
    ROUND(AVG(d_drb), 2) AS d_DRB,
    ROUND(AVG(d_fga), 2) AS d_2GA,
    ROUND(AVG(d_tga), 2) AS d_3GA,
    ROUND(AVG(d_fta), 2) AS d_FTA
FROM deltas
WHERE ytp BETWEEN -2 AND 2
GROUP BY tsi_group
ORDER BY tsi_group;

SELECT '' AS '';
SELECT '=== TSI Spread (high - low) at Peak — Expected Hierarchy ===' AS '';
SELECT '    Finding 5: FTP(13.3) > TVR(12.5) > FGP(10.0) > ... > BLK(1.2)' AS '';

WITH deltas AS (
    SELECT
        curr.pid, curr.season_year,
        COALESCE(s.tsi_sum, p.tsi_sum) AS tsi_sum,
        COALESCE(s.age, p.age - ((SELECT MAX(season_year) FROM dim_season) - curr.season_year))
            - COALESCE(s.peak, p.peak) AS ytp,
        curr.r_2gp - prev.r_2gp AS d_fgp,
        curr.r_ftp - prev.r_ftp AS d_ftp,
        curr.r_tvr - prev.r_tvr AS d_tvr,
        curr.r_3gp - prev.r_3gp AS d_tgp,
        curr.r_ast - prev.r_ast AS d_ast,
        curr.r_stl - prev.r_stl AS d_stl,
        curr.r_blk - prev.r_blk AS d_blk,
        curr.r_orb - prev.r_orb AS d_orb,
        curr.r_drb - prev.r_drb AS d_drb
    FROM fact_player_season curr
    JOIN fact_player_season prev ON curr.pid = prev.pid AND curr.season_year = prev.season_year + 1
    LEFT JOIN dim_player p ON curr.pid = p.pid
    LEFT JOIN fact_plr_snapshots s ON curr.pid = s.pid AND curr.season_year = s.season_year
        AND s.snapshot_phase = 'heat-end'
    WHERE curr.games > 0 AND prev.games > 0
      AND curr.r_2gp BETWEEN 40 AND 55
      AND COALESCE(s.peak, p.peak) IS NOT NULL AND COALESCE(s.peak, p.peak) > 0
),
by_group AS (
    SELECT
        CASE WHEN tsi_sum <= 9 THEN 'low' ELSE 'high' END AS grp,
        AVG(d_fgp) AS fgp, AVG(d_ftp) AS ftp, AVG(d_tvr) AS tvr,
        AVG(d_tgp) AS tgp, AVG(d_ast) AS ast, AVG(d_stl) AS stl,
        AVG(d_blk) AS blk, AVG(d_orb) AS orb, AVG(d_drb) AS drb
    FROM deltas WHERE ytp BETWEEN -2 AND 2
    GROUP BY grp
)
SELECT
    'spread' AS metric,
    ROUND(h.fgp - l.fgp, 2) AS FGP,
    ROUND(h.ftp - l.ftp, 2) AS FTP,
    ROUND(h.tvr - l.tvr, 2) AS TVR,
    ROUND(h.tgp - l.tgp, 2) AS "3GP",
    ROUND(h.ast - l.ast, 2) AS AST,
    ROUND(h.stl - l.stl, 2) AS STL,
    ROUND(h.blk - l.blk, 2) AS BLK,
    ROUND(h.orb - l.orb, 2) AS ORB,
    ROUND(h.drb - l.drb, 2) AS DRB
FROM by_group h, by_group l
WHERE h.grp = 'high' AND l.grp = 'low';
