-- Replace ibl_hist VIEW to source primarily from ibl_plr_snapshots instead of
-- aggregating 589K ibl_box_scores rows.
--
-- Analysis confirmed ibl_plr_snapshots end-of-season stats match box score
-- aggregations at 98.6% accuracy (6,392/6,482 perfect matches across all 16
-- stat columns). The ~1.4% mismatches are explained by incomplete box score
-- archives in 3 seasons and final-game timing in playoffs.
--
-- Primary branch: ibl_plr_snapshots (end-of-season, stats_gm > 0) — ~12K rows.
-- Fallback branch: ibl_hist_archive for player-seasons without snapshots
-- (CI seed data, current in-progress season).

-- Must drop dependent views first
DROP VIEW IF EXISTS `vw_career_totals`;
DROP VIEW IF EXISTS `ibl_hist`;

CREATE VIEW `ibl_hist` AS
SELECT
  snap.pid,
  snap.name,
  snap.season_year                                           AS `year`,
  snap.tid                                                   AS teamid,
  COALESCE(fs.team_name, '')                                 AS team,
  CAST(snap.stats_gm  AS SIGNED)                             AS games,
  CAST(snap.stats_min AS SIGNED)                             AS minutes,
  CAST(snap.stats_fgm AS SIGNED)                             AS fgm,
  CAST(snap.stats_fga AS SIGNED)                             AS fga,
  CAST(snap.stats_ftm AS SIGNED)                             AS ftm,
  CAST(snap.stats_fta AS SIGNED)                             AS fta,
  CAST(snap.stats_3gm AS SIGNED)                             AS tgm,
  CAST(snap.stats_3ga AS SIGNED)                             AS tga,
  CAST(snap.stats_orb AS SIGNED)                             AS orb,
  CAST(snap.stats_reb AS SIGNED)                             AS reb,
  CAST(snap.stats_ast AS SIGNED)                             AS ast,
  CAST(snap.stats_stl AS SIGNED)                             AS stl,
  CAST(snap.stats_blk AS SIGNED)                             AS blk,
  CAST(snap.stats_to  AS SIGNED)                             AS tvr,
  CAST(snap.stats_pf  AS SIGNED)                             AS pf,
  CAST(snap.stats_pts AS SIGNED)                             AS pts,
  -- Ratings aliased to legacy ibl_hist naming convention
  CAST(COALESCE(snap.r_fga, 0) AS SIGNED)                   AS r_2ga,
  CAST(COALESCE(snap.r_fgp, 0) AS SIGNED)                   AS r_2gp,
  CAST(COALESCE(snap.r_fta, 0) AS SIGNED)                   AS r_fta,
  CAST(COALESCE(snap.r_ftp, 0) AS SIGNED)                   AS r_ftp,
  CAST(COALESCE(snap.r_tga, 0) AS SIGNED)                   AS r_3ga,
  CAST(COALESCE(snap.r_tgp, 0) AS SIGNED)                   AS r_3gp,
  CAST(COALESCE(snap.r_orb, 0) AS SIGNED)                   AS r_orb,
  CAST(COALESCE(snap.r_drb, 0) AS SIGNED)                   AS r_drb,
  CAST(COALESCE(snap.r_ast, 0) AS SIGNED)                   AS r_ast,
  CAST(COALESCE(snap.r_stl, 0) AS SIGNED)                   AS r_stl,
  CAST(COALESCE(snap.r_blk, 0) AS SIGNED)                   AS r_blk,
  CAST(COALESCE(snap.r_to,  0) AS SIGNED)                   AS r_tvr,
  CAST(COALESCE(snap.oo,    0) AS SIGNED)                    AS r_oo,
  CAST(COALESCE(snap.`do`,  0) AS SIGNED)                    AS r_do,
  CAST(COALESCE(snap.po,    0) AS SIGNED)                    AS r_po,
  CAST(COALESCE(snap.`to`,  0) AS SIGNED)                    AS r_to,
  CAST(COALESCE(snap.od,    0) AS SIGNED)                    AS r_od,
  CAST(COALESCE(snap.dd,    0) AS SIGNED)                    AS r_dd,
  CAST(COALESCE(snap.pd,    0) AS SIGNED)                    AS r_pd,
  CAST(COALESCE(snap.td,    0) AS SIGNED)                    AS r_td,
  -- Salary derived from contract position
  CAST(COALESCE(CASE snap.cy
    WHEN 1 THEN snap.cy1  WHEN 2 THEN snap.cy2
    WHEN 3 THEN snap.cy3  WHEN 4 THEN snap.cy4
    WHEN 5 THEN snap.cy5  WHEN 6 THEN snap.cy6
    ELSE 0 END, 0) AS SIGNED)                                AS salary,
  -- TSI and attributes
  CAST(COALESCE(snap.talent, 0)                  AS SIGNED)  AS talent,
  CAST(COALESCE(snap.skill, 0)                   AS SIGNED)  AS skill,
  CAST(COALESCE(snap.intangibles, 0)             AS SIGNED)  AS intangibles,
  CAST(COALESCE(snap.talent + snap.skill + snap.intangibles, 0) AS SIGNED) AS tsi_sum,
  CAST(COALESCE(snap.clutch, 0)                  AS SIGNED)  AS clutch,
  CAST(COALESCE(snap.consistency, 0)             AS SIGNED)  AS consistency,
  CAST(COALESCE(snap.age, 0)                     AS SIGNED)  AS age,
  CAST(COALESCE(snap.peak, 0)                    AS SIGNED)  AS peak,
  CAST(COALESCE(snap.cy1, 0)                     AS SIGNED)  AS cy1,
  CAST(COALESCE(snap.cy2, 0)                     AS SIGNED)  AS cy2,
  CAST(COALESCE(snap.cy3, 0)                     AS SIGNED)  AS cy3,
  CAST(COALESCE(snap.cy4, 0)                     AS SIGNED)  AS cy4,
  CAST(COALESCE(snap.cy5, 0)                     AS SIGNED)  AS cy5,
  CAST(COALESCE(snap.cy6, 0)                     AS SIGNED)  AS cy6
