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

-- ============================================================
-- View regeneration — CHANGE COLUMN invalidates view DEFINITIONs;
-- recreate each view that referenced a renamed column.
-- ============================================================

CREATE OR REPLACE VIEW `ibl_sophomore_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`gameDRB`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` = 41 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `vw_free_agency_offers` AS select `fa`.`primary_key` AS `offer_id`,`p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `player_name`,`p`.`pos` AS `position`,`p`.`age` AS `age`,`t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`fa`.`offer1` AS `year1_amount`,`fa`.`offer2` AS `year2_amount`,`fa`.`offer3` AS `year3_amount`,`fa`.`offer4` AS `year4_amount`,`fa`.`offer5` AS `year5_amount`,`fa`.`offer6` AS `year6_amount`,`fa`.`offer1` + `fa`.`offer2` + `fa`.`offer3` + `fa`.`offer4` + `fa`.`offer5` + `fa`.`offer6` AS `total_contract_value`,`fa`.`modifier` AS `modifier`,`fa`.`random` AS `random`,`fa`.`perceivedvalue` AS `perceived_value`,`fa`.`MLE` AS `is_mle`,`fa`.`LLE` AS `is_lle`,`fa`.`created_at` AS `created_at`,`fa`.`updated_at` AS `updated_at` from ((`iblhoops_ibl5`.`ibl_fa_offers` `fa` join `iblhoops_ibl5`.`ibl_plr` `p` on(`fa`.`pid` = `p`.`pid`)) join `iblhoops_ibl5`.`ibl_team_info` `t` on(`fa`.`teamid` = `t`.`teamid`));

CREATE OR REPLACE VIEW `vw_team_awards` AS select `iblhoops_ibl5`.`ibl_team_awards`.`year` AS `year`,`iblhoops_ibl5`.`ibl_team_awards`.`name` AS `name`,`iblhoops_ibl5`.`ibl_team_awards`.`Award` AS `Award`,`iblhoops_ibl5`.`ibl_team_awards`.`ID` AS `ID` from `iblhoops_ibl5`.`ibl_team_awards` union all select `ranked`.`year` AS `year`,`ranked`.`name` AS `name`,'IBL Champions' AS `Award`,0 AS `ID` from (select `psr`.`year` AS `year`,`psr`.`winner` AS `name`,`psr`.`round` AS `round`,max(`psr`.`round`) over ( partition by `psr`.`year`) AS `max_round`,count(0) over ( partition by `psr`.`year`,`psr`.`round`) AS `series_in_round` from `iblhoops_ibl5`.`vw_playoff_series_results` `psr`) `ranked` where `ranked`.`round` = `ranked`.`max_round` and `ranked`.`series_in_round` = 1 union all select `hc`.`year` AS `year`,`ti`.`team_name` AS `name`,'IBL HEAT Champions' AS `Award`,0 AS `ID` from ((select year(`bst`.`Date`) AS `year`,case when `bst`.`homeQ1points` + `bst`.`homeQ2points` + `bst`.`homeQ3points` + `bst`.`homeQ4points` + coalesce(`bst`.`homeOTpoints`,0) > `bst`.`visitorQ1points` + `bst`.`visitorQ2points` + `bst`.`visitorQ3points` + `bst`.`visitorQ4points` + coalesce(`bst`.`visitorOTpoints`,0) then `bst`.`home_teamid` else `bst`.`visitor_teamid` end AS `winner_tid`,row_number() over ( partition by year(`bst`.`Date`) order by `bst`.`Date` desc,`bst`.`gameOfThatDay`) AS `rn` from `iblhoops_ibl5`.`ibl_box_scores_teams` `bst` where `bst`.`game_type` = 3) `hc` join `iblhoops_ibl5`.`ibl_team_info` `ti` on(`ti`.`teamid` = `hc`.`winner_tid`)) where `hc`.`rn` = 1;

