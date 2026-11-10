-- Undo for migration 207's backfill. Apply by hand. bin/db-migrate globs only top-level ibl5/migrations/*.sql, so it never runs a file in undo/.
--
-- Restores ibl_gm_tenures.gm_display_name to the value it held before the backfill, by id, from the
-- full-row copies migration 207 wrote to ibl_gm_tenures_backup_207. Rows the backfill did not touch
-- have no backup row and are left alone. Re-running is safe: it writes the same old values again.
--
-- Restore the old trigger first (migration 192's trg_gm_tenure_track block), or the next GM change
-- opens a display-name tenure again.

UPDATE ibl_gm_tenures, ibl_gm_tenures_backup_207 b
   SET ibl_gm_tenures.gm_display_name = b.gm_display_name
 WHERE ibl_gm_tenures.id = b.id;
