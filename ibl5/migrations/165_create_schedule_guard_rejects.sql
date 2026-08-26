-- Migration 165 — schedule guard reject audit table.
-- Forward-only, purely additive: one new table. No destructive DDL, no data transform.

CREATE TABLE IF NOT EXISTS `schedule_guard_rejects` (
    `id`                      INT UNSIGNED      NOT NULL AUTO_INCREMENT,
    `rejected_at`             DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'When the import run recorded this reject.',
    `season_year`             SMALLINT UNSIGNED NOT NULL COMMENT 'Operating season the guard was scoped to — matches ibl_schedule.season_year.',
    `game_date`               DATE              NOT NULL COMMENT 'Decoded game date from the .sco record.',
    `visitor_teamid`          INT               NOT NULL COMMENT 'Decoded visitor team id.',
    `home_teamid`             INT               NOT NULL COMMENT 'Decoded home team id.',
    `game_of_that_day`        INT               NOT NULL DEFAULT 0 COMMENT '1-based league-wide ordinal within the date, as decoded (NOT a per-matchup counter).',
    `reason`                  VARCHAR(32)       NOT NULL COMMENT 'ScheduleMembershipGuard reason constant, e.g. not-in-schedule / duplicate-triple.',
    `stored_game_of_that_day` VARCHAR(64)       NOT NULL DEFAULT '' COMMENT 'Comma-joined ordinals already stored for this triple; populated for duplicate-triple only.',
    `source_archive`          VARCHAR(255)      NOT NULL DEFAULT '' COMMENT 'Basename of the archive or .sco the run read, from JsbSourceResolver::describeLastSource().',
    PRIMARY KEY (`id`),
    INDEX `idx_season_rejected` (`season_year`, `rejected_at`) COMMENT 'The listing query: most recent rejects for a season.',
    INDEX `idx_triple` (`game_date`, `visitor_teamid`, `home_teamid`) COMMENT 'Forensic lookup: was this specific game ever blocked?'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
