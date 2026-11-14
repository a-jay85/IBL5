-- Migration 215: trg_gm_tenure_track no longer fails on uq_tenure when a same-season GM change sets only gm_username (backlog#1812).
--
-- Before (migration 207): a gm_username change closed the franchise's open tenure, then inserted the new
-- tenure under owner_name. When owner_name was unchanged and the outgoing tenure started this season, the
-- new row carried the same (franchise_id, gm_display_name, start_season_year) as the row just closed. The
-- INSERT failed with ERROR 1062 on uq_tenure, and the admin's UPDATE of ibl_team_info failed with it.
-- Statement order inside the trigger plays no part. The collision is in the INSERT.
--
-- After:
--   * When owner_name already names a tenure of this franchise that started this season, the new tenure
--     opens under gm_username. Migration 207 uses the same fallback for an empty owner_name.
--   * When that name collides too (a username changed and then changed back within one season), the INSERT
--     reopens the existing row through ON DUPLICATE KEY UPDATE. The close step has already closed every open
--     row of the franchise, so the franchise ends with exactly one open tenure either way.
--   * Every other path keeps migration 207's body unchanged.
--
-- Write orders for the two columns (no PHP writer exists, so an admin edits them in a DB tool):
--   1. Both columns in one UPDATE statement: exact.
--   2. gm_username first, then owner_name: exact. If the outgoing tenure started in an earlier season, the
--      first statement opens the new tenure under the stale owner_name. If it started this season, the first
--      statement opens it under gm_username. In both cases the second statement takes the rename branch and
--      sets the open tenure to the new owner_name, so orders 1 and 2 end in the same rows.
--   3. owner_name first, then gm_username: unchanged from migration 207. One closed row carries the incoming
--      GM's name. Recovery is a one-row UPDATE on that closed tenure.
--
-- Residual: when the same GM changes only gm_username within the season their tenure started, the open
-- tenure sits under the username next to their closed same-season row. Recovery is two hand edits on
-- ibl_gm_tenures: remove the open username row, then set end_season_year = NULL and is_mid_season_end = 0
-- on the closed row.
--
-- Rollback: re-run the trg_gm_tenure_track block of migration 207 (its DROP TRIGGER IF EXISTS and CREATE
-- TRIGGER ... FOLLOWS trg_team_identity_sync). This migration changes no rows, so it ships no undo script and
-- no .down.sql partner.
-- Idempotent: DROP TRIGGER IF EXISTS, then CREATE TRIGGER.
--
-- destructive-migration[rewrite-no-backup,down-file-missing]: the flagged UPDATEs of ibl_gm_tenures and ibl_gm_history sit inside the CREATE TRIGGER body of trg_gm_tenure_track and run per row at trigger time. This migration rewrites no data when it applies, so a .down.sql partner has nothing to restore. Rollback is re-running migration 207's trigger block.

DELIMITER $$

DROP TRIGGER IF EXISTS trg_gm_tenure_track$$

CREATE TRIGGER trg_gm_tenure_track
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
FOLLOWS trg_team_identity_sync
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;
  DECLARE v_phase          VARCHAR(128);
  DECLARE v_is_mid_season  TINYINT(1);
  -- Same collation as ibl_gm_tenures.gm_display_name, so the IF EXISTS comparison below does not
  -- raise "Illegal mix of collations" (the trigger runs under the session's utf8mb4_general_ci).
  DECLARE v_new_name       VARCHAR(60) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

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

      -- owner_name still names a tenure that started this season: open under the username (backlog#1812).
      IF EXISTS (SELECT 1 FROM ibl_gm_tenures
                  WHERE franchise_id      = NEW.teamid
                    AND gm_display_name   = v_new_name
                    AND start_season_year = v_beginning_year) THEN
        SET v_new_name = NEW.gm_username;
      END IF;

      -- A username changed back within one season collides again: reopen that row.
      INSERT INTO ibl_gm_tenures
        (franchise_id, gm_display_name, start_season_year, is_mid_season_start)
      VALUES
        (NEW.teamid, v_new_name, v_beginning_year, v_is_mid_season)
      ON DUPLICATE KEY UPDATE
        end_season_year   = NULL,
        is_mid_season_end = 0;
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
