-- Replace ibl_hist table with a VIEW derived from ibl_box_scores + ibl_plr_snapshots.
--
-- Stats come from ibl_box_scores (game_type=1 regular season, aggregated per player per season).
-- Ratings, salary, and TSI come from ibl_plr_snapshots (end-of-season phase).
-- Team name comes from ibl_franchise_seasons via the snapshot's tid.
--
-- The original table is preserved as ibl_hist_archive for safety.

-- 1. Drop dependent views first
DROP VIEW IF EXISTS `vw_career_totals`;

-- 2. Rename the table to archive
RENAME TABLE `ibl_hist` TO `ibl_hist_archive`;

-- 3. Create VIEW ibl_hist — backward-compatible column names + bonus columns
CREATE VIEW `ibl_hist` AS
SELECT
  stats.pid,
  p.name,
  stats.season_year                                        AS `year`,
  COALESCE(snap.tid, 0)                                    AS teamid,
  COALESCE(fs.team_name, '')                               AS team,
  stats.games,
  stats.minutes,
  stats.fgm,
  stats.fga,
  stats.ftm,
  stats.fta,
  stats.tgm,
  stats.tga,
  stats.orb,
  stats.reb,
  stats.ast,
  stats.stl,
  stats.blk,
  stats.tvr,
  stats.pf,
  stats.pts,
  -- Ratings aliased to legacy ibl_hist naming convention
  COALESCE(snap.r_fga, 0)                                 AS r_2ga,
  COALESCE(snap.r_fgp, 0)                                 AS r_2gp,
  COALESCE(snap.r_fta, 0)                                 AS r_fta,
  COALESCE(snap.r_ftp, 0)                                 AS r_ftp,
  COALESCE(snap.r_tga, 0)                                 AS r_3ga,
  COALESCE(snap.r_tgp, 0)                                 AS r_3gp,
  COALESCE(snap.r_orb, 0)                                 AS r_orb,
  COALESCE(snap.r_drb, 0)                                 AS r_drb,
  COALESCE(snap.r_ast, 0)                                 AS r_ast,
  COALESCE(snap.r_stl, 0)                                 AS r_stl,
  COALESCE(snap.r_blk, 0)                                 AS r_blk,
  COALESCE(snap.r_to,  0)                                 AS r_tvr,
  COALESCE(snap.oo,    0)                                  AS r_oo,
  COALESCE(snap.`do`,  0)                                  AS r_do,
  COALESCE(snap.po,    0)                                  AS r_po,
  COALESCE(snap.`to`,  0)                                  AS r_to,
  COALESCE(snap.od,    0)                                  AS r_od,
  COALESCE(snap.dd,    0)                                  AS r_dd,
  COALESCE(snap.pd,    0)                                  AS r_pd,
  COALESCE(snap.td,    0)                                  AS r_td,
  -- Salary derived from contract position
  COALESCE(CASE snap.cy
    WHEN 1 THEN snap.cy1  WHEN 2 THEN snap.cy2
    WHEN 3 THEN snap.cy3  WHEN 4 THEN snap.cy4
    WHEN 5 THEN snap.cy5  WHEN 6 THEN snap.cy6
    ELSE 0 END, 0)                                         AS salary,
  -- Bonus columns not available in the old ibl_hist table
  COALESCE(snap.talent, 0)                                 AS talent,
  COALESCE(snap.skill, 0)                                  AS skill,
  COALESCE(snap.intangibles, 0)                            AS intangibles,
  COALESCE(snap.talent + snap.skill + snap.intangibles, 0) AS tsi_sum,
  COALESCE(snap.clutch, 0)                                 AS clutch,
  COALESCE(snap.consistency, 0)                            AS consistency,
  COALESCE(snap.age, 0)                                    AS age,
  COALESCE(snap.peak, 0)                                   AS peak,
  COALESCE(snap.cy1, 0)                                    AS cy1,
  COALESCE(snap.cy2, 0)                                    AS cy2,
  COALESCE(snap.cy3, 0)                                    AS cy3,
  COALESCE(snap.cy4, 0)                                    AS cy4,
  COALESCE(snap.cy5, 0)                                    AS cy5,
  COALESCE(snap.cy6, 0)                                    AS cy6
