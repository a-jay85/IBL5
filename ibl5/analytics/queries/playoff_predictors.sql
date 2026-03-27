-- playoff_predictors.sql: Correlation between regular-season metrics and playoff success
-- Identifies which roster metrics best predict playoff outcomes.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/playoff_predictors.sql

.mode column
.headers on

SELECT '=== Playoff Outcome by Roster Metrics ===' AS '';

-- Average roster stats grouped by playoff outcome
SELECT
    playoff_outcome,
    COUNT(*) AS team_seasons,
    ROUND(AVG(win_pct), 3) AS avg_win_pct,
    ROUND(AVG(avg_tsi_sum), 1) AS avg_tsi,
    ROUND(AVG(star_count), 1) AS avg_stars,
    ROUND(AVG(quality_count), 1) AS avg_quality,
    ROUND(AVG(avg_age), 1) AS avg_age,
    ROUND(AVG(salary_total), 0) AS avg_salary
FROM agg_playoff_predictor
GROUP BY playoff_outcome
ORDER BY
    CASE playoff_outcome
        WHEN 'champion' THEN 1
        WHEN 'finals' THEN 2
        WHEN 'semis' THEN 3
        WHEN 'quarters' THEN 4
        WHEN 'first_round' THEN 5
        WHEN 'missed' THEN 6
    END;

SELECT '' AS '';
SELECT '=== Correlation: Win% vs Roster Metrics ===' AS '';

-- Pearson correlations between win% and various roster metrics
-- DuckDB supports corr() aggregate
SELECT
    ROUND(corr(win_pct, avg_tsi_sum), 3) AS "r(win%, avg_tsi)",
    ROUND(corr(win_pct, star_count), 3) AS "r(win%, stars)",
    ROUND(corr(win_pct, quality_count), 3) AS "r(win%, quality)",
    ROUND(corr(win_pct, avg_age), 3) AS "r(win%, avg_age)",
    ROUND(corr(win_pct, salary_total), 3) AS "r(win%, salary)",
    ROUND(corr(win_pct, roster_size), 3) AS "r(win%, roster_sz)"
FROM agg_playoff_predictor
WHERE win_pct IS NOT NULL;

SELECT '' AS '';
SELECT '=== Playoff Probability by Star Count ===' AS '';

-- What percentage of teams with N stars make playoffs?
SELECT
    star_count AS stars,
    COUNT(*) AS total,
    SUM(CASE WHEN made_playoffs = 1 THEN 1 ELSE 0 END) AS made_playoffs,
    ROUND(SUM(CASE WHEN made_playoffs = 1 THEN 1.0 ELSE 0 END) / COUNT(*) * 100, 1) AS "Playoff %",
    SUM(CASE WHEN won_championship = 1 THEN 1 ELSE 0 END) AS championships
FROM agg_playoff_predictor
GROUP BY star_count
ORDER BY star_count;

SELECT '' AS '';
SELECT '=== Minimum Win% to Make Playoffs by Season ===' AS '';

-- Playoff threshold over time
SELECT
    season_year,
    MIN(win_pct) AS min_playoff_win_pct,
    MAX(CASE WHEN playoff_outcome = 'missed' THEN win_pct END) AS max_miss_win_pct,
    COUNT(CASE WHEN made_playoffs = 1 THEN 1 END) AS playoff_teams
FROM agg_playoff_predictor
WHERE win_pct IS NOT NULL
GROUP BY season_year
ORDER BY season_year;

SELECT '' AS '';
SELECT '=== Championship Predictor: Combined Metrics ===' AS '';

-- What combination of metrics best predicts championships?
SELECT
    CASE WHEN won_championship = 1 THEN 'Champions' ELSE 'Non-Champions' END AS category,
    COUNT(*) AS n,
    ROUND(AVG(win_pct), 3) AS avg_win_pct,
    ROUND(AVG(avg_tsi_sum), 1) AS avg_tsi,
    ROUND(AVG(star_count), 1) AS avg_stars,
    ROUND(AVG(quality_count), 1) AS avg_quality_players,
    ROUND(AVG(avg_age), 1) AS avg_age,
    ROUND(AVG(salary_total), 0) AS avg_salary
FROM agg_playoff_predictor
WHERE made_playoffs = 1
GROUP BY category;
