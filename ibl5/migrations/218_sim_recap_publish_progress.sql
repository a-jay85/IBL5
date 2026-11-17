-- Migration 218: per-sim publish progress for the Discord sim-recap drip.
--
-- One row per sim. It is the only source of truth for where a sim's drip stands, so a bot restart or a
-- duplicate trigger never starts a second drip. IBLbot claims a row through a service-key API route and
-- reports each posted step back. There is no foreign key to ibl_sim_summaries: rows are written only by the
-- prod notify path and the title-override verb.

CREATE TABLE IF NOT EXISTS `ibl_sim_publish_progress` (
  `sim`            INT UNSIGNED NOT NULL COMMENT 'Overall sim number (same type as ibl_sim_summaries.sim)',
  `title_override` VARCHAR(100) NULL COMMENT 'Manual thread name; NULL = computed title',
  `enqueued_at`    DATETIME NULL COMMENT 'Set by the done notify; NULL = row exists only to hold an override',
  `claim_token`    CHAR(36) NULL COMMENT 'UUID of the IBLbot process that owns the drip',
  `heartbeat_at`   DATETIME NULL COMMENT 'Refreshed on every claim and step advance',
  `attempts`       TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Claim count; a row at the cap is never claimed',
  `thread_id`      VARCHAR(20) NULL COMMENT 'Discord snowflake of the created thread',
  `last_step`      INT NOT NULL DEFAULT -1 COMMENT 'Index of the last step confirmed posted; -1 = none',
  `finished_at`    DATETIME NULL COMMENT 'Set when the whole drip is posted',
  `created_at`     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`sim`),
  KEY `idx_pending` (`finished_at`, `enqueued_at`, `sim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