FROM (
  SELECT
    pid,
    season_year,
    CAST(COUNT(*)                AS SIGNED) AS games,
    CAST(SUM(gameMIN)            AS SIGNED) AS minutes,
    CAST(SUM(calc_fg_made)       AS SIGNED) AS fgm,
    CAST(SUM(game2GA + game3GA)  AS SIGNED) AS fga,
    CAST(SUM(gameFTM)            AS SIGNED) AS ftm,
    CAST(SUM(gameFTA)            AS SIGNED) AS fta,
    CAST(SUM(game3GM)            AS SIGNED) AS tgm,
    CAST(SUM(game3GA)            AS SIGNED) AS tga,
    CAST(SUM(gameORB)            AS SIGNED) AS orb,
    CAST(SUM(calc_rebounds)      AS SIGNED) AS reb,
    CAST(SUM(gameAST)            AS SIGNED) AS ast,
    CAST(SUM(gameSTL)            AS SIGNED) AS stl,
    CAST(SUM(gameBLK)            AS SIGNED) AS blk,
    CAST(SUM(gameTOV)            AS SIGNED) AS tvr,
    CAST(SUM(gamePF)             AS SIGNED) AS pf,
    CAST(SUM(calc_points)        AS SIGNED) AS pts
  FROM ibl_box_scores
  WHERE game_type = 1
  GROUP BY pid, season_year
) stats
JOIN ibl_plr p ON stats.pid = p.pid
LEFT JOIN ibl_plr_snapshots snap
  ON stats.pid = snap.pid
  AND stats.season_year = snap.season_year
  AND snap.snapshot_phase = 'end-of-season'
LEFT JOIN ibl_franchise_seasons fs
  ON snap.tid = fs.franchise_id
  AND stats.season_year = fs.season_ending_year

UNION ALL

-- Fallback: archive rows for player+seasons not yet in ibl_box_scores.
-- This provides backward compatibility during the transition period.
SELECT
  ha.pid, ha.name, ha.`year`, ha.teamid, ha.team,
  ha.games, ha.minutes, ha.fgm, ha.fga, ha.ftm, ha.fta,
  ha.tgm, ha.tga, ha.orb, ha.reb, ha.ast, ha.stl,
  ha.blk, ha.tvr, ha.pf, ha.pts,
  ha.r_2ga, ha.r_2gp, ha.r_fta, ha.r_ftp, ha.r_3ga, ha.r_3gp,
  ha.r_orb, ha.r_drb, ha.r_ast, ha.r_stl, ha.r_blk, ha.r_tvr,
  ha.r_oo, ha.r_do, ha.r_po, ha.r_to, ha.r_od, ha.r_dd, ha.r_pd, ha.r_td,
  ha.salary,
  0 AS talent, 0 AS skill, 0 AS intangibles, 0 AS tsi_sum,
  0 AS clutch, 0 AS consistency, 0 AS age, 0 AS peak,
  0 AS cy1, 0 AS cy2, 0 AS cy3, 0 AS cy4, 0 AS cy5, 0 AS cy6
FROM ibl_hist_archive ha
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_box_scores bs
  WHERE bs.pid = ha.pid AND bs.season_year = ha.`year` AND bs.game_type = 1
);

-- 4. Recreate vw_career_totals (now reads from the view)
CREATE VIEW `vw_career_totals` AS
SELECT
  pid,
  name,
  COUNT(*)     AS seasons,
  SUM(games)   AS games,
  SUM(minutes) AS minutes,
  SUM(fgm)     AS fgm,
  SUM(fga)     AS fga,
  SUM(ftm)     AS ftm,
  SUM(fta)     AS fta,
  SUM(tgm)     AS tgm,
  SUM(tga)     AS tga,
  SUM(orb)     AS orb,
  SUM(reb)     AS reb,
  SUM(ast)     AS ast,
  SUM(stl)     AS stl,
  SUM(blk)     AS blk,
  SUM(tvr)     AS tvr,
  SUM(pf)      AS pf,
  SUM(pts)     AS pts
FROM ibl_hist
GROUP BY pid, name;
