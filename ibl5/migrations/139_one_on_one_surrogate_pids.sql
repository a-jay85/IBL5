-- Migration 139: ibl_one_on_one winner_pid / loser_pid surrogate FKs
-- (maintenance-28 — backlog 15.14)
--
-- ibl_one_on_one stored winners/losers as bare player-name strings with no FK.
-- This adds nullable surrogate keys winner_pid / loser_pid -> ibl_plr.pid and
-- backfills them from ibl_plr by name. The display strings winner/loser are kept
-- (backward compat); the surrogate keys are the join-safe identity.
--
-- Ambiguity handling: a player *name* is not unique in ibl_plr (the same name can
-- map to multiple pids). The backfill therefore matches ONLY names that resolve
-- to exactly one pid (HAVING COUNT(DISTINCT pid) = 1); an ambiguous name is left
-- NULL rather than guessing. (CI-seed audit: zero ambiguous names, zero rows in
-- ibl_one_on_one, so the backfill is a no-op on the seed.)
--
-- The FKs are ON DELETE SET NULL: deleting a player nulls the surrogate but
-- preserves the historical matchup row (and its display strings). Signedness
-- matches — winner_pid/loser_pid int(11) <-> ibl_plr.pid int(11).
--
-- Idempotent: ADD COLUMN/INDEX IF NOT EXISTS; the FK is DROP IF EXISTS then ADD;
-- the backfill is guarded by `IS NULL` so re-running never overwrites a value.

ALTER TABLE `ibl_one_on_one`
  ADD COLUMN IF NOT EXISTS `winner_pid` int(11) DEFAULT NULL
    COMMENT 'Winning player surrogate FK -> ibl_plr.pid (NULL if name unmatched/ambiguous)' AFTER `winner`,
  ADD COLUMN IF NOT EXISTS `loser_pid` int(11) DEFAULT NULL
    COMMENT 'Losing player surrogate FK -> ibl_plr.pid (NULL if name unmatched/ambiguous)' AFTER `loser`;

ALTER TABLE `ibl_one_on_one` ADD INDEX IF NOT EXISTS `idx_winner_pid` (`winner_pid`);
ALTER TABLE `ibl_one_on_one` ADD INDEX IF NOT EXISTS `idx_loser_pid` (`loser_pid`);

-- Backfill unambiguous names only (name -> single pid).
UPDATE `ibl_one_on_one` o
  JOIN (SELECT `name`, MIN(`pid`) AS pid FROM `ibl_plr` GROUP BY `name` HAVING COUNT(DISTINCT `pid`) = 1) p
    ON o.`winner` = p.`name`
   SET o.`winner_pid` = p.pid
 WHERE o.`winner_pid` IS NULL AND o.`winner` <> '';

UPDATE `ibl_one_on_one` o
  JOIN (SELECT `name`, MIN(`pid`) AS pid FROM `ibl_plr` GROUP BY `name` HAVING COUNT(DISTINCT `pid`) = 1) p
    ON o.`loser` = p.`name`
   SET o.`loser_pid` = p.pid
 WHERE o.`loser_pid` IS NULL AND o.`loser` <> '';

-- Add the FKs after backfill (drop-then-add for idempotency).
ALTER TABLE `ibl_one_on_one` DROP FOREIGN KEY IF EXISTS `fk_one_on_one_winner_pid`;
ALTER TABLE `ibl_one_on_one` DROP FOREIGN KEY IF EXISTS `fk_one_on_one_loser_pid`;

ALTER TABLE `ibl_one_on_one`
  ADD CONSTRAINT `fk_one_on_one_winner_pid` FOREIGN KEY (`winner_pid`)
    REFERENCES `ibl_plr` (`pid`) ON DELETE SET NULL ON UPDATE CASCADE;
ALTER TABLE `ibl_one_on_one`
  ADD CONSTRAINT `fk_one_on_one_loser_pid` FOREIGN KEY (`loser_pid`)
    REFERENCES `ibl_plr` (`pid`) ON DELETE SET NULL ON UPDATE CASCADE;
