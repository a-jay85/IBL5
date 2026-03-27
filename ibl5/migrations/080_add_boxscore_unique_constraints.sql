-- Clean up known duplicate in ibl_box_scores (keep newer row by higher id)
DELETE b1 FROM ibl_box_scores b1
  INNER JOIN ibl_box_scores b2
  ON b1.Date = b2.Date AND b1.pid = b2.pid
     AND b1.visitorTID = b2.visitorTID AND b1.homeTID = b2.homeTID
     AND b1.gameOfThatDay = b2.gameOfThatDay
     AND b1.id < b2.id
WHERE b1.pid IS NOT NULL;

-- Clean up known duplicate in ibl_box_scores_teams (keep newer row by higher id)
DELETE b1 FROM ibl_box_scores_teams b1
  INNER JOIN ibl_box_scores_teams b2
  ON b1.Date = b2.Date AND b1.visitorTeamID = b2.visitorTeamID
     AND b1.homeTeamID = b2.homeTeamID AND b1.gameOfThatDay = b2.gameOfThatDay
     AND b1.name = b2.name
     AND b1.id < b2.id;

-- Add natural unique key for player box scores
-- Includes teamID to handle All-Star games where a player can appear for both teams
ALTER TABLE ibl_box_scores
  ADD UNIQUE KEY uq_game_player (Date, pid, visitorTID, homeTID, gameOfThatDay, teamID);

-- Add natural unique key for team box scores
ALTER TABLE ibl_box_scores_teams
  ADD UNIQUE KEY uq_game_team (Date, visitorTeamID, homeTeamID, gameOfThatDay, name);
