-- Migration 077: Add missing indexes for slow-query tables
-- Production logs show full table scans on ibl_one_on_one (winner/loser lookups)
-- and nuke_stats_hour (year/month/date/hour UPDATEs with no PK or indexes).

-- 1. ibl_one_on_one: add indexes on winner and loser columns
ALTER TABLE ibl_one_on_one
  ADD INDEX IF NOT EXISTS idx_winner (winner),
  ADD INDEX IF NOT EXISTS idx_loser (loser);

-- 2. nuke_stats_hour: deduplicate then add PRIMARY KEY
-- All steps guarded by @has_pk so re-runs are a no-op.
-- Uses DELETE (transactional) instead of TRUNCATE (DDL, non-rollbackable).
SET @has_pk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nuke_stats_hour'
               AND CONSTRAINT_TYPE = 'PRIMARY KEY');

-- Dedup: merge any rows sharing the same (year, month, date, hour) by summing hits
SET @sql = IF(@has_pk = 0,
  'CREATE TEMPORARY TABLE _nsh_dedup AS SELECT `year`, `month`, `date`, `hour`, SUM(hits) AS hits FROM nuke_stats_hour GROUP BY `year`, `month`, `date`, `hour`',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_pk = 0,
  'DELETE FROM nuke_stats_hour',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_pk = 0,
  'INSERT INTO nuke_stats_hour (`year`, `month`, `date`, `hour`, hits) SELECT `year`, `month`, `date`, `hour`, hits FROM _nsh_dedup',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

SET @sql = IF(@has_pk = 0,
  'DROP TEMPORARY TABLE IF EXISTS _nsh_dedup',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add PK (only if not already present)
SET @sql = IF(@has_pk = 0,
  'ALTER TABLE nuke_stats_hour ADD PRIMARY KEY (`year`, `month`, `date`, `hour`)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
