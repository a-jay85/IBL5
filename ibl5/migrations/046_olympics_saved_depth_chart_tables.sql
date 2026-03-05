-- Olympics equivalents of ibl_saved_depth_charts and ibl_saved_depth_chart_players
-- Header table must be created first (players table has FK to it)

CREATE TABLE IF NOT EXISTS `ibl_olympics_saved_depth_charts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL COMMENT 'Team ID (FK to ibl_olympics_team_info)',
  `username` varchar(25) NOT NULL COMMENT 'GM username who saved',
  `name` varchar(100) DEFAULT NULL COMMENT 'User-assigned label',
  `phase` varchar(30) NOT NULL COMMENT 'Season phase at save time',
  `season_year` smallint(5) unsigned NOT NULL COMMENT 'Season ending year',
  `sim_start_date` date NOT NULL COMMENT 'Next sim start date when saved',
  `sim_end_date` date DEFAULT NULL COMMENT 'Extended as sims run',
  `sim_number_start` int(10) unsigned NOT NULL COMMENT 'Sim number when chart was saved',
  `sim_number_end` int(10) unsigned DEFAULT NULL COMMENT 'Latest sim number chart was active',
  `is_active` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1=currently active depth chart',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tid_active` (`tid`,`is_active`),
  KEY `idx_tid_created` (`tid`,`created_at` DESC),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ibl_olympics_saved_depth_chart_players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `depth_chart_id` int(10) unsigned NOT NULL COMMENT 'FK to ibl_olympics_saved_depth_charts.id',
  `pid` int(11) NOT NULL COMMENT 'Player ID at save time',
  `player_name` varchar(64) NOT NULL COMMENT 'Snapshot for historical display',
  `ordinal` int(11) NOT NULL DEFAULT 0 COMMENT 'Roster sort order at save time',
  `dc_PGDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Point guard depth setting',
  `dc_SGDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Shooting guard depth setting',
  `dc_SFDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Small forward depth setting',
  `dc_PFDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Power forward depth setting',
  `dc_CDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Center depth setting',
  `dc_active` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Active flag at save time',
  `dc_minutes` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Minutes setting at save time',
  `dc_of` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Offensive focus at save time',
  `dc_df` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Defensive focus at save time',
  `dc_oi` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Offensive importance at save time',
  `dc_di` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Defensive importance at save time',
  `dc_bh` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Ball handling at save time',
  PRIMARY KEY (`id`),
  KEY `idx_depth_chart_id` (`depth_chart_id`),
  KEY `idx_pid` (`pid`),
  CONSTRAINT `fk_olympics_saved_dc_header` FOREIGN KEY (`depth_chart_id`) REFERENCES `ibl_olympics_saved_depth_charts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
