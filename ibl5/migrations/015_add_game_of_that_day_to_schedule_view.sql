-- ---------------------------------------------------------------------------
-- Migration 015: Add gameOfThatDay to vw_schedule_upcoming
-- ---------------------------------------------------------------------------
-- Adds gameOfThatDay from ibl_box_scores_teams to the schedule view so the
-- API can expose it for IBL6 SvelteKit box score URL construction.
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_schedule_upcoming;
CREATE SQL SECURITY INVOKER VIEW vw_schedule_upcoming AS
SELECT
  sch.uuid AS game_uuid,
  sch.SchedID AS schedule_id,
  sch.Year AS season_year,
  sch.Date AS game_date,
  sch.BoxID AS box_score_id,
  COALESCE(bst.gameOfThatDay, 0) AS game_of_that_day,
  -- Visitor Team
  tv.uuid AS visitor_uuid,
  tv.teamid AS visitor_team_id,
  tv.team_city AS visitor_city,
  tv.team_name AS visitor_name,
  CONCAT(tv.team_city, ' ', tv.team_name) AS visitor_full_name,
  sch.VScore AS visitor_score,
  -- Home Team
  th.uuid AS home_uuid,
  th.teamid AS home_team_id,
  th.team_city AS home_city,
  th.team_name AS home_name,
  CONCAT(th.team_city, ' ', th.team_name) AS home_full_name,
  sch.HScore AS home_score,
  -- Game Status
  CASE
    WHEN sch.VScore = 0 AND sch.HScore = 0 THEN 'scheduled'
    ELSE 'completed'
  END AS game_status,
  -- Timestamps
  sch.created_at,
  sch.updated_at
FROM ibl_schedule sch
INNER JOIN ibl_team_info tv ON sch.Visitor = tv.teamid
INNER JOIN ibl_team_info th ON sch.Home = th.teamid
LEFT JOIN (
    SELECT Date, visitorTeamID, homeTeamID, MIN(gameOfThatDay) AS gameOfThatDay
    FROM ibl_box_scores_teams
    GROUP BY Date, visitorTeamID, homeTeamID
) bst ON bst.Date = sch.Date AND bst.visitorTeamID = sch.Visitor AND bst.homeTeamID = sch.Home;
