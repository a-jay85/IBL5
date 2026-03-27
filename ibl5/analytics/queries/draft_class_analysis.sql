-- draft_class_analysis.sql: Draft cohort career trajectory comparison
-- Uses first season year as proxy for draft year.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/draft_class_analysis.sql

.mode column
.headers on

SELECT '=== Draft Cohort Overview ===' AS '';

-- Summary by first-season year (approximates draft class)
SELECT
    first_season_year AS "Draft Year",
    cohort_size AS players,
    avg_career_ppg AS "Avg PPG",
    avg_career_seasons AS "Avg Seasons",
    avg_tsi_sum AS "Avg TSI",
    max_career_ppg AS "Best PPG"
FROM agg_draft_cohort
ORDER BY first_season_year;

SELECT '' AS '';
SELECT '=== Draft Class Quality Distribution ===' AS '';

-- How many elite/high/mid/low TSI players per class?
WITH player_class AS (
    SELECT
        c.pid,
        c.first_season AS draft_year,
        p.tsi_band,
        c.career_ppg,
        c.career_seasons
    FROM agg_player_career c
    JOIN dim_player p ON c.pid = p.pid
    WHERE p.tsi_sum > 0
)
SELECT
    draft_year,
    COUNT(*) AS total,
    COUNT(CASE WHEN tsi_band = 'elite' THEN 1 END) AS elite,
    COUNT(CASE WHEN tsi_band = 'high' THEN 1 END) AS high,
    COUNT(CASE WHEN tsi_band = 'mid' THEN 1 END) AS mid,
    COUNT(CASE WHEN tsi_band = 'low' THEN 1 END) AS low,
    ROUND(AVG(career_ppg), 1) AS avg_ppg
FROM player_class
GROUP BY draft_year
ORDER BY draft_year;

SELECT '' AS '';
SELECT '=== Best Players by Draft Class ===' AS '';

-- Top 3 career PPG players per draft class
WITH ranked AS (
    SELECT
        c.first_season AS draft_year,
        c.name,
        c.career_ppg,
        c.career_seasons,
        c.tsi_sum,
        ROW_NUMBER() OVER (PARTITION BY c.first_season ORDER BY c.career_ppg DESC NULLS LAST) AS rn
    FROM agg_player_career c
    WHERE c.career_games >= 20
)
SELECT
    draft_year,
    name,
    career_ppg AS ppg,
    career_seasons AS seasons,
    tsi_sum AS tsi
FROM ranked
WHERE rn <= 3
ORDER BY draft_year, rn;

SELECT '' AS '';
SELECT '=== Draft Class Career Trajectories ===' AS '';

-- Average PPG in year 1, 2, 3, etc. of career for each cohort
WITH career_year AS (
    SELECT
        f.pid,
        f.season_year,
        f.ppg,
        f.season_year - c.first_season + 1 AS career_year_num,
        c.first_season
    FROM fact_player_season f
    JOIN agg_player_career c ON f.pid = c.pid
    WHERE f.games >= 10
)
SELECT
    first_season AS "Draft Year",
    career_year_num AS "Career Yr",
    ROUND(AVG(ppg), 1) AS avg_ppg,
    COUNT(*) AS players
FROM career_year
WHERE career_year_num BETWEEN 1 AND 10
GROUP BY first_season, career_year_num
HAVING COUNT(*) >= 3
ORDER BY first_season, career_year_num;
