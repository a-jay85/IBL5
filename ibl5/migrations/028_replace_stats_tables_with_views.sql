-- Migration 028: Replace 9 stats tables with database views over box scores
--
-- Converts ibl_season_career_avgs, ibl_playoff_career_avgs, ibl_heat_career_avgs,
-- ibl_playoff_career_totals, ibl_heat_career_totals, ibl_playoff_stats,
-- ibl_heat_stats, ibl_team_offense_stats, and ibl_team_defense_stats
-- from manually-populated tables to auto-computed views over ibl_box_scores.

-- ============================================================
-- Phase 1: Add generated columns and indexes for view performance
-- ============================================================

-- Add season_year generated column to team box scores (matches ibl_box_scores)
-- Uses a procedure to check if column already exists (idempotent)
DROP PROCEDURE IF EXISTS add_season_year_if_missing;
DELIMITER //
CREATE PROCEDURE add_season_year_if_missing()
BEGIN
  IF NOT EXISTS (
    SELECT 1 FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'ibl_box_scores_teams'
      AND COLUMN_NAME = 'season_year'
  ) THEN
    ALTER TABLE ibl_box_scores_teams ADD COLUMN season_year SMALLINT UNSIGNED
      GENERATED ALWAYS AS (
        CASE WHEN YEAR(`Date`) = 0 THEN 0
             WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
             ELSE YEAR(`Date`) END
      ) STORED AFTER game_type;
  END IF;
END //
DELIMITER ;
CALL add_season_year_if_missing();
DROP PROCEDURE IF EXISTS add_season_year_if_missing;

-- Career aggregation by player within a game type (IF NOT EXISTS is supported for indexes)
ALTER TABLE ibl_box_scores ADD INDEX IF NOT EXISTS idx_gt_pid (game_type, pid);

-- Team box score aggregation
ALTER TABLE ibl_box_scores_teams ADD INDEX IF NOT EXISTS idx_gt_name_season (game_type, name, season_year);

-- ============================================================
-- Phase 2a: Player career averages views (3 views)
-- ============================================================

DROP VIEW IF EXISTS ibl_season_career_avgs;
DROP TABLE IF EXISTS ibl_season_career_avgs;

