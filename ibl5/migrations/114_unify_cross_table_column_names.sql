-- Migration 114: Tier 2 cross-table column-naming unification
--
-- Builds on PR #632 / migration 113 (Tier 1: reserved-word + space-containing
-- columns). This migration unifies three cross-table concepts whose divergent
-- spellings forced JOIN aliases, hand-translated array keys in PHP, and
-- ad-hoc per-layer code paths.
--
-- Three concepts unified:
--
-- 1. Turnovers (live layer). `stats_to` on ibl_plr / ibl_plr_snapshots /
--    ibl_olympics_plr renamed to `stats_tvr`. The hist layer already uses
--    `tvr` and PR 1 standardised the rating as `r_tvr`; this finishes the
--    family. Box-score `gameTOV` is intentionally LEFT alone — it belongs
--    to the PascalCase `game*` family (gameFGM, gameREB, gameAST, ...) which
--    is internally consistent and is a Tier 3 follow-up if ever.
--
-- 2. 3-pointer ratings. `r_tga` / `r_tgp` (live + snapshot + olympics_plr)
--    and bare `tga` / `tgp` (ibl_draft_class) renamed to `r_3ga` / `r_3gp`,
--    matching the canonical names already on ibl_hist / ibl_olympics_hist.
--    After this migration:
--      * `r_3ga` / `r_3gp` uniformly mean "3P attempts/percentage rating"
--        across every layer.
--      * NOTE: ibl_hist (and ibl_olympics_hist) keep a SEPARATE `tga`
--        column that is the 3PA *counting stat* (not a rating). They
--        coexist with `r_3ga` on the same table — different concepts, same
--        table, no collision. A future Tier 3 PR may rename the stat too.
--
-- 3. Team identifier. Five surface spellings (`tid`, `teamID`, `TeamID`,
--    `team_id`, plus compounds `homeTID`/`visitorTID`/`homeTeamID`/
--    `visitorTeamID`/`owner_tid`/`teampick_tid`) all unified to lowercase
--    `teamid` (matching ibl_team_info PK) or `{prefix}_teamid` for compounds.
--    Group-C renames touch FK-bearing columns; each is drop FK → CHANGE
--    COLUMN → re-add FK.
--
-- Out of scope (deferred):
--   * `gameTOV` / `gameMIN` / `Clutch` / `Consistency` PascalCase columns —
--     ADR-0008 §Alternatives Considered defers to Tier 3.
--   * `ibl_hist.tga` / `tgm` (counting stats) — different concept from
--     ratings; skipping keeps blast radius proportional.
--   * `ibl_*.year` vs `season_year` — already aliased in views; not
--     reserved-word, not a meaning-flip; user explicitly excluded.
--   * `ibl_schedule.Home` / `Visitor` — team-NAME FKs, not team-id.
--
-- Enforcement: new PHPStan rule `BanInconsistentColumnNamesRule` (identifier
-- `ibl.bannedInconsistentColumnName`) flags backtick-quoted references to
-- the old names in SQL string literals under classes/ and html/. New
-- assertions added to `config/schema-assertions.php`.

-- ============================================================
-- Group A — Turnovers (live layer, no FK churn)
-- ============================================================

ALTER TABLE `ibl_plr`
  CHANGE COLUMN `stats_to` `stats_tvr` smallint(5) unsigned DEFAULT 0 COMMENT 'Turnovers';

ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `stats_to` `stats_tvr` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Turnovers';

ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `stats_to` `stats_tvr` smallint(5) unsigned DEFAULT 0 COMMENT 'Turnovers';

-- ============================================================
-- Group B — 3-pointer ratings (no FK churn)
-- ============================================================

ALTER TABLE `ibl_plr`
  CHANGE COLUMN `r_tga` `r_3ga` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating: 3P attempts',
  CHANGE COLUMN `r_tgp` `r_3gp` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating: 3P percentage';

ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `r_tga` `r_3ga` smallint(6) DEFAULT 0 COMMENT 'Rating: 3P attempts',
  CHANGE COLUMN `r_tgp` `r_3gp` smallint(6) DEFAULT 0 COMMENT 'Rating: 3P percentage';

ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `r_tga` `r_3ga` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating: 3P attempts',
  CHANGE COLUMN `r_tgp` `r_3gp` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating: 3P percentage';

