-- Add All-Star Weekend placeholder teams to ibl_team_info
-- These satisfy FK constraints for boxscore inserts (visitorTID/homeTID → ibl_team_info.teamid)

-- Rising Stars Game teams
INSERT INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (40, 'IBL', 'Rookies', UUID());

INSERT INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (41, 'IBL', 'Sophomores', UUID());

-- All-Star Game teams (display names overridden per year in boxscore rows)
INSERT INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (50, 'IBL', 'All-Star Away', UUID());

INSERT INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (51, 'IBL', 'All-Star Home', UUID());
