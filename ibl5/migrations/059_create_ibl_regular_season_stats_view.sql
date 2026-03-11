-- Migration 059: Create ibl_regular_season_stats view
-- Mirrors ibl_playoff_stats (migration 028) but for regular season (game_type = 1)

CREATE OR REPLACE VIEW ibl_regular_season_stats AS
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
WHERE bs.game_type = 1
GROUP BY bs.pid, p.name, bs.season_year, fs.team_name;
