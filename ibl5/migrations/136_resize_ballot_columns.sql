-- Migration 136: Resize over-wide ballot columns (maintenance-28 — backlog 15.15)
--
-- The ASG and EOY ballot columns store player names (copied from ibl_plr.name)
-- and GM-of-Year usernames (copied from ibl_team_info.gm_username). They were
-- declared varchar(255) but the values they hold are bounded by their source
-- columns:
--   * ibl_plr.name          = varchar(32)  -> player-name ballots -> varchar(32)
--   * ibl_team_info.gm_username = varchar(25) -> GM ballots       -> varchar(25)
--
-- Because every stored value originates from one of those columns, NO existing
-- ballot value can exceed the new width — the shrink cannot truncate data. This
-- holds regardless of row contents, so it is safe on production as well as the
-- CI seed.
--
-- Pre-conversion audit (run against the CI seed before authoring):
--   SELECT GREATEST(MAX(LENGTH(east_f1)), ... , MAX(LENGTH(west_b4)))
--     FROM ibl_votes_ASG;                                   -- 14 (<= 32)
--   SELECT GREATEST(MAX(LENGTH(mvp_1)), ... , MAX(LENGTH(roy_3)))
--     FROM ibl_votes_EOY;                                   -- 14 (<= 32)
--   SELECT GREATEST(MAX(LENGTH(gm_1)), MAX(LENGTH(gm_3)))
--     FROM ibl_votes_EOY;                                   -- 0  (<= 25)
--
-- Idempotent: every statement is MODIFY COLUMN, which is naturally re-runnable
-- (setting a column to a type it already has is a no-op, not an error).
--
-- Fail-loud on dirty data: STRICT_ALL_TABLES makes any over-width value ERROR
-- rather than silently truncate, should the source-width invariant ever be
-- violated on the target server.

SET SESSION sql_mode = CONCAT(@@sql_mode, ',STRICT_ALL_TABLES');

-- ASG ballot picks (16) -> varchar(32). Nullability and comments preserved.
ALTER TABLE `ibl_votes_ASG`
  MODIFY COLUMN `east_f1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 1st pick',
  MODIFY COLUMN `east_f2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 2nd pick',
  MODIFY COLUMN `east_f3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 3rd pick',
  MODIFY COLUMN `east_f4` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 4th pick',
  MODIFY COLUMN `east_b1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 1st pick',
  MODIFY COLUMN `east_b2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 2nd pick',
  MODIFY COLUMN `east_b3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 3rd pick',
  MODIFY COLUMN `east_b4` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 4th pick',
  MODIFY COLUMN `west_f1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 1st pick',
  MODIFY COLUMN `west_f2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 2nd pick',
  MODIFY COLUMN `west_f3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 3rd pick',
  MODIFY COLUMN `west_f4` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 4th pick',
  MODIFY COLUMN `west_b1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 1st pick',
  MODIFY COLUMN `west_b2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 2nd pick',
  MODIFY COLUMN `west_b3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 3rd pick',
  MODIFY COLUMN `west_b4` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 4th pick';

-- EOY player-name ballots (MVP / Sixth Man / ROY, 9) -> varchar(32).
ALTER TABLE `ibl_votes_EOY`
  MODIFY COLUMN `mvp_1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 1st place',
  MODIFY COLUMN `mvp_2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 2nd place',
  MODIFY COLUMN `mvp_3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 3rd place',
  MODIFY COLUMN `six_1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 1st place',
  MODIFY COLUMN `six_2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 2nd place',
  MODIFY COLUMN `six_3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 3rd place',
  MODIFY COLUMN `roy_1` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 1st place',
  MODIFY COLUMN `roy_2` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 2nd place',
  MODIFY COLUMN `roy_3` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 3rd place',
-- EOY GM-of-Year ballots (3) hold GM usernames -> varchar(25) (gm_username width).
  MODIFY COLUMN `gm_1` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 1st place',
  MODIFY COLUMN `gm_2` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 2nd place',
  MODIFY COLUMN `gm_3` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 3rd place';
