-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 5: Advanced Optimization
-- ============================================================================
-- This migration implements advanced performance optimizations:
-- 1. Table partitioning for historical data (ibl_hist, ibl_box_scores)
-- 2. Additional composite indexes based on usage patterns
-- 3. Column size optimization to reduce storage
-- 4. Query performance tuning
--
-- PREREQUISITES:
-- - Phase 1, 2, 3, and 4 migrations must be completed
-- - InnoDB tables with foreign keys, timestamps, and data type optimizations in place
-- - MySQL 8.0 or higher (for partitioning features)
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 3-5 hours depending on data size
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- Verify MySQL version (8.0+ recommended for optimal partitioning support)
SELECT 'Checking MySQL version (8.0+ recommended for partitioning)...' AS message;
SELECT VERSION() AS mysql_version;

-- ============================================================================
-- PART 1: TABLE PARTITIONING FOR HISTORICAL DATA
-- ============================================================================
-- Partition large historical tables by year for improved query performance
-- and easier data archival
--
-- Benefits:
-- - Faster queries when filtering by year (partition pruning)
-- - Easier archival and backup of old data
-- - Better index performance on smaller partitions
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Partition ibl_hist by year
-- ---------------------------------------------------------------------------
-- Historical player statistics table - partition by season year
-- This allows queries filtering by year to access only relevant partitions

-- Note: Table must not have any existing partitions and must have a PRIMARY KEY
-- or UNIQUE KEY that includes the partitioning column (year)

-- First, check if table has appropriate key structure
-- The year column must be part of the primary key for partitioning to work
-- If it's not, we'll need to add it to a composite key

-- Check current keys on ibl_hist
SELECT 'Checking current key structure on ibl_hist...' AS message;
SHOW KEYS FROM ibl_hist;

-- If year is not part of primary/unique key, add a composite unique key:
-- ALTER TABLE ibl_hist 
--   ADD UNIQUE KEY idx_hist_pid_year (pid, year);

-- Then partition by year (adjust partition ranges based on your data):
-- ALTER TABLE ibl_hist PARTITION BY RANGE (year) (
--   PARTITION p_hist_2000 VALUES LESS THAN (2001),
--   PARTITION p_hist_2001 VALUES LESS THAN (2002),
--   PARTITION p_hist_2002 VALUES LESS THAN (2003),
--   PARTITION p_hist_2003 VALUES LESS THAN (2004),
--   PARTITION p_hist_2004 VALUES LESS THAN (2005),
--   PARTITION p_hist_2005 VALUES LESS THAN (2006),
--   PARTITION p_hist_2006 VALUES LESS THAN (2007),
--   PARTITION p_hist_2007 VALUES LESS THAN (2008),
--   PARTITION p_hist_2008 VALUES LESS THAN (2009),
--   PARTITION p_hist_2009 VALUES LESS THAN (2010),
--   PARTITION p_hist_2010 VALUES LESS THAN (2011),
--   PARTITION p_hist_2011 VALUES LESS THAN (2012),
--   PARTITION p_hist_2012 VALUES LESS THAN (2013),
--   PARTITION p_hist_2013 VALUES LESS THAN (2014),
--   PARTITION p_hist_2014 VALUES LESS THAN (2015),
--   PARTITION p_hist_2015 VALUES LESS THAN (2016),
--   PARTITION p_hist_2016 VALUES LESS THAN (2017),
--   PARTITION p_hist_2017 VALUES LESS THAN (2018),
--   PARTITION p_hist_2018 VALUES LESS THAN (2019),
--   PARTITION p_hist_2019 VALUES LESS THAN (2020),
--   PARTITION p_hist_2020 VALUES LESS THAN (2021),
--   PARTITION p_hist_2021 VALUES LESS THAN (2022),
--   PARTITION p_hist_2022 VALUES LESS THAN (2023),
--   PARTITION p_hist_2023 VALUES LESS THAN (2024),
--   PARTITION p_hist_2024 VALUES LESS THAN (2025),
--   PARTITION p_hist_2025 VALUES LESS THAN (2026),
--   PARTITION p_hist_future VALUES LESS THAN MAXVALUE
-- );

-- Note: Partitioning is commented out by default. Review your data ranges
-- and table structure before enabling. Partitioning requires:
-- 1. The partitioning column (year) must be part of every unique key
-- 2. No foreign keys can reference this table (or they must be adjusted)
-- 3. Consider your query patterns - partitioning helps year-based queries

-- ---------------------------------------------------------------------------
-- Partition ibl_box_scores by Date (year)
-- ---------------------------------------------------------------------------
-- Game box scores - partition by game date for historical data management

-- Check current keys on ibl_box_scores
SELECT 'Checking current key structure on ibl_box_scores...' AS message;
SHOW KEYS FROM ibl_box_scores;

