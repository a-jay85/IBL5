-- Migration: Convert ibl_trade_queue from raw SQL strings to structured data
-- This improves security by storing operation parameters instead of executable SQL
-- The parameters are executed via prepared statements when the queue is processed

-- Step 1: Create new table with structured columns
CREATE TABLE IF NOT EXISTS `ibl_trade_queue_new` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_type` VARCHAR(50) NOT NULL COMMENT 'Type: player_transfer, pick_transfer',
  `params` JSON NOT NULL COMMENT 'JSON-encoded operation parameters',
  `tradeline` TEXT DEFAULT NULL COMMENT 'Human-readable trade description',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: Migrate existing data (if any)
-- Old format: query (raw SQL), tradeline (text)
-- New format: operation_type, params (JSON), tradeline
-- Note: Existing raw SQL queries cannot be safely migrated to structured format
-- They will be logged and cleared - any pending trades should be manually re-entered

-- Step 3: Backup old table (just in case)
RENAME TABLE `ibl_trade_queue` TO `ibl_trade_queue_backup`;

-- Step 4: Rename new table to production name
RENAME TABLE `ibl_trade_queue_new` TO `ibl_trade_queue`;

-- Step 5: Drop backup after verification (run manually after confirming migration success)
-- DROP TABLE IF EXISTS `ibl_trade_queue_backup`;
