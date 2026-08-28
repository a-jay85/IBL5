-- Backup tables for the season-2008 phantom-boxscore repair (migration 168).
-- Created empty and additively; filled and verified by 168 before any DELETE runs.
-- NOT dropped by any migration: they are the rollback source of record for
-- bin/rollback-phantom-repair.
--
-- CREATE TABLE ... LIKE is deliberate: it copies `id`, the UNIQUE keys and every
-- secondary index, which is what makes the rollback an exact restore rather than a
-- re-insert with fresh surrogate keys. Foreign keys are NOT copied by LIKE, which is
-- also what we want -- a backup row must survive even if its parent row is gone.

CREATE TABLE IF NOT EXISTS ibl_box_scores_teams_phantom_backup LIKE ibl_box_scores_teams;
CREATE TABLE IF NOT EXISTS ibl_box_scores_phantom_backup       LIKE ibl_box_scores;
CREATE TABLE IF NOT EXISTS ibl_sim_game_recaps_phantom_backup  LIKE ibl_sim_game_recaps;

-- game_type / season_year / calc_* are STORED GENERATED in the two live boxscore
-- tables, and CREATE TABLE ... LIKE copies the AS (...) STORED clause verbatim. A
-- generated column cannot be written by an INSERT, so migration 168's explicit-column
-- INSERT ... SELECT would fail (ERROR 3105). Stored here as ordinary NULL-able columns
-- so the backup holds the exact live bytes and a restore reproduces them rather than
-- recomputing them from game_date. Types are copied verbatim from SHOW CREATE TABLE --
-- note calc_rebounds / calc_fg_made are smallint on the teams table but tinyint on the
-- player table; narrowing either would truncate on backup and corrupt the rollback.
-- NULL rather than NOT NULL: these columns are always written explicitly and have no
-- default, so NOT NULL would trip bin/check-destructive-migrations' add-not-null-no-default rule.
ALTER TABLE ibl_box_scores_teams_phantom_backup
    MODIFY game_type     tinyint(3) unsigned NULL,
    MODIFY season_year   smallint(5) unsigned NULL,
    MODIFY calc_points   smallint(5) unsigned NULL,
    MODIFY calc_rebounds smallint(5) unsigned NULL,
    MODIFY calc_fg_made  smallint(5) unsigned NULL;

ALTER TABLE ibl_box_scores_phantom_backup
    MODIFY game_type     tinyint(3) unsigned NULL,
    MODIFY season_year   smallint(5) unsigned NULL,
    MODIFY calc_points   smallint(5) unsigned NULL,
    MODIFY calc_rebounds tinyint(3) unsigned NULL,
    MODIFY calc_fg_made  tinyint(3) unsigned NULL;

-- ibl_sim_game_recaps has no generated columns (season_year is a real column there),
-- so its backup needs no MODIFY treatment.
