-- Backup tables for the 2004 Suns-Heat boxscore restore (migration 171).
-- Created empty and additively; filled and verified by 171 before any UPDATE runs.
-- These tables are dedicated to the 2004 season restore and are NOT the 2008 rollback
-- source used by bin/rollback-phantom-repair. See ibl_box_scores_teams_phantom_backup
-- and ibl_box_scores_phantom_backup (migration 167) for those.
--
-- CREATE TABLE ... LIKE is deliberate: it copies `id`, the UNIQUE keys and every
-- secondary index, which makes the backup an exact snapshot rather than a re-insert
-- with fresh surrogate keys. Foreign keys are NOT copied by LIKE, which is also what
-- we want -- a backup row must survive even if its parent row is gone.
-- NOTE: LIKE copies the UNIQUE KEY uuid onto the player backup; this is harmless
-- because the 24 backed-up rows carry 24 distinct uuids, and migration 171's guard
-- makes a second population impossible.

CREATE TABLE IF NOT EXISTS ibl_box_scores_teams_season2004_backup LIKE ibl_box_scores_teams;
CREATE TABLE IF NOT EXISTS ibl_box_scores_season2004_backup       LIKE ibl_box_scores;

-- game_type / season_year / calc_* are STORED GENERATED in the two live boxscore
-- tables, and CREATE TABLE ... LIKE copies the AS (...) STORED clause verbatim. A
-- generated column cannot be written by an INSERT, so migration 171's explicit-column
-- INSERT ... SELECT would fail (ERROR 3105). Stored here as ordinary NULL-able columns
-- so the backup holds the exact live bytes and a restore reproduces them rather than
-- recomputing them from game_date. Types are copied verbatim from SHOW CREATE TABLE --
-- note calc_rebounds / calc_fg_made are smallint on the teams table but tinyint on the
-- player table; narrowing either would truncate on backup and corrupt the rollback.
-- NULL rather than NOT NULL: these columns are always written explicitly and have no
-- default, so NOT NULL would trip bin/check-destructive-migrations' add-not-null-no-default rule.
ALTER TABLE ibl_box_scores_teams_season2004_backup
    MODIFY game_type     tinyint(3) unsigned NULL,
    MODIFY season_year   smallint(5) unsigned NULL,
    MODIFY calc_points   smallint(5) unsigned NULL,
    MODIFY calc_rebounds smallint(5) unsigned NULL,
    MODIFY calc_fg_made  smallint(5) unsigned NULL;

ALTER TABLE ibl_box_scores_season2004_backup
    MODIFY game_type     tinyint(3) unsigned NULL,
    MODIFY season_year   smallint(5) unsigned NULL,
    MODIFY calc_points   smallint(5) unsigned NULL,
    MODIFY calc_rebounds tinyint(3) unsigned NULL,
    MODIFY calc_fg_made  tinyint(3) unsigned NULL;
