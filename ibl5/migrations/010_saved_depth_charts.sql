-- Migration: Add saved depth charts tables
-- Purpose: Persistent depth chart snapshots for recall, tracking, and analytics
--
-- Player ratings are NOT snapshotted here. For analysis, join with ibl_hist
-- on pid + season_year to get position ratings and JSB skill ratings.

CREATE TABLE `ibl_saved_depth_charts` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tid` INT NOT NULL,
  `username` VARCHAR(25) NOT NULL,
  `name` VARCHAR(100) DEFAULT NULL COMMENT 'User-assigned label',
  `phase` VARCHAR(30) NOT NULL COMMENT 'Season phase at save time',
  `season_year` SMALLINT UNSIGNED NOT NULL COMMENT 'Season ending year',
  `sim_start_date` DATE NOT NULL COMMENT 'Next sim start date when saved',
  `sim_end_date` DATE DEFAULT NULL COMMENT 'Extended as sims run',
  `sim_number_start` INT UNSIGNED NOT NULL,
  `sim_number_end` INT UNSIGNED DEFAULT NULL,
  `is_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tid_active` (`tid`, `is_active`),
  KEY `idx_tid_created` (`tid`, `created_at` DESC),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE `ibl_saved_depth_chart_players` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `depth_chart_id` INT UNSIGNED NOT NULL,
  `pid` INT NOT NULL,
  `player_name` VARCHAR(64) NOT NULL COMMENT 'Snapshot for historical display',
  `ordinal` INT NOT NULL DEFAULT 0,
  -- Depth chart settings (the independent variables for analysis)
  `dc_PGDepth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_SGDepth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_SFDepth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_PFDepth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_CDepth` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_active` TINYINT UNSIGNED NOT NULL DEFAULT 1,
  `dc_minutes` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_of` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_df` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `dc_oi` TINYINT NOT NULL DEFAULT 0,
  `dc_di` TINYINT NOT NULL DEFAULT 0,
  `dc_bh` TINYINT NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  KEY `idx_depth_chart_id` (`depth_chart_id`),
  KEY `idx_pid` (`pid`),
  CONSTRAINT `fk_saved_dc_header` FOREIGN KEY (`depth_chart_id`)
    REFERENCES `ibl_saved_depth_charts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
