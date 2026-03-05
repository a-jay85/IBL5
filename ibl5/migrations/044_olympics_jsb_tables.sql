-- Migration 044: Create Olympics equivalents of JSB import tables
-- These tables mirror their IBL counterparts for Olympics league data.
-- The .plr, .car, .trn, .his, and .rcb file parsers write to these
-- when running in Olympics context.

-- Olympics player file table (mirrors ibl_plr)
-- IBL-specific columns (contracts, bird rights) are populated by JSB engine
-- but not displayed for Olympics.
CREATE TABLE IF NOT EXISTS `ibl_olympics_plr` LIKE `ibl_plr`;

-- Olympics career/historical stats (mirrors ibl_hist)
-- Distinct from ibl_olympics_stats (different schema — .car import needs
-- ratings/salary columns that ibl_olympics_stats lacks).
CREATE TABLE IF NOT EXISTS `ibl_olympics_hist` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `year` smallint unsigned NOT NULL DEFAULT '0',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `teamid` int NOT NULL DEFAULT '0',
  `games` smallint unsigned NOT NULL DEFAULT '0',
  `minutes` mediumint unsigned NOT NULL DEFAULT '0',
  `fgm` smallint unsigned NOT NULL DEFAULT '0',
  `fga` smallint unsigned NOT NULL DEFAULT '0',
  `ftm` smallint unsigned NOT NULL DEFAULT '0',
  `fta` smallint unsigned NOT NULL DEFAULT '0',
  `tgm` smallint unsigned NOT NULL DEFAULT '0',
  `tga` smallint unsigned NOT NULL DEFAULT '0',
  `orb` smallint unsigned NOT NULL DEFAULT '0',
  `reb` smallint unsigned NOT NULL DEFAULT '0',
  `ast` smallint unsigned NOT NULL DEFAULT '0',
  `stl` smallint unsigned NOT NULL DEFAULT '0',
  `blk` smallint unsigned NOT NULL DEFAULT '0',
  `tvr` smallint unsigned NOT NULL DEFAULT '0',
  `pf` smallint unsigned NOT NULL DEFAULT '0',
  `pts` smallint unsigned NOT NULL DEFAULT '0',
  `r_2ga` int NOT NULL DEFAULT '0',
  `r_2gp` int NOT NULL DEFAULT '0',
  `r_fta` int NOT NULL DEFAULT '0',
  `r_ftp` int NOT NULL DEFAULT '0',
  `r_3ga` int NOT NULL DEFAULT '0',
  `r_3gp` int NOT NULL DEFAULT '0',
  `r_orb` int NOT NULL DEFAULT '0',
  `r_drb` int NOT NULL DEFAULT '0',
  `r_ast` int NOT NULL DEFAULT '0',
  `r_stl` int NOT NULL DEFAULT '0',
  `r_blk` int NOT NULL DEFAULT '0',
  `r_tvr` int NOT NULL DEFAULT '0',
  `r_oo` int NOT NULL DEFAULT '0',
  `r_do` int NOT NULL DEFAULT '0',
  `r_po` int NOT NULL DEFAULT '0',
  `r_to` int NOT NULL DEFAULT '0',
  `r_od` int NOT NULL DEFAULT '0',
  `r_dd` int NOT NULL DEFAULT '0',
  `r_pd` int NOT NULL DEFAULT '0',
  `r_td` int NOT NULL DEFAULT '0',
  `salary` int NOT NULL DEFAULT '0',
  `nuke_iblhist` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nuke_iblhist`),
  UNIQUE KEY `unique_composite_key` (`pid`,`name`,`year`),
  KEY `idx_pid_year` (`pid`,`year`),
  KEY `idx_team_year` (`team`,`year`),
  KEY `idx_teamid_year` (`teamid`,`year`),
  KEY `idx_year` (`year`),
  KEY `idx_pid_year_team` (`pid`,`year`,`team`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Olympics JSB history (mirrors ibl_jsb_history)
CREATE TABLE IF NOT EXISTS `ibl_olympics_jsb_history` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint unsigned NOT NULL,
  `team_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `teamid` int DEFAULT NULL,
  `wins` smallint unsigned NOT NULL DEFAULT '0',
  `losses` smallint unsigned NOT NULL DEFAULT '0',
  `made_playoffs` tinyint unsigned NOT NULL DEFAULT '0',
  `playoff_result` text COLLATE utf8mb4_unicode_ci,
  `playoff_round_reached` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `won_championship` tinyint unsigned NOT NULL DEFAULT '0',
  `source_file` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_team` (`season_year`,`team_name`),
  KEY `idx_teamid` (`teamid`),
  KEY `idx_season` (`season_year`),
  KEY `idx_champion` (`won_championship`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Olympics JSB transactions (mirrors ibl_jsb_transactions)
CREATE TABLE IF NOT EXISTS `ibl_olympics_jsb_transactions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint unsigned NOT NULL,
  `transaction_month` tinyint unsigned NOT NULL,
  `transaction_day` tinyint unsigned NOT NULL,
  `transaction_type` tinyint unsigned NOT NULL,
  `pid` int NOT NULL DEFAULT '0',
  `player_name` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `from_teamid` int NOT NULL DEFAULT '0',
  `to_teamid` int NOT NULL DEFAULT '0',
  `injury_games_missed` smallint unsigned DEFAULT NULL,
  `injury_description` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `trade_group_id` int unsigned DEFAULT NULL,
  `is_draft_pick` tinyint unsigned NOT NULL DEFAULT '0',
  `draft_pick_year` smallint unsigned DEFAULT NULL,
  `source_file` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_record` (`season_year`,`transaction_month`,`transaction_day`,`transaction_type`,`pid`,`from_teamid`,`to_teamid`),
  KEY `idx_season` (`season_year`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_pid` (`pid`),
  KEY `idx_trade_group` (`trade_group_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Olympics RCB all-time records (mirrors ibl_rcb_alltime_records)
CREATE TABLE IF NOT EXISTS `ibl_olympics_rcb_alltime_records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `scope` enum('league','team') COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` tinyint unsigned NOT NULL DEFAULT '0',
  `record_type` enum('single_season','career') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stat_category` enum('ppg','pts','rpg','trb','apg','ast','spg','stl','bpg','blk','fg_pct','ft_pct','three_pct') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ranking` tinyint unsigned NOT NULL,
  `player_name` varchar(33) COLLATE utf8mb4_unicode_ci NOT NULL,
  `car_block_id` smallint unsigned DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `stat_value` decimal(10,4) NOT NULL,
  `stat_raw` int NOT NULL,
  `team_of_record` tinyint unsigned DEFAULT NULL,
  `season_year` smallint unsigned DEFAULT NULL,
  `career_total` int DEFAULT NULL,
  `source_file` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_record` (`scope`,`team_id`,`record_type`,`stat_category`,`ranking`),
  KEY `idx_pid` (`pid`),
  KEY `idx_team` (`team_id`),
  KEY `idx_stat_type` (`stat_category`,`record_type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Olympics RCB season records (mirrors ibl_rcb_season_records)
CREATE TABLE IF NOT EXISTS `ibl_olympics_rcb_season_records` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint unsigned NOT NULL,
  `scope` enum('league','team') COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_id` tinyint unsigned NOT NULL DEFAULT '0',
  `context` enum('home','away') COLLATE utf8mb4_unicode_ci NOT NULL,
  `stat_category` enum('pts','reb','ast','stl','blk','two_gm','three_gm','ftm') COLLATE utf8mb4_unicode_ci NOT NULL,
  `ranking` tinyint unsigned NOT NULL,
  `player_name` varchar(33) COLLATE utf8mb4_unicode_ci NOT NULL,
  `player_position` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `car_block_id` smallint unsigned DEFAULT NULL,
  `pid` int DEFAULT NULL,
  `stat_value` smallint unsigned NOT NULL,
  `record_season_year` smallint unsigned NOT NULL,
  `source_file` varchar(128) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_record` (`season_year`,`scope`,`team_id`,`context`,`stat_category`,`ranking`),
  KEY `idx_pid` (`pid`),
  KEY `idx_season` (`season_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
