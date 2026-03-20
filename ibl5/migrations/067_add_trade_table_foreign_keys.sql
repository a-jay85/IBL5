-- Migration 067: Add foreign key constraints to trade child tables
--
-- Enforces referential integrity between ibl_trade_offers (parent) and
-- ibl_trade_info / ibl_trade_cash (children). CASCADE delete is defense-in-depth —
-- application code still explicitly deletes children before parent.

-- Clean orphaned rows first (idempotent)
DELETE FROM ibl_trade_info WHERE tradeofferid NOT IN (SELECT id FROM ibl_trade_offers);
DELETE FROM ibl_trade_cash WHERE tradeOfferID NOT IN (SELECT id FROM ibl_trade_offers);

-- Add index on ibl_trade_cash.tradeOfferID (FK requires index)
-- Use IF NOT EXISTS to make idempotent
SET @exists = (SELECT COUNT(1) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_trade_cash'
                 AND INDEX_NAME = 'idx_tradeOfferID');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_trade_cash ADD INDEX idx_tradeOfferID (tradeOfferID)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key on ibl_trade_info.tradeofferid
SET @exists = (SELECT COUNT(1) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_trade_info'
                 AND CONSTRAINT_NAME = 'fk_trade_info_offer');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_trade_info
       ADD CONSTRAINT fk_trade_info_offer
       FOREIGN KEY (tradeofferid) REFERENCES ibl_trade_offers(id)
       ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Add foreign key on ibl_trade_cash.tradeOfferID
SET @exists = (SELECT COUNT(1) FROM information_schema.TABLE_CONSTRAINTS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_trade_cash'
                 AND CONSTRAINT_NAME = 'fk_trade_cash_offer');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_trade_cash
       ADD CONSTRAINT fk_trade_cash_offer
       FOREIGN KEY (tradeOfferID) REFERENCES ibl_trade_offers(id)
       ON DELETE CASCADE ON UPDATE CASCADE',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
