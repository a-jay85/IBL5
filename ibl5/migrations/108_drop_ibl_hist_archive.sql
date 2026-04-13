-- Migration 108: Drop ibl_hist_archive
--
-- ibl_hist_archive was the original ibl_hist table, renamed by migration 088
-- when ibl_hist became a VIEW. The VIEW had two branches:
--   1. Primary: canonical snapshots from ibl_plr_snapshots (stats_gm > 0)
--   2. Fallback: rows from ibl_hist_archive without a matching snapshot
--
-- All 181 fallback rows have been verified as exact duplicates of snapshot
-- data (PID mismatches, year-convention off-by-ones, or 0-game ghost entries
-- from .car file imports). With zero orphans remaining, the fallback branch
-- returns no rows and the archive table is dead weight.
--
-- This migration:
--   1. Rebuilds ibl_hist VIEW without the UNION ALL fallback branch
--   2. Rebuilds vw_career_totals (depends on ibl_hist)
--   3. Drops the ibl_hist_archive table

-- Step 1: Rebuild ibl_hist VIEW (primary branch only, from migration 105)
DROP VIEW IF EXISTS `vw_career_totals`;
DROP VIEW IF EXISTS `ibl_hist`;

CREATE ALGORITHM = TEMPTABLE VIEW `ibl_hist` AS
SELECT
  snap.pid,
  snap.name,
  snap.season_year                                           AS `year`,
  snap.tid                                                   AS teamid,
  COALESCE(fs.team_name, '')                                 AS team,
  CAST(snap.stats_gm - snap.phantom_games AS SIGNED)         AS games,
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
  CAST(COALESCE(snap.r_fga, 0) AS SIGNED)                    AS r_2ga,
  CAST(COALESCE(snap.r_fgp, 0) AS SIGNED)                    AS r_2gp,
  CAST(COALESCE(snap.r_fta, 0) AS SIGNED)                    AS r_fta,
  CAST(COALESCE(snap.r_ftp, 0) AS SIGNED)                    AS r_ftp,
  CAST(COALESCE(snap.r_tga, 0) AS SIGNED)                    AS r_3ga,
  CAST(COALESCE(snap.r_tgp, 0) AS SIGNED)                    AS r_3gp,
  CAST(COALESCE(snap.r_orb, 0) AS SIGNED)                    AS r_orb,
  CAST(COALESCE(snap.r_drb, 0) AS SIGNED)                    AS r_drb,
  CAST(COALESCE(snap.r_ast, 0) AS SIGNED)                    AS r_ast,
  CAST(COALESCE(snap.r_stl, 0) AS SIGNED)                    AS r_stl,
  CAST(COALESCE(snap.r_blk, 0) AS SIGNED)                    AS r_blk,
  CAST(COALESCE(snap.r_to,  0) AS SIGNED)                    AS r_tvr,
  CAST(COALESCE(snap.oo,    0) AS SIGNED)                    AS r_oo,
  CAST(COALESCE(snap.`do`,  0) AS SIGNED)                    AS r_do,
  CAST(COALESCE(snap.po,    0) AS SIGNED)                    AS r_po,
  CAST(COALESCE(snap.`to`,  0) AS SIGNED)                    AS r_to,
  CAST(COALESCE(snap.od,    0) AS SIGNED)                    AS r_od,
  CAST(COALESCE(snap.dd,    0) AS SIGNED)                    AS r_dd,
  CAST(COALESCE(snap.pd,    0) AS SIGNED)                    AS r_pd,
  CAST(COALESCE(snap.td,    0) AS SIGNED)                    AS r_td,
  CAST(COALESCE(CASE snap.cy
    WHEN 1 THEN snap.cy1  WHEN 2 THEN snap.cy2
    WHEN 3 THEN snap.cy3  WHEN 4 THEN snap.cy4
    WHEN 5 THEN snap.cy5  WHEN 6 THEN snap.cy6
    ELSE 0 END, 0) AS SIGNED)                                AS salary,
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
  CAST(COALESCE(snap.cy6, 0)                     AS SIGNED)  AS cy6,
  CAST(snap.phantom_games AS SIGNED)                         AS phantom_games
FROM (
  SELECT
    s.*,
    ROW_NUMBER() OVER (
      PARTITION BY s.pid, s.season_year
      ORDER BY
        s.stats_gm DESC,
        CASE s.snapshot_phase
          WHEN 'end-of-season'       THEN  1
          WHEN 'finals'              THEN  2
          WHEN 'post-heat'           THEN  3
          WHEN 'heat-finals'         THEN  4
          WHEN 'heat-end'            THEN  5
          WHEN 'playoffs-rd2-gm4-7'  THEN  6
          WHEN 'playoffs-rd2-gm1-3'  THEN  7
          WHEN 'playoffs-rd1-gm4-7'  THEN  8
          WHEN 'playoffs-rd1-gm1-3'  THEN  9
          WHEN 'conf-finals-gm4-7'   THEN 10
          WHEN 'conf-finals-gm1-3'   THEN 11
          WHEN 'heat-wb'             THEN 12
          WHEN 'heat-lb'             THEN 13
          ELSE 99
        END ASC,
        s.id DESC
    ) AS rn
  FROM ibl_plr_snapshots s
  WHERE s.stats_gm > 0
) snap
LEFT JOIN ibl_franchise_seasons fs
  ON snap.tid = fs.franchise_id
  AND snap.season_year = fs.season_ending_year
WHERE snap.rn = 1;

-- Step 2: Rebuild vw_career_totals
CREATE VIEW `vw_career_totals` AS
SELECT
  pid,
  name,
  COUNT(*)              AS seasons,
  SUM(games)            AS games,
  SUM(minutes)          AS minutes,
  SUM(fgm)              AS fgm,
  SUM(fga)              AS fga,
  SUM(ftm)              AS ftm,
  SUM(fta)              AS fta,
  SUM(tgm)              AS tgm,
  SUM(tga)              AS tga,
  SUM(orb)              AS orb,
  SUM(reb)              AS reb,
  SUM(ast)              AS ast,
  SUM(stl)              AS stl,
  SUM(blk)              AS blk,
  SUM(tvr)              AS tvr,
  SUM(pf)               AS pf,
  SUM(pts)              AS pts,
  SUM(phantom_games)    AS phantom_games
FROM ibl_hist
GROUP BY pid, name;

-- Step 3: Drop the archive table
DROP TABLE IF EXISTS `ibl_hist_archive`;
