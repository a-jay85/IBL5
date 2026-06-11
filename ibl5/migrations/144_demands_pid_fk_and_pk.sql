-- Migration 144: ibl_demands — add pid FK to ibl_plr and rebuild PK from name to pid (maintenance-43, backlog 15.8)
-- Data soundness: signedness int(11)/int(11); pid unique. ibl_plr.name is NOT unique
-- (migration 129), so the old name PK was a latent bug; no UNIQUE on name is added.
-- CASCADE forced: pid becomes NOT NULL PK (SET NULL impossible; RESTRICT blocks deletion);
-- matches migration 038 fk_olympics_stats_pid.
-- idx_pid retained intentionally — redundant once pid is PK, but DROP INDEX would trip adr-check.
-- Idempotency: FK is DROP IF EXISTS then ADD; PK rebuild relies on the runner version tracker (cf. 132).
--
-- Orphan cleanup (added 2026-06-11): the original "zero orphans" claim was verified against
-- a stale 2026-03-05 prod dump. Live prod had drifted — a player deleted from ibl_plr left a
-- dangling demand row (pid 4891, "CJ Elleby"), tripping errno 1452 on the FK ADD during deploy
-- (run 27371947228). A demand referencing a non-existent player is meaningless and CASCADE would
-- have removed it had the player been deleted via the FK, so we delete orphans before adding it.
-- LEFT JOIN form (not NOT IN) avoids the NULL-in-subquery footgun and matches 038's join idiom.
-- Idempotent: re-running on clean data deletes nothing.

DELETE d FROM `ibl_demands` d
  LEFT JOIN `ibl_plr` p ON d.`pid` = p.`pid`
  WHERE p.`pid` IS NULL;

ALTER TABLE `ibl_demands` DROP FOREIGN KEY IF EXISTS `fk_demands_player`;
ALTER TABLE `ibl_demands`
  ADD CONSTRAINT `fk_demands_player` FOREIGN KEY (`pid`)
    REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ibl_demands`
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`pid`);
