-- Migration 195 — sim recap self-healing: cause routing, host hand-off, per-cause counters, notified state.
-- Forward-only, purely additive: ADD COLUMN IF NOT EXISTS plus a comment-only MODIFY. No destructive DDL.

ALTER TABLE `ibl_sim_summaries`
  ADD COLUMN IF NOT EXISTS `route` enum('any','mac','actions') NOT NULL DEFAULT 'any'
    COMMENT 'Which host may claim: any = normal pending; mac/actions = handed off to that host.' AFTER `blocked_until`,
  ADD COLUMN IF NOT EXISTS `routed_at` datetime DEFAULT NULL
    COMMENT 'When the row last became claimable on its route.' AFTER `route`,
  ADD COLUMN IF NOT EXISTS `claimed_by` enum('mac','actions') DEFAULT NULL
    COMMENT 'Host holding (or last holding) the claim.' AFTER `routed_at`,
  ADD COLUMN IF NOT EXISTS `last_cause` enum('flaky','usage_limit','auth','transport','prompt') DEFAULT NULL
    COMMENT 'Failure cause of the most recent park.' AFTER `claimed_by`,
  ADD COLUMN IF NOT EXISTS `flaky_failures` tinyint(3) unsigned NOT NULL DEFAULT 0
    COMMENT 'Parks with cause flaky (no payload, non-transport store failure).' AFTER `last_cause`,
  ADD COLUMN IF NOT EXISTS `transport_failures` tinyint(3) unsigned NOT NULL DEFAULT 0
    COMMENT 'Parks with cause transport (ssh, tunnel, input fetch, store transport).' AFTER `flaky_failures`,
  ADD COLUMN IF NOT EXISTS `usage_waits` tinyint(3) unsigned NOT NULL DEFAULT 0
    COMMENT 'Usage-limit waits. Informational; never terminal on its own.' AFTER `transport_failures`,
  ADD COLUMN IF NOT EXISTS `auth_failed_hosts` set('mac','actions') NOT NULL DEFAULT ''
    COMMENT 'Hosts whose Claude login failed for this sim. Both set = terminal.' AFTER `usage_waits`,
  ADD COLUMN IF NOT EXISTS `notified_state` enum('retrying','waiting','handed_mac','handed_actions','posted','recovered','unavailable') DEFAULT NULL
    COMMENT 'Last #admin-chat status posted for this sim; compare-and-set de-dup key.' AFTER `auth_failed_hosts`,
  MODIFY COLUMN `attempts` tinyint(3) unsigned NOT NULL DEFAULT 0
    COMMENT 'Claims taken. Safety backstop: 20 → failed. Per-cause bounds live in the *_failures columns.';
