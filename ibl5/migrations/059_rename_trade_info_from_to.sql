-- Migration 059: Rename `from` and `to` columns in ibl_trade_info
-- These are SQL reserved words requiring backtick workarounds in 33+ PHP locations.
-- Renaming to `trade_from` and `trade_to` eliminates the need for backticks.

-- Rename the columns
ALTER TABLE `ibl_trade_info` CHANGE COLUMN IF EXISTS `from` `trade_from` varchar(128) NOT NULL DEFAULT '' COMMENT 'Sending team name';
ALTER TABLE `ibl_trade_info` CHANGE COLUMN IF EXISTS `to` `trade_to` varchar(128) NOT NULL DEFAULT '' COMMENT 'Receiving team name';

-- Rename indexes to match (drop old, add new)
DROP INDEX IF EXISTS `idx_from` ON `ibl_trade_info`;
DROP INDEX IF EXISTS `idx_to` ON `ibl_trade_info`;

ALTER TABLE `ibl_trade_info`
    ADD INDEX IF NOT EXISTS `idx_trade_from` (`trade_from`),
    ADD INDEX IF NOT EXISTS `idx_trade_to` (`trade_to`);
