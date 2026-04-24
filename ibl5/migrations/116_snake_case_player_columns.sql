-- Migration 116: Tier 3a cosmetic case-consistency renames.
--
-- Builds on migration 113 (reserved-word renames) and 114 (cross-table
-- unification). ADR-0010 covers the full Tier 3 roadmap; this migration is
-- PR 1 of 4.
--
-- Scope:
--   1. Player rating columns PascalCase/camelCase → snake_case
--      (Clutch, Consistency, {pos}Depth, dc_{pos}Depth, dc_canPlayInGame,
--      playingTime) across the player + depth-chart family.
--   2. `sta` → `stamina` (self-documenting) on player tables + draft class.
--   3. `cache.key` / `cache_locks.key` → `cache_key`
--      (Laravel-style columns kept a SQL reserved word).
--
-- Tables touched (8):
--   * ibl_plr
--   * ibl_plr_snapshots
--   * ibl_olympics_plr
--   * ibl_saved_depth_chart_players
--   * ibl_olympics_saved_depth_chart_players
--   * ibl_draft_class
--   * cache
--   * cache_locks
--
-- View regeneration: `vw_player_current` references `p.dc_canPlayInGame` —
-- recreated at the bottom with the new column name. No other views, triggers,
-- or generated columns reference the renamed columns (verified via
-- information_schema).
--
-- Out of scope: `cy1`-`cy6` → `salary_yr1`-`salary_yr6` (Tier 4 — 288 prod
-- hits). `gameMIN`/`gameTOV`/box-score PascalCase family (Tier 6). Team-info
-- and standings camelCase (Tier 3b / 3c).
--
-- Enforcement: new PHPStan rule `BanNonSnakeCaseColumnsRule` (identifier
-- `ibl.bannedNonSnakeCaseColumn`) flags backtick-quoted references to old
-- names. `BanReservedWordColumnsRule` is extended with `` `key` ``. New
-- assertions added to `config/schema-assertions.php` for every renamed column.

-- ============================================================
-- ibl_plr (15 columns)
-- ============================================================

ALTER TABLE `ibl_plr`
  CHANGE COLUMN `Clutch`           `clutch`              tinyint(4)                   DEFAULT NULL COMMENT 'Clutch performance rating',
  CHANGE COLUMN `Consistency`      `consistency`         tinyint(4)                   DEFAULT NULL COMMENT 'Consistency rating',
  CHANGE COLUMN `PGDepth`          `pg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Point guard depth',
  CHANGE COLUMN `SGDepth`          `sg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Shooting guard depth',
  CHANGE COLUMN `SFDepth`          `sf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Small forward depth',
  CHANGE COLUMN `PFDepth`          `pf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Power forward depth',
  CHANGE COLUMN `CDepth`           `c_depth`             tinyint(3) unsigned          DEFAULT 0    COMMENT 'Center depth',
  CHANGE COLUMN `dc_PGDepth`       `dc_pg_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC point guard depth',
  CHANGE COLUMN `dc_SGDepth`       `dc_sg_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC shooting guard depth',
  CHANGE COLUMN `dc_SFDepth`       `dc_sf_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC small forward depth',
  CHANGE COLUMN `dc_PFDepth`       `dc_pf_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC power forward depth',
  CHANGE COLUMN `dc_CDepth`        `dc_c_depth`          tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC center depth',
  CHANGE COLUMN `dc_canPlayInGame` `dc_can_play_in_game` tinyint(4)          NOT NULL DEFAULT 1    COMMENT 'Can play in game (1=yes)',
  CHANGE COLUMN `playingTime`      `playing_time`        tinyint(4)                   DEFAULT NULL COMMENT 'FA pref: playing time weight',
  CHANGE COLUMN `sta`              `stamina`             tinyint(3) unsigned          DEFAULT 0    COMMENT 'Stamina rating';

-- ============================================================
-- ibl_plr_snapshots (7 columns — clutch/consistency already lowercase)
-- ============================================================

ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `PGDepth`          `pg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Point guard depth',
  CHANGE COLUMN `SGDepth`          `sg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Shooting guard depth',
  CHANGE COLUMN `SFDepth`          `sf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Small forward depth',
  CHANGE COLUMN `PFDepth`          `pf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Power forward depth',
  CHANGE COLUMN `CDepth`           `c_depth`             tinyint(3) unsigned          DEFAULT 0    COMMENT 'Center depth',
  CHANGE COLUMN `dc_canPlayInGame` `dc_can_play_in_game` tinyint(4)          NOT NULL DEFAULT 1    COMMENT 'Can play in game (1=yes)',
  CHANGE COLUMN `playingTime`      `playing_time`        tinyint(4)          NOT NULL DEFAULT 0    COMMENT 'FA pref: playing time weight';

