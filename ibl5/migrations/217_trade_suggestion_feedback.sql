-- Migration 217 — GM hide feedback on homepage trade ideas (one row per hidden idea).
-- Forward-only, purely additive: CREATE TABLE IF NOT EXISTS. No destructive DDL.
-- Rollback (lossless for all other data): DROP TABLE IF EXISTS ibl_trade_suggestion_feedback;

CREATE TABLE IF NOT EXISTS `ibl_trade_suggestion_feedback` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `tid` INT NOT NULL COMMENT 'GM team from the session, never from POST',
  `deal_key` CHAR(40) NOT NULL COMMENT 'sha1(userTid|partnerTid|side-tagged sorted asset keys)',
  `core_key` CHAR(40) NOT NULL COMMENT 'same as deal_key with players only',
  `partner_tid` INT NOT NULL,
  `deal_json` TEXT NOT NULL COMMENT 'asset keys and labels for the tuning page',
  `reason_codes` VARCHAR(80) NOT NULL COMMENT 'comma-joined closed set, see Feedback\\HideReason',
  `free_text` VARCHAR(500) NULL,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tid_deal` (`tid`, `deal_key`),
  KEY `idx_tid_core` (`tid`, `core_key`),
  KEY `idx_partner_tid` (`partner_tid`),
  CONSTRAINT `fk_tsf_tid` FOREIGN KEY (`tid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_tsf_partner_tid` FOREIGN KEY (`partner_tid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
