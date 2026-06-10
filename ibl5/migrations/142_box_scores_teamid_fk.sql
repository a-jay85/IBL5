-- Migration 142: ibl_box_scores.teamid foreign key (+ Olympics parity)
-- (maintenance-41 — backlog 15.10; finding 15.2 already done by migration 114)
--
-- ibl_box_scores.teamid referenced ibl_team_info.teamid by value only, with no
-- FK constraint. This adds:
--   1. fk_boxscore_team          on ibl_box_scores.teamid          -> ibl_team_info.teamid
--   2. fk_olympics_boxscore_team on ibl_olympics_box_scores.teamid -> ibl_olympics_team_info.teamid (parity)
--
-- Column already exists with the correct name (single-token `teamid`, per
-- migration 114 and config/schema-assertions.php), signedness, and a covering
-- index (`idx_team_id`) — so this is an FK-add only: no rename, no index add,
-- no expand-contract. Mirrors migration 140 (idempotent DROP FK IF EXISTS -> ADD).
--
-- Signedness matches: ibl_box_scores.teamid int(11) (signed) <-> ibl_team_info.teamid
-- int(11) NOT NULL (signed). Constraint is valid.
--
-- Delete semantics: ON UPDATE CASCADE only (default RESTRICT on delete), mirroring
-- the existing sibling team FKs fk_boxscore_home / fk_boxscore_visitor. Franchises
-- are renamed (CASCADE keeps box-score teamids correct) but effectively never
-- deleted. Column stays nullable (no change) — legacy rows may carry NULL.
--
-- FK is safe — verified empirically, not assumed. Special teams are real rows in
-- ibl_team_info: 0 Free Agents, 1-28 franchises, 40 Rookies, 41 Sophomores,
-- 50 All-Star Away, 51 All-Star Home (League constants FREE_AGENTS_TEAMID(0),
-- ROOKIES_TEAMID(40), SOPHOMORES_TEAMID(41), ALL_STAR_AWAY_TEAMID(50),
-- ALL_STAR_HOME_TEAMID(51)). LEFT JOIN orphan check returned zero orphans on both
-- ibl_box_scores and ibl_olympics_box_scores. If prod held an orphan, ADD CONSTRAINT
-- fails loudly (fail-closed) rather than silently corrupting.
--
-- Idempotent: each FK is DROP IF EXISTS then ADD, so re-running is a no-op.
--
-- Pre-flight (run on prod before deploy if paranoid):
-- SELECT DISTINCT bs.teamid FROM ibl_box_scores bs LEFT JOIN ibl_team_info ti ON bs.teamid = ti.teamid WHERE ti.teamid IS NULL AND bs.teamid IS NOT NULL; -- expect empty
-- SELECT DISTINCT bs.teamid FROM ibl_olympics_box_scores bs LEFT JOIN ibl_olympics_team_info ti ON bs.teamid = ti.teamid WHERE ti.teamid IS NULL AND bs.teamid IS NOT NULL; -- expect empty

ALTER TABLE `ibl_box_scores` DROP FOREIGN KEY IF EXISTS `fk_boxscore_team`;
ALTER TABLE `ibl_box_scores`
  ADD CONSTRAINT `fk_boxscore_team` FOREIGN KEY (`teamid`)
    REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

ALTER TABLE `ibl_olympics_box_scores` DROP FOREIGN KEY IF EXISTS `fk_olympics_boxscore_team`;
ALTER TABLE `ibl_olympics_box_scores`
  ADD CONSTRAINT `fk_olympics_boxscore_team` FOREIGN KEY (`teamid`)
    REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;
