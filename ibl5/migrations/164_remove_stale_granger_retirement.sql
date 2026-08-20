-- Migration: 164_remove_stale_granger_retirement.sql
-- Purpose: Danny Granger (pid/jsb_pid 5645) was never actually retired by JSB.
--          He was imported from a SUPERSEDED .ret snapshot and has been stuck
--          retired ever since. This removes the source row and undoes the flag
--          that migration 163 derived from it.
--
-- Trace:
--   * `ibl5/backups/06-07/06-07_36_finals.zip` (example) -> IBL5.ret, JSB internal
--     timestamp 2026-03-11 20:05, lists "Danny Granger 5645" on line 1.
--   * The very next snapshot, `ibl5/backups/07-08/07-08_01_preseason.zip` (example)
--     -> IBL5.ret, JSB timestamp 2026-03-11 23:12 (~3h later), is byte-identical
--     MINUS that single line. JSB un-retired him the same night. He appears in no
--     later archive, and the live .ret on disk today does not name him.
--   * BackupArchiveLocator::findLatestArchive() selects by newest mtime. On import
--     day 2026-04-19 the stale finals zip was genuinely the newest file present --
--     the corrected preseason zip did not land until 2026-04-23. The locator read
--     the only file that existed; it is not the defect.
--   * ibl5/classes/JsbParser/Repositories/RetRepository.php writes
--     INSERT ... ON DUPLICATE KEY UPDATE with NO DELETE. A .ret file is a full
--     OVERWRITE snapshot, not a cumulative log, so a name dropped from a later
--     snapshot is never cleared. That made row 137 permanent.
--   * Migration 163 then propagated row 137 into ibl_plr.retired = 1, which is
--     what drives the career-leaderboard asterisk and hid him from the Heat roster.
--
-- Why the DELETE and not just the flag: the .ret -> ibl_plr propagation write path
-- is still unbuilt (see ibl5/docs/backlog/retired-flag-backfill-and-ret-propagation.md).
-- Clearing ibl_plr.retired alone would be re-flagged off row 137 the moment that
-- write path ships. The stale source row has to go.
--
-- Scope: exactly one player. The other seven pids backfilled by 163
-- (327, 1250, 2000, 2750, 3301, 3307, 5318) are all still named in the CURRENT
-- .ret snapshot and are left untouched.
--
-- Idempotent: DELETE is naturally idempotent; the UPDATE carries an AND retired = 1
-- guard, so a second run touches 0 rows.
-- Date: 2026-08-20

DELETE FROM ibl_jsb_retired_players
 WHERE jsb_pid = 5645
   AND retirement_year = 2007;

UPDATE ibl_plr
   SET retired = 0
 WHERE pid = 5645
   AND retired = 1;