FROM ibl_plr_snapshots snap
LEFT JOIN ibl_franchise_seasons fs
  ON snap.tid = fs.franchise_id
  AND snap.season_year = fs.season_ending_year
WHERE snap.snapshot_phase = 'end-of-season'
  AND snap.stats_gm > 0

UNION ALL

-- Fallback: player-seasons in ibl_hist_archive without a matching snapshot.
-- Covers CI seed data and current in-progress seasons.
SELECT
  ha.pid,
  ha.name,
  ha.`year`,
  ha.teamid,
  ha.team,
  CAST(ha.games   AS SIGNED) AS games,
  CAST(ha.minutes AS SIGNED) AS minutes,
  CAST(ha.fgm     AS SIGNED) AS fgm,
  CAST(ha.fga     AS SIGNED) AS fga,
  CAST(ha.ftm     AS SIGNED) AS ftm,
  CAST(ha.fta     AS SIGNED) AS fta,
  CAST(ha.tgm     AS SIGNED) AS tgm,
  CAST(ha.tga     AS SIGNED) AS tga,
  CAST(ha.orb     AS SIGNED) AS orb,
  CAST(ha.reb     AS SIGNED) AS reb,
  CAST(ha.ast     AS SIGNED) AS ast,
  CAST(ha.stl     AS SIGNED) AS stl,
  CAST(ha.blk     AS SIGNED) AS blk,
  CAST(ha.tvr     AS SIGNED) AS tvr,
  CAST(ha.pf      AS SIGNED) AS pf,
  CAST(ha.pts     AS SIGNED) AS pts,
  CAST(ha.r_2ga   AS SIGNED) AS r_2ga,
  CAST(ha.r_2gp   AS SIGNED) AS r_2gp,
  CAST(ha.r_fta   AS SIGNED) AS r_fta,
  CAST(ha.r_ftp   AS SIGNED) AS r_ftp,
  CAST(ha.r_3ga   AS SIGNED) AS r_3ga,
  CAST(ha.r_3gp   AS SIGNED) AS r_3gp,
  CAST(ha.r_orb   AS SIGNED) AS r_orb,
  CAST(ha.r_drb   AS SIGNED) AS r_drb,
  CAST(ha.r_ast   AS SIGNED) AS r_ast,
  CAST(ha.r_stl   AS SIGNED) AS r_stl,
  CAST(ha.r_blk   AS SIGNED) AS r_blk,
  CAST(ha.r_tvr   AS SIGNED) AS r_tvr,
  CAST(ha.r_oo    AS SIGNED) AS r_oo,
  CAST(ha.r_do    AS SIGNED) AS r_do,
  CAST(ha.r_po    AS SIGNED) AS r_po,
  CAST(ha.r_to    AS SIGNED) AS r_to,
  CAST(ha.r_od    AS SIGNED) AS r_od,
  CAST(ha.r_dd    AS SIGNED) AS r_dd,
  CAST(ha.r_pd    AS SIGNED) AS r_pd,
  CAST(ha.r_td    AS SIGNED) AS r_td,
  CAST(ha.salary  AS SIGNED) AS salary,
  0 AS talent,
  0 AS skill,
  0 AS intangibles,
  0 AS tsi_sum,
  0 AS clutch,
  0 AS consistency,
  0 AS age,
  0 AS peak,
  0 AS cy1,
  0 AS cy2,
  0 AS cy3,
  0 AS cy4,
  0 AS cy5,
  0 AS cy6
FROM ibl_hist_archive ha
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_plr_snapshots s
  WHERE s.pid = ha.pid
    AND s.season_year = ha.`year`
    AND s.snapshot_phase = 'end-of-season'
    AND s.stats_gm > 0
);

-- Recreate vw_career_totals (depends on ibl_hist)
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
