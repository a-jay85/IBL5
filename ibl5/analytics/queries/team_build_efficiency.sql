-- team_build_efficiency.sql: Roster composition metrics correlated with win%
-- Analyzes what makes winning teams — star count, depth, age, salary.
--
-- Usage: duckdb data/ibl_analytics.duckdb < queries/team_build_efficiency.sql

.mode column
.headers on

SELECT '=== Team Build Profiles by Win Tier ===' AS '';

-- Segment teams by win percentage and compare roster composition
SELECT
    CASE
        WHEN win_pct >= 0.700 THEN 'elite (70%+)'
        WHEN win_pct >= 0.550 THEN 'good (55-69%)'
        WHEN win_pct >= 0.400 THEN 'mediocre (40-54%)'
        ELSE 'poor (<40%)'
    END AS win_tier,
    COUNT(*) AS team_seasons,
    ROUND(AVG(avg_tsi_sum), 1) AS avg_tsi,
    ROUND(AVG(star_count), 1) AS avg_stars,
    ROUND(AVG(quality_count), 1) AS avg_quality,
    ROUND(AVG(roster_size), 1) AS avg_roster,
    ROUND(AVG(avg_age), 1) AS avg_age,
    ROUND(AVG(salary_total), 0) AS avg_salary,
    ROUND(AVG(win_pct), 3) AS avg_win_pct
FROM agg_team_season_roster
WHERE win_pct IS NOT NULL
GROUP BY win_tier
ORDER BY avg_win_pct DESC;

SELECT '' AS '';
SELECT '=== Star Count vs Win% ===' AS '';

-- Direct star count to win percentage relationship
SELECT
    star_count AS stars,
    COUNT(*) AS team_seasons,
    ROUND(AVG(win_pct), 3) AS avg_win_pct,
    ROUND(AVG(wins), 1) AS avg_wins,
    SUM(CASE WHEN made_playoffs = 1 THEN 1 ELSE 0 END) AS playoff_teams,
    SUM(CASE WHEN won_championship = 1 THEN 1 ELSE 0 END) AS champions
FROM agg_team_season_roster
WHERE win_pct IS NOT NULL
GROUP BY star_count
ORDER BY star_count;

SELECT '' AS '';
SELECT '=== Average TSI vs Win% ===' AS '';

-- Roster average TSI grouped into bins
SELECT
    CASE
        WHEN avg_tsi_sum >= 12 THEN '12+ (elite)'
        WHEN avg_tsi_sum >= 10 THEN '10-11.9'
        WHEN avg_tsi_sum >= 8  THEN '8-9.9'
        ELSE '<8'
    END AS tsi_tier,
    COUNT(*) AS team_seasons,
    ROUND(AVG(win_pct), 3) AS avg_win_pct,
    ROUND(AVG(star_count), 1) AS avg_stars,
    SUM(CASE WHEN won_championship = 1 THEN 1 ELSE 0 END) AS champions
FROM agg_team_season_roster
WHERE win_pct IS NOT NULL
GROUP BY tsi_tier
ORDER BY avg_win_pct DESC;

SELECT '' AS '';
SELECT '=== Championship Team Profiles ===' AS '';

-- What do championship-winning rosters look like?
SELECT
    season_year,
    team_name,
    wins || '-' || losses AS record,
    ROUND(win_pct, 3) AS "Win%",
    roster_size,
    ROUND(avg_tsi_sum, 1) AS avg_tsi,
    star_count AS stars,
    quality_count AS quality,
    ROUND(avg_age, 1) AS avg_age,
    salary_total AS salary
FROM agg_team_season_roster
WHERE won_championship = 1
ORDER BY season_year;

SELECT '' AS '';
SELECT '=== Roster Age Distribution vs Success ===' AS '';

-- Young vs old teams
SELECT
    CASE
        WHEN avg_age < 26 THEN 'young (<26)'
        WHEN avg_age < 29 THEN 'prime (26-28)'
        WHEN avg_age < 32 THEN 'veteran (29-31)'
        ELSE 'old (32+)'
    END AS age_tier,
    COUNT(*) AS team_seasons,
    ROUND(AVG(win_pct), 3) AS avg_win_pct,
    ROUND(AVG(star_count), 1) AS avg_stars,
    SUM(CASE WHEN made_playoffs = 1 THEN 1 ELSE 0 END) AS playoff_teams
FROM agg_team_season_roster
WHERE win_pct IS NOT NULL AND avg_age IS NOT NULL
GROUP BY age_tier
ORDER BY avg_win_pct DESC;
