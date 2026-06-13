-- Migration 149: Add composite party-derivation index to ibl_trade_info
--
-- Multi-team (N-party) trade support derives the set of party teams for an
-- offer by scanning that offer's trade_info rows' trade_from / trade_to columns.
-- This runs on every trade accept/reject (authz gate + N-party validation), but
-- the existing indexes on ibl_trade_info are single-column. This adds a composite
-- index covering the derivation query's access path.
--
-- ADDITIVE ONLY — no DROP, no rewrite. adr-check stays green; no ADR required.

-- Idempotent add (copy of migration 067's information_schema.STATISTICS guard).
SET @exists = (SELECT COUNT(1) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_trade_info'
                 AND INDEX_NAME = 'idx_trade_info_offer_from_to');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_trade_info ADD INDEX idx_trade_info_offer_from_to (tradeofferid, trade_from, trade_to)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
