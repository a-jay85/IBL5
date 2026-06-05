-- Migration 117: Tier 3b — snake_case team-info columns.
-- Renames 9 columns on ibl_team_info and 5 on ibl_olympics_team_info.
-- No FK drops needed. No views/generated columns reference these columns.
--
-- made idempotent 2026-06-05 (maintenance-27, backlog 15.22): each pure rename
-- is now `RENAME COLUMN IF EXISTS old TO new` followed by a guarded
-- `MODIFY COLUMN IF EXISTS new ...` that reproduces the exact target definition
-- (type / nullability / default / comment) the original `CHANGE COLUMN`
-- produced. Re-running on an already-migrated DB is a no-op (the RENAME finds no
-- old column; the MODIFY sets the type the column already has). Safe to edit
-- this applied migration: the runner records it as run, so the body only
-- matters on fresh installs / re-seeds.

ALTER TABLE `ibl_team_info`
  RENAME COLUMN IF EXISTS `discordID`                  TO `discord_id`,
  RENAME COLUMN IF EXISTS `Contract_Wins`              TO `contract_wins`,
  RENAME COLUMN IF EXISTS `Contract_Losses`            TO `contract_losses`,
  RENAME COLUMN IF EXISTS `Contract_AvgW`              TO `contract_avg_w`,
  RENAME COLUMN IF EXISTS `Contract_AvgL`              TO `contract_avg_l`,
  RENAME COLUMN IF EXISTS `Used_Extension_This_Chunk`  TO `used_extension_this_chunk`,
  RENAME COLUMN IF EXISTS `Used_Extension_This_Season` TO `used_extension_this_season`,
  RENAME COLUMN IF EXISTS `HasMLE`                     TO `has_mle`,
  RENAME COLUMN IF EXISTS `HasLLE`                     TO `has_lle`;
ALTER TABLE `ibl_team_info`
  MODIFY COLUMN IF EXISTS `discord_id`                  bigint(20) unsigned DEFAULT NULL COMMENT 'GM Discord user ID',
  MODIFY COLUMN IF EXISTS `contract_wins`               int(11) NOT NULL DEFAULT 0 COMMENT 'Wins from last season for FA Play for Winner weight',
  MODIFY COLUMN IF EXISTS `contract_losses`             int(11) NOT NULL DEFAULT 0 COMMENT 'Losses from last season for FA Play for Winner weight',
  MODIFY COLUMN IF EXISTS `contract_avg_w`              int(11) NOT NULL DEFAULT 0 COMMENT 'Avg wins from last five seasons for FA Tradition weight',
  MODIFY COLUMN IF EXISTS `contract_avg_l`              int(11) NOT NULL DEFAULT 0 COMMENT 'Avg losses from last five seasons for FA Tradition weight',
  MODIFY COLUMN IF EXISTS `used_extension_this_chunk`   int(11) NOT NULL DEFAULT 0 COMMENT '1=used extension in current sim chunk',
  MODIFY COLUMN IF EXISTS `used_extension_this_season`  int(11) DEFAULT 0 COMMENT '1=used extension this season',
  MODIFY COLUMN IF EXISTS `has_mle`                     int(11) NOT NULL DEFAULT 0 COMMENT '1=Mid-Level Exception already used',
  MODIFY COLUMN IF EXISTS `has_lle`                     int(11) NOT NULL DEFAULT 0 COMMENT '1=Lower-Level Exception already used';

ALTER TABLE `ibl_olympics_team_info`
  RENAME COLUMN IF EXISTS `discordID`       TO `discord_id`,
  RENAME COLUMN IF EXISTS `Contract_Wins`   TO `contract_wins`,
  RENAME COLUMN IF EXISTS `Contract_Losses` TO `contract_losses`,
  RENAME COLUMN IF EXISTS `Contract_AvgW`   TO `contract_avg_w`,
  RENAME COLUMN IF EXISTS `Contract_AvgL`   TO `contract_avg_l`;
ALTER TABLE `ibl_olympics_team_info`
  MODIFY COLUMN IF EXISTS `discord_id`      bigint(20) unsigned DEFAULT NULL COMMENT 'Discord user ID',
  MODIFY COLUMN IF EXISTS `contract_wins`   int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  MODIFY COLUMN IF EXISTS `contract_losses` int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  MODIFY COLUMN IF EXISTS `contract_avg_w`  int(11) NOT NULL DEFAULT 0 COMMENT 'Average wins per contract',
  MODIFY COLUMN IF EXISTS `contract_avg_l`  int(11) NOT NULL DEFAULT 0 COMMENT 'Average losses per contract';
