CREATE TABLE IF NOT EXISTS `ibl_analytics_player_peaks` (
    `pid`               INT NOT NULL,
    `peak_season_year`  SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Season with highest PPG',
    `peak_ppg`          DECIMAL(5,1) DEFAULT NULL COMMENT 'Highest single-season PPG',
    `career_ppg`        DECIMAL(5,1) DEFAULT NULL COMMENT 'Career PPG average',
    `career_seasons`    SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `created_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`pid`),
    INDEX `idx_peak_ppg` (`peak_ppg`),
    INDEX `idx_career_ppg` (`career_ppg`),
    CONSTRAINT `fk_player_peaks_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DuckDB analytics write-back: player career peaks';
