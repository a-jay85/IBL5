-- Migration 155 — sim recap queue table.
-- Forward-only, purely additive: one new table plus one idempotent seed row. No destructive DDL.

CREATE TABLE IF NOT EXISTS `ibl_sim_summaries` (
    `sim`           INT UNSIGNED     NOT NULL COMMENT 'PK — one row per sim; idempotency key for the queue insert and the seed.',
    `status`        ENUM('pending','generating','done','failed') NOT NULL DEFAULT 'pending' COMMENT 'Lifecycle: pending → generating → done|failed.',
    `recap_text`    MEDIUMTEXT       NULL COMMENT 'The generated prose (up to 16 MB).',
    `themes_used`   JSON             NULL COMMENT 'Anti-repetition ledger — the themes used in this recap, read back over the last 5 sims.',
    `claimed_at`    DATETIME         NULL COMMENT 'When the tick claimed this row for generation.',
    `generated_at`  DATETIME         NULL COMMENT 'When the recap was stored.',
    `attempts`      TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Number of generation attempts; ceiling 2 before → failed.',
    `blocked_until` DATETIME         NULL COMMENT 'Usage-limit backoff — row is not eligible until this time.',
    `created_at`    DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp.',
    PRIMARY KEY (`sim`),
    INDEX `idx_claim` (`status`, `blocked_until`, `sim`) COMMENT 'The composite index the oldest-pending-first selection scans.'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

INSERT IGNORE INTO `ibl_sim_summaries` (`sim`, `status`, `generated_at`)
SELECT MAX(`sim`), 'done', NOW() FROM `ibl_sim_dates` HAVING MAX(`sim`) IS NOT NULL;