CREATE OR REPLACE VIEW `ibl_team_win_loss` AS with unique_games as (select `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date` AS `Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitor_total`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeOTpoints`,0) AS `home_total` from `iblhoops_ibl5`.`ibl_box_scores_teams` where `iblhoops_ibl5`.`ibl_box_scores_teams`.`game_type` = 1 group by `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay`), team_games as (select `unique_games`.`visitor_teamid` AS `teamid`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`home_teamid` AS `teamid`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `iblhoops_ibl5`.`ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end)) group by `tg`.`teamid`,case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end,`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);

CREATE OR REPLACE VIEW `ibl_heat_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`bs`.`teamid` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name`;

CREATE OR REPLACE VIEW `ibl_allstar_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`gameMIN`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game2GA` + `bs`.`game3GA`),2) AS `fga`,case when sum(`bs`.`game2GA` + `bs`.`game3GA`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game2GA` + `bs`.`game3GA`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`gameFTM`),2) AS `ftm`,round(avg(`bs`.`gameFTA`),2) AS `fta`,case when sum(`bs`.`gameFTA`) > 0 then round(sum(`bs`.`gameFTM`) / sum(`bs`.`gameFTA`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game3GM`),2) AS `tgm`,round(avg(`bs`.`game3GA`),2) AS `tga`,case when sum(`bs`.`game3GA`) > 0 then round(sum(`bs`.`game3GM`) / sum(`bs`.`game3GA`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`gameORB`),2) AS `orb`,round(avg(`bs`.`gameDRB`),2) AS `drb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`gameAST`),2) AS `ast`,round(avg(`bs`.`gameSTL`),2) AS `stl`,round(avg(`bs`.`gameTOV`),2) AS `tvr`,round(avg(`bs`.`gameBLK`),2) AS `blk`,round(avg(`bs`.`gamePF`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` in (50,51) group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `vw_playoff_series_results` AS with playoff_games as (select `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date` AS `Date`,year(`iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`) AS `year`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `v_total`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeOTpoints`,0) AS `h_total` from `iblhoops_ibl5`.`ibl_box_scores_teams` where `iblhoops_ibl5`.`ibl_box_scores_teams`.`game_type` = 2 group by `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay`), game_results as (select `playoff_games`.`Date` AS `Date`,`playoff_games`.`year` AS `year`,`playoff_games`.`visitor_teamid` AS `visitor_teamid`,`playoff_games`.`home_teamid` AS `home_teamid`,`playoff_games`.`gameOfThatDay` AS `gameOfThatDay`,`playoff_games`.`v_total` AS `v_total`,`playoff_games`.`h_total` AS `h_total`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`visitor_teamid` else `playoff_games`.`home_teamid` end AS `winner_tid`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`home_teamid` else `playoff_games`.`visitor_teamid` end AS `loser_tid` from `playoff_games`), team_wins as (select `game_results`.`year` AS `year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_a`,greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_b`,`game_results`.`winner_tid` AS `winner_tid`,count(0) AS `wins`,row_number() over ( partition by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) order by count(0) desc) AS `rn` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),`game_results`.`winner_tid`), series_meta as (select `game_results`.`year` AS `year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_a`,greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_b`,count(0) AS `total_games`,min(`game_results`.`Date`) AS `series_start`,dense_rank() over ( partition by `game_results`.`year` order by min(`game_results`.`Date`)) AS `round` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`))select `sm`.`year` AS `year`,`sm`.`round` AS `round`,`tw`.`winner_tid` AS `winner_tid`,case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end AS `loser_tid`,`w`.`team_name` AS `winner`,`l`.`team_name` AS `loser`,`tw`.`wins` AS `winner_games`,`sm`.`total_games` - `tw`.`wins` AS `loser_games`,`sm`.`total_games` AS `total_games` from (((`series_meta` `sm` join `team_wins` `tw` on(`tw`.`year` = `sm`.`year` and `tw`.`team_a` = `sm`.`team_a` and `tw`.`team_b` = `sm`.`team_b` and `tw`.`rn` = 1)) join `iblhoops_ibl5`.`ibl_team_info` `w` on(`w`.`teamid` = `tw`.`winner_tid`)) join `iblhoops_ibl5`.`ibl_team_info` `l` on(`l`.`teamid` = case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end)) order by `sm`.`year` desc,`sm`.`round`;

CREATE OR REPLACE VIEW `ibl_heat_win_loss` AS with unique_games as (select `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date` AS `Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitor_total`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeOTpoints`,0) AS `home_total` from `iblhoops_ibl5`.`ibl_box_scores_teams` where `iblhoops_ibl5`.`ibl_box_scores_teams`.`game_type` = 3 and year(`iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`) < 9000 group by `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay`), team_games as (select `unique_games`.`visitor_teamid` AS `teamid`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`home_teamid` AS `teamid`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select year(`tg`.`Date`) AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `iblhoops_ibl5`.`ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = year(`tg`.`Date`) + 1)) group by `tg`.`teamid`,year(`tg`.`Date`),`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);

