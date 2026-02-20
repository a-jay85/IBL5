-- Migration 036: Create JSB file import tables
-- Tables for structured data parsed from Jump Shot Basketball engine files (.car, .his, .trn, .asw)

-- Structured transaction log from .trn files
CREATE TABLE IF NOT EXISTS `ibl_jsb_transactions` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_year` SMALLINT UNSIGNED NOT NULL,
    `transaction_month` TINYINT UNSIGNED NOT NULL,
    `transaction_day` TINYINT UNSIGNED NOT NULL,
    `transaction_type` TINYINT UNSIGNED NOT NULL COMMENT '1=injury, 2=trade, 3=waiver_claim, 4=waiver_release',
    `pid` INT NOT NULL DEFAULT 0 COMMENT 'FK to ibl_plr.pid; 0 = no player (e.g. draft pick trade)',
    `player_name` VARCHAR(32) DEFAULT NULL,
    `from_teamid` INT NOT NULL DEFAULT 0 COMMENT '0 = not applicable',
    `to_teamid` INT NOT NULL DEFAULT 0 COMMENT '0 = not applicable',
    `injury_games_missed` SMALLINT UNSIGNED DEFAULT NULL,
    `injury_description` VARCHAR(64) DEFAULT NULL,
    `trade_group_id` INT UNSIGNED DEFAULT NULL COMMENT 'Groups items in same trade',
    `is_draft_pick` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `draft_pick_year` SMALLINT UNSIGNED DEFAULT NULL,
    `source_file` VARCHAR(128) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_season_record` (`season_year`, `transaction_month`, `transaction_day`, `transaction_type`, `pid`, `from_teamid`, `to_teamid`),
    KEY `idx_season` (`season_year`),
    KEY `idx_type` (`transaction_type`),
    KEY `idx_pid` (`pid`),
    KEY `idx_trade_group` (`trade_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Team season results and playoff outcomes from .his files
CREATE TABLE IF NOT EXISTS `ibl_jsb_history` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_year` SMALLINT UNSIGNED NOT NULL,
    `team_name` VARCHAR(32) NOT NULL,
    `teamid` INT DEFAULT NULL COMMENT 'FK to ibl_team_info.teamid',
    `wins` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `losses` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `made_playoffs` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `playoff_result` TEXT DEFAULT NULL COMMENT 'Full result text from .his',
    `playoff_round_reached` VARCHAR(32) DEFAULT NULL COMMENT 'first round, quarter-finals, semi-finals, finals, championship',
    `won_championship` TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `source_file` VARCHAR(128) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_season_team` (`season_year`, `team_name`),
    KEY `idx_teamid` (`teamid`),
    KEY `idx_season` (`season_year`),
    KEY `idx_champion` (`won_championship`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- All-Star Weekend rosters from .asw files
CREATE TABLE IF NOT EXISTS `ibl_jsb_allstar_rosters` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_year` SMALLINT UNSIGNED NOT NULL,
    `event_type` ENUM('allstar_1', 'allstar_2', 'rookie_1', 'rookie_2', 'three_point', 'dunk_contest') NOT NULL,
    `roster_slot` TINYINT UNSIGNED NOT NULL,
    `pid` INT DEFAULT NULL COMMENT 'FK to ibl_plr.pid',
    `player_name` VARCHAR(32) DEFAULT NULL,
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_season_event_slot` (`season_year`, `event_type`, `roster_slot`),
    KEY `idx_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Contest scores from .asw files (3-point shootout and dunk contest)
CREATE TABLE IF NOT EXISTS `ibl_jsb_allstar_scores` (
    `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    `season_year` SMALLINT UNSIGNED NOT NULL,
    `contest_type` ENUM('three_point', 'dunk_contest') NOT NULL,
    `round` TINYINT UNSIGNED NOT NULL COMMENT '1=round1, 2=semifinals, 3=finals',
    `participant_slot` TINYINT UNSIGNED NOT NULL,
    `pid` INT DEFAULT NULL,
    `score` INT NOT NULL COMMENT '3pt: raw count. Dunk: score*10 (932=93.2)',
    `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY `uq_season_contest_round_slot` (`season_year`, `contest_type`, `round`, `participant_slot`),
    KEY `idx_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
