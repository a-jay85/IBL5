-- Migration: 162_correct_game_of_that_day_comments.sql
-- Purpose: Correct the misleading COMMENT on every game_of_that_day column.
--          The existing comments (migration 034 / 121 for the box-score tables,
--          migration 156 for ibl_sim_game_recaps) describe the column as a
--          doubleheader / per-matchup counter. It is not: it is the 1-based index
--          of a game within its DATE across the entire league (sim 725 had 49
--          games on one date, indexed 1..49). Basketball has no doubleheaders and
--          JSB never sims one, so a (game_date, visitor_teamid, home_teamid)
--          triple is already unique. The false reading has produced a real bug
--          (see ibl5/migrations/157_repair_sim725_game_of_that_day.sql) and
--          repeated phantom defect reports.
--          Correct semantics are already documented in bin/sim-recap-prompt.
-- Scope: COMMENT text only. Every MODIFY COLUMN below restates the column's
--        existing type, nullability and default verbatim (verified against
--        SHOW FULL COLUMNS) so no schema change ships with the wording fix.
-- Date: 2026-08-18

ALTER TABLE `ibl_box_scores` MODIFY COLUMN `game_of_that_day` tinyint(3) unsigned DEFAULT NULL COMMENT '1-based index of this game within its date across the whole league (NOT a doubleheader / per-matchup counter)';

ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `game_of_that_day` int(11) DEFAULT NULL COMMENT '1-based index of this game within its date across the whole league (NOT a doubleheader / per-matchup counter)';

ALTER TABLE `ibl_olympics_box_scores` MODIFY COLUMN `game_of_that_day` int(11) DEFAULT NULL COMMENT '1-based index of this game within its date across the whole league (NOT a doubleheader / per-matchup counter)';

ALTER TABLE `ibl_olympics_box_scores_teams` MODIFY COLUMN `game_of_that_day` int(11) DEFAULT NULL COMMENT '1-based index of this game within its date across the whole league (NOT a doubleheader / per-matchup counter)';

ALTER TABLE `ibl_sim_game_recaps` MODIFY COLUMN `game_of_that_day` int(11) NOT NULL DEFAULT 0 COMMENT '1-based index of this game within its date across the whole league (NOT a per-matchup counter); NULL->0 normalised (matches ibl_box_scores_teams)';
