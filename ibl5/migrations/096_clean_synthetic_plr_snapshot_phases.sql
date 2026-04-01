-- Remove synthetic phase names from ibl_plr_snapshots.
-- These were created by bulkPlrSnapshotImport.php which previously normalized
-- archive phases to 'end-of-season' and 'heat-end'. The script now uses the
-- actual archive phase names (e.g., 'finals', 'heat-wb', 'reg-sim01').
-- Re-running the import will repopulate with archive-derived phase names.

DELETE FROM ibl_plr_snapshots WHERE snapshot_phase IN ('end-of-season', 'heat-end');
