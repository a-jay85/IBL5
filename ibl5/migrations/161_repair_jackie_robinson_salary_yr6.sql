-- Migration 161: Repair Jackie Robinson's (pid 4158) corrupt sixth-year salary.
--
-- Reported via the bug pipeline (ibl5-bugs #13): the player page showed a 4th-year
-- salary that did not match his contract. Diagnosis: `salary_yr6` is stored as 159
-- where it should be 1591.
--
-- The contract is a Bird-max deal whose yearly raise is a flat +132:
--   1063 -> 1063 -> 1195 -> 1327 -> 1459 -> [1591]
-- Every other year in the ladder is intact; only the final year is wrong, and it is
-- wrong by exactly a dropped trailing digit (1591 -> 159). This is a data-entry /
-- truncation defect in the original contract row, NOT a code bug: seven code paths
-- that write salary_yr6 were checked and all round-trip the value unchanged.
--
-- Scope. Two tables carry the bad value:
--   * ibl_plr             — the live roster row (1 row).
--   * ibl_plr_snapshots   — 37 archived rows, seasons 2007 and 2008. The corruption
--                           is present from the earliest snapshot that has a yr6 at
--                           all (2007 training-camp), so there is no "good" earlier
--                           value to restore from; 1591 is derived from the raise
--                           ladder, not recovered.
--
-- ibl_hist is deliberately NOT touched. It is a materialized projection that
-- Updater\Steps\RefreshIblHistStep rebuilds with DELETE + INSERT..SELECT from
-- ibl_plr_snapshots, so repairing the snapshots is the durable fix; the next refresh
-- propagates it.
--
-- Idempotent. Both statements are guarded on the wrong value (`salary_yr6 = 159`), so
-- a second run matches zero rows. They cannot touch a correctly-valued row, and they
-- cannot touch any player other than pid 4158.
--
-- KNOWN FOLLOW-UP (not fixable in SQL): the roster file IBL5.plr carries ' 159' at
-- byte offsets 318-321 for pid 4158, and PlrParser\PlrParserRepository upserts
-- `salary_yr6 = VALUES(salary_yr6)` on every .plr import. Unless the DB->.plr export
-- (ibl5/scripts/jsbExport.php, which writes salary_yr1..6 via PlrFileWriter) runs and
-- overwrites that field before the next sim import, this correction is reverted and
-- the bug is refiled. Run the export after this migration deploys.

UPDATE ibl_plr
   SET salary_yr6 = 1591
 WHERE pid = 4158
   AND salary_yr6 = 159;

UPDATE ibl_plr_snapshots
   SET salary_yr6 = 1591
 WHERE pid = 4158
   AND salary_yr6 = 159;