-- Box scores partitioning (by year extracted from Date column)
-- Note: Commented out - requires careful consideration of foreign keys and indexes
-- ALTER TABLE ibl_box_scores PARTITION BY RANGE (YEAR(Date)) (
--   PARTITION p_box_2000 VALUES LESS THAN (2001),
--   PARTITION p_box_2001 VALUES LESS THAN (2002),
--   ... (similar to ibl_hist)
--   PARTITION p_box_future VALUES LESS THAN MAXVALUE
-- );

-- ============================================================================
-- PART 2: ADDITIONAL COMPOSITE INDEXES
-- ============================================================================
-- Add composite indexes based on common query patterns
-- These indexes speed up multi-column WHERE clauses and JOINs
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Player Statistics Indexes
-- ---------------------------------------------------------------------------
-- Common query: Get player stats for a specific season
-- Already added in Phase 3, verify it exists:
SELECT 'Verifying existing composite indexes...' AS message;
SHOW INDEX FROM ibl_plr WHERE Key_name LIKE 'idx_%';

-- Add additional useful composite indexes if not present:

-- Players by team and position (for roster queries)
-- Check if exists first:
SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_plr' 
  AND INDEX_NAME = 'idx_plr_team_pos';

-- Add if doesn't exist:
SET @sql = IF(@idx_exists = 0, 
  'ALTER TABLE ibl_plr ADD INDEX idx_plr_team_pos (tid, pos, active)',
  'SELECT "Index idx_plr_team_pos already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Players by team and active status (for active roster queries)
SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_plr' 
  AND INDEX_NAME = 'idx_plr_team_active';

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ibl_plr ADD INDEX idx_plr_team_active (tid, active, ordinal)',
  'SELECT "Index idx_plr_team_active already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Schedule Indexes
-- ---------------------------------------------------------------------------
-- Common query: Get games for a specific team in a season
-- Year + Team composite indexes

SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_schedule' 
  AND INDEX_NAME = 'idx_schedule_year_home';

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ibl_schedule ADD INDEX idx_schedule_year_home (Year, Home)',
  'SELECT "Index idx_schedule_year_home already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_schedule' 
  AND INDEX_NAME = 'idx_schedule_year_visitor';

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ibl_schedule ADD INDEX idx_schedule_year_visitor (Year, Visitor)',
  'SELECT "Index idx_schedule_year_visitor already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Historical Stats Indexes
-- ---------------------------------------------------------------------------
-- Common query: Get player season stats
SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_hist' 
  AND INDEX_NAME = 'idx_hist_pid_year';

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ibl_hist ADD INDEX idx_hist_pid_year (pid, year)',
  'SELECT "Index idx_hist_pid_year already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ---------------------------------------------------------------------------
-- Standings Indexes
-- ---------------------------------------------------------------------------
-- Common query: Get standings for a specific season
SELECT COUNT(*) INTO @idx_exists 
FROM information_schema.STATISTICS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME = 'ibl_standings' 
  AND INDEX_NAME = 'idx_standings_year';

SET @sql = IF(@idx_exists = 0,
  'ALTER TABLE ibl_standings ADD INDEX idx_standings_year (year, conference)',
  'SELECT "Index idx_standings_year already exists" AS message');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- ============================================================================
-- PART 3: COLUMN SIZE OPTIMIZATION
-- ============================================================================
-- Further optimize column sizes to reduce storage and improve cache efficiency
-- These changes are conservative and based on actual data ranges
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Team Name and City Lengths
-- ---------------------------------------------------------------------------
-- Review and potentially reduce VARCHAR sizes for team names
-- Current: team_name VARCHAR(32), team_city VARCHAR(32)
-- Most team names/cities are much shorter

-- Check maximum lengths in use:
SELECT 'Checking actual team name lengths...' AS message;
SELECT 
  MAX(LENGTH(team_name)) AS max_team_name_length,
  MAX(LENGTH(team_city)) AS max_team_city_length
FROM ibl_team_info;

-- If max lengths are significantly less than 32, consider reducing:
-- ALTER TABLE ibl_team_info
--   MODIFY team_name VARCHAR(24) NOT NULL,
--   MODIFY team_city VARCHAR(24) NOT NULL;

-- ---------------------------------------------------------------------------
-- Player Name Optimization
-- ---------------------------------------------------------------------------
-- Check actual player name lengths
SELECT 'Checking actual player name lengths...' AS message;
SELECT MAX(LENGTH(name)) AS max_player_name_length FROM ibl_plr;

-- If significantly less than 32, consider reducing:
-- ALTER TABLE ibl_plr MODIFY name VARCHAR(28) NOT NULL DEFAULT '';

-- ============================================================================
-- PART 4: QUERY PERFORMANCE TUNING
-- ============================================================================
-- Analyze and optimize slow queries
-- ============================================================================

-- Update table statistics for better query planning
SELECT 'Updating table statistics...' AS message;
ANALYZE TABLE ibl_plr;
ANALYZE TABLE ibl_hist;
ANALYZE TABLE ibl_schedule;
ANALYZE TABLE ibl_box_scores;
ANALYZE TABLE ibl_standings;
ANALYZE TABLE ibl_team_info;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify changes

-- Check partitioning status (if partitioning was enabled)
SELECT 'Checking partitioning status...' AS message;
SELECT 
  TABLE_NAME,
  PARTITION_NAME,
  PARTITION_METHOD,
  PARTITION_EXPRESSION,
  TABLE_ROWS
FROM information_schema.PARTITIONS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('ibl_hist', 'ibl_box_scores')
  AND PARTITION_NAME IS NOT NULL
ORDER BY TABLE_NAME, PARTITION_ORDINAL_POSITION;

-- Verify composite indexes were created
SELECT 'Verifying composite indexes...' AS message;
SELECT 
  TABLE_NAME,
  INDEX_NAME,
  GROUP_CONCAT(COLUMN_NAME ORDER BY SEQ_IN_INDEX) AS index_columns,
  INDEX_TYPE
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND INDEX_NAME LIKE 'idx_%'
  AND TABLE_NAME IN ('ibl_plr', 'ibl_schedule', 'ibl_hist', 'ibl_standings')
GROUP BY TABLE_NAME, INDEX_NAME, INDEX_TYPE
ORDER BY TABLE_NAME, INDEX_NAME;

-- Check index usage and table statistics
SELECT 'Checking table statistics after ANALYZE...' AS message;
SELECT 
  TABLE_NAME,
  TABLE_ROWS,
  AVG_ROW_LENGTH,
  DATA_LENGTH,
  INDEX_LENGTH,
  ROUND(DATA_LENGTH / 1024 / 1024, 2) AS data_mb,
  ROUND(INDEX_LENGTH / 1024 / 1024, 2) AS index_mb
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'ibl_%'
  AND ENGINE = 'InnoDB'
ORDER BY DATA_LENGTH DESC
LIMIT 20;

-- ============================================================================
-- ROLLBACK PROCEDURES
-- ============================================================================
-- If issues occur, changes can be reversed:

-- Remove partitioning (returns table to non-partitioned state):
-- ALTER TABLE ibl_hist REMOVE PARTITIONING;
-- ALTER TABLE ibl_box_scores REMOVE PARTITIONING;

-- Drop composite indexes:
-- ALTER TABLE ibl_plr DROP INDEX idx_plr_team_pos;
-- ALTER TABLE ibl_plr DROP INDEX idx_plr_team_active;
-- ALTER TABLE ibl_schedule DROP INDEX idx_schedule_year_home;
-- ALTER TABLE ibl_schedule DROP INDEX idx_schedule_year_visitor;
-- ALTER TABLE ibl_hist DROP INDEX idx_hist_pid_year;
-- ALTER TABLE ibl_standings DROP INDEX idx_standings_year;

-- Revert column size changes (if made):
-- ALTER TABLE ibl_team_info
--   MODIFY team_name VARCHAR(32) NOT NULL,
--   MODIFY team_city VARCHAR(32) NOT NULL;
-- ALTER TABLE ibl_plr MODIFY name VARCHAR(32) NOT NULL DEFAULT '';

-- ============================================================================
-- PERFORMANCE TESTING
-- ============================================================================
-- After migration, test these queries to measure improvements:

-- Test 1: Player stats by season (should benefit from partitioning if enabled)
-- EXPLAIN SELECT * FROM ibl_hist WHERE pid = 123 AND year = 2024;

-- Test 2: Team roster query (should use idx_plr_team_active)
-- EXPLAIN SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1 ORDER BY ordinal;

-- Test 3: Team schedule query (should use idx_schedule_year_home)
-- EXPLAIN SELECT * FROM ibl_schedule WHERE Year = 2024 AND Home = 1;

-- Test 4: Conference standings (should use idx_standings_year)
-- EXPLAIN SELECT * FROM ibl_standings WHERE year = 2024 AND conference = 'Eastern';

-- Look for:
-- - 'partitions' column showing which partitions are accessed (if partitioning enabled)
-- - 'key' column showing the composite indexes are being used
-- - 'rows' column showing fewer rows examined than before
-- - 'type' = 'ref' or 'range' (good) vs 'ALL' (full table scan - bad)

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================
SELECT 'Phase 5 Migration Complete!' AS message;
SELECT 'Advanced optimizations have been applied.' AS details;
SELECT 'Please review the verification queries above to confirm all changes.' AS next_step;
SELECT 'Note: Table partitioning is commented out by default and requires manual review before enabling.' AS important_note;
