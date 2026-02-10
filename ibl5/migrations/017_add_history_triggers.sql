-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 17: History Auto-Maintenance Triggers
-- ============================================================================
-- Adds three triggers to automatically maintain ibl_franchise_seasons and
-- ibl_gm_tenures when GMs are reassigned, teams are rebranded, or a new
-- season begins.
--
-- PREREQUISITES:
-- - ibl_franchise_seasons and ibl_gm_tenures tables must exist
-- - ibl_settings must contain 'Current Season Ending Year' and
--   'Current Season Phase' rows
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: < 1 minute
--
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 1. trg_gm_tenure_track — AFTER UPDATE ON nuke_users
-- ---------------------------------------------------------------------------
-- Fires when user_ibl_team changes. Closes the old tenure row (if any) and
-- opens a new one for the new team (if any).
--
-- Guards: nuke_users is MyISAM so the UPDATE has already committed by the
-- time the trigger body runs. We use NULL checks on franchise_id lookups so
-- a typo in user_ibl_team doesn't raise an error.
-- ---------------------------------------------------------------------------

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

SELECT 'Trigger trg_gm_tenure_track created successfully' AS status;


-- ---------------------------------------------------------------------------
-- 2. trg_team_identity_sync — AFTER UPDATE ON ibl_team_info
-- ---------------------------------------------------------------------------
-- Fires when team_city or team_name changes. Upserts the current season's
-- ibl_franchise_seasons row with the new identity.
-- ---------------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_team_identity_sync;

DELIMITER $$
CREATE TRIGGER trg_team_identity_sync
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;

  IF OLD.team_city <> NEW.team_city OR OLD.team_name <> NEW.team_name THEN

    SELECT CAST(value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE name = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    INSERT INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    VALUES
      (NEW.teamid, v_beginning_year, v_ending_year, NEW.team_city, NEW.team_name)
    ON DUPLICATE KEY UPDATE
      team_city = NEW.team_city,
      team_name = NEW.team_name;

  END IF;
END$$
DELIMITER ;

SELECT 'Trigger trg_team_identity_sync created successfully' AS status;


-- ---------------------------------------------------------------------------
-- 3. trg_season_rollover — AFTER UPDATE ON ibl_settings
-- ---------------------------------------------------------------------------
-- Fires when the 'Current Season Ending Year' value changes. Bulk-inserts
-- one ibl_franchise_seasons row per team for the new season.
--
-- INSERT IGNORE handles the case where trg_team_identity_sync already
-- created a row for a team that was renamed in the same season.
-- ---------------------------------------------------------------------------

DROP TRIGGER IF EXISTS trg_season_rollover;

DELIMITER $$
CREATE TRIGGER trg_season_rollover
AFTER UPDATE ON ibl_settings
FOR EACH ROW
BEGIN
  DECLARE v_new_ending_year    SMALLINT UNSIGNED;
  DECLARE v_new_beginning_year SMALLINT UNSIGNED;

  IF NEW.name = 'Current Season Ending Year' AND OLD.value <> NEW.value THEN

    SET v_new_ending_year    = CAST(NEW.value AS UNSIGNED);
    SET v_new_beginning_year = v_new_ending_year - 1;

    INSERT IGNORE INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    SELECT teamid, v_new_beginning_year, v_new_ending_year, team_city, team_name
      FROM ibl_team_info;

  END IF;
END$$
DELIMITER ;

SELECT 'Trigger trg_season_rollover created successfully' AS status;
