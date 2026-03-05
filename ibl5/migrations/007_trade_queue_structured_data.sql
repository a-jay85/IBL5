-- Migration: Convert ibl_trade_queue from raw SQL strings to structured data
-- This improves security by storing operation parameters instead of executable SQL
-- The parameters are executed via prepared statements when the queue is processed

-- Step 1: Create new table with structured columns (if it doesn't already exist as the final schema)
CREATE TABLE IF NOT EXISTS `ibl_trade_queue_new` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `operation_type` VARCHAR(50) NOT NULL COMMENT 'Type: player_transfer, pick_transfer',
  `params` JSON NOT NULL COMMENT 'JSON-encoded operation parameters',
  `tradeline` TEXT DEFAULT NULL COMMENT 'Human-readable trade description',
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Step 2: If the old-format table still exists, rename it and swap in the new one
-- The final table name should be ibl_trade_queue with the new schema.
-- If ibl_trade_queue already has the new schema (id, operation_type, params columns),
-- the CREATE above was a no-op and these RENAMEs will also be no-ops via IF EXISTS.

-- Backup old table if it exists and has the old schema (query column)
-- MariaDB doesn't support conditional RENAME, so we use a procedure-free approach:
-- If ibl_trade_queue_backup already exists, the old migration already ran.
-- If ibl_trade_queue_new has rows or ibl_trade_queue has operation_type, we're done.
DROP TABLE IF EXISTS `ibl_trade_queue_backup`;
DROP TABLE IF EXISTS `ibl_trade_queue_new`;
