-- Migration 113: Rename reserved-word rating columns and fix r_to meaning-flip
--
-- Three classes of rename:
--
-- 1. Reserved-word columns `to` and `do` on player-record tables. These are
--    SQL reserved words that force backtick-quoting in every query. They name
--    the transition-offense and drive-offense ratings. Renamed to r_trans_off
--    and r_drive_off — self-documenting and reserved-word-free.
--
-- 2. r_to meaning-flip between live and materialized tables. On the live and
--    snapshot tables, r_to meant "turnover rating". On ibl_hist (materialized
--    from snapshots in migration 109), r_to meant "transition offense rating"
--    (populated from snap.`to`). The ibl_hist INSERT silently re-aliased
--    snap.r_to AS r_tvr and snap.`to` AS r_to, inverting semantics. Any code
--    reading r_to from the wrong layer got the wrong stat. After this
--    migration:
--      * live/snapshot r_to → r_tvr       (turnover rating, unified name)
--      * live/snapshot `to` → r_trans_off (transition offense rating)
--      * hist r_to       → r_trans_off    (same semantic, same name)
--      * hist r_do       → r_drive_off    (same semantic as live `do`, unified)
--
-- 3. `Start Date` / `End Date` on ibl_sim_dates. Column identifiers with
--    spaces, forcing backtick-quoting everywhere. Renamed to snake_case.
--
-- Corresponding PHP updates: PlrParser, Draft, FreeAgencyPreview,
-- PlayerDatabase, Updater/RefreshIblHistStep, Negotiation, plus any Service
-- or View reading these columns. Enforced by SchemaValidator (config/
-- schema-assertions.php) and a new PHPStan BanReservedWordColumnsRule.
--
-- made idempotent 2026-06-05 (maintenance-27, backlog 15.22): each pure rename
-- is now `RENAME COLUMN IF EXISTS old TO new` followed by a guarded
-- `MODIFY COLUMN IF EXISTS new ...` that reproduces the exact target definition
-- (type / nullability / default / comment) the original `CHANGE COLUMN`
-- produced. Re-running on an already-migrated DB is a no-op (the RENAME finds no
-- old column; the MODIFY sets the type the column already has). Safe to edit
-- this applied migration: the runner records it as run, so the body only
-- matters on fresh installs / re-seeds.

-- Tables where `to` (transition offense) and `do` (drive offense) are ratings
ALTER TABLE `ibl_plr`
  RENAME COLUMN IF EXISTS `to`   TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `do`   TO `r_drive_off`,
  RENAME COLUMN IF EXISTS `r_to` TO `r_tvr`;
ALTER TABLE `ibl_plr`
  MODIFY COLUMN IF EXISTS `r_trans_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Drive offense rating',
  MODIFY COLUMN IF EXISTS `r_tvr` smallint(5) unsigned DEFAULT 0 COMMENT 'Turnover rating';

ALTER TABLE `ibl_plr_snapshots`
  RENAME COLUMN IF EXISTS `to`   TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `do`   TO `r_drive_off`,
  RENAME COLUMN IF EXISTS `r_to` TO `r_tvr`;
ALTER TABLE `ibl_plr_snapshots`
  MODIFY COLUMN IF EXISTS `r_trans_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Drive offense rating',
  MODIFY COLUMN IF EXISTS `r_tvr` smallint(6) DEFAULT 0 COMMENT 'Turnover rating';

ALTER TABLE `ibl_olympics_plr`
  RENAME COLUMN IF EXISTS `to`   TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `do`   TO `r_drive_off`,
  RENAME COLUMN IF EXISTS `r_to` TO `r_tvr`;
ALTER TABLE `ibl_olympics_plr`
  MODIFY COLUMN IF EXISTS `r_trans_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` tinyint(3) unsigned DEFAULT 0 COMMENT 'Drive offense rating',
  MODIFY COLUMN IF EXISTS `r_tvr` smallint(5) unsigned DEFAULT 0 COMMENT 'Turnover rating';

ALTER TABLE `ibl_draft_class`
  RENAME COLUMN IF EXISTS `to` TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `do` TO `r_drive_off`;
ALTER TABLE `ibl_draft_class`
  MODIFY COLUMN IF EXISTS `r_trans_off` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Drive offense rating';

-- Materialized history tables: r_to and r_do here are already ratings
-- (populated from snap.`to` / snap.`do`). Rename for semantic uniformity
-- with the live/snapshot tables above. This also removes the meaning-flip
-- described above.
ALTER TABLE `ibl_hist`
  RENAME COLUMN IF EXISTS `r_to` TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `r_do` TO `r_drive_off`;
ALTER TABLE `ibl_hist`
  MODIFY COLUMN IF EXISTS `r_trans_off` int(11) NOT NULL DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` int(11) NOT NULL DEFAULT 0 COMMENT 'Drive offense rating';

ALTER TABLE `ibl_olympics_hist`
  RENAME COLUMN IF EXISTS `r_to` TO `r_trans_off`,
  RENAME COLUMN IF EXISTS `r_do` TO `r_drive_off`;
ALTER TABLE `ibl_olympics_hist`
  MODIFY COLUMN IF EXISTS `r_trans_off` int(11) NOT NULL DEFAULT 0 COMMENT 'Transition offense rating',
  MODIFY COLUMN IF EXISTS `r_drive_off` int(11) NOT NULL DEFAULT 0 COMMENT 'Drive offense rating';

-- Identifiers with spaces: ibl_sim_dates
ALTER TABLE `ibl_sim_dates`
  RENAME COLUMN IF EXISTS `Start Date` TO `start_date`,
  RENAME COLUMN IF EXISTS `End Date`   TO `end_date`;
ALTER TABLE `ibl_sim_dates`
  MODIFY COLUMN IF EXISTS `start_date` date DEFAULT NULL COMMENT 'First date in sim range',
  MODIFY COLUMN IF EXISTS `end_date`   date DEFAULT NULL COMMENT 'Last date in sim range';
