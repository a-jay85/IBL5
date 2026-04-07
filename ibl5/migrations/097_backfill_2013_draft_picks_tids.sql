-- Migration 097: Backfill owner_tid / teampick_tid for 2013 ibl_draft_picks rows
--
-- The 2013 picks were imported after migration 040 ran and thus missed the
-- one-time backfill JOIN that populates owner_tid / teampick_tid from
-- ibl_team_info. All 56 rows were left at the default 0 for both columns.
--
-- Two of the 2013 franchise names in ownerofpick / teampick use historical
-- JSB names that no longer exist in ibl_team_info:
--   Hornets -> Sting  (teamid 10)
--   Thunder -> Aces   (teamid 16)
-- These aliases mirror JsbImportRepository::TEAM_NAME_ALIASES.

-- Direct matches (26 of 28 franchises).
UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.ownerofpick = t.team_name
   SET dp.owner_tid = t.teamid
 WHERE dp.year = 2013
   AND dp.owner_tid = 0;

UPDATE ibl_draft_picks dp
  JOIN ibl_team_info t ON dp.teampick = t.team_name
   SET dp.teampick_tid = t.teamid
 WHERE dp.year = 2013
   AND dp.teampick_tid = 0;

-- Historical alias: Hornets -> Sting.
UPDATE ibl_draft_picks
   SET owner_tid = (SELECT teamid FROM ibl_team_info WHERE team_name = 'Sting' LIMIT 1)
 WHERE year = 2013
   AND ownerofpick = 'Hornets'
   AND owner_tid = 0;

UPDATE ibl_draft_picks
   SET teampick_tid = (SELECT teamid FROM ibl_team_info WHERE team_name = 'Sting' LIMIT 1)
 WHERE year = 2013
   AND teampick = 'Hornets'
   AND teampick_tid = 0;

-- Historical alias: Thunder -> Aces.
UPDATE ibl_draft_picks
   SET owner_tid = (SELECT teamid FROM ibl_team_info WHERE team_name = 'Aces' LIMIT 1)
 WHERE year = 2013
   AND ownerofpick = 'Thunder'
   AND owner_tid = 0;

UPDATE ibl_draft_picks
   SET teampick_tid = (SELECT teamid FROM ibl_team_info WHERE team_name = 'Aces' LIMIT 1)
 WHERE year = 2013
   AND teampick = 'Thunder'
   AND teampick_tid = 0;
