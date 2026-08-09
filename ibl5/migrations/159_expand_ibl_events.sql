-- Migration 159: expand ibl_events for product analytics.
--
-- Forward-only and additive: four nullable columns plus one composite index.
-- No column is removed, retyped, or tightened, so this cannot fail on existing
-- data and existing rows stay valid with NULL in the new columns. Rows written
-- before this migration were genuinely not measured; NULL is the honest value.
-- There is no backfill.
--
-- Baseline: 154_create_ibl_events.sql

ALTER TABLE `ibl_events`
    ADD COLUMN IF NOT EXISTS `session_id` VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'sha256 of PHP session id; NOT the raw id, never replayable. Rotates on login (session_regenerate_id), so one visit spanning a login yields two values.'
        AFTER `user_agent`,
    ADD COLUMN IF NOT EXISTS `traffic_class` VARCHAR(32) NULL DEFAULT NULL
        COMMENT 'smoke-test | authenticated | crawler | spam | anonymous-human. Reporting label derived from user_agent/username; NEVER an authorization signal.'
        AFTER `session_id`,
    ADD COLUMN IF NOT EXISTS `http_status` SMALLINT NULL DEFAULT NULL
        COMMENT 'Response status captured at shutdown; NULL when the request died before shutdown or the code was outside 100-599.'
        AFTER `traffic_class`,
    ADD COLUMN IF NOT EXISTS `action` VARCHAR(64) NULL DEFAULT NULL
        COMMENT 'Domain event set by a controller via EventLogger::setAction(); hardcoded literal, never user input. NULL for plain pageviews.'
        AFTER `http_status`,
    ADD INDEX IF NOT EXISTS `idx_traffic_created` (`traffic_class`, `created_at`);
