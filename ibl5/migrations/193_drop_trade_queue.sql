-- destructive-migration[drop-table]: ibl_trade_queue is a dead deferred-replay queue with no remaining readers
-- rollback-incompatible: pre-PR TradeProcessor still inserts into ibl_trade_queue when a trade is accepted in Playoffs, Draft or Free Agency; a rollback in those phases errors on trade accept until the table is recreated from migration 007
--
-- The nightly replay that consumed this table was removed from updateAllTheThings.php
-- on 2025-08-11 (dce6a3342) and never replaced. TradeProcessor writes ibl_plr.teamid
-- and ibl_draft_picks ownership immediately in every season phase, so the queue
-- (introduced in migration 007) carried rows nobody read. The PHP writers were
-- removed in the same PR as this migration.

DROP TABLE IF EXISTS `ibl_trade_queue`;
