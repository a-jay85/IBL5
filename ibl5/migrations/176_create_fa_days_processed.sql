-- Migration 176 — free agency day re-run guard.
-- Forward-only, purely additive: one new table. No destructive DDL, no data transform.
-- The composite PRIMARY KEY is the guard: a second attempt at the same
-- (league, season_ending_year, day) is a constraint violation, not an application check.

CREATE TABLE IF NOT EXISTS `ibl_fa_days_processed` (
    `league` VARCHAR(20) NOT NULL COMMENT 'League identifier from LeagueContext (ibl | olympics)',
    `season_ending_year` INT NOT NULL COMMENT 'ibl_settings "Current Season Ending Year" at execution time',
    `day` TINYINT UNSIGNED NOT NULL COMMENT 'Free agency day 1-12 as clamped by block.php',
    `processed_at` DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the day was executed',
    `signings_submitted` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Count of signings in the submitted payload',
    PRIMARY KEY (`league`, `season_ending_year`, `day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
