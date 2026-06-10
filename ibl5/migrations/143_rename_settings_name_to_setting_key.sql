-- Migration 143: Rename ibl_settings.name -> setting_key (reserved-word cleanup).
-- Per ADR-0008 (ban reserved-word columns). Composite PK (name, league) [migration 132]
-- rebuilt as (setting_key, league). THREE triggers recreated from latest canonical bodies:
--   trg_team_identity_sync (mig 140), trg_gm_tenure_track (mig 100), trg_season_rollover (mig 017).
-- Recreation avoids the PRECEDES/FOLLOWS circular dep: drop both ibl_team_info triggers,
-- recreate trg_team_identity_sync with NO ordering clause (order 1), then trg_gm_tenure_track
-- FOLLOWS it (order 2). Matches verified live action_order 1 -> 2.
-- destructive-migration: reserved-word column rename with composite primary key rebuild, reviewed under ADR-0008

ALTER TABLE `ibl_settings`
    DROP PRIMARY KEY,
    CHANGE COLUMN `name` `setting_key` VARCHAR(128) NOT NULL COMMENT 'Setting key',
    ADD PRIMARY KEY (`setting_key`, `league`);

DROP TRIGGER IF EXISTS trg_gm_tenure_track;
DROP TRIGGER IF EXISTS trg_team_identity_sync;

DELIMITER $$

-- trg_team_identity_sync: migration 140 body, recreated with NO ordering clause
-- (sole trigger -> action_order 1), WHERE setting_key = 'Current Season Ending Year'.
CREATE TRIGGER trg_team_identity_sync
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;

  IF OLD.team_city <> NEW.team_city OR OLD.team_name <> NEW.team_name THEN

    SELECT CAST(value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE setting_key = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    INSERT INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    VALUES
      (NEW.teamid, v_beginning_year, v_ending_year, NEW.team_city, NEW.team_name)
    ON DUPLICATE KEY UPDATE
      team_city = NEW.team_city,
      team_name = NEW.team_name;

    -- maintenance-28 (15.19): keep the denormalized league-config name in sync.
    UPDATE ibl_league_config
       SET team_name = NEW.team_name
     WHERE teamid = NEW.teamid;

  END IF;
END$$

-- trg_gm_tenure_track: migration 100 body, recreated FOLLOWS trg_team_identity_sync
-- (action_order 2), WHERE setting_key = 'Current Season Ending Year' / 'Current Season Phase'.
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
     WHERE setting_key = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    SELECT value INTO v_phase
      FROM ibl_settings
     WHERE setting_key = 'Current Season Phase'
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

-- trg_season_rollover: migration 017 body, AFTER UPDATE ON ibl_settings,
-- IF NEW.setting_key = 'Current Season Ending Year' AND OLD.value <> NEW.value.
DROP TRIGGER IF EXISTS trg_season_rollover$$
CREATE TRIGGER trg_season_rollover
AFTER UPDATE ON ibl_settings
FOR EACH ROW
BEGIN
  DECLARE v_new_ending_year    SMALLINT UNSIGNED;
  DECLARE v_new_beginning_year SMALLINT UNSIGNED;

  IF NEW.setting_key = 'Current Season Ending Year' AND OLD.value <> NEW.value THEN

    SET v_new_ending_year    = CAST(NEW.value AS UNSIGNED);
    SET v_new_beginning_year = v_new_ending_year - 1;

    INSERT IGNORE INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    SELECT teamid, v_new_beginning_year, v_new_ending_year, team_city, team_name
      FROM ibl_team_info;

  END IF;
END$$

DELIMITER ;