ALTER TABLE `ibl_draft_class`
  CHANGE COLUMN `tga` `r_3ga` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Rating: 3P attempts',
  CHANGE COLUMN `tgp` `r_3gp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Rating: 3P percentage';

-- ============================================================
-- Group C.1 — Team-id outliers (no FK dependents, simple CHANGE COLUMN)
-- ============================================================

-- ibl_power: TeamID is the PK; no incoming or outgoing FKs (verified via
-- information_schema.KEY_COLUMN_USAGE).
ALTER TABLE `ibl_power`
  CHANGE COLUMN `TeamID` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (PK, FK target for ibl_team_info)';

ALTER TABLE `ibl_olympics_power`
  CHANGE COLUMN `TeamID` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (PK, FK target for ibl_olympics_team_info)';

-- ibl_rcb_*: team_id is unconstrained (no FK; rcb tables are read-only
-- analytics derived from .rcb files).
ALTER TABLE `ibl_rcb_alltime_records`
  CHANGE COLUMN `team_id` `teamid` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0 for league scope; JSB team ID 1-28 for team scope';

ALTER TABLE `ibl_rcb_season_records`
  CHANGE COLUMN `team_id` `teamid` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '0 for league scope; JSB team ID 1-28 for team scope';

-- ============================================================
-- Group C.2 — Team-id columns without FKs (simple CHANGE COLUMN)
-- ============================================================

-- ibl_olympics_plr.tid: indexed but no FK (verified)
ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (0 = free agent)';

-- ibl_plr_snapshots.tid: indexed but no FK (verified)
ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID at the time of the snapshot';

-- ibl_saved_depth_charts.tid: no FK (verified)
ALTER TABLE `ibl_saved_depth_charts`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL COMMENT 'Team ID owning this saved depth chart';

-- ibl_olympics_saved_depth_charts.tid: no FK (verified)
ALTER TABLE `ibl_olympics_saved_depth_charts`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL COMMENT 'Team ID owning this saved depth chart';

-- ibl_plb_snapshots.tid: no FK; JSB-engine team ID (1-28), part of unique key
-- uq_archive_team_slot. CHANGE COLUMN updates the key's column-list pointer in
-- place; the key NAME stays the same.
ALTER TABLE `ibl_plb_snapshots`
  CHANGE COLUMN `tid` `teamid` tinyint(3) unsigned NOT NULL COMMENT 'JSB-engine team ID (1-28)';

-- ibl_box_scores.teamID (player's team — no FK, separate from home/visitorTID)
ALTER TABLE `ibl_box_scores`
  CHANGE COLUMN `teamID` `teamid` int(11) DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)';

ALTER TABLE `ibl_olympics_box_scores`
  CHANGE COLUMN `teamID` `teamid` int(11) DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)';

-- ============================================================
-- Group C.3 — FK-bearing renames (drop FK → CHANGE COLUMN → re-add FK)
-- ============================================================

-- ibl_plr.tid → teamid
ALTER TABLE `ibl_plr` DROP FOREIGN KEY `fk_plr_team`;
ALTER TABLE `ibl_plr`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (0 = free agent)';
ALTER TABLE `ibl_plr` ADD CONSTRAINT `fk_plr_team`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_draft.tid → teamid
ALTER TABLE `ibl_draft` DROP FOREIGN KEY `fk_draft_tid`;
ALTER TABLE `ibl_draft`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID making the pick';
ALTER TABLE `ibl_draft` ADD CONSTRAINT `fk_draft_tid`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ibl_fa_offers.tid → teamid
ALTER TABLE `ibl_fa_offers` DROP FOREIGN KEY `fk_faoffer_tid`;
ALTER TABLE `ibl_fa_offers`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID making the offer';
ALTER TABLE `ibl_fa_offers` ADD CONSTRAINT `fk_faoffer_tid`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ibl_cash_considerations.tid → teamid
ALTER TABLE `ibl_cash_considerations` DROP FOREIGN KEY `fk_cash_considerations_team`;
ALTER TABLE `ibl_cash_considerations`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL COMMENT 'Team ID receiving cash';
ALTER TABLE `ibl_cash_considerations` ADD CONSTRAINT `fk_cash_considerations_team`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_standings.tid → teamid (PK + FK)
ALTER TABLE `ibl_standings` DROP FOREIGN KEY `fk_standings_team`;
ALTER TABLE `ibl_standings`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL COMMENT 'Team ID (PK)';
ALTER TABLE `ibl_standings` ADD CONSTRAINT `fk_standings_team`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ibl_olympics_standings.tid → teamid (PK + FK)
ALTER TABLE `ibl_olympics_standings` DROP FOREIGN KEY `fk_olympics_standings_team`;
ALTER TABLE `ibl_olympics_standings`
  CHANGE COLUMN `tid` `teamid` int(11) NOT NULL COMMENT 'Team ID (PK)';
ALTER TABLE `ibl_olympics_standings` ADD CONSTRAINT `fk_olympics_standings_team`
  FOREIGN KEY (`teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;

