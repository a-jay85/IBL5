-- Store two GM tenure names as display names, matching every other ibl_gm_tenures row.
--
-- trg_gm_tenure_track opens a tenure with the new GM's site username
-- (ibl_team_info.gm_username). The rest of the table holds display names, the same
-- spelling as ibl_team_info.owner_name. Two GMs ended up under their usernames:
--   SpecBob    -> Spec-Bob   (owner_name of the Sting; also his earlier tenures)
--   Smandel999 -> High Dave  (owner_name of the Rockets)
-- The Rivalries team page looks the current GM up by owner_name, so both teams showed
-- "This franchise has no general manager right now."
--
-- The WHERE on the old spelling makes this idempotent.

UPDATE ibl_gm_tenures
   SET gm_display_name = 'Spec-Bob'
 WHERE gm_display_name = 'SpecBob';

UPDATE ibl_gm_tenures
   SET gm_display_name = 'High Dave'
 WHERE gm_display_name = 'Smandel999'
   AND franchise_id = 17;
