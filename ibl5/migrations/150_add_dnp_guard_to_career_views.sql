-- Migration 149: Add the DNP (game_min > 0) guard to the box-score-derived career
-- leaderboard views, so COUNT(*) games and AVG(...) per-game averages exclude
-- DNP rows — matching the PHP read-path fix in PlayerStatsRepository
-- (maintenance-40b, PR #1087) and closing the player-page-vs-leaderboard divergence.
--
-- 9 views read by CareerLeaderboardsService: 4 avgs (season/playoff/heat/allstar)
-- + 5 totals (playoff/heat/allstar/rookie/sophomore). ibl_olympics_career_{avgs,totals}
-- are BASE TABLEs (manual/JSB import, not box-score aggregations) — NOT touched.
-- ibl_season_career_totals does not exist (regular-season totals = ibl_hist, already
-- WHERE games > 0). Bodies copied verbatim from current information_schema.VIEWS
-- (snake-case post-112/116/121; the drb column is from migration 112) — NOT the stale
-- camelCase migrations 028/047. game_min > 0 matches BaseMysqliRepository::playedCondition();
-- it also excludes NULL game_min, intentional parity with the PHP path.
-- CREATE OR REPLACE VIEW = idempotent; no DROP (no adr-check / destructive-migration trigger).

-- Season Career Averages (game_type = 1)
CREATE OR REPLACE VIEW ibl_season_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.game_min), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game_2ga + bs.game_3ga), 2) AS fga,
  CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.game_ftm), 2) AS ftm,
  ROUND(AVG(bs.game_fta), 2) AS fta,
  CASE WHEN SUM(bs.game_fta) > 0
    THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game_3gm), 2) AS tgm,
  ROUND(AVG(bs.game_3ga), 2) AS tga,
  CASE WHEN SUM(bs.game_3ga) > 0
    THEN ROUND(SUM(bs.game_3gm) / SUM(bs.game_3ga), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.game_orb), 2) AS orb,
  ROUND(AVG(bs.game_drb), 2) AS drb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.game_ast), 2) AS ast,
  ROUND(AVG(bs.game_stl), 2) AS stl,
  ROUND(AVG(bs.game_tov), 2) AS tvr,
  ROUND(AVG(bs.game_blk), 2) AS blk,
  ROUND(AVG(bs.game_pf), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 1 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Playoff Career Averages (game_type = 2)
CREATE OR REPLACE VIEW ibl_playoff_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.game_min), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game_2ga + bs.game_3ga), 2) AS fga,
  CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.game_ftm), 2) AS ftm,
  ROUND(AVG(bs.game_fta), 2) AS fta,
  CASE WHEN SUM(bs.game_fta) > 0
    THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game_3gm), 2) AS tgm,
  ROUND(AVG(bs.game_3ga), 2) AS tga,
  CASE WHEN SUM(bs.game_3ga) > 0
    THEN ROUND(SUM(bs.game_3gm) / SUM(bs.game_3ga), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.game_orb), 2) AS orb,
  ROUND(AVG(bs.game_drb), 2) AS drb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.game_ast), 2) AS ast,
  ROUND(AVG(bs.game_stl), 2) AS stl,
  ROUND(AVG(bs.game_tov), 2) AS tvr,
  ROUND(AVG(bs.game_blk), 2) AS blk,
  ROUND(AVG(bs.game_pf), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 2 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Playoff Career Totals (game_type = 2)
CREATE OR REPLACE VIEW ibl_playoff_career_totals AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
  CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
  CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
  CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
  CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
  CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
  CAST(SUM(bs.game_drb) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
  CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
  CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
  CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
  CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 2 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Heat Career Averages (game_type = 3)
CREATE OR REPLACE VIEW ibl_heat_career_avgs AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.game_min), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game_2ga + bs.game_3ga), 2) AS fga,
  CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3)
    ELSE 0.000 END AS fgpct,
  ROUND(AVG(bs.game_ftm), 2) AS ftm,
  ROUND(AVG(bs.game_fta), 2) AS fta,
  CASE WHEN SUM(bs.game_fta) > 0
    THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3)
    ELSE 0.000 END AS ftpct,
  ROUND(AVG(bs.game_3gm), 2) AS tgm,
  ROUND(AVG(bs.game_3ga), 2) AS tga,
  CASE WHEN SUM(bs.game_3ga) > 0
    THEN ROUND(SUM(bs.game_3gm) / SUM(bs.game_3ga), 3)
    ELSE 0.000 END AS tpct,
  ROUND(AVG(bs.game_orb), 2) AS orb,
  ROUND(AVG(bs.game_drb), 2) AS drb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.game_ast), 2) AS ast,
  ROUND(AVG(bs.game_stl), 2) AS stl,
  ROUND(AVG(bs.game_tov), 2) AS tvr,
  ROUND(AVG(bs.game_blk), 2) AS blk,
  ROUND(AVG(bs.game_pf), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 3 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Heat Career Totals (game_type = 3)
CREATE OR REPLACE VIEW ibl_heat_career_totals AS
SELECT
  bs.pid,
  p.name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
  CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
  CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
  CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
  CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
  CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
  CAST(SUM(bs.game_drb) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
  CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
  CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
  CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
  CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.game_type = 3 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- All-Star Career Totals (teamid IN 50, 51)
CREATE OR REPLACE VIEW ibl_allstar_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
  CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
  CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
  CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
  CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
  CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
  CAST(SUM(bs.game_drb) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
  CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
  CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
  CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
  CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamid IN (50, 51) AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- All-Star Career Averages (teamid IN 50, 51)
CREATE OR REPLACE VIEW ibl_allstar_career_avgs AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  ROUND(AVG(bs.game_min), 2) AS minutes,
  ROUND(AVG(bs.calc_fg_made), 2) AS fgm,
  ROUND(AVG(bs.game_2ga + bs.game_3ga), 2) AS fga,
  CASE WHEN SUM(bs.game_2ga + bs.game_3ga) > 0
    THEN ROUND(SUM(bs.calc_fg_made) / SUM(bs.game_2ga + bs.game_3ga), 3)
    ELSE 0.000
  END AS fgpct,
  ROUND(AVG(bs.game_ftm), 2) AS ftm,
  ROUND(AVG(bs.game_fta), 2) AS fta,
  CASE WHEN SUM(bs.game_fta) > 0
    THEN ROUND(SUM(bs.game_ftm) / SUM(bs.game_fta), 3)
    ELSE 0.000
  END AS ftpct,
  ROUND(AVG(bs.game_3gm), 2) AS tgm,
  ROUND(AVG(bs.game_3ga), 2) AS tga,
  CASE WHEN SUM(bs.game_3ga) > 0
    THEN ROUND(SUM(bs.game_3gm) / SUM(bs.game_3ga), 3)
    ELSE 0.000
  END AS tpct,
  ROUND(AVG(bs.game_orb), 2) AS orb,
  ROUND(AVG(bs.game_drb), 2) AS drb,
  ROUND(AVG(bs.calc_rebounds), 2) AS reb,
  ROUND(AVG(bs.game_ast), 2) AS ast,
  ROUND(AVG(bs.game_stl), 2) AS stl,
  ROUND(AVG(bs.game_tov), 2) AS tvr,
  ROUND(AVG(bs.game_blk), 2) AS blk,
  ROUND(AVG(bs.game_pf), 2) AS pf,
  ROUND(AVG(bs.calc_points), 2) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamid IN (50, 51) AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Rookie Game Career Totals (teamid = 40)
CREATE OR REPLACE VIEW ibl_rookie_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
  CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
  CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
  CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
  CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
  CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
  CAST(SUM(bs.game_drb) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
  CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
  CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
  CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
  CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamid = 40 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;

-- Sophomore Game Career Totals (teamid = 41)
CREATE OR REPLACE VIEW ibl_sophomore_career_totals AS
SELECT
  bs.pid AS pid,
  p.name AS name,
  CAST(COUNT(*) AS SIGNED) AS games,
  CAST(SUM(bs.game_min) AS SIGNED) AS minutes,
  CAST(SUM(bs.calc_fg_made) AS SIGNED) AS fgm,
  CAST(SUM(bs.game_2ga + bs.game_3ga) AS SIGNED) AS fga,
  CAST(SUM(bs.game_ftm) AS SIGNED) AS ftm,
  CAST(SUM(bs.game_fta) AS SIGNED) AS fta,
  CAST(SUM(bs.game_3gm) AS SIGNED) AS tgm,
  CAST(SUM(bs.game_3ga) AS SIGNED) AS tga,
  CAST(SUM(bs.game_orb) AS SIGNED) AS orb,
  CAST(SUM(bs.game_drb) AS SIGNED) AS drb,
  CAST(SUM(bs.calc_rebounds) AS SIGNED) AS reb,
  CAST(SUM(bs.game_ast) AS SIGNED) AS ast,
  CAST(SUM(bs.game_stl) AS SIGNED) AS stl,
  CAST(SUM(bs.game_tov) AS SIGNED) AS tvr,
  CAST(SUM(bs.game_blk) AS SIGNED) AS blk,
  CAST(SUM(bs.game_pf) AS SIGNED) AS pf,
  CAST(SUM(bs.calc_points) AS SIGNED) AS pts,
  p.retired AS retired
FROM ibl_box_scores bs
JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.teamid = 41 AND bs.game_min > 0
GROUP BY bs.pid, p.name, p.retired;
