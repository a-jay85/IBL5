-- Migration 138: ibl_box_scores_teams.name NOT NULL + doc (maintenance-28 — backlog 15.13)
--
-- `name` is a denormalized per-row team label written by the box-score writer
-- (BoxscoreRepository): it holds values like 'Team Home' / 'Team Away' or the
-- team name at game time. It intentionally has NO foreign key — ibl_team_info
-- has no unique team-name key and historical team names change, so a FK would
-- break on rename. The correct hardening is therefore NOT NULL + DEFAULT '' plus
-- a comment documenting the intentional denormalization.
--
-- Olympics parity: ibl_box_scores_teams <-> ibl_olympics_box_scores_teams is a
-- TABLE_PAIRS entry, so the identical ALTER is applied to both. (The Olympics
-- mirror previously carried a stale 'Arena/venue name' comment; it holds the
-- same denormalized team label as the IBL table, so the comment is corrected to
-- match.)
--
-- Pre-conversion audit (CI seed): both tables have zero NULL `name` rows
--   SELECT COUNT(*) FROM ibl_box_scores_teams          WHERE name IS NULL;  -- 0
--   SELECT COUNT(*) FROM ibl_olympics_box_scores_teams WHERE name IS NULL;  -- 0
-- The defensive backfill below makes the NOT NULL change safe even if a
-- production row holds NULL (it becomes '' rather than erroring the ALTER).
--
-- Idempotent: the UPDATEs are no-ops once no NULLs remain; MODIFY COLUMN
-- re-specifying the resulting type is a no-op, not an error.

UPDATE `ibl_box_scores_teams`          SET `name` = '' WHERE `name` IS NULL;
UPDATE `ibl_olympics_box_scores_teams` SET `name` = '' WHERE `name` IS NULL;

ALTER TABLE `ibl_box_scores_teams`
  MODIFY COLUMN `name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'Denormalized team label snapshot (e.g. Team Home/Team Away); no FK by design';

ALTER TABLE `ibl_olympics_box_scores_teams`
  MODIFY COLUMN `name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
    COMMENT 'Denormalized team label snapshot (e.g. Team Home/Team Away); no FK by design';
