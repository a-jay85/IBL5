-- Migration 151: Create gm_notification_prefs (preferences storage only).
-- One row per GM account, keyed by auth_users.id (INT UNSIGNED — see migration 101).
-- Read by a future digest sender (separate PR); this migration only stores prefs.
CREATE TABLE IF NOT EXISTS `gm_notification_prefs` (
    `user_id` INT UNSIGNED NOT NULL,
    `notify_trade_offers`         TINYINT(1) NOT NULL DEFAULT 1,
    `notify_waiver_claims`        TINYINT(1) NOT NULL DEFAULT 1,
    `notify_fa_outbids`           TINYINT(1) NOT NULL DEFAULT 1,
    `digest_depth_chart_reminder` TINYINT(1) NOT NULL DEFAULT 0,
    `digest_weekly_transactions`  TINYINT(1) NOT NULL DEFAULT 0,
    `digest_channel_discord`      TINYINT(1) NOT NULL DEFAULT 0,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`user_id`),
    CONSTRAINT `fk_gm_notification_prefs_user`
        FOREIGN KEY (`user_id`) REFERENCES `auth_users`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
