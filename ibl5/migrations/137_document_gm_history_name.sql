-- Migration 137: Disambiguate ibl_gm_history.name (maintenance-28 — backlog 15.23)
--
-- 15.23 asked: is `ibl_gm_history.name` a GM *username* or a *display* name, and
-- should it shrink from varchar(50)?
--
-- Resolution — DOCUMENT, do not shrink:
--   * Semantics: the column has been commented 'GM username' since migration 034,
--     and ibl_gm_history is a historical GM-awards log keyed by (year, name) whose
--     `name` references the GM's site username — the same identity as
--     ibl_team_info.gm_username. So it is a username, not a display name.
--   * Width: a shrink to varchar(25) (the ibl_team_info.gm_username width) is NOT
--     provably safe. ibl_gm_history holds *historical* usernames and the related
--     history table ibl_gm_tenures.gm_username is varchar(50), so a legacy GM
--     username longer than 25 cannot be ruled out. The CI seed has zero rows, so
--     the length audit (`SELECT MAX(LENGTH(name)) FROM ibl_gm_history`) cannot
--     confirm the shrink is non-destructive. Shrinking blind risks silent
--     truncation of production history -> keep varchar(50).
--
-- This migration therefore makes only the documentation change: it records the
-- intended (unenforced) FK relationship in the column comment. gm_username is not
-- unique, so no real FK is added.
--
-- Idempotent: MODIFY COLUMN re-specifying the existing type is a no-op, not an
-- error. No data is read or written.

ALTER TABLE `ibl_gm_history`
  MODIFY COLUMN `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL
    COMMENT 'GM username (ref ibl_team_info.gm_username; no enforced FK)';
