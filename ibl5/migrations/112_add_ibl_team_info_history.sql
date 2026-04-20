-- Store team_info snapshots for historical (inactive) franchises so past eras
-- can carry their own colors, arenas, and GM info. Rows join with
-- ibl_franchise_history on (franchise_id, team_city, team_name) so teams of
-- the past render with period-correct colors and logos.
CREATE TABLE `ibl_team_info_history` (
  `id` INT(11) NOT NULL AUTO_INCREMENT,
  `franchise_id` INT(11) NOT NULL COMMENT 'Historical franchise id (formerly teamid in ibl_team_info)',
  `team_city` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'Franchise city for this era',
  `team_name` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'Franchise name for this era',
  `color1` VARCHAR(6) NOT NULL DEFAULT '' COMMENT 'Primary hex color (no #)',
  `color2` VARCHAR(6) NOT NULL DEFAULT '' COMMENT 'Secondary hex color (no #)',
  `arena` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Home arena name',
  `capacity` INT(11) NOT NULL DEFAULT 0 COMMENT 'Arena seating capacity',
  `owner_name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'GM display name',
  `owner_email` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'GM email address',
  `gm_username` VARCHAR(25) DEFAULT NULL,
  `discordID` BIGINT(20) UNSIGNED DEFAULT NULL COMMENT 'GM Discord user ID',
  `Contract_Wins` INT(11) NOT NULL DEFAULT 0 COMMENT 'Wins from last season for FA Play for Winner weight',
  `Contract_Losses` INT(11) NOT NULL DEFAULT 0 COMMENT 'Losses from last season for FA Play for Winner weight',
  `Contract_AvgW` INT(11) NOT NULL DEFAULT 0 COMMENT 'Avg wins from last five seasons for FA Tradition weight',
  `Contract_AvgL` INT(11) NOT NULL DEFAULT 0 COMMENT 'Avg losses from last five seasons for FA Tradition weight',
  `Used_Extension_This_Chunk` INT(11) NOT NULL DEFAULT 0 COMMENT '1=used extension in current sim chunk',
  `Used_Extension_This_Season` INT(11) DEFAULT 0 COMMENT '1=used extension this season',
  `HasMLE` INT(11) NOT NULL DEFAULT 0 COMMENT '1=Mid-Level Exception already used',
  `HasLLE` INT(11) NOT NULL DEFAULT 0 COMMENT '1=Lower-Level Exception already used',
  `chart` CHAR(2) NOT NULL DEFAULT '' COMMENT 'Depth chart format code',
  `depth` VARCHAR(100) NOT NULL DEFAULT '' COMMENT 'Depth chart submission status',
  `sim_depth` VARCHAR(100) NOT NULL DEFAULT 'No Depth Chart' COMMENT 'Depth chart status at last sim',
  `asg_vote` VARCHAR(100) NOT NULL DEFAULT 'No Vote' COMMENT 'All-Star voting status',
  `eoy_vote` VARCHAR(100) NOT NULL DEFAULT 'No Vote' COMMENT 'End-of-year voting status',
  `created_at` TIMESTAMP NOT NULL DEFAULT current_timestamp(),
  `updated_at` TIMESTAMP NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` CHAR(36) NOT NULL DEFAULT uuid(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_franchise_era` (`franchise_id`, `team_city`, `team_name`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_franchise_id` (`franchise_id`),
  KEY `idx_team_name` (`team_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
