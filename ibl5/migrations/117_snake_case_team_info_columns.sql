-- Migration 117: Tier 3b — snake_case team-info columns.
-- Renames 9 columns on ibl_team_info and 5 on ibl_olympics_team_info.
-- No FK drops needed. No views/generated columns reference these columns.

ALTER TABLE `ibl_team_info`
  CHANGE COLUMN `discordID`                  `discord_id`                  bigint(20) unsigned DEFAULT NULL COMMENT 'GM Discord user ID',
  CHANGE COLUMN `Contract_Wins`              `contract_wins`               int(11) NOT NULL DEFAULT 0 COMMENT 'Wins from last season for FA Play for Winner weight',
  CHANGE COLUMN `Contract_Losses`            `contract_losses`             int(11) NOT NULL DEFAULT 0 COMMENT 'Losses from last season for FA Play for Winner weight',
  CHANGE COLUMN `Contract_AvgW`              `contract_avg_w`              int(11) NOT NULL DEFAULT 0 COMMENT 'Avg wins from last five seasons for FA Tradition weight',
  CHANGE COLUMN `Contract_AvgL`              `contract_avg_l`              int(11) NOT NULL DEFAULT 0 COMMENT 'Avg losses from last five seasons for FA Tradition weight',
  CHANGE COLUMN `Used_Extension_This_Chunk`  `used_extension_this_chunk`   int(11) NOT NULL DEFAULT 0 COMMENT '1=used extension in current sim chunk',
  CHANGE COLUMN `Used_Extension_This_Season` `used_extension_this_season`  int(11) DEFAULT 0 COMMENT '1=used extension this season',
  CHANGE COLUMN `HasMLE`                     `has_mle`                     int(11) NOT NULL DEFAULT 0 COMMENT '1=Mid-Level Exception already used',
  CHANGE COLUMN `HasLLE`                     `has_lle`                     int(11) NOT NULL DEFAULT 0 COMMENT '1=Lower-Level Exception already used';

ALTER TABLE `ibl_olympics_team_info`
  CHANGE COLUMN `discordID`       `discord_id`       bigint(20) unsigned DEFAULT NULL COMMENT 'Discord user ID',
  CHANGE COLUMN `Contract_Wins`   `contract_wins`    int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  CHANGE COLUMN `Contract_Losses` `contract_losses`  int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  CHANGE COLUMN `Contract_AvgW`   `contract_avg_w`   int(11) NOT NULL DEFAULT 0 COMMENT 'Average wins per contract',
  CHANGE COLUMN `Contract_AvgL`   `contract_avg_l`   int(11) NOT NULL DEFAULT 0 COMMENT 'Average losses per contract';
