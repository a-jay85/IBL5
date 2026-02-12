-- Migration 027: Replace ibl_team_win_loss and ibl_heat_win_loss tables with views
-- Derives win/loss records directly from ibl_box_scores_teams, eliminating the
-- manual sync from ibl_power that previously required extra writes after every sim.
-- Uses the same CTE deduplication pattern as vw_playoff_series_results (migration 026).

-- Drop the existing tables so we can create views with the same names
DROP TABLE IF EXISTS ibl_team_win_loss;
DROP TABLE IF EXISTS ibl_heat_win_loss;

-- View: ibl_team_win_loss (Regular Season)
-- Computes team win/loss records per season from box score data.
-- year = season ending year (matches old table convention)
-- currentname = current franchise name from ibl_team_info
-- namethatyear = historical name from ibl_franchise_seasons, falling back to current name
CREATE OR REPLACE VIEW ibl_team_win_loss AS
WITH unique_games AS (
    -- Deduplicate: 2 rows per game in ibl_box_scores_teams, pick one per game
    SELECT
        Date,
        visitorTeamID,
        homeTeamID,
        gameOfThatDay,
        (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
         + COALESCE(visitorOTpoints, 0)) AS visitor_total,
        (homeQ1points + homeQ2points + homeQ3points + homeQ4points
         + COALESCE(homeOTpoints, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 1   -- Regular Season only
    GROUP BY Date, visitorTeamID, homeTeamID, gameOfThatDay
),
team_games AS (
    -- Visitor team results
    SELECT visitorTeamID AS team_id, Date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    -- Home team results
    SELECT homeTeamID AS team_id, Date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
)
SELECT
    CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1
         ELSE YEAR(tg.Date) END                            AS year,
    ti.team_name                                            AS currentname,
    COALESCE(fs.team_name, ti.team_name)                    AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED)                          AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED)                          AS losses
FROM team_games tg
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (
        CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1
             ELSE YEAR(tg.Date) END
    )
GROUP BY
    tg.team_id,
    CASE WHEN MONTH(tg.Date) >= 10 THEN YEAR(tg.Date) + 1 ELSE YEAR(tg.Date) END,
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name);

-- View: ibl_heat_win_loss (HEAT Tournament)
-- Computes HEAT tournament win/loss records from box score data.
-- year = HEAT beginning year = YEAR(Date) for October dates
-- YEAR(Date) < 9000 excludes preseason placeholder dates (year 9998)
-- season_ending_year = YEAR(Date) + 1 since October is always month >= 10
CREATE OR REPLACE VIEW ibl_heat_win_loss AS
WITH unique_games AS (
    SELECT
        Date,
        visitorTeamID,
        homeTeamID,
        gameOfThatDay,
        (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
         + COALESCE(visitorOTpoints, 0)) AS visitor_total,
        (homeQ1points + homeQ2points + homeQ3points + homeQ4points
         + COALESCE(homeOTpoints, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 3          -- October games (HEAT / preseason)
      AND YEAR(Date) < 9000      -- Exclude preseason placeholder dates (year 9998)
    GROUP BY Date, visitorTeamID, homeTeamID, gameOfThatDay
),
team_games AS (
    SELECT visitorTeamID AS team_id, Date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    SELECT homeTeamID AS team_id, Date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
)
SELECT
    YEAR(tg.Date)                                           AS year,
    ti.team_name                                            AS currentname,
    COALESCE(fs.team_name, ti.team_name)                    AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED)                          AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED)                          AS losses
FROM team_games tg
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (YEAR(tg.Date) + 1)
GROUP BY
    tg.team_id,
    YEAR(tg.Date),
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name);
