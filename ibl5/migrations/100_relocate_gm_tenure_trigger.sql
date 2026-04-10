-- Migration 100: Relocate trg_gm_tenure_track from nuke_users to ibl_team_info
--
-- The trigger now fires when ibl_team_info.gm_username changes (admin assigns
-- a new GM via direct DB edit). The old trigger on nuke_users is dropped.
--
-- Simplifications vs the old trigger:
-- - No dual-write to ibl_team_info (this table IS the source of truth)
-- - No discordID sync branch (discordID lives in ibl_team_info directly)
-- - NEW.teamid is directly available (no lookup needed)
-- - Uses <=> (null-safe equals) for the guard condition

DROP TRIGGER IF EXISTS trg_gm_tenure_track;

DELIMITER $$

CREATE TRIGGER trg_gm_tenure_track
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
FOLLOWS trg_team_identity_sync
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;
  DECLARE v_phase          VARCHAR(128);
  DECLARE v_is_mid_season  TINYINT(1);

  -- Only fire when gm_username changes
  IF NOT (OLD.gm_username <=> NEW.gm_username) THEN

    -- Read current season context
    SELECT CAST(value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE name = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    SELECT value INTO v_phase
      FROM ibl_settings
     WHERE name = 'Current Season Phase'
     LIMIT 1;

    SET v_is_mid_season = (v_phase IN ('Regular Season', 'Playoffs', 'HEAT'));

    -- Close the old tenure (if old GM was assigned)
    IF OLD.gm_username IS NOT NULL AND OLD.gm_username != '' THEN
      UPDATE ibl_gm_tenures
         SET end_season_year   = v_beginning_year,
             is_mid_season_end = v_is_mid_season
       WHERE franchise_id    = OLD.teamid
         AND gm_display_name = OLD.gm_username
         AND end_season_year IS NULL;
    END IF;

    -- Open a new tenure (if new GM assigned)
    IF NEW.gm_username IS NOT NULL AND NEW.gm_username != '' THEN
      INSERT INTO ibl_gm_tenures
        (franchise_id, gm_display_name, start_season_year, is_mid_season_start)
      VALUES
        (NEW.teamid, NEW.gm_username, v_beginning_year, v_is_mid_season);
    END IF;

  END IF;
END$$

DELIMITER ;
