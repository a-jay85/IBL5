-- JSB file parsers (TRN, HIS, RCB alltime) stored the season beginning year
-- (e.g. 1988 for the 1988-89 season) while other import paths (ASW, PLB, RCB
-- season, RET) correctly stored the ending year (1989). Shift the four affected
-- columns so every table uses ending-year convention.
--
-- ORDER BY ... DESC avoids UNIQUE KEY collisions when the same key combination
-- appears in consecutive seasons (e.g. same player injured on the same date).

UPDATE ibl_jsb_transactions
   SET season_year = season_year + 1
 ORDER BY season_year DESC;

UPDATE ibl_jsb_history
   SET season_year = season_year + 1
 ORDER BY season_year DESC;

UPDATE ibl_rcb_alltime_records
   SET season_year = season_year + 1
 WHERE season_year IS NOT NULL
   AND season_year > 0
 ORDER BY season_year DESC;

UPDATE ibl_rcb_season_records
   SET record_season_year = record_season_year + 1
 ORDER BY record_season_year DESC;
