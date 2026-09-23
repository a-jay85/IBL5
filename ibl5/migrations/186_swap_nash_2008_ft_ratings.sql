-- Un-transpose Steve Nash's (pid 3558) 2007-08 free-throw ratings.
--
-- A JSB bug swapped his FTA and FTP ratings. The sim ran with the corrected values from
-- January 2008 on (he shot 85-97% from the line after shooting ~50% in Nov-Dec), but every
-- stored copy of his 2008 ratings kept the swapped pair: FTA 84, FTP 45. The 2008
-- end-of-season snapshot is the TrainingCampRatingsDiff baseline, so the page showed
-- -36 FTA / +43 FTP against his corrected 2009 ratings.
--
-- ibl_hist is rebuilt from ibl_plr_snapshots by RefreshIblHistStep; it is patched here
-- too so it is right before the next updater run. The WHERE on the swapped pair makes
-- this idempotent.

UPDATE ibl_plr_snapshots
   SET r_fta = 45, r_ftp = 84
 WHERE pid = 3558
   AND season_year = 2008
   AND snapshot_phase IN ('mid-season', 'end-of-season')
   AND r_fta = 84
   AND r_ftp = 45;

UPDATE ibl_hist
   SET r_fta = 45, r_ftp = 84
 WHERE pid = 3558
   AND year = 2008
   AND r_fta = 84
   AND r_ftp = 45;
