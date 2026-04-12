-- Mark phantom-game discrepancies in ibl_plr_snapshots.
--
-- 90 player-seasons across 3 seasons (1992: 11, 2000: 59, 2004: 20) have +1
-- phantom game from re-simulated JSB games. The PLR accumulated stats from both
-- simulation runs, but the .sco file only retained the second run's box scores.
-- See ibl5/docs/PLR_VS_BOXSCORES_ANALYSIS.md for the full analysis.
--
-- This migration:
--   1. Adds a phantom_games column to ibl_plr_snapshots
--   2. Marks the 90 affected (pid, season_year) pairs
--   3. Rebuilds the ibl_hist VIEW to subtract phantom_games from games played
--   4. Rebuilds vw_career_totals to propagate the correction

-- Step 1: Add the column
ALTER TABLE ibl_plr_snapshots
  ADD COLUMN phantom_games TINYINT UNSIGNED NOT NULL DEFAULT 0
  AFTER stats_gm;

-- Step 2: Mark affected player-seasons
-- 1992 — Team 7 (Bulls), 11 players, all +1 phantom game
UPDATE ibl_plr_snapshots SET phantom_games = 1
WHERE season_year = 1992 AND pid IN (
  33,   -- Rony Seikaly
  104,  -- Ron Harper
  131,  -- Kelly Tripucka
  303,  -- Winston Garland
  649,  -- Yinka Dare
  666,  -- Dickey Simpkins
  949,  -- Leon Powe
  955,  -- Shelden Williams
  1235, -- Nick Galis
  1237, -- Brandon Ingram
  1278  -- Georgios Papagiannis
);

-- 2000 — Teams 3, 4, 7, 10, 13, 19, 20, 21, 24, 25 — 59 players, all +1
UPDATE ibl_plr_snapshots SET phantom_games = 1
WHERE season_year = 2000 AND pid IN (
  304,  -- Mitch Richmond
  620,  -- Mark Aguirre
  626,  -- Brian Grant
  627,  -- Hanamichi Sakuragi
  636,  -- Randolph Lillehammer
  2422, -- Brian Cardinal
  649,  -- Yinka Dare
  926,  -- Len Bias
  930,  -- Arvydas Macijauskas
  936,  -- Robert Jaworski
  937,  -- Thabo Sefolosha
  950,  -- J.J. Redick
  1230, -- Michael Jordan
  1235, -- Nick Galis
  1236, -- Dejounte Murray
  1239, -- Earl Manigault
  1243, -- Vladimir Tkachenko
  1245, -- Malcolm Brogdon
  1253, -- Pierluigi Marzorati
  1262, -- Taurean Prince
  1479, -- Tim Duncan
  1480, -- Tracy McGrady
  1484, -- Chauncey Billups
  1485, -- Stephen Jackson
  1510, -- Antonio Daniels
  1523, -- Wat Misaka
  1757, -- Mehmet Okur
  1762, -- DeSagana Diop
  1767, -- Jamaal Tinsley
  2007, -- Vern Mikkelsen
  2010, -- Dino Radja
  2016, -- Tyrone Hill
  2435, -- Darius Miles
  2439, -- Josip Sesar
  2445, -- Marko Jaric
  2700, -- Andre Iguodala
  2709, -- Rickey Green II
  2712, -- J.R. Smith
  2720, -- Tree Rollins II
  2721, -- Anderson Varejao
  2979, -- Maurice Stokes
  2982, -- Clyde Lovellette
  2987, -- Doug Christie
  2991, -- Tracy Murray
  2998, -- Oliver Miller
  3282, -- Brandon Tomyoy
  3285, -- Clifford Robinson
  3289, -- Allie Quigley
  3290, -- Sherman Douglas
  3297, -- Michael Ansley
  3555, -- Jermaine ONeal
  3556, -- Nancy Lieberman
  3564, -- Zydrunas Ilgauskas
  3569, -- Sergio Llull
  3577, -- Antoine Walker
  3579, -- Boban Marjanovic
  3581, -- Vitaly Potapenko
  3592, -- Tiffany Hayes
  3596  -- Travis Knight
);

-- 2004 — Teams 1, 2, 23 — 20 players, all +1
UPDATE ibl_plr_snapshots SET phantom_games = 1
WHERE season_year = 2004 AND pid IN (
  1235, -- Nick Galis
  1480, -- Tracy McGrady
  1758, -- Darryl Dawkins
  2714, -- Purvis Short
  2983, -- Ben Wallace
  2989, -- Todd Day
  3279, -- Maurice Cheeks
  3280, -- Mookie Blaylock
  3285, -- Clifford Robinson
  3553, -- Brittney Griner
  3857, -- Ralph Sampson
  4164, -- Vin Baker
  4167, -- Allan Houston
  4496, -- Alex English
  4826, -- Anthony Edwards
  4834, -- Chet Walker
  4843, -- Nick Richards
  4844, -- Jalen Smith
  4845, -- Devin Vassell
  4852  -- Immanuel Quickley
);

-- Step 3: Rebuild ibl_hist VIEW
-- Based on migration 104, with two additions:
--   - Primary branch: games = stats_gm - phantom_games; expose phantom_games
--   - Fallback branch: phantom_games = 0
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
  0 AS cy6,
  0 AS phantom_games
FROM ibl_hist_archive ha
LEFT JOIN (
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

-- Step 4: Rebuild vw_career_totals (depends on ibl_hist)
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