-- ibl_box_scores.{homeTID,visitorTID} → {home_teamid,visitor_teamid}
ALTER TABLE `ibl_box_scores` DROP FOREIGN KEY `fk_boxscore_home`;
ALTER TABLE `ibl_box_scores` DROP FOREIGN KEY `fk_boxscore_visitor`;
ALTER TABLE `ibl_box_scores`
  CHANGE COLUMN `homeTID` `home_teamid` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)',
  CHANGE COLUMN `visitorTID` `visitor_teamid` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_box_scores`
  ADD CONSTRAINT `fk_boxscore_home` FOREIGN KEY (`home_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_boxscore_visitor` FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_box_scores_teams.{homeTeamID,visitorTeamID} → {home_teamid,visitor_teamid}
ALTER TABLE `ibl_box_scores_teams` DROP FOREIGN KEY `fk_boxscoreteam_home`;
ALTER TABLE `ibl_box_scores_teams` DROP FOREIGN KEY `fk_boxscoreteam_visitor`;
ALTER TABLE `ibl_box_scores_teams`
  CHANGE COLUMN `homeTeamID` `home_teamid` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)',
  CHANGE COLUMN `visitorTeamID` `visitor_teamid` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_box_scores_teams`
  ADD CONSTRAINT `fk_boxscoreteam_home` FOREIGN KEY (`home_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_boxscoreteam_visitor` FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_olympics_box_scores.{homeTID,visitorTID} → {home_teamid,visitor_teamid}
ALTER TABLE `ibl_olympics_box_scores` DROP FOREIGN KEY `fk_olympics_boxscore_home`;
ALTER TABLE `ibl_olympics_box_scores` DROP FOREIGN KEY `fk_olympics_boxscore_visitor`;
ALTER TABLE `ibl_olympics_box_scores`
  CHANGE COLUMN `homeTID` `home_teamid` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_olympics_team_info)',
  CHANGE COLUMN `visitorTID` `visitor_teamid` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_olympics_team_info)';
ALTER TABLE `ibl_olympics_box_scores`
  ADD CONSTRAINT `fk_olympics_boxscore_home` FOREIGN KEY (`home_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_olympics_boxscore_visitor` FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_olympics_box_scores_teams.{homeTeamID,visitorTeamID} → {home_teamid,visitor_teamid}
ALTER TABLE `ibl_olympics_box_scores_teams` DROP FOREIGN KEY `fk_olympics_boxscoreteam_home`;
ALTER TABLE `ibl_olympics_box_scores_teams` DROP FOREIGN KEY `fk_olympics_boxscoreteam_visitor`;
ALTER TABLE `ibl_olympics_box_scores_teams`
  CHANGE COLUMN `homeTeamID` `home_teamid` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_olympics_team_info)',
  CHANGE COLUMN `visitorTeamID` `visitor_teamid` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_olympics_team_info)';
ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD CONSTRAINT `fk_olympics_boxscoreteam_home` FOREIGN KEY (`home_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_olympics_boxscoreteam_visitor` FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_draft_picks.{owner_tid,teampick_tid} → {owner_teamid,teampick_teamid}
-- Rename the FK constraint names alongside the columns so information_schema
-- doesn't retain the old `_tid` vocabulary.
ALTER TABLE `ibl_draft_picks` DROP FOREIGN KEY `fk_draftpick_owner_tid`;
ALTER TABLE `ibl_draft_picks` DROP FOREIGN KEY `fk_draftpick_teampick_tid`;
ALTER TABLE `ibl_draft_picks`
  CHANGE COLUMN `owner_tid` `owner_teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team that currently owns the pick',
  CHANGE COLUMN `teampick_tid` `teampick_teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team whose original pick this is';
ALTER TABLE `ibl_draft_picks`
  ADD CONSTRAINT `fk_draftpick_owner_teamid` FOREIGN KEY (`owner_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_draftpick_teampick_teamid` FOREIGN KEY (`teampick_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;
