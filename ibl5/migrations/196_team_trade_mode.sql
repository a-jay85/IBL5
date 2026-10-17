-- Migration 196 — per-team trade suggestion mode override (GM homepage trade-ideas panel).
-- Forward-only, purely additive: ADD COLUMN IF NOT EXISTS. No destructive DDL.
-- Rollback (lossless for all other data): ALTER TABLE `ibl_team_info` DROP COLUMN `trade_mode`;
-- rollback-incompatible: additive column only; production PHP is unaffected, but production's OlympicsSchemaParityTest allowlist lacks trade_mode (main-only by design) until this PR ships its allowlist entry.

ALTER TABLE `ibl_team_info`
  ADD COLUMN IF NOT EXISTS `trade_mode` enum('auto','win_now','rebuild') NOT NULL DEFAULT 'auto'
    COMMENT 'Trade-suggestion team mode. auto = guessed from record and roster age; win_now/rebuild = GM override.' AFTER `eoy_vote`;