CREATE VIEW ibl_season_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.gameMIN), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game2GA + bs.game3GA), 2) AS fga,
  CASE WHEN SUM(bs.game2GA + bs.game3GA) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game2GA + bs.game3GA), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.gameFTM), 2) AS ftm,
  ROUND(AVG(bs.gameFTA), 2) AS fta,
  CASE WHEN SUM(bs.gameFTA) > 0
    THEN ROUND(SUM(bs.gameFTM) / SUM(bs.gameFTA), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game3GM), 2) AS tgm,
  ROUND(AVG(bs.game3GA), 2) AS tga,
  CASE WHEN SUM(bs.game3GA) > 0
    THEN ROUND(SUM(bs.game3GM) / SUM(bs.game3GA), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.gameORB), 2) AS orb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.gameAST), 2) AS ast,
  ROUND(AVG(bs.gameSTL), 2) AS stl,
  ROUND(AVG(bs.gameTOV), 2) AS tvr,
  ROUND(AVG(bs.gameBLK), 2) AS blk,
  ROUND(AVG(bs.gamePF), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 1
GROUP BY bs.pid, p.name, p.retired;

-- ---

DROP VIEW IF EXISTS ibl_playoff_career_avgs;
DROP TABLE IF EXISTS ibl_playoff_career_avgs;

CREATE VIEW ibl_playoff_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.gameMIN), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game2GA + bs.game3GA), 2) AS fga,
  CASE WHEN SUM(bs.game2GA + bs.game3GA) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game2GA + bs.game3GA), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.gameFTM), 2) AS ftm,
  ROUND(AVG(bs.gameFTA), 2) AS fta,
  CASE WHEN SUM(bs.gameFTA) > 0
    THEN ROUND(SUM(bs.gameFTM) / SUM(bs.gameFTA), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game3GM), 2) AS tgm,
  ROUND(AVG(bs.game3GA), 2) AS tga,
  CASE WHEN SUM(bs.game3GA) > 0
    THEN ROUND(SUM(bs.game3GM) / SUM(bs.game3GA), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.gameORB), 2) AS orb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.gameAST), 2) AS ast,
  ROUND(AVG(bs.gameSTL), 2) AS stl,
  ROUND(AVG(bs.gameTOV), 2) AS tvr,
  ROUND(AVG(bs.gameBLK), 2) AS blk,
  ROUND(AVG(bs.gamePF), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 2
GROUP BY bs.pid, p.name, p.retired;

-- ---

DROP VIEW IF EXISTS ibl_heat_career_avgs;
DROP TABLE IF EXISTS ibl_heat_career_avgs;

CREATE VIEW ibl_heat_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.gameMIN), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game2GA + bs.game3GA), 2) AS fga,
  CASE WHEN SUM(bs.game2GA + bs.game3GA) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game2GA + bs.game3GA), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.gameFTM), 2) AS ftm,
  ROUND(AVG(bs.gameFTA), 2) AS fta,
  CASE WHEN SUM(bs.gameFTA) > 0
    THEN ROUND(SUM(bs.gameFTM) / SUM(bs.gameFTA), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game3GM), 2) AS tgm,
  ROUND(AVG(bs.game3GA), 2) AS tga,
  CASE WHEN SUM(bs.game3GA) > 0
    THEN ROUND(SUM(bs.game3GM) / SUM(bs.game3GA), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.gameORB), 2) AS orb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.gameAST), 2) AS ast,
  ROUND(AVG(bs.gameSTL), 2) AS stl,
  ROUND(AVG(bs.gameTOV), 2) AS tvr,
  ROUND(AVG(bs.gameBLK), 2) AS blk,
  ROUND(AVG(bs.gamePF), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 3
GROUP BY bs.pid, p.name, p.retired;

-- ============================================================
-- Phase 2b: Player career totals views (2 views)
-- ============================================================

DROP VIEW IF EXISTS ibl_playoff_career_totals;
DROP TABLE IF EXISTS ibl_playoff_career_totals;

CREATE VIEW ibl_playoff_career_totals AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 2
GROUP BY bs.pid, p.name, p.retired;

-- ---

DROP VIEW IF EXISTS ibl_heat_career_totals;
DROP TABLE IF EXISTS ibl_heat_career_totals;

CREATE VIEW ibl_heat_career_totals AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 3
GROUP BY bs.pid, p.name, p.retired;

-- ============================================================
-- Phase 2c: Per-season stats views (2 views)
-- ============================================================

DROP VIEW IF EXISTS ibl_playoff_stats;
DROP TABLE IF EXISTS ibl_playoff_stats;

CREATE VIEW ibl_playoff_stats AS
SELECT
  bs.season_year AS year,
  MIN(bs.pos) AS pos,
  bs.pid,
  p.name,
  fs.team_name AS team,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
JOIN ibl_franchise_seasons fs ON bs.teamID = fs.franchise_id AND bs.season_year = fs.season_ending_year
WHERE bs.game_type = 2
GROUP BY bs.pid, p.name, bs.season_year, fs.team_name;

-- ---

DROP VIEW IF EXISTS ibl_heat_stats;
DROP TABLE IF EXISTS ibl_heat_stats;

CREATE VIEW ibl_heat_stats AS
SELECT
  bs.season_year AS year,
  MIN(bs.pos) AS pos,
  bs.pid,
  p.name,
  fs.team_name AS team,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
JOIN ibl_franchise_seasons fs ON bs.teamID = fs.franchise_id AND bs.season_year = fs.season_ending_year
WHERE bs.game_type = 3
GROUP BY bs.pid, p.name, bs.season_year, fs.team_name;

-- ============================================================
-- Phase 2d: Team offense stats view
-- ============================================================

DROP VIEW IF EXISTS ibl_team_offense_stats;
DROP TABLE IF EXISTS ibl_team_offense_stats;

CREATE VIEW ibl_team_offense_stats AS
SELECT
  fs.franchise_id AS teamID,
  fs.team_name AS name,
  bst.season_year,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bst.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bst.game2GM + bst.game3GM) AS SIGNED) AS fgm,
  CAST(SUM(bst.game2GA + bst.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bst.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bst.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bst.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bst.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bst.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bst.gameORB + bst.gameDRB) AS SIGNED) AS reb,
  CAST(SUM(bst.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bst.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bst.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bst.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bst.gamePF) AS SIGNED) AS pf
FROM ibl_box_scores_teams bst
JOIN ibl_franchise_seasons fs
  ON fs.team_name = bst.name AND fs.season_ending_year = bst.season_year
WHERE bst.game_type = 1
GROUP BY fs.franchise_id, fs.team_name, bst.season_year;

-- ============================================================
-- Phase 2e: Team defense stats view
-- ============================================================

DROP VIEW IF EXISTS ibl_team_defense_stats;
DROP TABLE IF EXISTS ibl_team_defense_stats;

CREATE VIEW ibl_team_defense_stats AS
SELECT
  fs.franchise_id AS teamID,
  fs.team_name AS name,
  my.season_year,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(opp.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(opp.game2GM + opp.game3GM) AS SIGNED) AS fgm,
  CAST(SUM(opp.game2GA + opp.game3GA) AS SIGNED) AS fga,
  CAST(SUM(opp.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(opp.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(opp.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(opp.game3GA) AS SIGNED) AS tga,
  CAST(SUM(opp.gameORB) AS SIGNED) AS orb,
  CAST(SUM(opp.gameORB + opp.gameDRB) AS SIGNED) AS reb,
  CAST(SUM(opp.gameAST) AS SIGNED) AS ast,
  CAST(SUM(opp.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(opp.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(opp.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(opp.gamePF) AS SIGNED) AS pf
FROM ibl_box_scores_teams my
JOIN ibl_box_scores_teams opp
  ON my.Date = opp.Date
  AND my.visitorTeamID = opp.visitorTeamID
  AND my.homeTeamID = opp.homeTeamID
  AND my.gameOfThatDay = opp.gameOfThatDay
  AND my.name <> opp.name
JOIN ibl_franchise_seasons fs
  ON fs.team_name = my.name AND fs.season_ending_year = my.season_year
WHERE my.game_type = 1
GROUP BY fs.franchise_id, fs.team_name, my.season_year;
