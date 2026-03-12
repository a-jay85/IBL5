-- Migration 060: Fix ibl_trade_queue schema
-- Migration 007 was broken (created new table then dropped it without swapping).
-- The PHP code expects (id, operation_type, params, tradeline) but production
-- still has the old schema (id, query, tradeline).
-- This migration alters the existing table in place.

-- Add the new columns (IF NOT EXISTS for idempotency)
ALTER TABLE `ibl_trade_queue` ADD COLUMN IF NOT EXISTS `operation_type` VARCHAR(50) NOT NULL DEFAULT '' COMMENT 'Type: player_transfer, pick_transfer' AFTER `id`;
ALTER TABLE `ibl_trade_queue` ADD COLUMN IF NOT EXISTS `params` JSON DEFAULT NULL COMMENT 'JSON-encoded operation parameters' AFTER `operation_type`;

-- Drop the old `query` column (IF EXISTS for idempotency)
ALTER TABLE `ibl_trade_queue` DROP COLUMN IF EXISTS `query`;
