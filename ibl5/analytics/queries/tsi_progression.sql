-- tsi_progression.sql: TSI band × development phase × rating deltas
-- Replicates the analysis from tsi-progression-analysis.md
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/tsi_progression.sql

.mode column
.headers on

SELECT '=== TSI Progression: Development Phase (far from peak) ===' AS '';
SELECT '    Controlled for starting FGP 40-55' AS '';

-- Development phase: far from peak (age_relative_to_peak <= -3)
-- Controlled for starting FGP range 40-55
SELECT
    p.tsi_band,
    ROUND(AVG(p.delta_r_2gp), 2) AS "Δ FGP/yr",
    ROUND(AVG(p.delta_r_ftp), 2) AS "Δ FTP/yr",
    ROUND(AVG(p.delta_r_ast), 2) AS "Δ AST/yr",
    ROUND(AVG(p.delta_r_stl), 2) AS "Δ STL/yr",
    COUNT(*) AS n
FROM agg_tsi_progression p
JOIN fact_player_season prev
    ON p.pid = prev.pid AND prev.season_year = p.season_year - 1
WHERE p.development_phase = 'far_from_peak'
    AND prev.r_2gp BETWEEN 40 AND 55
GROUP BY p.tsi_band
ORDER BY
    CASE p.tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== TSI Progression: Near Peak (±2 years) ===' AS '';
SELECT '    Controlled for starting FGP 40-55' AS '';

-- Near peak (age_relative_to_peak between -2 and 2)
SELECT
    p.tsi_band,
    ROUND(AVG(p.delta_r_2gp), 2) AS "Δ FGP/yr",
    ROUND(AVG(p.delta_r_ftp), 2) AS "Δ FTP/yr",
    COUNT(*) AS n
FROM agg_tsi_progression p
JOIN fact_player_season prev
    ON p.pid = prev.pid AND prev.season_year = p.season_year - 1
WHERE p.development_phase = 'near_peak'
    AND prev.r_2gp BETWEEN 40 AND 55
GROUP BY p.tsi_band
ORDER BY
    CASE p.tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== TSI Progression: Post Peak ===' AS '';

-- Post peak (age_relative_to_peak > 2)
SELECT
    tsi_band,
    ROUND(AVG(delta_r_2gp), 2) AS "Δ FGP/yr",
    ROUND(AVG(delta_r_ftp), 2) AS "Δ FTP/yr",
    ROUND(AVG(delta_r_ast), 2) AS "Δ AST/yr",
    ROUND(AVG(delta_ppg), 2) AS "Δ PPG/yr",
    COUNT(*) AS n
FROM agg_tsi_progression
WHERE development_phase = 'post_peak'
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== Peak Age by TSI Band ===' AS '';

SELECT
    tsi_band,
    ROUND(AVG(peak), 1) AS avg_peak_age,
    COUNT(DISTINCT pid) AS players
FROM agg_tsi_progression
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== All Rating Deltas by TSI Band (Development Phase) ===' AS '';

-- Comprehensive: all 20 rating column deltas during development
SELECT
    tsi_band,
    ROUND(AVG(delta_r_2ga), 1) AS "Δ2GA",
    ROUND(AVG(delta_r_2gp), 1) AS "ΔFGP",
    ROUND(AVG(delta_r_fta), 1) AS "ΔFTA",
    ROUND(AVG(delta_r_ftp), 1) AS "ΔFTP",
    ROUND(AVG(delta_r_3ga), 1) AS "Δ3GA",
    ROUND(AVG(delta_r_3gp), 1) AS "Δ3GP",
    ROUND(AVG(delta_r_orb), 1) AS "ΔORB",
    ROUND(AVG(delta_r_drb), 1) AS "ΔDRB",
    ROUND(AVG(delta_r_ast), 1) AS "ΔAST",
    ROUND(AVG(delta_r_stl), 1) AS "ΔSTL",
    ROUND(AVG(delta_r_blk), 1) AS "ΔBLK",
    ROUND(AVG(delta_r_tvr), 1) AS "ΔTVR",
    COUNT(*) AS n
FROM agg_tsi_progression
WHERE development_phase = 'far_from_peak'
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;
