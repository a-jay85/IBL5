-- Migration 190: Google Sheets server-push export connections (one row per GM).
--
-- refresh_token_enc holds a Security\SecretBox ciphertext, never plaintext.
-- user_id is nuke_users.user_id, the same identity ibl_api_keys keys on (no FK,
-- matching ibl_api_keys). Idempotent via information_schema.
-- Rollback: DROP TABLE ibl_google_sheet_connections (nothing references it).

SET @tbl_exists = (SELECT COUNT(*) FROM information_schema.TABLES
  WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_google_sheet_connections');
SET @ddl = IF(@tbl_exists = 0, 'CREATE TABLE ibl_google_sheet_connections (
  id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
  user_id INT NOT NULL,
  refresh_token_enc TEXT NOT NULL,
  spreadsheet_id VARCHAR(128) NOT NULL,
  spreadsheet_url VARCHAR(255) NOT NULL,
  status ENUM(''active'',''broken'') NOT NULL DEFAULT ''active'',
  broken_reason VARCHAR(64) NULL,
  refresh_pending TINYINT(1) NOT NULL DEFAULT 0,
  last_refresh_at DATETIME NULL,
  last_refresh_status VARCHAR(32) NULL,
  last_error VARCHAR(255) NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY uq_gsc_user (user_id),
  KEY idx_gsc_pending (status, refresh_pending)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4', 'SELECT 1');
PREPARE s FROM @ddl;
EXECUTE s;
DEALLOCATE PREPARE s;