-- ============================================================
-- ibl_olympics_plr (15 columns)
-- ============================================================

ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `Clutch`           `clutch`              tinyint(4)                   DEFAULT NULL COMMENT 'Clutch performance rating',
  CHANGE COLUMN `Consistency`      `consistency`         tinyint(4)                   DEFAULT NULL COMMENT 'Consistency rating',
  CHANGE COLUMN `PGDepth`          `pg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Point guard depth',
  CHANGE COLUMN `SGDepth`          `sg_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Shooting guard depth',
  CHANGE COLUMN `SFDepth`          `sf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Small forward depth',
  CHANGE COLUMN `PFDepth`          `pf_depth`            tinyint(3) unsigned          DEFAULT 0    COMMENT 'Power forward depth',
  CHANGE COLUMN `CDepth`           `c_depth`             tinyint(3) unsigned          DEFAULT 0    COMMENT 'Center depth',
  CHANGE COLUMN `dc_PGDepth`       `dc_pg_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC point guard depth',
  CHANGE COLUMN `dc_SGDepth`       `dc_sg_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC shooting guard depth',
  CHANGE COLUMN `dc_SFDepth`       `dc_sf_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC small forward depth',
  CHANGE COLUMN `dc_PFDepth`       `dc_pf_depth`         tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC power forward depth',
  CHANGE COLUMN `dc_CDepth`        `dc_c_depth`          tinyint(3) unsigned          DEFAULT 0    COMMENT 'DC center depth',
  CHANGE COLUMN `dc_canPlayInGame` `dc_can_play_in_game` tinyint(4)          NOT NULL DEFAULT 1    COMMENT 'Can play in game (1=yes)',
  CHANGE COLUMN `playingTime`      `playing_time`        tinyint(4)                   DEFAULT NULL COMMENT 'FA pref: playing time weight',
  CHANGE COLUMN `sta`              `stamina`             tinyint(3) unsigned          DEFAULT 0    COMMENT 'Stamina rating';

-- ============================================================
-- ibl_saved_depth_chart_players (6 columns)
-- ============================================================

ALTER TABLE `ibl_saved_depth_chart_players`
  CHANGE COLUMN `dc_PGDepth`       `dc_pg_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC point guard depth',
  CHANGE COLUMN `dc_SGDepth`       `dc_sg_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC shooting guard depth',
  CHANGE COLUMN `dc_SFDepth`       `dc_sf_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC small forward depth',
  CHANGE COLUMN `dc_PFDepth`       `dc_pf_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC power forward depth',
  CHANGE COLUMN `dc_CDepth`        `dc_c_depth`          tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC center depth',
  CHANGE COLUMN `dc_canPlayInGame` `dc_can_play_in_game` tinyint(4)          NOT NULL DEFAULT 1    COMMENT 'Can play in game (1=yes)';

-- ============================================================
-- ibl_olympics_saved_depth_chart_players (6 columns)
-- ============================================================

