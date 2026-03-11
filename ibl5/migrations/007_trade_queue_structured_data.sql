-- Migration: Convert ibl_trade_queue from raw SQL strings to structured data
-- This improves security by storing operation parameters instead of executable SQL
-- The parameters are executed via prepared statements when the queue is processed

-- Idempotent: only runs the ALTER if the old `query` column still exists
ALTER TABLE `ibl_trade_queue`
    ADD COLUMN IF NOT EXISTS `operation_type` VARCHAR(50) NOT NULL COMMENT 'Type: player_transfer, pick_transfer' AFTER `id`,
    ADD COLUMN IF NOT EXISTS `params` JSON NOT NULL COMMENT 'JSON-encoded operation parameters' AFTER `operation_type`,
    ADD COLUMN IF NOT EXISTS `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    DROP COLUMN IF EXISTS `query`;