CREATE OR REPLACE VIEW `ibl_team_defense_stats` AS select `fs`.`franchise_id` AS `teamid`,`fs`.`team_name` AS `name`,`my`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`opp`.`gameMIN`) as signed) AS `minutes`,cast(sum(`opp`.`game2GM` + `opp`.`game3GM`) as signed) AS `fgm`,cast(sum(`opp`.`game2GA` + `opp`.`game3GA`) as signed) AS `fga`,cast(sum(`opp`.`gameFTM`) as signed) AS `ftm`,cast(sum(`opp`.`gameFTA`) as signed) AS `fta`,cast(sum(`opp`.`game3GM`) as signed) AS `tgm`,cast(sum(`opp`.`game3GA`) as signed) AS `tga`,cast(sum(`opp`.`gameORB`) as signed) AS `orb`,cast(sum(`opp`.`gameORB` + `opp`.`gameDRB`) as signed) AS `reb`,cast(sum(`opp`.`gameAST`) as signed) AS `ast`,cast(sum(`opp`.`gameSTL`) as signed) AS `stl`,cast(sum(`opp`.`gameTOV`) as signed) AS `tvr`,cast(sum(`opp`.`gameBLK`) as signed) AS `blk`,cast(sum(`opp`.`gamePF`) as signed) AS `pf` from ((`iblhoops_ibl5`.`ibl_box_scores_teams` `my` join `iblhoops_ibl5`.`ibl_box_scores_teams` `opp` on(`my`.`Date` = `opp`.`Date` and `my`.`visitor_teamid` = `opp`.`visitor_teamid` and `my`.`home_teamid` = `opp`.`home_teamid` and `my`.`gameOfThatDay` = `opp`.`gameOfThatDay` and `my`.`name` <> `opp`.`name`)) join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `my`.`name` and `fs`.`season_ending_year` = `my`.`season_year`)) where `my`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`my`.`season_year`;

