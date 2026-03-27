-- Migration 078: Add UNIQUE KEYs for idempotent upserts on award tables
-- Required for INSERT ... ON DUPLICATE KEY UPDATE to function correctly in
-- JsbImportRepository::upsertAward() and LeagueControlPanelRepository::upsertAward()
--
-- ibl_awards needs (year, Award, name) because team awards share the same
-- (year, Award) across 5 different players (e.g., "All-League First Team").
-- ibl_gm_awards also needs (year, Award, name) because ASG awards have
-- multiple winners per year (2 head coaches, 4 assistant coaches).

-- 0. Clean up any duplicate awards before adding UNIQUE constraint
DELETE a1 FROM ibl_awards a1
  INNER JOIN ibl_awards a2
  ON a1.year = a2.year AND a1.Award = a2.Award AND a1.name = a2.name
     AND a1.table_ID < a2.table_ID;

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

-- 2. ibl_gm_awards: clean up duplicates, then UNIQUE on (year, Award, name)
DELETE a1 FROM ibl_gm_awards a1
  INNER JOIN ibl_gm_awards a2
  ON a1.year = a2.year AND a1.Award = a2.Award AND a1.name = a2.name
     AND a1.table_ID < a2.table_ID;

SET @exists = (SELECT COUNT(*) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_gm_awards'
                 AND INDEX_NAME = 'uk_year_award_name');
SET @sql = IF(@exists = 0,
              'ALTER TABLE ibl_gm_awards ADD UNIQUE KEY uk_year_award_name (year, Award, name)',
              'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
