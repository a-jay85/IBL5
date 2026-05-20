-- Migration 128: Tier 2.5 — unify career stat column names with their season counterparts.
-- Extends ADR-0009 (migration 114) to career columns on ibl_plr / ibl_plr_snapshots /
-- ibl_olympics_plr. Same enforcement pattern: SchemaValidator boot assertions +
-- BanInconsistentColumnNamesRule.
--
-- Renames:
--   car_to  → car_tvr  (matches stats_tvr from migration 114)
--   car_tgm → car_3gm  (matches game_3gm / stats counting; ibl_hist already uses 3GM)
--   car_tga → car_3ga

-- -------------------------------------------------------
-- ibl_plr
-- -------------------------------------------------------
ALTER TABLE `ibl_plr`
  CHANGE COLUMN `car_to`  `car_tvr` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career turnovers',
  CHANGE COLUMN `car_tgm` `car_3gm` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PM',
  CHANGE COLUMN `car_tga` `car_3ga` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PA';

-- -------------------------------------------------------
-- ibl_plr_snapshots
-- -------------------------------------------------------
ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `car_to`  `car_tvr` mediumint(8) unsigned NOT NULL DEFAULT 0,
  CHANGE COLUMN `car_tgm` `car_3gm` mediumint(8) unsigned NOT NULL DEFAULT 0,
  CHANGE COLUMN `car_tga` `car_3ga` mediumint(8) unsigned NOT NULL DEFAULT 0;

-- -------------------------------------------------------
-- ibl_olympics_plr
-- -------------------------------------------------------
ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `car_to`  `car_tvr` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career turnovers',
  CHANGE COLUMN `car_tgm` `car_3gm` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PM',
  CHANGE COLUMN `car_tga` `car_3ga` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PA';

-- -------------------------------------------------------
-- View refresh: vw_player_career_stats references car_tgm and car_tga.
-- MariaDB resolves column names case-insensitively within the view body,
-- but the physical column has been renamed; recreate with new names.
-- -------------------------------------------------------
CREATE OR REPLACE VIEW `vw_player_career_stats` AS
SELECT
  `p`.`uuid`        AS `player_uuid`,
  `p`.`pid`         AS `pid`,
  `p`.`name`        AS `name`,
  `p`.`car_gm`      AS `career_games`,
  `p`.`car_min`     AS `career_minutes`,
  ROUND(`p`.`car_fgm` * 2 + `p`.`car_3gm` + `p`.`car_ftm`, 0) AS `career_points`,
  `p`.`car_orb` + `p`.`car_drb` AS `career_rebounds`,
  `p`.`car_ast`     AS `career_assists`,
  `p`.`car_stl`     AS `career_steals`,
  `p`.`car_blk`     AS `career_blocks`,
  ROUND((`p`.`car_fgm` * 2 + `p`.`car_3gm` + `p`.`car_ftm`) / NULLIF(`p`.`car_gm`, 0), 1) AS `ppg_career`,
  ROUND((`p`.`car_orb` + `p`.`car_drb`) / NULLIF(`p`.`car_gm`, 0), 1) AS `rpg_career`,
  ROUND(`p`.`car_ast` / NULLIF(`p`.`car_gm`, 0), 1) AS `apg_career`,
  ROUND(`p`.`car_fgm` / NULLIF(`p`.`car_fga`, 0), 3) AS `fg_pct_career`,
  ROUND(`p`.`car_ftm` / NULLIF(`p`.`car_fta`, 0), 3) AS `ft_pct_career`,
  ROUND(`p`.`car_3gm` / NULLIF(`p`.`car_3ga`, 0), 3) AS `three_pt_pct_career`,
  `p`.`car_playoff_min` AS `playoff_minutes`,
  `p`.`draftyear`   AS `draft_year`,
  `p`.`draftround`  AS `draft_round`,
  `p`.`draftpickno` AS `draft_pick`,
  `p`.`draftedby`   AS `drafted_by_team`,
  `p`.`created_at`  AS `created_at`,
  `p`.`updated_at`  AS `updated_at`
FROM `ibl_plr` `p`;