CREATE OR REPLACE VIEW `ibl_rookie_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`gameDRB`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` = 40 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `vw_team_standings` AS select `t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`t`.`owner_name` AS `owner_name`,`s`.`leagueRecord` AS `league_record`,`s`.`pct` AS `win_percentage`,`s`.`conference` AS `conference`,`s`.`confRecord` AS `conference_record`,`s`.`confGB` AS `conference_games_back`,`s`.`division` AS `division`,`s`.`divRecord` AS `division_record`,`s`.`divGB` AS `division_games_back`,`s`.`homeWins` AS `home_wins`,`s`.`homeLosses` AS `home_losses`,`s`.`awayWins` AS `away_wins`,`s`.`awayLosses` AS `away_losses`,concat(`s`.`homeWins`,'-',`s`.`homeLosses`) AS `home_record`,concat(`s`.`awayWins`,'-',`s`.`awayLosses`) AS `away_record`,`s`.`gamesUnplayed` AS `games_remaining`,`s`.`confWins` AS `conference_wins`,`s`.`confLosses` AS `conference_losses`,`s`.`divWins` AS `division_wins`,`s`.`divLosses` AS `division_losses`,`s`.`clinchedConference` AS `clinched_conference`,`s`.`clinchedDivision` AS `clinched_division`,`s`.`clinchedPlayoffs` AS `clinched_playoffs`,`s`.`clinchedLeague` AS `clinched_league`,`s`.`confMagicNumber` AS `conference_magic_number`,`s`.`divMagicNumber` AS `division_magic_number`,`s`.`created_at` AS `created_at`,`s`.`updated_at` AS `updated_at` from (`iblhoops_ibl5`.`ibl_team_info` `t` join `iblhoops_ibl5`.`ibl_standings` `s` on(`t`.`teamid` = `s`.`teamid`));

CREATE OR REPLACE VIEW `vw_player_current` AS select `p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `name`,`p`.`nickname` AS `nickname`,`p`.`age` AS `age`,`p`.`pos` AS `position`,`p`.`htft` AS `htft`,`p`.`htin` AS `htin`,`p`.`dc_canPlayInGame` AS `dc_canPlayInGame`,`p`.`retired` AS `retired`,`p`.`exp` AS `experience`,`p`.`bird` AS `bird_rights`,`t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,`t`.`owner_name` AS `owner_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`p`.`cy` AS `contract_year`,case `p`.`cy` when 1 then `p`.`cy1` when 2 then `p`.`cy2` when 3 then `p`.`cy3` when 4 then `p`.`cy4` when 5 then `p`.`cy5` when 6 then `p`.`cy6` else 0 end AS `current_salary`,`p`.`cy1` AS `year1_salary`,`p`.`cy2` AS `year2_salary`,`p`.`cy3` AS `year3_salary`,`p`.`cy4` AS `year4_salary`,`p`.`cy5` AS `year5_salary`,`p`.`cy6` AS `year6_salary`,`p`.`stats_gm` AS `games_played`,`p`.`stats_min` AS `minutes_played`,`p`.`stats_fgm` AS `field_goals_made`,`p`.`stats_fga` AS `field_goals_attempted`,`p`.`stats_ftm` AS `free_throws_made`,`p`.`stats_fta` AS `free_throws_attempted`,`p`.`stats_3gm` AS `three_pointers_made`,`p`.`stats_3ga` AS `three_pointers_attempted`,`p`.`stats_orb` AS `offensive_rebounds`,`p`.`stats_drb` AS `defensive_rebounds`,`p`.`stats_ast` AS `assists`,`p`.`stats_stl` AS `steals`,`p`.`stats_tvr` AS `turnovers`,`p`.`stats_blk` AS `blocks`,`p`.`stats_pf` AS `personal_fouls`,round(`p`.`stats_fgm` / nullif(`p`.`stats_fga`,0),3) AS `fg_percentage`,round(`p`.`stats_ftm` / nullif(`p`.`stats_fta`,0),3) AS `ft_percentage`,round(`p`.`stats_3gm` / nullif(`p`.`stats_3ga`,0),3) AS `three_pt_percentage`,round((`p`.`stats_fgm` * 2 + `p`.`stats_3gm` + `p`.`stats_ftm`) / nullif(`p`.`stats_gm`,0),1) AS `points_per_game`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at` from (`iblhoops_ibl5`.`ibl_plr` `p` left join `iblhoops_ibl5`.`ibl_team_info` `t` on(`p`.`teamid` = `t`.`teamid`));

CREATE OR REPLACE VIEW `ibl_team_offense_stats` AS select `fs`.`franchise_id` AS `teamid`,`fs`.`team_name` AS `name`,`bst`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`bst`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bst`.`game2GM` + `bst`.`game3GM`) as signed) AS `fgm`,cast(sum(`bst`.`game2GA` + `bst`.`game3GA`) as signed) AS `fga`,cast(sum(`bst`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bst`.`gameFTA`) as signed) AS `fta`,cast(sum(`bst`.`game3GM`) as signed) AS `tgm`,cast(sum(`bst`.`game3GA`) as signed) AS `tga`,cast(sum(`bst`.`gameORB`) as signed) AS `orb`,cast(sum(`bst`.`gameORB` + `bst`.`gameDRB`) as signed) AS `reb`,cast(sum(`bst`.`gameAST`) as signed) AS `ast`,cast(sum(`bst`.`gameSTL`) as signed) AS `stl`,cast(sum(`bst`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bst`.`gameBLK`) as signed) AS `blk`,cast(sum(`bst`.`gamePF`) as signed) AS `pf` from (`iblhoops_ibl5`.`ibl_box_scores_teams` `bst` join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `bst`.`name` and `fs`.`season_ending_year` = `bst`.`season_year`)) where `bst`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`bst`.`season_year`;

