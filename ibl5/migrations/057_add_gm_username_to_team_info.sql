-- Migration 056: Add gm_username column to ibl_team_info
-- Relocates the user-to-team mapping from nuke_users.user_ibl_team to ibl_team_info.gm_username

-- Add gm_username column
ALTER TABLE ibl_team_info
  ADD COLUMN IF NOT EXISTS gm_username VARCHAR(25) DEFAULT NULL AFTER owner_email,
  ADD INDEX IF NOT EXISTS idx_gm_username (gm_username);

-- Backfill from nuke_users
UPDATE ibl_team_info ti
  JOIN nuke_users nu ON nu.user_ibl_team = ti.team_name
SET ti.gm_username = nu.username
WHERE nu.user_ibl_team != ''
  AND ti.teamid BETWEEN 1 AND 28;

-- Update trigger to dual-write: when nuke_users.user_ibl_team changes,
-- also update ibl_team_info.gm_username to keep both columns in sync
DROP TRIGGER IF EXISTS trg_gm_tenure_track;

DELIMITER $$

CREATE TRIGGER trg_gm_tenure_track
AFTER UPDATE ON nuke_users
FOR EACH ROW
BEGIN
  DECLARE v_ending_year   SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;
  DECLARE v_phase         VARCHAR(128);
  DECLARE v_is_mid_season TINYINT(1);
  DECLARE v_old_franchise INT;
  DECLARE v_new_franchise INT;

  IF OLD.user_ibl_team <> NEW.user_ibl_team THEN

    -- ===== Dual-write: sync ibl_team_info.gm_username =====

    -- Clear gm_username on old team
    IF OLD.user_ibl_team <> '' THEN
      UPDATE ibl_team_info
         SET gm_username = NULL
       WHERE team_name = OLD.user_ibl_team
         AND gm_username = OLD.username;
    END IF;

    -- Set gm_username on new team
    IF NEW.user_ibl_team <> '' THEN
      UPDATE ibl_team_info
         SET gm_username = NEW.username
       WHERE team_name = NEW.user_ibl_team;
    END IF;

    -- ===== GM tenure tracking (existing logic) =====

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

    -- Close the old tenure (if the user was on a real team)
    IF OLD.user_ibl_team <> '' THEN
      SELECT teamid INTO v_old_franchise
        FROM ibl_team_info
       WHERE team_name = OLD.user_ibl_team
       LIMIT 1;

      IF v_old_franchise IS NOT NULL THEN
        UPDATE ibl_gm_tenures
           SET end_season_year   = v_beginning_year,
               is_mid_season_end = v_is_mid_season
         WHERE franchise_id   = v_old_franchise
           AND gm_username    = OLD.username
           AND end_season_year IS NULL;
      END IF;
    END IF;

    -- Open a new tenure (if the user is assigned to a real team)
    IF NEW.user_ibl_team <> '' THEN
      SELECT teamid INTO v_new_franchise
        FROM ibl_team_info
       WHERE team_name = NEW.user_ibl_team
       LIMIT 1;

      IF v_new_franchise IS NOT NULL THEN
        INSERT INTO ibl_gm_tenures
          (franchise_id, gm_username, start_season_year, is_mid_season_start)
        VALUES
          (v_new_franchise, NEW.username, v_beginning_year, v_is_mid_season);
      END IF;
    END IF;

  END IF;
END$$

DELIMITER ;
