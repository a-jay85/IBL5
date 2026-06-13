-- Migration 146: Create gm_notifications table for the in-app GM notification inbox.
--
-- Generic per-team notification store keyed by team_id (FK to ibl_team_info.teamid).
-- Written through Notifications\NotificationService::notify(); read by the
-- Notifications module page and the nav bell unread-count badge. read_at NULL
-- means unread (a timestamp, not a `read` boolean — `read` is a SQL reserved word
-- and the timestamp gives a mark-read audit for free).

CREATE TABLE IF NOT EXISTS `gm_notifications` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `team_id` INT NOT NULL,              -- matches ibl_team_info.teamid (signed int)
    `type` VARCHAR(40) NOT NULL,
    `message` VARCHAR(500) NOT NULL,
    `link` VARCHAR(255) DEFAULT NULL,
    `read_at` TIMESTAMP NULL DEFAULT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_team_unread` (`team_id`, `read_at`),
    INDEX `idx_team_created` (`team_id`, `created_at`),
    CONSTRAINT `fk_gm_notifications_team`
        FOREIGN KEY (`team_id`) REFERENCES `ibl_team_info` (`teamid`)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
