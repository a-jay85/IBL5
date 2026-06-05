-- Migration 135: Schema type alignment (maintenance-27 — backlog 15.4 / 15.5 / 15.20)
--
-- Non-breaking type narrowing. Every existing value already fits the target
-- type (the boolean-intent columns hold only 0/1; pos holds valid position
-- codes), so native-type reads return identical PHP values and NO PHP changes
-- are required.
--
-- Fail-loud on dirty data: STRICT_ALL_TABLES guarantees the pos ENUM cast
-- ERRORS (rather than silently truncating to '') if any out-of-range position
-- value exists. MariaDB 10.11's default sql_mode already includes
-- STRICT_TRANS_TABLES; widening it to STRICT_ALL_TABLES for this session makes
-- the guarantee hold regardless of how the target server is configured.
-- Pre-conversion audit (run against the seed before authoring this migration,
-- and documented for prod-via-CI):
--   SELECT DISTINCT pos FROM ibl_box_scores
--     WHERE pos NOT IN ('PG','SG','SF','PF','C','G','F','GF','');          -- expect empty
--   SELECT DISTINCT pos FROM ibl_olympics_box_scores
--     WHERE pos NOT IN ('PG','SG','SF','PF','C','G','F','GF','');          -- expect empty
--
-- Idempotent: every statement is MODIFY COLUMN, which is naturally re-runnable
-- (setting a column to the type it already has is a no-op, not an error).
--
-- DELIBERATE DEVIATION from the maintenance-27 plan's literal SQL: the plan
-- listed `used_extension_this_season ... NOT NULL`, but migration 117 defines it
-- nullable (`int(11) DEFAULT 0`). This migration preserves the existing
-- nullability (no NOT NULL) so the change stays strictly type-only and
-- non-breaking — adding NOT NULL would be an out-of-scope constraint change
-- (and could itself error under STRICT_ALL_TABLES if any row held NULL).

SET SESSION sql_mode = CONCAT(@@sql_mode, ',STRICT_ALL_TABLES');

-- 15.4 — `retired`: int(11) -> tinyint(1) on the Olympics career aggregate
-- tables, aligning them with ibl_plr/ibl_olympics_plr (tinyint). NOT NULL and
-- comment preserved exactly.
ALTER TABLE `ibl_olympics_career_avgs`
  MODIFY COLUMN `retired` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=retired from league';
ALTER TABLE `ibl_olympics_career_totals`
  MODIFY COLUMN `retired` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=retired from league';

-- 15.5 — boolean-intent flags: int(11) -> tinyint(1) on ibl_team_info.
-- Nullability and comments preserved exactly as migration 117 defined them
-- (used_extension_this_season is nullable; the other three are NOT NULL).
ALTER TABLE `ibl_team_info`
  MODIFY COLUMN `used_extension_this_chunk`  tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=used extension in current sim chunk',
  MODIFY COLUMN `used_extension_this_season` tinyint(1)          DEFAULT 0 COMMENT '1=used extension this season',
  MODIFY COLUMN `has_mle`                     tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Mid-Level Exception already used',
  MODIFY COLUMN `has_lle`                     tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=Lower-Level Exception already used';

-- 15.20 — `pos`: varchar(2) -> ENUM on BOTH box-score tables (the Olympics
-- parity pair must move together). Collation, default, and nullability are
-- preserved; out-of-range values ERROR under STRICT_ALL_TABLES rather than
-- truncating to ''.
ALTER TABLE `ibl_box_scores`
  MODIFY COLUMN `pos` enum('PG','SG','SF','PF','C','G','F','GF','') COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Player position at game time';
ALTER TABLE `ibl_olympics_box_scores`
  MODIFY COLUMN `pos` enum('PG','SG','SF','PF','C','G','F','GF','') COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Position played';
