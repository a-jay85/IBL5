-- Migration 037: Create RCB (Record Book) tables
-- Tables for structured data parsed from JSB engine .rcb files.
-- Stores all-time records (league-wide + per-team) and current season single-game records.

CREATE TABLE IF NOT EXISTS `ibl_rcb_alltime_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `scope` ENUM('league', 'team') NOT NULL,
    `team_id` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 for league scope; JSB team ID 1-28 for team scope',
    `record_type` ENUM('single_season', 'career') NOT NULL,
    `stat_category` ENUM('ppg','pts','rpg','trb','apg','ast','spg','stl','bpg','blk','fg_pct','ft_pct','three_pct') NOT NULL,
    `ranking` TINYINT UNSIGNED NOT NULL COMMENT 'Ranking position 1-50',
    `player_name` VARCHAR(33) NOT NULL COMMENT 'Player name from .rcb file (right-justified, trimmed)',
    `car_block_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Block ID from .car file for player cross-reference',
    `pid` INT DEFAULT NULL COMMENT 'Resolved FK to ibl_plr.pid',
    `stat_value` DECIMAL(10,4) NOT NULL COMMENT 'Decoded value: per-game avg or percentage',
    `stat_raw` INT NOT NULL COMMENT 'Raw encoded value from .rcb file',
    `team_of_record` TINYINT UNSIGNED DEFAULT NULL COMMENT 'JSB team ID when record was set',
    `season_year` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Season year (single_season records only)',
    `career_total` INT DEFAULT NULL COMMENT 'Career counting total (career records only)',
    `source_file` VARCHAR(128) DEFAULT NULL COMMENT 'Source file label for auditing',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_record` (`scope`, `team_id`, `record_type`, `stat_category`, `ranking`),
    KEY `idx_pid` (`pid`),
    KEY `idx_team` (`team_id`),
    KEY `idx_stat_type` (`stat_category`, `record_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ibl_rcb_season_records` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_year` SMALLINT UNSIGNED NOT NULL COMMENT 'IBL season year this data belongs to',
    `scope` ENUM('league', 'team') NOT NULL,
    `team_id` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT '0 for league scope; JSB team ID 1-28 for team scope',
    `context` ENUM('home', 'away') NOT NULL,
    `stat_category` ENUM('pts','reb','ast','stl','blk','two_gm','three_gm','ftm') NOT NULL,
    `ranking` TINYINT UNSIGNED NOT NULL COMMENT 'Ranking position 1-10',
    `player_name` VARCHAR(33) NOT NULL COMMENT 'Player name from .rcb file',
    `player_position` VARCHAR(2) DEFAULT NULL COMMENT 'Position code (PG, SG, SF, PF, C)',
    `car_block_id` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Block ID from .car file',
    `pid` INT DEFAULT NULL COMMENT 'Resolved FK to ibl_plr.pid',
    `stat_value` SMALLINT UNSIGNED NOT NULL COMMENT 'Single-game stat count',
    `record_season_year` SMALLINT UNSIGNED NOT NULL COMMENT 'Season year when the record performance occurred',
    `source_file` VARCHAR(128) DEFAULT NULL COMMENT 'Source file label for auditing',
    `updated_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_record` (`season_year`, `scope`, `team_id`, `context`, `stat_category`, `ranking`),
    KEY `idx_pid` (`pid`),
    KEY `idx_season` (`season_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
