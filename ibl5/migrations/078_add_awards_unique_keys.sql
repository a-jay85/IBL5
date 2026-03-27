-- Migration 078: Add UNIQUE KEYs for idempotent upserts on award tables
-- Required for INSERT ... ON DUPLICATE KEY UPDATE to function correctly in
-- JsbImportRepository::upsertAward() and LeagueControlPanelRepository::upsertAward()
--
-- ibl_awards needs (year, Award, name) because team awards share the same
-- (year, Award) across 5 different players (e.g., "All-League First Team").
-- ibl_gm_awards uses (year, Award) since each award has exactly one winner.

-- 1. ibl_awards: UNIQUE on (year, Award, name)
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_awards'
                 AND INDEX_NAME = 'uk_year_award_name');
SET @sql = IF(@exists = 0,
              'ALTER TABLE ibl_awards ADD UNIQUE KEY uk_year_award_name (year, Award, name)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 2. ibl_gm_awards: UNIQUE on (year, Award)
SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_gm_awards'
                 AND INDEX_NAME = 'uk_year_award');
SET @sql = IF(@exists = 0,
              'ALTER TABLE ibl_gm_awards ADD UNIQUE KEY uk_year_award (year, Award)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
