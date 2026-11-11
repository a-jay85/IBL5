-- Migration 211 — add 'middle' to the per-team trade suggestion mode (F8 Middle mode).
-- Forward-only, purely additive: appends one enum value. No row changes, no destructive DDL.
-- Rollback: UPDATE ibl_team_info SET trade_mode = 'auto' WHERE trade_mode = 'middle';
--   then ALTER TABLE `ibl_team_info` MODIFY COLUMN `trade_mode` enum('auto','win_now','rebuild') NOT NULL DEFAULT 'auto'
--   COMMENT 'Trade-suggestion team mode. auto = guessed from record and roster age; win_now/rebuild = GM override.';
-- rollback-compatible: production PHP reads an unknown stored value as auto (TeamModeSetting::tryFrom ?? Auto).

ALTER TABLE `ibl_team_info` MODIFY COLUMN `trade_mode` enum('auto','win_now','rebuild','middle') NOT NULL DEFAULT 'auto' COMMENT 'Trade-suggestion team mode. auto = guessed from record and roster age; win_now/rebuild/middle = GM override.';
