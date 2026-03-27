CREATE TABLE IF NOT EXISTS `ibl_analytics_tsi_bands` (
    `pid`                   INT NOT NULL,
    `season_year`           SMALLINT UNSIGNED NOT NULL,
    `tsi_sum`               TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `tsi_band`              ENUM('low','mid','high','elite') NOT NULL DEFAULT 'low',
    `delta_r_2gp`           SMALLINT DEFAULT NULL COMMENT 'Year-over-year FGP rating change',
    `delta_r_ftp`           SMALLINT DEFAULT NULL COMMENT 'Year-over-year FTP rating change',
    `delta_r_ast`           SMALLINT DEFAULT NULL COMMENT 'Year-over-year AST rating change',
    `age_relative_to_peak`  SMALLINT DEFAULT NULL COMMENT 'Estimated age minus peak age',
    `created_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`            TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`pid`, `season_year`),
    INDEX `idx_tsi_band` (`tsi_band`),
    INDEX `idx_season` (`season_year`),
    CONSTRAINT `fk_tsi_bands_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
  COMMENT='DuckDB analytics write-back: TSI progression bands per player-season';
