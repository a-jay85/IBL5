-- Migration 026: Create vw_playoff_series_results view
-- Derives playoff series results from individual box score games in ibl_box_scores_teams.
-- Replaces direct queries to the denormalized ibl_playoff_results table.
-- Correctly handles both best-of-5 (1989 R1) and best-of-7 series.

CREATE OR REPLACE VIEW vw_playoff_series_results AS
WITH playoff_games AS (
    -- Deduplicate: 2 rows per game in ibl_box_scores_teams, pick one per game
    SELECT
        Date,
        YEAR(Date) AS year,
        visitorTeamID,
        homeTeamID,
        gameOfThatDay,
        (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
         + COALESCE(visitorOTpoints, 0)) AS v_total,
        (homeQ1points + homeQ2points + homeQ3points + homeQ4points
         + COALESCE(homeOTpoints, 0)) AS h_total
    FROM ibl_box_scores_teams
    WHERE game_type = 2
    GROUP BY Date, visitorTeamID, homeTeamID, gameOfThatDay
),
game_results AS (
    SELECT *,
        CASE WHEN v_total > h_total THEN visitorTeamID ELSE homeTeamID END AS winner_tid,
        CASE WHEN v_total > h_total THEN homeTeamID ELSE visitorTeamID END AS loser_tid
    FROM playoff_games
),
-- Count wins per team within each series matchup
team_wins AS (
    SELECT
        year,
        LEAST(visitorTeamID, homeTeamID) AS team_a,
        GREATEST(visitorTeamID, homeTeamID) AS team_b,
        winner_tid,
        COUNT(*) AS wins,
        ROW_NUMBER() OVER (
            PARTITION BY year, LEAST(visitorTeamID, homeTeamID), GREATEST(visitorTeamID, homeTeamID)
            ORDER BY COUNT(*) DESC
        ) AS rn
    FROM game_results
    GROUP BY year, LEAST(visitorTeamID, homeTeamID), GREATEST(visitorTeamID, homeTeamID), winner_tid
),
-- Get series metadata (round via date ordering, total games)
series_meta AS (
    SELECT
        year,
        LEAST(visitorTeamID, homeTeamID) AS team_a,
        GREATEST(visitorTeamID, homeTeamID) AS team_b,
        COUNT(*) AS total_games,
        MIN(Date) AS series_start,
        DENSE_RANK() OVER (PARTITION BY year ORDER BY MIN(Date)) AS round
    FROM game_results
    GROUP BY year, LEAST(visitorTeamID, homeTeamID), GREATEST(visitorTeamID, homeTeamID)
)
SELECT
    sm.year,
    sm.round,
    tw.winner_tid,
    CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END AS loser_tid,
    w.team_name AS winner,
    l.team_name AS loser,
    tw.wins AS winner_games,
    sm.total_games - tw.wins AS loser_games,
    sm.total_games
FROM series_meta sm
JOIN team_wins tw
    ON tw.year = sm.year AND tw.team_a = sm.team_a AND tw.team_b = sm.team_b AND tw.rn = 1
JOIN ibl_team_info w ON w.teamid = tw.winner_tid
JOIN ibl_team_info l ON l.teamid = CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END
ORDER BY sm.year DESC, sm.round ASC;
