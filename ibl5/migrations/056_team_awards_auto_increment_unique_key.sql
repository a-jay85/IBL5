-- Migration 056: Add AUTO_INCREMENT to ibl_team_awards.ID and UNIQUE KEY on (year, Award)
-- This enables idempotent upserts via INSERT ... ON DUPLICATE KEY UPDATE

ALTER TABLE ibl_team_awards MODIFY COLUMN ID int(11) NOT NULL AUTO_INCREMENT;

-- Idempotent: only add if the key doesn't already exist (schema.sql may include it)
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_team_awards'
                 AND INDEX_NAME = 'uk_year_award');
SET @sql = IF(@exists = 0,
              'ALTER TABLE ibl_team_awards ADD UNIQUE KEY uk_year_award (year, Award)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