ALTER TABLE `ibl_olympics_saved_depth_chart_players`
  CHANGE COLUMN `dc_PGDepth`       `dc_pg_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC point guard depth',
  CHANGE COLUMN `dc_SGDepth`       `dc_sg_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC shooting guard depth',
  CHANGE COLUMN `dc_SFDepth`       `dc_sf_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC small forward depth',
  CHANGE COLUMN `dc_PFDepth`       `dc_pf_depth`         tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC power forward depth',
  CHANGE COLUMN `dc_CDepth`        `dc_c_depth`          tinyint(3) unsigned NOT NULL DEFAULT 0    COMMENT 'DC center depth',
  CHANGE COLUMN `dc_canPlayInGame` `dc_can_play_in_game` tinyint(4)          NOT NULL DEFAULT 1    COMMENT 'Can play in game (1=yes)';

-- ============================================================
-- ibl_draft_class (1 column)
-- ============================================================

ALTER TABLE `ibl_draft_class`
  CHANGE COLUMN `sta` `stamina` int(11) DEFAULT 0 COMMENT 'Stamina rating';

-- ============================================================
-- cache tables: `key` → `cache_key` (reserved-word fix).
-- Both columns are PRIMARY KEY; CHANGE COLUMN preserves the PK.
-- ============================================================

ALTER TABLE `cache`
  CHANGE COLUMN `key` `cache_key` varchar(255) NOT NULL;

ALTER TABLE `cache_locks`
  CHANGE COLUMN `key` `cache_key` varchar(255) NOT NULL;

-- ============================================================
-- View regeneration — `vw_player_current` referenced `p.dc_canPlayInGame`.
-- ============================================================

CREATE OR REPLACE VIEW `vw_player_current` AS
SELECT `p`.`uuid`              AS `player_uuid`,
       `p`.`pid`               AS `pid`,
       `p`.`name`              AS `name`,
       `p`.`nickname`          AS `nickname`,
       `p`.`age`               AS `age`,
       `p`.`pos`               AS `position`,
       `p`.`htft`              AS `htft`,
       `p`.`htin`              AS `htin`,
       `p`.`dc_can_play_in_game` AS `dc_can_play_in_game`,
       `p`.`retired`           AS `retired`,
       `p`.`exp`               AS `experience`,
       `p`.`bird`              AS `bird_rights`,
       `t`.`uuid`              AS `team_uuid`,
       `t`.`teamid`            AS `teamid`,
       `t`.`team_city`         AS `team_city`,
       `t`.`team_name`         AS `team_name`,
       `t`.`owner_name`        AS `owner_name`,
       CONCAT(`t`.`team_city`, ' ', `t`.`team_name`) AS `full_team_name`,
       `p`.`cy`                AS `contract_year`,
       CASE `p`.`cy`
           WHEN 1 THEN `p`.`cy1`
           WHEN 2 THEN `p`.`cy2`
           WHEN 3 THEN `p`.`cy3`
           WHEN 4 THEN `p`.`cy4`
           WHEN 5 THEN `p`.`cy5`
           WHEN 6 THEN `p`.`cy6`
           ELSE 0
       END AS `current_salary`,
       `p`.`cy1`               AS `year1_salary`,
       `p`.`cy2`               AS `year2_salary`,
       `p`.`cy3`               AS `year3_salary`,
       `p`.`cy4`               AS `year4_salary`,
       `p`.`cy5`               AS `year5_salary`,
       `p`.`cy6`               AS `year6_salary`,
       `p`.`stats_gm`          AS `games_played`,
       `p`.`stats_min`         AS `minutes_played`,
       `p`.`stats_fgm`         AS `field_goals_made`,
       `p`.`stats_fga`         AS `field_goals_attempted`,
       `p`.`stats_ftm`         AS `free_throws_made`,
       `p`.`stats_fta`         AS `free_throws_attempted`,
       `p`.`stats_3gm`         AS `three_pointers_made`,
       `p`.`stats_3ga`         AS `three_pointers_attempted`,
       `p`.`stats_orb`         AS `offensive_rebounds`,
       `p`.`stats_drb`         AS `defensive_rebounds`,
       `p`.`stats_ast`         AS `assists`,
       `p`.`stats_stl`         AS `steals`,
       `p`.`stats_tvr`         AS `turnovers`,
       `p`.`stats_blk`         AS `blocks`,
       `p`.`stats_pf`          AS `personal_fouls`,
       ROUND(`p`.`stats_fgm` / NULLIF(`p`.`stats_fga`, 0), 3) AS `fg_percentage`,
       ROUND(`p`.`stats_ftm` / NULLIF(`p`.`stats_fta`, 0), 3) AS `ft_percentage`,
       ROUND(`p`.`stats_3gm` / NULLIF(`p`.`stats_3ga`, 0), 3) AS `three_pt_percentage`,
       ROUND((`p`.`stats_fgm` * 2 + `p`.`stats_3gm` + `p`.`stats_ftm`) / NULLIF(`p`.`stats_gm`, 0), 1) AS `points_per_game`,
       `p`.`created_at`        AS `created_at`,
       `p`.`updated_at`        AS `updated_at`
FROM `ibl_plr` `p`
LEFT JOIN `ibl_team_info` `t` ON `p`.`teamid` = `t`.`teamid`;
