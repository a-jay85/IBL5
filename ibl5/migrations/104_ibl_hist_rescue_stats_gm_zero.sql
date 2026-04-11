-- Rescue ratings for ibl_hist fallback-branch rows by joining to stats_gm=0
-- snapshots when available. Keeps the ibl_hist row set identical to migration
-- 103 (no benchwarmer row additions) while populating r_*, TSI, and age/peak
-- columns from the best available snapshot for each (pid, year) in the
-- fallback branch.
--
-- Migration 103 left 380 rows in ibl_hist with all-zero ratings. Investigation
-- revealed:
--   - 111 rows have no snapshot at all → truly unrescuable (archive coverage
--     gaps, dominated by 1989-1990).
--   - 269 rows have at least one snapshot but every phase has stats_gm = 0 →
--     these are players on a roster who never played a game (benchwarmers,
--     mid-season waives before first game, injury-list shells).
--   - Of those 269, 268 have non-zero ratings in at least one phase. Only 1
--     has all-zero ratings across every snapshot.
--
-- Fix: the fallback branch (ibl_hist_archive) now LEFT JOINs to a
-- canonical-per-(pid, year) snapshot subquery. Rating, TSI, age, and peak
-- columns use COALESCE(snapshot.value, archive.value_or_zero). Stats (games,
-- pts, etc.) and salary remain sourced from ibl_hist_archive — accurate for
-- non-playing roster spots (all zeros). The primary branch's stats_gm > 0
-- filter is unchanged, so ibl_hist's row count stays identical to 103.
--
-- Expected impact on prod: blank-rating rows drop 380 → 112. ibl_hist row
-- count unchanged (still 7,961 = 7,581 primary + 380 fallback).

-- Must drop dependent views first
DROP VIEW IF EXISTS `vw_career_totals`;
DROP VIEW IF EXISTS `ibl_hist`;

CREATE ALGORITHM = TEMPTABLE VIEW `ibl_hist` AS
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
  CAST(COALESCE(snap.cy6, 0)                     AS SIGNED)  AS cy6
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
WHERE snap.rn = 1

UNION ALL

-- Fallback: player-seasons in ibl_hist_archive without a stats_gm > 0
-- snapshot. For rows that DO have a stats_gm = 0 snapshot (benchwarmers,
-- roster-only), rescue ratings/TSI/age/peak from the best available
-- snapshot via LEFT JOIN. Stats and salary stay sourced from the archive.
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
  CAST(COALESCE(rs.r_fga, ha.r_2ga) AS SIGNED)                AS r_2ga,
  CAST(COALESCE(rs.r_fgp, ha.r_2gp) AS SIGNED)                AS r_2gp,
  CAST(COALESCE(rs.r_fta, ha.r_fta) AS SIGNED)                AS r_fta,
  CAST(COALESCE(rs.r_ftp, ha.r_ftp) AS SIGNED)                AS r_ftp,
  CAST(COALESCE(rs.r_tga, ha.r_3ga) AS SIGNED)                AS r_3ga,
  CAST(COALESCE(rs.r_tgp, ha.r_3gp) AS SIGNED)                AS r_3gp,
  CAST(COALESCE(rs.r_orb, ha.r_orb) AS SIGNED)                AS r_orb,
  CAST(COALESCE(rs.r_drb, ha.r_drb) AS SIGNED)                AS r_drb,
  CAST(COALESCE(rs.r_ast, ha.r_ast) AS SIGNED)                AS r_ast,
  CAST(COALESCE(rs.r_stl, ha.r_stl) AS SIGNED)                AS r_stl,
  CAST(COALESCE(rs.r_blk, ha.r_blk) AS SIGNED)                AS r_blk,
  CAST(COALESCE(rs.r_to,  ha.r_tvr) AS SIGNED)                AS r_tvr,
  CAST(COALESCE(rs.oo,    ha.r_oo)  AS SIGNED)                AS r_oo,
  CAST(COALESCE(rs.`do`,  ha.r_do)  AS SIGNED)                AS r_do,
  CAST(COALESCE(rs.po,    ha.r_po)  AS SIGNED)                AS r_po,
  CAST(COALESCE(rs.`to`,  ha.r_to)  AS SIGNED)                AS r_to,
  CAST(COALESCE(rs.od,    ha.r_od)  AS SIGNED)                AS r_od,
  CAST(COALESCE(rs.dd,    ha.r_dd)  AS SIGNED)                AS r_dd,
  CAST(COALESCE(rs.pd,    ha.r_pd)  AS SIGNED)                AS r_pd,
  CAST(COALESCE(rs.td,    ha.r_td)  AS SIGNED)                AS r_td,
  CAST(ha.salary  AS SIGNED)                                  AS salary,
  CAST(COALESCE(rs.talent, 0)                 AS SIGNED)      AS talent,
  CAST(COALESCE(rs.skill, 0)                  AS SIGNED)      AS skill,
  CAST(COALESCE(rs.intangibles, 0)            AS SIGNED)      AS intangibles,
  CAST(COALESCE(rs.talent + rs.skill + rs.intangibles, 0) AS SIGNED) AS tsi_sum,
  CAST(COALESCE(rs.clutch, 0)                 AS SIGNED)      AS clutch,
  CAST(COALESCE(rs.consistency, 0)            AS SIGNED)      AS consistency,
  CAST(COALESCE(rs.age, 0)                    AS SIGNED)      AS age,
  CAST(COALESCE(rs.peak, 0)                   AS SIGNED)      AS peak,
  0 AS cy1,
  0 AS cy2,
  0 AS cy3,
  0 AS cy4,
  0 AS cy5,
  0 AS cy6
FROM ibl_hist_archive ha
LEFT JOIN (
  -- Best available snapshot per (pid, year) — used ONLY for the fallback
  -- branch to rescue ratings/TSI/age/peak for non-playing roster spots.
  -- When this branch runs, no stats_gm > 0 snapshot exists (guaranteed by
  -- the NOT EXISTS clause below), so rn=1 naturally picks the best
  -- stats_gm = 0 row per (pid, year).
  SELECT
    s.pid, s.season_year,
    s.r_fga, s.r_fgp, s.r_fta, s.r_ftp, s.r_tga, s.r_tgp,
    s.r_orb, s.r_drb, s.r_ast, s.r_stl, s.r_blk, s.r_to,
    s.oo, s.`do`, s.po, s.`to`, s.od, s.dd, s.pd, s.td,
    s.talent, s.skill, s.intangibles, s.clutch, s.consistency,
    s.age, s.peak,
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
) rs
  ON rs.pid = ha.pid
  AND rs.season_year = ha.`year`
  AND rs.rn = 1
WHERE NOT EXISTS (
  SELECT 1 FROM ibl_plr_snapshots s
  WHERE s.pid = ha.pid
    AND s.season_year = ha.`year`
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