CREATE OR REPLACE VIEW `vw_current_salary` AS select `p`.`pid` AS `pid`,`p`.`name` AS `name`,`p`.`teamid` AS `teamid`,`t`.`team_name` AS `teamname`,`p`.`pos` AS `pos`,`p`.`cy` AS `cy`,`p`.`cyt` AS `cyt`,`p`.`cy1` AS `cy1`,`p`.`cy2` AS `cy2`,`p`.`cy3` AS `cy3`,`p`.`cy4` AS `cy4`,`p`.`cy5` AS `cy5`,`p`.`cy6` AS `cy6`,case `p`.`cy` when 1 then `p`.`cy1` when 2 then `p`.`cy2` when 3 then `p`.`cy3` when 4 then `p`.`cy4` when 5 then `p`.`cy5` when 6 then `p`.`cy6` else 0 end AS `current_salary`,case `p`.`cy` when 0 then `p`.`cy1` when 1 then `p`.`cy2` when 2 then `p`.`cy3` when 3 then `p`.`cy4` when 4 then `p`.`cy5` when 5 then `p`.`cy6` else 0 end AS `next_year_salary` from (`iblhoops_ibl5`.`ibl_plr` `p` left join `iblhoops_ibl5`.`ibl_team_info` `t` on(`p`.`teamid` = `t`.`teamid`)) where `p`.`retired` = 0 union all select -`cc`.`id` AS `pid`,`cc`.`label` AS `name`,`cc`.`teamid` AS `teamid`,`t`.`team_name` AS `teamname`,'' AS `pos`,`cc`.`cy` AS `cy`,`cc`.`cyt` AS `cyt`,`cc`.`cy1` AS `cy1`,`cc`.`cy2` AS `cy2`,`cc`.`cy3` AS `cy3`,`cc`.`cy4` AS `cy4`,`cc`.`cy5` AS `cy5`,`cc`.`cy6` AS `cy6`,case `cc`.`cy` when 1 then `cc`.`cy1` when 2 then `cc`.`cy2` when 3 then `cc`.`cy3` when 4 then `cc`.`cy4` when 5 then `cc`.`cy5` when 6 then `cc`.`cy6` else 0 end AS `current_salary`,case `cc`.`cy` when 0 then `cc`.`cy1` when 1 then `cc`.`cy2` when 2 then `cc`.`cy3` when 3 then `cc`.`cy4` when 4 then `cc`.`cy5` when 5 then `cc`.`cy6` else 0 end AS `next_year_salary` from (`iblhoops_ibl5`.`ibl_cash_considerations` `cc` left join `iblhoops_ibl5`.`ibl_team_info` `t` on(`cc`.`teamid` = `t`.`teamid`));

CREATE OR REPLACE VIEW `vw_franchise_summary` AS select `ti`.`teamid` AS `teamid`,coalesce(`wl`.`totwins`,0) AS `totwins`,coalesce(`wl`.`totloss`,0) AS `totloss`,case when coalesce(`wl`.`totwins`,0) + coalesce(`wl`.`totloss`,0) = 0 then 0.000 else round(coalesce(`wl`.`totwins`,0) / (coalesce(`wl`.`totwins`,0) + coalesce(`wl`.`totloss`,0)),3) end AS `winpct`,coalesce(`po`.`playoffs`,0) AS `playoffs`,coalesce(`tc`.`div_titles`,0) AS `div_titles`,coalesce(`tc`.`conf_titles`,0) AS `conf_titles`,coalesce(`tc`.`ibl_titles`,0) AS `ibl_titles`,coalesce(`tc`.`heat_titles`,0) AS `heat_titles` from (((`iblhoops_ibl5`.`ibl_team_info` `ti` left join (select `ibl_team_win_loss`.`currentname` AS `currentname`,sum(`ibl_team_win_loss`.`wins`) AS `totwins`,sum(`ibl_team_win_loss`.`losses`) AS `totloss` from `iblhoops_ibl5`.`ibl_team_win_loss` group by `ibl_team_win_loss`.`currentname`) `wl` on(`wl`.`currentname` = `ti`.`team_name`)) left join (select `po_inner`.`team_name` AS `team_name`,count(distinct `po_inner`.`year`) AS `playoffs` from (select `vw_playoff_series_results`.`winner` AS `team_name`,`vw_playoff_series_results`.`year` AS `year` from `iblhoops_ibl5`.`vw_playoff_series_results` where `vw_playoff_series_results`.`round` = 1 union select `vw_playoff_series_results`.`loser` AS `team_name`,`vw_playoff_series_results`.`year` AS `year` from `iblhoops_ibl5`.`vw_playoff_series_results` where `vw_playoff_series_results`.`round` = 1) `po_inner` group by `po_inner`.`team_name`) `po` on(`po`.`team_name` = `ti`.`team_name`)) left join (select `vw_team_awards`.`name` AS `name`,sum(case when `vw_team_awards`.`Award` like '%Division%' then 1 else 0 end) AS `div_titles`,sum(case when `vw_team_awards`.`Award` like '%Conference%' then 1 else 0 end) AS `conf_titles`,sum(case when `vw_team_awards`.`Award` like '%IBL Champions%' then 1 else 0 end) AS `ibl_titles`,sum(case when `vw_team_awards`.`Award` like '%HEAT%' then 1 else 0 end) AS `heat_titles` from `iblhoops_ibl5`.`vw_team_awards` group by `vw_team_awards`.`name`) `tc` on(`tc`.`name` = `ti`.`team_name`)) where `ti`.`teamid` between 1 and 30;

