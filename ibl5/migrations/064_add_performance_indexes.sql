-- Migration 064: Add performance indexes for trade cash lookups and transaction history
--
-- ibl_trade_cash: Composite index for lookups by (tradeOfferID, sendingTeam)
-- nuke_stories: Composite index for transaction history filtered by catid + time ordering

ALTER TABLE ibl_trade_cash ADD INDEX idx_offer_team (tradeOfferID, sendingTeam);

ALTER TABLE nuke_stories ADD INDEX idx_catid_time (catid, `time`);
