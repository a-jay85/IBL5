-- Migration 207: trg_gm_tenure_track stores GM display names and follows owner_name renames (backlog#1708).
--
-- Before: the trigger opened a tenure under ibl_team_info.gm_username (the site username) and closed
-- the old tenure by matching that same username. Every other ibl_gm_tenures row holds a display name,
-- the same spelling as ibl_team_info.owner_name. So the next GM change wrote a username row, and the
-- close step could never find a row that held a display name.
--
-- After:
--   * A gm_username change closes the franchise's open tenure by franchise and open state, then opens
--     the new tenure under owner_name. An empty owner_name falls back to gm_username.
--   * An owner_name change with an unchanged gm_username is a rename of the same person. The trigger
--     rewrites the franchise's open tenure and the matching 'Name (Team)' rows in ibl_gm_history.
--   * ibl_gm_awards and ibl_votes_EOY are out of scope. Their name columns carry no franchise key.
--
-- Write orders for the two columns (no PHP writer exists, so an admin edits them in a DB tool):
--   1. Both columns in one UPDATE statement: exact. This is the supported procedure.
--   2. gm_username first, then owner_name: exact. The second statement takes the rename branch and
--      fixes the tenure the first statement opened under the stale name.
--   3. owner_name first, then gm_username: the rename branch renames the outgoing GM's open tenure
--      to the incoming GM's name before the second statement closes it. One closed row then carries
--      the wrong name. No single-row AFTER UPDATE trigger can tell this from a plain rename.
--      Recovery is a one-row UPDATE on that closed tenure.
--
-- Apply-time guard: the new trigger closes and renames "the franchise's open tenure", so exactly one
-- open row per franchise must hold when it takes over. The guard runs before any DROP, so an abort
-- leaves both triggers in place.
--
-- Rollback: re-run the trigger block of migration 192 (trg_gm_tenure_track), then restore the rows the
-- backfill touched with UPDATE ibl_gm_tenures SET gm_display_name = <gm_username> WHERE id = <id>.
-- Idempotent: DROP TRIGGER IF EXISTS, and a backfill whose WHERE matches nothing on a second run.
--
-- trg_team_identity_sync is recreated with migration 192's body unchanged and no ordering clause
-- (action_order 1). trg_gm_tenure_track is recreated FOLLOWS it (action_order 2), which avoids the
-- PRECEDES/FOLLOWS circular dependency.

DELIMITER $$

BEGIN NOT ATOMIC
  IF EXISTS (SELECT 1 FROM ibl_gm_tenures
              WHERE end_season_year IS NULL
              GROUP BY franchise_id HAVING COUNT(*) > 1) THEN
    SIGNAL SQLSTATE '45000'
      SET MESSAGE_TEXT = 'gm_tenure_trigger_display_name aborted: a franchise has more than one open ibl_gm_tenures row';
  END IF;
END$$

DROP TRIGGER IF EXISTS trg_gm_tenure_track$$
DROP TRIGGER IF EXISTS trg_team_identity_sync$$

-- trg_team_identity_sync: migration 192 body, unchanged.
CREATE TRIGGER trg_team_identity_sync
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;

  IF OLD.team_city <> NEW.team_city OR OLD.team_name <> NEW.team_name THEN

    SELECT CAST(setting_value AS UNSIGNED) INTO v_ending_year
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

CREATE TRIGGER trg_gm_tenure_track
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
FOLLOWS trg_team_identity_sync
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;
  DECLARE v_phase          VARCHAR(128);
  DECLARE v_is_mid_season  TINYINT(1);
  DECLARE v_new_name       VARCHAR(60);

  IF NOT (OLD.gm_username <=> NEW.gm_username) THEN

    SELECT CAST(setting_value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE setting_key = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    SELECT setting_value INTO v_phase
      FROM ibl_settings
     WHERE setting_key = 'Current Season Phase'
     LIMIT 1;

    SET v_is_mid_season = (v_phase IN ('Regular Season', 'Playoffs', 'HEAT'));

    -- Close the franchise's open tenure whatever name it holds.
    UPDATE ibl_gm_tenures
       SET end_season_year   = v_beginning_year,
           is_mid_season_end = v_is_mid_season
     WHERE franchise_id    = OLD.teamid
       AND end_season_year IS NULL;

    IF NEW.gm_username IS NOT NULL AND NEW.gm_username <> '' THEN
      SET v_new_name = IF(NEW.owner_name <> '', NEW.owner_name, NEW.gm_username);
      INSERT INTO ibl_gm_tenures
        (franchise_id, gm_display_name, start_season_year, is_mid_season_start)
      VALUES
        (NEW.teamid, v_new_name, v_beginning_year, v_is_mid_season);
    END IF;

  ELSEIF NOT (OLD.owner_name <=> NEW.owner_name) AND NEW.owner_name <> '' THEN

    -- Same GM, new display name: rename the franchise's open tenure only.
    UPDATE ibl_gm_tenures
       SET gm_display_name = NEW.owner_name
     WHERE franchise_id    = NEW.teamid
       AND end_season_year IS NULL
       AND gm_display_name <> NEW.owner_name;

    -- ibl_gm_history stores 'Name (Team)'. The team suffix is the franchise key.
    IF OLD.owner_name <> ''
       AND CHAR_LENGTH(CONCAT(NEW.owner_name, ' (', NEW.team_name, ')')) <= 50 THEN
      UPDATE ibl_gm_history
         SET name = CONCAT(NEW.owner_name, ' (', NEW.team_name, ')')
       WHERE name = CONCAT(OLD.owner_name, ' (', NEW.team_name, ')');
    END IF;

  END IF;
END$$

DELIMITER ;

-- Backfill: open rows that hold exactly the username get the display name.
UPDATE ibl_gm_tenures t
  JOIN ibl_team_info ti ON ti.teamid = t.franchise_id
   SET t.gm_display_name = ti.owner_name
 WHERE t.end_season_year IS NULL
   AND ti.owner_name <> ''
   AND ti.gm_username IS NOT NULL
   AND t.gm_display_name = ti.gm_username
   AND t.gm_display_name <> ti.owner_name;
