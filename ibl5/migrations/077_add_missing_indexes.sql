-- Migration 077: Add missing indexes for slow-query tables
-- Production logs show full table scans on ibl_one_on_one (winner/loser lookups)
-- and nuke_stats_hour (year/month/date/hour UPDATEs with no PK or indexes).

-- 1. ibl_one_on_one: add indexes on winner and loser columns
ALTER TABLE ibl_one_on_one
  ADD INDEX IF NOT EXISTS idx_winner (winner),
  ADD INDEX IF NOT EXISTS idx_loser (loser);

-- 2. nuke_stats_hour: deduplicate then add PRIMARY KEY
-- Dedup: merge any rows sharing the same (year, month, date, hour) by summing hits
CREATE TEMPORARY TABLE _nsh_dedup AS
SELECT `year`, `month`, `date`, `hour`, SUM(hits) AS hits
FROM nuke_stats_hour
GROUP BY `year`, `month`, `date`, `hour`;

TRUNCATE TABLE nuke_stats_hour;

INSERT INTO nuke_stats_hour (`year`, `month`, `date`, `hour`, hits)
SELECT `year`, `month`, `date`, `hour`, hits FROM _nsh_dedup;

DROP TEMPORARY TABLE _nsh_dedup;

-- Add PK conditionally (ADD PRIMARY KEY has no IF NOT EXISTS syntax)
SET @has_pk = (SELECT COUNT(*) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = 'nuke_stats_hour'
               AND CONSTRAINT_TYPE = 'PRIMARY KEY');
SET @sql = IF(@has_pk = 0,
  'ALTER TABLE nuke_stats_hour ADD PRIMARY KEY (`year`, `month`, `date`, `hour`)',
  'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
