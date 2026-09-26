-- Migration 188: retag September (preseason) box-score rows.
--
-- game_type: September rows become 4 (Season::IBL_PRESEASON_GAME_TYPE) instead
-- of falling through to ELSE 1 (regular season). Every regular-season, playoff,
-- HEAT and record-holder consumer filters game_type IN a subset of (0,1,2,3),
-- so preseason rows drop out of all of them without per-view patches.
--
-- season_year: MONTH >= 9 (was >= 10) so a September row belongs to the season
-- it precedes (ending year), matching what Preseason-phase pages query with.
--
-- Pattern copied from migration 121: drop every idx_gt_* index first (MariaDB
-- shrinks a composite index on DROP COLUMN instead of dropping it), drop the two
-- generated columns, re-add them with the new expression, re-add the indexes.
-- Base data is untouched; STORED values are recomputed on ADD COLUMN.
-- Olympics box-score tables are intentionally not changed.

-- ============================================================
-- ibl_box_scores
-- ============================================================

ALTER TABLE `ibl_box_scores`
  DROP INDEX IF EXISTS `idx_gt_points`,
  DROP INDEX IF EXISTS `idx_gt_rebounds`,
  DROP INDEX IF EXISTS `idx_gt_fg_made`,
  DROP INDEX IF EXISTS `idx_gt_ast`,
  DROP INDEX IF EXISTS `idx_gt_stl`,
  DROP INDEX IF EXISTS `idx_gt_blk`,
  DROP INDEX IF EXISTS `idx_gt_tov`,
  DROP INDEX IF EXISTS `idx_gt_ftm`,
  DROP INDEX IF EXISTS `idx_gt_3gm`,
  DROP INDEX IF EXISTS `idx_gt_pid`,
  DROP INDEX IF EXISTS `idx_gt_pid_season`;

ALTER TABLE `ibl_box_scores`
  DROP COLUMN IF EXISTS `game_type`,
  DROP COLUMN IF EXISTS `season_year`;

ALTER TABLE `ibl_box_scores`
  ADD COLUMN `game_type`   tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 9 THEN 4 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED AFTER `teamid`,
  ADD COLUMN `season_year` smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 9 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED AFTER `game_type`;

ALTER TABLE `ibl_box_scores`
  ADD KEY `idx_gt_points`     (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds`   (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`    (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`        (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`        (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`        (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`        (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`        (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`        (`game_type`, `game_3gm`),
  ADD KEY `idx_gt_pid`        (`game_type`, `pid`),
  ADD KEY `idx_gt_pid_season` (`game_type`, `pid`, `season_year`);

-- ============================================================
-- ibl_box_scores_teams
-- ============================================================

ALTER TABLE `ibl_box_scores_teams`
  DROP INDEX IF EXISTS `idx_gt_points`,
  DROP INDEX IF EXISTS `idx_gt_rebounds`,
  DROP INDEX IF EXISTS `idx_gt_fg_made`,
  DROP INDEX IF EXISTS `idx_gt_ast`,
  DROP INDEX IF EXISTS `idx_gt_stl`,
  DROP INDEX IF EXISTS `idx_gt_blk`,
  DROP INDEX IF EXISTS `idx_gt_tov`,
  DROP INDEX IF EXISTS `idx_gt_ftm`,
  DROP INDEX IF EXISTS `idx_gt_3gm`,
  DROP INDEX IF EXISTS `idx_gt_date_teams`,
  DROP INDEX IF EXISTS `idx_gt_name_season`;

ALTER TABLE `ibl_box_scores_teams`
  DROP COLUMN IF EXISTS `game_type`,
  DROP COLUMN IF EXISTS `season_year`;

ALTER TABLE `ibl_box_scores_teams`
  ADD COLUMN `game_type`   tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 9 THEN 4 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED AFTER `updated_at`,
  ADD COLUMN `season_year` smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 9 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED AFTER `game_type`;

ALTER TABLE `ibl_box_scores_teams`
  ADD KEY `idx_gt_points`      (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds`    (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`     (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`         (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`         (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`         (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`         (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`         (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`         (`game_type`, `game_3gm`),
  ADD KEY `idx_gt_date_teams`  (`game_type`, `game_date`, `visitor_teamid`, `home_teamid`),
  ADD KEY `idx_gt_name_season` (`game_type`, `name`, `season_year`);
