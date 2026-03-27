-- salary_efficiency.sql: PPG-per-salary-dollar by position and TSI band
-- Identifies salary inefficiencies and value contracts.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/salary_efficiency.sql

.mode column
.headers on

SELECT '=== Salary Efficiency by TSI Band ===' AS '';

-- PPG per $1000 salary by TSI band
SELECT
    tsi_band,
    ROUND(AVG(ppg), 1) AS avg_ppg,
    ROUND(AVG(salary), 0) AS avg_salary,
    CASE WHEN AVG(salary) > 0
        THEN ROUND(AVG(ppg) / (AVG(salary) / 1000.0), 2)
    END AS ppg_per_1k,
    COUNT(*) AS player_seasons
FROM fact_player_season
WHERE games >= 20 AND salary > 0 AND tsi_sum IS NOT NULL
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== QA per Salary Dollar by TSI Band ===' AS '';

-- Quality Adjusted metric is a better all-around measure
SELECT
    tsi_band,
    ROUND(AVG(qa), 1) AS avg_qa,
    ROUND(AVG(salary), 0) AS avg_salary,
    CASE WHEN AVG(salary) > 0
        THEN ROUND(AVG(qa) / (AVG(salary) / 1000.0), 2)
    END AS qa_per_1k,
    COUNT(*) AS player_seasons
FROM fact_player_season
WHERE games >= 20 AND salary > 0 AND tsi_sum IS NOT NULL
GROUP BY tsi_band
ORDER BY
    CASE tsi_band WHEN 'low' THEN 1 WHEN 'mid' THEN 2 WHEN 'high' THEN 3 WHEN 'elite' THEN 4 END;

SELECT '' AS '';
SELECT '=== Best Value Contracts (min 40 games, salary > 0) ===' AS '';

-- Players who produced the most PPG relative to salary
SELECT
    name,
    season_year,
    team,
    ppg,
    salary,
    ROUND(ppg / (salary / 1000.0), 2) AS ppg_per_1k,
    tsi_band,
    tsi_sum
FROM fact_player_season
WHERE games >= 40 AND salary > 0
ORDER BY ppg_per_1k DESC
LIMIT 25;

SELECT '' AS '';
SELECT '=== Worst Value Contracts (min 20 games, salary >= 2000) ===' AS '';

-- Highest-paid underperformers
SELECT
    name,
    season_year,
    team,
    ppg,
    salary,
    ROUND(ppg / (salary / 1000.0), 2) AS ppg_per_1k,
    tsi_band,
    tsi_sum
FROM fact_player_season
WHERE games >= 20 AND salary >= 2000
ORDER BY ppg_per_1k ASC
LIMIT 25;

SELECT '' AS '';
SELECT '=== Salary Distribution by Season ===' AS '';

-- How has salary spending evolved?
SELECT
    season_year,
    ROUND(AVG(salary), 0) AS avg_salary,
    ROUND(MEDIAN(salary), 0) AS median_salary,
    MAX(salary) AS max_salary,
    COUNT(*) AS players
FROM fact_player_season
WHERE salary > 0 AND games > 0
GROUP BY season_year
ORDER BY season_year;
