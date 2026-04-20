-- Migration: Add defensive rebounds (drb) column to career leaderboard views
-- Source: ibl_box_scores.gameDRB — inserted between orb and reb in each view

-- Season Career Averages (game_type = 1)
CREATE OR REPLACE VIEW ibl_season_career_avgs AS
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
  ROUND(AVG(bs.gameDRB), 2) AS drb,
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

-- Playoff Career Averages (game_type = 2)
CREATE OR REPLACE VIEW ibl_playoff_career_avgs AS
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
  ROUND(AVG(bs.gameDRB), 2) AS drb,
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

-- Playoff Career Totals (game_type = 2)
CREATE OR REPLACE VIEW ibl_playoff_career_totals AS
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
  CAST(SUM(bs.gameDRB) AS SIGNED) AS drb,
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

-- Heat Career Averages (game_type = 3)
CREATE OR REPLACE VIEW ibl_heat_career_avgs AS
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
  ROUND(AVG(bs.gameDRB), 2) AS drb,
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

-- Heat Career Totals (game_type = 3)
CREATE OR REPLACE VIEW ibl_heat_career_totals AS
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
  CAST(SUM(bs.gameDRB) AS SIGNED) AS drb,
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

-- All-Star Career Totals (teamID IN 50, 51)
CREATE OR REPLACE VIEW ibl_allstar_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.gameDRB) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamID IN (50, 51)
GROUP BY bs.pid, p.name, p.retired;

-- All-Star Career Averages (teamID IN 50, 51)
CREATE OR REPLACE VIEW ibl_allstar_career_avgs AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.gameMIN), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game2GA + bs.game3GA), 2) AS fga,
  CASE WHEN SUM(bs.game2GA + bs.game3GA) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game2GA + bs.game3GA), 3)
    ELSE 0.000
  END AS fgpct,
  ROUND(AVG(bs.gameFTM), 2) AS ftm,
  ROUND(AVG(bs.gameFTA), 2) AS fta,
  CASE WHEN SUM(bs.gameFTA) > 0
    THEN ROUND(SUM(bs.gameFTM) / SUM(bs.gameFTA), 3)
    ELSE 0.000
  END AS ftpct,
  ROUND(AVG(bs.game3GM), 2) AS tgm,
  ROUND(AVG(bs.game3GA), 2) AS tga,
  CASE WHEN SUM(bs.game3GA) > 0
    THEN ROUND(SUM(bs.game3GM) / SUM(bs.game3GA), 3)
    ELSE 0.000
  END AS tpct,
  ROUND(AVG(bs.gameORB), 2) AS orb,
  ROUND(AVG(bs.gameDRB), 2) AS drb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.gameAST), 2) AS ast,
  ROUND(AVG(bs.gameSTL), 2) AS stl,
  ROUND(AVG(bs.gameTOV), 2) AS tvr,
  ROUND(AVG(bs.gameBLK), 2) AS blk,
  ROUND(AVG(bs.gamePF), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamID IN (50, 51)
GROUP BY bs.pid, p.name, p.retired;

-- Rookie Game Career Totals (teamID = 40)
CREATE OR REPLACE VIEW ibl_rookie_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.gameDRB) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamID = 40
GROUP BY bs.pid, p.name, p.retired;

-- Sophomore Game Career Totals (teamID = 41)
CREATE OR REPLACE VIEW ibl_sophomore_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.gameMIN) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game2GA + bs.game3GA) AS SIGNED) AS fga,
  CAST(SUM(bs.gameFTM) AS SIGNED) AS ftm,
  CAST(SUM(bs.gameFTA) AS SIGNED) AS fta,
  CAST(SUM(bs.game3GM) AS SIGNED) AS tgm,
  CAST(SUM(bs.game3GA) AS SIGNED) AS tga,
  CAST(SUM(bs.gameORB) AS SIGNED) AS orb,
  CAST(SUM(bs.gameDRB) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.gameAST) AS SIGNED) AS ast,
  CAST(SUM(bs.gameSTL) AS SIGNED) AS stl,
  CAST(SUM(bs.gameTOV) AS SIGNED) AS tvr,
  CAST(SUM(bs.gameBLK) AS SIGNED) AS blk,
  CAST(SUM(bs.gamePF) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamID = 41
GROUP BY bs.pid, p.name, p.retired;
