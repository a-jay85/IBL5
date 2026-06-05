-- Migration 140: ibl_league_config.teamid surrogate FK + trigger sync
-- (maintenance-28 — backlog 15.19)
--
-- ibl_league_config referenced teams by team_name string only (no FK) and the
-- team-rename trigger did not keep that denormalized name in sync. This:
--   1. adds a nullable surrogate teamid -> ibl_team_info.teamid (FK, indexed),
--      backfilled from the current team_name;
--   2. extends trg_team_identity_sync so a team rename also rewrites
--      ibl_league_config.team_name for that teamid.
--
-- team_name in ibl_team_info is not unique across history, but among the CURRENT
-- franchises (the only rows ibl_team_info holds) it is, so the name-based
-- backfill is unambiguous. Signedness matches: teamid int(11) <-> teamid int(11).
-- FK is ON DELETE SET NULL (dropping a franchise nulls the surrogate, keeps the
-- config row).
--
-- Trigger: the body is the original franchise_seasons sync plus one statement —
-- `UPDATE ibl_league_config SET team_name = NEW.team_name WHERE teamid = NEW.teamid`
-- inside the existing identity-change guard. It is recreated PRECEDES
-- trg_gm_tenure_track to preserve the original activation order (migration 100
-- created that trigger FOLLOWS trg_team_identity_sync).
--
-- Idempotent: ADD COLUMN/INDEX IF NOT EXISTS; FK is DROP IF EXISTS then ADD;
-- backfill guarded by IS NULL; trigger is DROP IF EXISTS then CREATE.

ALTER TABLE `ibl_league_config`
  ADD COLUMN IF NOT EXISTS `teamid` int(11) DEFAULT NULL
    COMMENT 'FK -> ibl_team_info.teamid (NULL if team_name unmatched)' AFTER `team_name`;

ALTER TABLE `ibl_league_config` ADD INDEX IF NOT EXISTS `idx_teamid` (`teamid`);

UPDATE `ibl_league_config` lc
  JOIN `ibl_team_info` ti ON lc.`team_name` = ti.`team_name`
   SET lc.`teamid` = ti.`teamid`
 WHERE lc.`teamid` IS NULL;

ALTER TABLE `ibl_league_config` DROP FOREIGN KEY IF EXISTS `fk_league_config_teamid`;
ALTER TABLE `ibl_league_config`
  ADD CONSTRAINT `fk_league_config_teamid` FOREIGN KEY (`teamid`)
    REFERENCES `ibl_team_info` (`teamid`) ON DELETE SET NULL ON UPDATE CASCADE;

DROP TRIGGER IF EXISTS trg_team_identity_sync;

DELIMITER $$

CREATE TRIGGER trg_team_identity_sync
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
PRECEDES trg_gm_tenure_track
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

    -- maintenance-28 (15.19): keep the denormalized league-config name in sync.
    UPDATE ibl_league_config
       SET team_name = NEW.team_name
     WHERE teamid = NEW.teamid;

  END IF;
END$$

DELIMITER ;