CREATE OR REPLACE VIEW `vw_team_total_score` AS select `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date` AS `Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`game_type` AS `game_type`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitorScore`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ1points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ2points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ3points` + `iblhoops_ibl5`.`ibl_box_scores_teams`.`homeQ4points` + coalesce(`iblhoops_ibl5`.`ibl_box_scores_teams`.`homeOTpoints`,0) AS `homeScore` from `iblhoops_ibl5`.`ibl_box_scores_teams`;

CREATE OR REPLACE VIEW `vw_schedule_upcoming` AS select `sch`.`uuid` AS `game_uuid`,`sch`.`SchedID` AS `schedule_id`,`sch`.`Year` AS `season_year`,`sch`.`Date` AS `game_date`,`sch`.`BoxID` AS `box_score_id`,coalesce(`bst`.`gameOfThatDay`,0) AS `game_of_that_day`,`tv`.`uuid` AS `visitor_uuid`,`tv`.`teamid` AS `visitor_team_id`,`tv`.`team_city` AS `visitor_city`,`tv`.`team_name` AS `visitor_name`,concat(`tv`.`team_city`,' ',`tv`.`team_name`) AS `visitor_full_name`,`sch`.`VScore` AS `visitor_score`,`th`.`uuid` AS `home_uuid`,`th`.`teamid` AS `home_team_id`,`th`.`team_city` AS `home_city`,`th`.`team_name` AS `home_name`,concat(`th`.`team_city`,' ',`th`.`team_name`) AS `home_full_name`,`sch`.`HScore` AS `home_score`,case when `sch`.`VScore` = 0 and `sch`.`HScore` = 0 then 'scheduled' else 'completed' end AS `game_status`,`sch`.`created_at` AS `created_at`,`sch`.`updated_at` AS `updated_at` from (((`iblhoops_ibl5`.`ibl_schedule` `sch` join `iblhoops_ibl5`.`ibl_team_info` `tv` on(`sch`.`Visitor` = `tv`.`teamid`)) join `iblhoops_ibl5`.`ibl_team_info` `th` on(`sch`.`Home` = `th`.`teamid`)) left join (select `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date` AS `Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,min(`iblhoops_ibl5`.`ibl_box_scores_teams`.`gameOfThatDay`) AS `gameOfThatDay` from `iblhoops_ibl5`.`ibl_box_scores_teams` group by `iblhoops_ibl5`.`ibl_box_scores_teams`.`Date`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`visitor_teamid`,`iblhoops_ibl5`.`ibl_box_scores_teams`.`home_teamid`) `bst` on(`bst`.`Date` = `sch`.`Date` and `bst`.`visitor_teamid` = `sch`.`Visitor` and `bst`.`home_teamid` = `sch`.`Home`));

CREATE OR REPLACE VIEW `ibl_allstar_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`gameDRB`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` in (50,51) group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_playoff_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`iblhoops_ibl5`.`ibl_box_scores` `bs` join `iblhoops_ibl5`.`ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `iblhoops_ibl5`.`ibl_franchise_seasons` `fs` on(`bs`.`teamid` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name`;

