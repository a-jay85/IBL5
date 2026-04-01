-- Update ibl_hist VIEW to prefer snapshot season stats in the archive fallback branch.
--
-- After migration 092 expanded ibl_plr_snapshots with season stats, the snapshot
-- table can serve as a better source of per-season stats than ibl_hist_archive.
-- The fallback branch now uses CASE expressions: if the snapshot has stats
-- (stats_gm > 0), use snapshot stats; otherwise fall back to archive stats.
--
-- The primary branch (box_scores) is unchanged.

-- Must drop dependent views first
DROP VIEW IF EXISTS `vw_career_totals`;
DROP VIEW IF EXISTS `ibl_hist`;

CREATE VIEW `ibl_hist` AS
SELECT
  stats.pid,
  p.name,
  stats.season_year                                        AS `year`,
  COALESCE(snap.tid, p.tid, 0)                             AS teamid,
  COALESCE(fs.team_name, fs_fallback.team_name, '')        AS team,
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
LEFT JOIN ibl_franchise_seasons fs_fallback
  ON p.tid = fs_fallback.franchise_id
  AND stats.season_year = fs_fallback.season_ending_year

UNION ALL

-- Fallback: player+seasons not in ibl_box_scores.
-- Prefer snapshot season stats (stats_gm > 0) over ibl_hist_archive.
SELECT
  ha.pid,
  COALESCE(ha_snap.name, ha.name) AS name,
  ha.`year`,
  COALESCE(ha_snap.tid, ha.teamid) AS teamid,
  COALESCE(fs_snap.team_name, ha.team) AS team,
  -- Stats: prefer snapshot when populated, fall back to archive
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_gm  ELSE ha.games   END AS SIGNED) AS games,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_min  ELSE ha.minutes END AS SIGNED) AS minutes,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_fgm  ELSE ha.fgm    END AS SIGNED) AS fgm,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_fga  ELSE ha.fga    END AS SIGNED) AS fga,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_ftm  ELSE ha.ftm    END AS SIGNED) AS ftm,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_fta  ELSE ha.fta    END AS SIGNED) AS fta,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_3gm  ELSE ha.tgm    END AS SIGNED) AS tgm,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_3ga  ELSE ha.tga    END AS SIGNED) AS tga,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_orb  ELSE ha.orb    END AS SIGNED) AS orb,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_reb  ELSE ha.reb    END AS SIGNED) AS reb,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_ast  ELSE ha.ast    END AS SIGNED) AS ast,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_stl  ELSE ha.stl    END AS SIGNED) AS stl,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_blk  ELSE ha.blk    END AS SIGNED) AS blk,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_to   ELSE ha.tvr    END AS SIGNED) AS tvr,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_pf   ELSE ha.pf     END AS SIGNED) AS pf,
  CAST(CASE WHEN ha_snap.stats_gm > 0 THEN ha_snap.stats_pts  ELSE ha.pts    END AS SIGNED) AS pts,
  -- Ratings: prefer snapshot, fall back to archive
  CAST(COALESCE(ha_snap.r_fga, ha.r_2ga)  AS SIGNED) AS r_2ga,
  CAST(COALESCE(ha_snap.r_fgp, ha.r_2gp)  AS SIGNED) AS r_2gp,
  CAST(COALESCE(ha_snap.r_fta, ha.r_fta)  AS SIGNED) AS r_fta,
  CAST(COALESCE(ha_snap.r_ftp, ha.r_ftp)  AS SIGNED) AS r_ftp,
  CAST(COALESCE(ha_snap.r_tga, ha.r_3ga)  AS SIGNED) AS r_3ga,
  CAST(COALESCE(ha_snap.r_tgp, ha.r_3gp)  AS SIGNED) AS r_3gp,
  CAST(COALESCE(ha_snap.r_orb, ha.r_orb)  AS SIGNED) AS r_orb,
  CAST(COALESCE(ha_snap.r_drb, ha.r_drb)  AS SIGNED) AS r_drb,
  CAST(COALESCE(ha_snap.r_ast, ha.r_ast)  AS SIGNED) AS r_ast,
  CAST(COALESCE(ha_snap.r_stl, ha.r_stl)  AS SIGNED) AS r_stl,
  CAST(COALESCE(ha_snap.r_blk, ha.r_blk)  AS SIGNED) AS r_blk,
  CAST(COALESCE(ha_snap.r_to,  ha.r_tvr)  AS SIGNED) AS r_tvr,
  CAST(COALESCE(ha_snap.oo,    ha.r_oo)   AS SIGNED) AS r_oo,
  CAST(COALESCE(ha_snap.`do`,  ha.r_do)   AS SIGNED) AS r_do,
  CAST(COALESCE(ha_snap.po,    ha.r_po)   AS SIGNED) AS r_po,
  CAST(COALESCE(ha_snap.`to`,  ha.r_to)   AS SIGNED) AS r_to,
  CAST(COALESCE(ha_snap.od,    ha.r_od)   AS SIGNED) AS r_od,
  CAST(COALESCE(ha_snap.dd,    ha.r_dd)   AS SIGNED) AS r_dd,
  CAST(COALESCE(ha_snap.pd,    ha.r_pd)   AS SIGNED) AS r_pd,
  CAST(COALESCE(ha_snap.td,    ha.r_td)   AS SIGNED) AS r_td,
  -- Salary: prefer snapshot derived, fall back to archive
  CAST(COALESCE(ha_snap.salary, ha.salary) AS SIGNED) AS salary,
  -- TSI and attributes from snapshot
  CAST(COALESCE(ha_snap.talent, 0)      AS SIGNED) AS talent,
  CAST(COALESCE(ha_snap.skill, 0)       AS SIGNED) AS skill,
  CAST(COALESCE(ha_snap.intangibles, 0) AS SIGNED) AS intangibles,
  CAST(COALESCE(ha_snap.talent + ha_snap.skill + ha_snap.intangibles, 0) AS SIGNED) AS tsi_sum,
  CAST(COALESCE(ha_snap.clutch, 0)      AS SIGNED) AS clutch,
  CAST(COALESCE(ha_snap.consistency, 0) AS SIGNED) AS consistency,
  CAST(COALESCE(ha_snap.age, 0)         AS SIGNED) AS age,
  CAST(COALESCE(ha_snap.peak, 0)        AS SIGNED) AS peak,
  CAST(COALESCE(ha_snap.cy1, 0)         AS SIGNED) AS cy1,
  CAST(COALESCE(ha_snap.cy2, 0)         AS SIGNED) AS cy2,
  CAST(COALESCE(ha_snap.cy3, 0)         AS SIGNED) AS cy3,
  CAST(COALESCE(ha_snap.cy4, 0)         AS SIGNED) AS cy4,
  CAST(COALESCE(ha_snap.cy5, 0)         AS SIGNED) AS cy5,
  CAST(COALESCE(ha_snap.cy6, 0)         AS SIGNED) AS cy6
FROM ibl_hist_archive ha
LEFT JOIN ibl_plr_snapshots ha_snap
  ON ha.pid = ha_snap.pid
  AND ha.`year` = ha_snap.season_year
  AND ha_snap.snapshot_phase = 'end-of-season'
LEFT JOIN ibl_franchise_seasons fs_snap
  ON ha_snap.tid = fs_snap.franchise_id
  AND ha.`year` = fs_snap.season_ending_year
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_box_scores bs
  WHERE bs.pid = ha.pid AND bs.season_year = ha.`year` AND bs.game_type = 1
);

-- Recreate vw_career_totals
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
