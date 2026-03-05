-- Add All-Star Weekend placeholder teams to ibl_team_info
-- These satisfy FK constraints for boxscore inserts (visitorTID/homeTID -> ibl_team_info.teamid)
-- Use INSERT IGNORE since teamid is the PK -- duplicates are silently skipped

-- Rising Stars Game teams
INSERT IGNORE INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (40, 'IBL', 'Rookies', UUID());

INSERT IGNORE INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (41, 'IBL', 'Sophomores', UUID());

-- All-Star Game teams (display names overridden per year in boxscore rows)
INSERT IGNORE INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (50, 'IBL', 'All-Star Away', UUID());

INSERT IGNORE INTO ibl_team_info (teamid, team_city, team_name, uuid)
VALUES (51, 'IBL', 'All-Star Home', UUID());
