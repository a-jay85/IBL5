-- Keep the Rookies/Sophomores and All-Star exhibitions out of the regular-season
-- career averages view.
--
-- game_type comes from the month alone, so the February exhibition games are stored
-- as game_type = 1. The view counted them as regular-season games, which inflated
-- career games, minutes and averages for every participant (871 player rows across
-- 325 players at the time of writing). The ibl_rookie_*, ibl_sophomore_* and
-- ibl_allstar_* views already pick these games out by teamid.
--
-- Excluded teamids match the League constants: ROOKIES_TEAMID (40), SOPHOMORES_TEAMID
-- (41), ALL_STAR_AWAY_TEAMID (50), ALL_STAR_HOME_TEAMID (51). Body copied from migration
-- 150, which added the game_min > 0 DNP guard. CREATE OR REPLACE VIEW is idempotent.

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
WHERE bs.game_type = 1
  AND bs.game_min > 0
  AND bs.teamid NOT IN (40, 41, 50, 51)
GROUP BY bs.pid, p.name, p.retired;
