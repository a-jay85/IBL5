-- Migration 148: Create gm_draft_big_board — per-GM private ranked big board of draft prospects.
-- Keyed by (teamid, prospect_id). Signedness: teamid int(11)/int(11) → ibl_team_info(teamid);
-- prospect_id int(11)/int(11) → ibl_draft_class(id). Both targets are signed int(11), so the FK
-- columns are signed int(11) too — a signedness mismatch would trip errno 1452 at deploy.
-- UNIQUE(teamid, prospect_id) blocks a GM from adding the same prospect twice (service maps the
-- dup-key failure to a user error). rank is deliberately NOT unique — reorder = edit the integer,
-- ties broken deterministically by id ASC at read time. Both FKs CASCADE (a deleted team or
-- prospect drops its board rows; prospect rows persist across the draft — only `drafted` flips).
-- Plain CREATE TABLE IF NOT EXISTS is non-destructive (no DROP) → bin/adr-check does not fire.
CREATE TABLE IF NOT EXISTS `gm_draft_big_board` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `teamid` int(11) NOT NULL COMMENT 'Owning team (FK ibl_team_info.teamid); board is private to this GM',
  `prospect_id` int(11) NOT NULL COMMENT 'FK ibl_draft_class.id',
  `rank` int(11) NOT NULL DEFAULT 0 COMMENT 'GM-assigned rank; lower = higher priority; not unique, tie-broken by id',
  `note` varchar(255) NOT NULL DEFAULT '' COMMENT 'Free GM text; escaped on output',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uniq_team_prospect` (`teamid`, `prospect_id`),
  KEY `idx_team_rank` (`teamid`, `rank`),
  CONSTRAINT `fk_bigboard_team` FOREIGN KEY (`teamid`)
    REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE ON DELETE CASCADE,
  CONSTRAINT `fk_bigboard_prospect` FOREIGN KEY (`prospect_id`)
    REFERENCES `ibl_draft_class` (`id`) ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
