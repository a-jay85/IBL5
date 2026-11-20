-- Map of tracked GitHub bug issues (a-jay85/IBL5) to their Discord origin, plus close-notice state.
-- 26 of the 29 migrated issues have no ibl_bug_reports row, so the map cannot live on that table.
-- No foreign key to ibl_bug_reports: prod syncs have wiped that table before.
-- Rollback: DROP TABLE ibl_bug_issue_notices;
CREATE TABLE IF NOT EXISTS ibl_bug_issue_notices (
  issue_number INT UNSIGNED NOT NULL PRIMARY KEY,
  legacy_issue_number INT UNSIGNED NULL,
  reporter_discord_id BIGINT UNSIGNED NULL,
  channel_id BIGINT UNSIGNED NULL,
  thread_id BIGINT UNSIGNED NULL,
  original_message_id BIGINT UNSIGNED NULL,
  notice_state ENUM('pending','claimed','notified','seeded','failed','skipped') NOT NULL DEFAULT 'pending',
  notice_attempts TINYINT UNSIGNED NOT NULL DEFAULT 0,
  notified_reason VARCHAR(16) NULL,
  claimed_at DATETIME NULL,
  notified_at DATETIME NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_legacy_issue (legacy_issue_number),
  KEY idx_notice_state (notice_state)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
