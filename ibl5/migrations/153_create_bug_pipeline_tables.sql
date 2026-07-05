-- Discord bug/feature pipeline queue tables (see discord-bug-pipeline-shared-context.md §3a).
-- Forward-only, purely additive: three new tables, no destructive DDL.

CREATE TABLE IF NOT EXISTS `ibl_bug_reports` (
    `id`                  INT UNSIGNED     NOT NULL AUTO_INCREMENT,
    `discord_author_id`   BIGINT UNSIGNED  NOT NULL,
    `channel_id`          BIGINT UNSIGNED  NOT NULL,
    `original_message_id` BIGINT UNSIGNED  NOT NULL,
    `original_text`       TEXT             NOT NULL,
    `thread_id`           BIGINT UNSIGNED  NULL,
    `class`               ENUM('bug','feature','not_a_thing') NULL,
    `status`              ENUM('queued','awaiting_info','hunting','blocked','pr_open','fixed','needs_human','parked_idle','gathering','awaiting_ajay','planned','dropped') NOT NULL DEFAULT 'queued',
    `lease_owner`         VARCHAR(64)      NULL,
    `lease_expires`       DATETIME         NULL,
    `hunt_attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0,
    `pr_number`           INT UNSIGNED     NULL,
    `issue_number`        INT UNSIGNED     NULL,
    `approval_message_id` BIGINT UNSIGNED  NULL,
    `blocked_until`       DATETIME         NULL,
    `last_gm_reply_at`    DATETIME         NULL,
    `last_processed_at`   DATETIME         NULL,
    `reminder_sent_at`    DATETIME         NULL,
    `created_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`          DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    INDEX `idx_status` (`status`),
    INDEX `idx_thread` (`thread_id`),
    INDEX `idx_author` (`discord_author_id`),
    INDEX `idx_lease` (`status`, `lease_expires`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ibl_bug_reporter_profile` (
    `discord_author_id` BIGINT UNSIGNED  NOT NULL,
    `tech_level`        ENUM('technical','nontechnical') NOT NULL,
    `created_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at`        DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`discord_author_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ibl_bug_pipeline_state` (
    `channel_id`                BIGINT UNSIGNED  NOT NULL,
    `last_processed_message_id` BIGINT UNSIGNED  NOT NULL,
    `updated_at`                DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`channel_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
