-- Migration 098: Replace historical JSB franchise names in ibl_draft_picks
--
-- Migration 097 backfilled owner_tid / teampick_tid for 2013 picks but left
-- the historical JSB franchise names in ownerofpick / teampick untouched:
--   Hornets  (now Sting, teamid 10)
--   Thunder  (now Aces,  teamid 16)
--
-- Every other year (2008-2012) already stores current franchise names in the
-- text columns, so the 2013 rows are the lone inconsistency. This migration
-- rewrites them to the current names to match the rest of the table.
--
-- The tid columns stay correct (Sting=10, Aces=16) — migration 097 already
-- pointed them at the right franchises via JsbImportRepository::TEAM_NAME_ALIASES.

UPDATE ibl_draft_picks
   SET ownerofpick = 'Sting'
 WHERE year = 2013
   AND ownerofpick = 'Hornets';

UPDATE ibl_draft_picks
   SET teampick = 'Sting'
 WHERE year = 2013
   AND teampick = 'Hornets';

UPDATE ibl_draft_picks
   SET ownerofpick = 'Aces'
 WHERE year = 2013
   AND ownerofpick = 'Thunder';

UPDATE ibl_draft_picks
   SET teampick = 'Aces'
 WHERE year = 2013
   AND teampick = 'Thunder';
