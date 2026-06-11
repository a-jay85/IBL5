-- Migration 144: ibl_demands — add pid FK to ibl_plr and rebuild PK from name to pid (maintenance-43, backlog 15.8)
-- Data soundness (verified 2026-06-09): signedness int(11)/int(11); zero orphans; pid unique
-- (2026-03-05 prod dump = 762 demand rows / 762 distinct pids). ibl_plr.name is NOT unique
-- (migration 129), so the old name PK was a latent bug; no UNIQUE on name is added.
-- CASCADE forced: pid becomes NOT NULL PK (SET NULL impossible; RESTRICT blocks deletion);
-- matches migration 038 fk_olympics_stats_pid.
-- idx_pid retained intentionally — redundant once pid is PK, but DROP INDEX would trip adr-check.
-- Idempotency: FK is DROP IF EXISTS then ADD; PK rebuild relies on the runner version tracker (cf. 132).

ALTER TABLE `ibl_demands` DROP FOREIGN KEY IF EXISTS `fk_demands_player`;
ALTER TABLE `ibl_demands`
  ADD CONSTRAINT `fk_demands_player` FOREIGN KEY (`pid`)
    REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ibl_demands`
  DROP PRIMARY KEY,
  ADD PRIMARY KEY (`pid`);
