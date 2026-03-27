-- player_development_curves.sql: Age-indexed career trajectories
-- Analyzes how performance changes with age and how peak timing relates to TSI.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/player_development_curves.sql

.mode column
.headers on

SELECT '=== Average PPG by Age and TSI Band ===' AS '';

-- Career trajectory: average PPG at each estimated age, grouped by TSI band
SELECT
    estimated_age AS age,
    tsi_band,
    ROUND(AVG(ppg), 1) AS avg_ppg,
    ROUND(AVG(rpg), 1) AS avg_rpg,
    ROUND(AVG(apg), 1) AS avg_apg,
    COUNT(*) AS n
FROM fact_player_season
WHERE estimated_age BETWEEN 19 AND 40
    AND games >= 10
    AND tsi_sum IS NOT NULL
GROUP BY estimated_age, tsi_band
HAVING COUNT(*) >= 5
ORDER BY estimated_age,
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== Peak PPG Season Timing by TSI Band ===' AS '';

-- When do players peak? Average age of best PPG season.
WITH peak_seasons AS (
    SELECT
        pid,
        season_year,
        ppg,
        estimated_age,
        tsi_band,
        tsi_sum,
        ROW_NUMBER() OVER (PARTITION BY pid ORDER BY ppg DESC NULLS LAST) AS rn
    FROM fact_player_season
    WHERE games >= 20 AND tsi_sum IS NOT NULL
)
SELECT
    tsi_band,
    ROUND(AVG(estimated_age), 1) AS avg_peak_age,
    ROUND(AVG(ppg), 1) AS avg_peak_ppg,
    COUNT(*) AS players
FROM peak_seasons
WHERE rn = 1
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== Career Longevity by TSI Band ===' AS '';

-- How many seasons do players play by TSI band?
SELECT
    p.tsi_band,
    ROUND(AVG(c.career_seasons), 1) AS avg_seasons,
    ROUND(AVG(c.career_ppg), 1) AS avg_career_ppg,
    ROUND(AVG(c.career_games), 0) AS avg_career_games,
    COUNT(*) AS players
FROM agg_player_career c
JOIN dim_player p ON c.pid = p.pid
WHERE p.tsi_sum > 0
GROUP BY p.tsi_band
ORDER BY
    CASE p.tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== Post-Peak Decline Rate by TSI Band ===' AS '';

-- How fast do players decline after peak?
SELECT
    tsi_band,
    age_relative_to_peak AS years_past_peak,
    ROUND(AVG(delta_ppg), 2) AS avg_ppg_change,
    ROUND(AVG(delta_r_2gp), 2) AS avg_fgp_change,
    COUNT(*) AS n
FROM agg_tsi_progression
WHERE development_phase = 'post_peak'
    AND age_relative_to_peak BETWEEN 3 AND 8
GROUP BY tsi_band, age_relative_to_peak
HAVING COUNT(*) >= 5
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END,
    years_past_peak;
