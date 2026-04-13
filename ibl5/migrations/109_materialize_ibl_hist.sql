-- Migration 109: Materialize ibl_hist VIEW into a real table
--
-- The ibl_hist VIEW uses ALGORITHM = TEMPTABLE with a ROW_NUMBER() window
-- function. Every query materializes the entire result set (~12K+ rows),
-- computes the window function, then applies outer WHERE — no predicate
-- pushdown. With hundreds of queries per page cycle, this is the largest
-- performance bottleneck.
--
-- This migration replaces the VIEW with a real InnoDB table populated by the
-- same query. The table is refreshed by RefreshIblHistStep in the update
-- pipeline (updateAllTheThings.php) after every sim run.
--
-- See ADR-0006 for the full decision record.

-- Step 1: Drop dependent views
DROP VIEW IF EXISTS `vw_career_totals`;
DROP VIEW IF EXISTS `ibl_hist`;

-- Step 2: Create the materialized table
CREATE TABLE `ibl_hist` (
  `pid`           INT          NOT NULL,
  `name`          VARCHAR(100) NOT NULL DEFAULT '',
  `year`          INT          NOT NULL,
  `teamid`        INT          NOT NULL DEFAULT 0,
  `team`          VARCHAR(100) NOT NULL DEFAULT '',
  `games`         INT          NOT NULL DEFAULT 0,
  `minutes`       INT          NOT NULL DEFAULT 0,
  `fgm`           INT          NOT NULL DEFAULT 0,
  `fga`           INT          NOT NULL DEFAULT 0,
  `ftm`           INT          NOT NULL DEFAULT 0,
  `fta`           INT          NOT NULL DEFAULT 0,
  `tgm`           INT          NOT NULL DEFAULT 0,
  `tga`           INT          NOT NULL DEFAULT 0,
  `orb`           INT          NOT NULL DEFAULT 0,
  `reb`           INT          NOT NULL DEFAULT 0,
  `ast`           INT          NOT NULL DEFAULT 0,
  `stl`           INT          NOT NULL DEFAULT 0,
  `blk`           INT          NOT NULL DEFAULT 0,
  `tvr`           INT          NOT NULL DEFAULT 0,
  `pf`            INT          NOT NULL DEFAULT 0,
  `pts`           INT          NOT NULL DEFAULT 0,
  `r_2ga`         INT          NOT NULL DEFAULT 0,
  `r_2gp`         INT          NOT NULL DEFAULT 0,
  `r_fta`         INT          NOT NULL DEFAULT 0,
  `r_ftp`         INT          NOT NULL DEFAULT 0,
  `r_3ga`         INT          NOT NULL DEFAULT 0,
  `r_3gp`         INT          NOT NULL DEFAULT 0,
  `r_orb`         INT          NOT NULL DEFAULT 0,
  `r_drb`         INT          NOT NULL DEFAULT 0,
  `r_ast`         INT          NOT NULL DEFAULT 0,
  `r_stl`         INT          NOT NULL DEFAULT 0,
  `r_blk`         INT          NOT NULL DEFAULT 0,
  `r_tvr`         INT          NOT NULL DEFAULT 0,
  `r_oo`          INT          NOT NULL DEFAULT 0,
  `r_do`          INT          NOT NULL DEFAULT 0,
  `r_po`          INT          NOT NULL DEFAULT 0,
  `r_to`          INT          NOT NULL DEFAULT 0,
  `r_od`          INT          NOT NULL DEFAULT 0,
  `r_dd`          INT          NOT NULL DEFAULT 0,
  `r_pd`          INT          NOT NULL DEFAULT 0,
  `r_td`          INT          NOT NULL DEFAULT 0,
  `salary`        INT          NOT NULL DEFAULT 0,
  `talent`        INT          NOT NULL DEFAULT 0,
  `skill`         INT          NOT NULL DEFAULT 0,
  `intangibles`   INT          NOT NULL DEFAULT 0,
  `tsi_sum`       INT          NOT NULL DEFAULT 0,
  `clutch`        INT          NOT NULL DEFAULT 0,
  `consistency`   INT          NOT NULL DEFAULT 0,
  `age`           INT          NOT NULL DEFAULT 0,
  `peak`          INT          NOT NULL DEFAULT 0,
  `cy1`           INT          NOT NULL DEFAULT 0,
  `cy2`           INT          NOT NULL DEFAULT 0,
  `cy3`           INT          NOT NULL DEFAULT 0,
  `cy4`           INT          NOT NULL DEFAULT 0,
  `cy5`           INT          NOT NULL DEFAULT 0,
  `cy6`           INT          NOT NULL DEFAULT 0,
  `phantom_games` INT          NOT NULL DEFAULT 0,
  PRIMARY KEY (`pid`, `year`),
  KEY `idx_teamid_year` (`teamid`, `year`),
  KEY `idx_year`        (`year`),
  KEY `idx_name`        (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 3: Populate from existing snapshots (same query as the former VIEW)
INSERT INTO `ibl_hist`
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

-- Step 4: Recreate vw_career_totals (reads from real table now)
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
