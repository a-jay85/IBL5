-- Migration: Add generated columns and composite indexes to box score tables
-- Purpose: Eliminate full table scans for RecordHolders page queries

-- Temporarily relax strict mode to allow processing of legacy 0000-00-00 dates
SET @saved_sql_mode = @@sql_mode;
SET sql_mode = 'STRICT_TRANS_TABLES,ERROR_FOR_DIVISION_BY_ZERO,NO_ENGINE_SUBSTITUTION';

-- ============================================================
-- ibl_box_scores: Generated columns + indexes
-- ============================================================
ALTER TABLE `ibl_box_scores`
  ADD COLUMN IF NOT EXISTS `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN IF NOT EXISTS `season_year` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN YEAR(`Date`) = 0 THEN 0
        WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
        ELSE YEAR(`Date`)
      END
    ) STORED AFTER `game_type`,

  ADD COLUMN IF NOT EXISTS `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `season_year`,

  ADD COLUMN IF NOT EXISTS `calc_rebounds` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN IF NOT EXISTS `calc_fg_made` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`,

  ADD INDEX IF NOT EXISTS `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX IF NOT EXISTS `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX IF NOT EXISTS `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX IF NOT EXISTS `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX IF NOT EXISTS `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX IF NOT EXISTS `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX IF NOT EXISTS `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX IF NOT EXISTS `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX IF NOT EXISTS `idx_gt_3gm`       (`game_type`, `game3GM`);

-- ============================================================
-- ibl_box_scores_teams: Generated columns + indexes
-- ============================================================
ALTER TABLE `ibl_box_scores_teams`
  ADD COLUMN IF NOT EXISTS `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN IF NOT EXISTS `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `game_type`,

  ADD COLUMN IF NOT EXISTS `calc_rebounds` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN IF NOT EXISTS `calc_fg_made` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`,

  ADD INDEX IF NOT EXISTS `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX IF NOT EXISTS `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX IF NOT EXISTS `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX IF NOT EXISTS `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX IF NOT EXISTS `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX IF NOT EXISTS `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX IF NOT EXISTS `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX IF NOT EXISTS `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX IF NOT EXISTS `idx_gt_3gm`       (`game_type`, `game3GM`);

-- Restore original SQL mode
SET sql_mode = @saved_sql_mode;
