-- Migration 151: Downsize ibl_draft.team varchar(255) → varchar(35)
-- Backlog 15.16 / maintenance-41c
--
-- FORWARD BOUND: ibl_draft.team is written ONLY by ProjectedDraftOrderService
--   (classes/ProjectedDraftOrder/ProjectedDraftOrderService.php lines 153, 164, 175),
--   inserted via ProjectedDraftOrderRepository::saveFinalDraftOrder (Repository:117).
--   Every value resolves to a league team name whose source-of-truth column is
--   ibl_team_info.team_name varchar(16). No writer can produce > 16 chars, so 35 is
--   a provable static upper bound with headroom. NOTE: prod sql_mode is empty
--   (non-strict), so an over-length runtime write would TRUNCATE, not reject — this
--   static source bound is the only forward protection.
--
-- ROLLBACK (lossless — widening never loses data):
--   ALTER TABLE `ibl_draft` MODIFY COLUMN `team` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Drafting team name (FK to ibl_team_info)';
--
-- APPLY-TIME GUARD (statement 1): mode-independent UNION-subquery idiom forces
--   ERROR 1242 under BOTH strict and non-strict sql_mode iff any live row > 35,
--   aborting the migration before the ALTER. STRICT_ALL_TABLES is NOT used because
--   it would not abort on prod's empty sql_mode (prod max observed: 12 chars / 56 rows).
--
-- IDEMPOTENT: information_schema gate (copied from migration 009) makes a re-apply a
--   no-op when team is already varchar(35).

-- Statement 1: fail-closed guard (mode-independent, aborts multi_query before ALTER)
-- Returns 0 on clean data (NULL MAX → IF false branch → 0), errors with 1242 if any row > 35.
SELECT IF(
  (SELECT MAX(CHAR_LENGTH(team)) FROM ibl_draft) > 35,
  (SELECT 1 UNION SELECT 2),
  0
);

-- Statement 2: idempotent ALTER (information_schema-gated, copied from migration 009 idiom)
SET @needs_alter = (
  SELECT COUNT(*) FROM information_schema.COLUMNS
  WHERE TABLE_SCHEMA = DATABASE()
    AND TABLE_NAME = 'ibl_draft'
    AND COLUMN_NAME = 'team'
    AND COLUMN_TYPE <> 'varchar(35)'
);
SET @alter_sql = IF(@needs_alter = 1,
  'ALTER TABLE `ibl_draft` MODIFY COLUMN `team` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT \'\' COMMENT \'Drafting team name (FK to ibl_team_info)\'',
  'SELECT 1');
PREPARE _stmt FROM @alter_sql;
EXECUTE _stmt;
DEALLOCATE PREPARE _stmt;
