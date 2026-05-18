-- One-time cleanup. The .rcb file is cumulative and authoritative; the
-- next current-season import via ParseJsbFilesStep will repopulate clean.
-- Historical .rcb backups previously contributed orphan rows for teams
-- 4-28 (only rank 1 was overwritten by current-season).
DELETE FROM ibl_rcb_alltime_records;
DELETE FROM ibl_olympics_rcb_alltime_records;
