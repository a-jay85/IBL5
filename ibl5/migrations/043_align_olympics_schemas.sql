-- Migration 043: Align Olympics table schemas with IBL counterparts

-- ============================================================
-- 1A. ibl_olympics_box_scores: Add PK, game context columns, generated columns, indexes
-- ============================================================

ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN IF NOT EXISTS `id` INT NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN IF NOT EXISTS `gameOfThatDay` INT DEFAULT NULL COMMENT 'Game number for that date' AFTER `homeTID`,
  ADD COLUMN IF NOT EXISTS `attendance` INT DEFAULT NULL COMMENT 'Game attendance' AFTER `gameOfThatDay`,
  ADD COLUMN IF NOT EXISTS `capacity` INT DEFAULT NULL COMMENT 'Arena capacity' AFTER `attendance`,
  ADD COLUMN IF NOT EXISTS `visitorWins` INT DEFAULT NULL COMMENT 'Visitor team wins before game' AFTER `capacity`,
  ADD COLUMN IF NOT EXISTS `visitorLosses` INT DEFAULT NULL COMMENT 'Visitor team losses before game' AFTER `visitorWins`,
  ADD COLUMN IF NOT EXISTS `homeWins` INT DEFAULT NULL COMMENT 'Home team wins before game' AFTER `visitorLosses`,
  ADD COLUMN IF NOT EXISTS `homeLosses` INT DEFAULT NULL COMMENT 'Home team losses before game' AFTER `homeWins`,
  ADD COLUMN IF NOT EXISTS `teamID` INT DEFAULT NULL COMMENT 'Player team ID (visitor or home)' AFTER `homeLosses`;

ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN IF NOT EXISTS `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN IF NOT EXISTS `season_year` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN YEAR(`Date`) = 0 THEN 0
        WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
        ELSE YEAR(`Date`)
      END
    ) STORED AFTER `game_type`,

  ADD COLUMN IF NOT EXISTS `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `season_year`,

  ADD COLUMN IF NOT EXISTS `calc_rebounds` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN IF NOT EXISTS `calc_fg_made` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`;

ALTER TABLE `ibl_olympics_box_scores`
  ADD INDEX IF NOT EXISTS `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX IF NOT EXISTS `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX IF NOT EXISTS `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX IF NOT EXISTS `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX IF NOT EXISTS `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX IF NOT EXISTS `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX IF NOT EXISTS `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX IF NOT EXISTS `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX IF NOT EXISTS `idx_gt_3gm`       (`game_type`, `game3GM`),
  ADD INDEX IF NOT EXISTS `idx_team_id`      (`teamID`);

-- Drop duplicate idx_uuid (uuid UNIQUE KEY already provides uniqueness)
ALTER TABLE `ibl_olympics_box_scores`
  DROP INDEX IF EXISTS `idx_uuid`;


-- ============================================================
-- 1B. ibl_olympics_box_scores_teams: Add PK, generated columns, indexes
-- ============================================================

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD COLUMN IF NOT EXISTS `id` INT NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD COLUMN IF NOT EXISTS `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN IF NOT EXISTS `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `game_type`,

  ADD COLUMN IF NOT EXISTS `calc_rebounds` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN IF NOT EXISTS `calc_fg_made` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`;

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD INDEX IF NOT EXISTS `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX IF NOT EXISTS `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX IF NOT EXISTS `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX IF NOT EXISTS `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX IF NOT EXISTS `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX IF NOT EXISTS `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX IF NOT EXISTS `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX IF NOT EXISTS `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX IF NOT EXISTS `idx_gt_3gm`       (`game_type`, `game3GM`);


-- ============================================================
-- 1C. ibl_olympics_schedule: Fix types, add FKs, drop duplicate index
-- ============================================================

ALTER TABLE `ibl_olympics_schedule`
  MODIFY COLUMN `Visitor` INT NOT NULL COMMENT 'Visiting team ID',
  MODIFY COLUMN `Home` INT NOT NULL COMMENT 'Home team ID';

ALTER TABLE `ibl_olympics_schedule` DROP FOREIGN KEY IF EXISTS `fk_olympics_schedule_visitor`;
ALTER TABLE `ibl_olympics_schedule` DROP FOREIGN KEY IF EXISTS `fk_olympics_schedule_home`;
ALTER TABLE `ibl_olympics_schedule`
  ADD CONSTRAINT `fk_olympics_schedule_visitor` FOREIGN KEY (`Visitor`)
    REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_olympics_schedule_home` FOREIGN KEY (`Home`)
    REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;

ALTER TABLE `ibl_olympics_schedule`
  DROP INDEX IF EXISTS `idx_uuid`;


-- ============================================================
-- 1D. ibl_olympics_standings: Add wins/losses columns, CHECK constraints
-- ============================================================

ALTER TABLE `ibl_olympics_standings`
  ADD COLUMN IF NOT EXISTS `wins` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total wins' AFTER `leagueRecord`,
  ADD COLUMN IF NOT EXISTS `losses` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total losses' AFTER `wins`;

-- CHECK constraints -- use DROP IF EXISTS + re-add pattern
ALTER TABLE `ibl_olympics_standings`
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_games_unplayed`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_conf_wins`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_conf_losses`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_home_wins`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_home_losses`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_away_wins`,
  DROP CONSTRAINT IF EXISTS `chk_olympics_standings_away_losses`;

ALTER TABLE `ibl_olympics_standings`
  ADD CONSTRAINT `chk_olympics_standings_games_unplayed`
    CHECK (`gamesUnplayed` IS NULL OR `gamesUnplayed` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_conf_wins`
    CHECK (`confWins` IS NULL OR `confWins` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_conf_losses`
    CHECK (`confLosses` IS NULL OR `confLosses` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_home_wins`
    CHECK (`homeWins` IS NULL OR `homeWins` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_home_losses`
    CHECK (`homeLosses` IS NULL OR `homeLosses` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_away_wins`
    CHECK (`awayWins` IS NULL OR `awayWins` >= 0),
  ADD CONSTRAINT `chk_olympics_standings_away_losses`
    CHECK (`awayLosses` IS NULL OR `awayLosses` >= 0);


-- ============================================================
-- 1E. ibl_olympics_team_info: Remove legacy columns, add missing columns
-- ============================================================

ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN IF EXISTS `skype`,
  DROP COLUMN IF EXISTS `aim`,
  DROP COLUMN IF EXISTS `msn`;

ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN IF EXISTS `formerly_known_as`;

ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN IF EXISTS `Contract_Coach`;

ALTER TABLE `ibl_olympics_team_info`
  ADD COLUMN IF NOT EXISTS `capacity` INT NOT NULL DEFAULT 0 COMMENT 'Arena capacity' AFTER `arena`;

ALTER TABLE `ibl_olympics_team_info`
  DROP INDEX IF EXISTS `idx_uuid`;

ALTER TABLE `ibl_olympics_team_info`
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID()) COMMENT 'Public API identifier';


-- ============================================================
-- 1F. ibl_olympics_stats: Add generated pts column, timestamps, indexes
-- ============================================================

ALTER TABLE `ibl_olympics_stats`
  ADD COLUMN IF NOT EXISTS `pts` INT
    GENERATED ALWAYS AS (`fgm` * 2 + `ftm` + `tgm`) STORED
    COMMENT 'Calculated total points' AFTER `pf`;

ALTER TABLE `ibl_olympics_stats`
  ADD COLUMN IF NOT EXISTS `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP AFTER `pts`;

ALTER TABLE `ibl_olympics_stats`
  ADD INDEX IF NOT EXISTS `idx_pid_year` (`pid`, `year`),
  ADD INDEX IF NOT EXISTS `idx_year` (`year`);


-- ============================================================
-- 1G. ibl_olympics_power: Restructure to match slim ibl_power schema
-- ============================================================

-- Drop the varchar PK if it exists (Team column as PK)
-- If TeamID is already PK, the DROP PRIMARY KEY + re-add is harmless
ALTER TABLE `ibl_olympics_power`
  DROP COLUMN IF EXISTS `Team`,
  DROP COLUMN IF EXISTS `Division`,
  DROP COLUMN IF EXISTS `Conference`,
  DROP COLUMN IF EXISTS `win`,
  DROP COLUMN IF EXISTS `loss`,
  DROP COLUMN IF EXISTS `gb`,
  DROP COLUMN IF EXISTS `conf_win`,
  DROP COLUMN IF EXISTS `conf_loss`,
  DROP COLUMN IF EXISTS `div_win`,
  DROP COLUMN IF EXISTS `div_loss`,
  DROP COLUMN IF EXISTS `home_win`,
  DROP COLUMN IF EXISTS `home_loss`,
  DROP COLUMN IF EXISTS `road_win`,
  DROP COLUMN IF EXISTS `road_loss`;

ALTER TABLE `ibl_olympics_power`
  MODIFY COLUMN `TeamID` INT NOT NULL DEFAULT 0 COMMENT 'Team ID (PK, FK to ibl_olympics_team_info)';

ALTER TABLE `ibl_olympics_power`
  ADD PRIMARY KEY IF NOT EXISTS (`TeamID`);

-- Add SOS columns
ALTER TABLE `ibl_olympics_power`
  ADD COLUMN IF NOT EXISTS `sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Strength of schedule' AFTER `streak`,
  ADD COLUMN IF NOT EXISTS `remaining_sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Remaining strength of schedule' AFTER `sos`,
  ADD COLUMN IF NOT EXISTS `sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SOS league rank' AFTER `remaining_sos`,
  ADD COLUMN IF NOT EXISTS `remaining_sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Remaining SOS league rank' AFTER `sos_rank`;


-- ============================================================
-- 1H. ibl_olympics_league_config: Create Olympics equivalent of ibl_league_config
-- ============================================================

CREATE TABLE IF NOT EXISTS `ibl_olympics_league_config` (
  `id` INT NOT NULL AUTO_INCREMENT,
  `season_ending_year` SMALLINT UNSIGNED NOT NULL COMMENT 'Season ending year',
  `team_slot` TINYINT UNSIGNED NOT NULL COMMENT 'Team position in conference bracket',
  `team_name` VARCHAR(32) NOT NULL COMMENT 'Team name (FK to ibl_olympics_team_info)',
  `conference` VARCHAR(16) NOT NULL COMMENT 'Conference/group name',
  `division` VARCHAR(16) NOT NULL COMMENT 'Division/pool name',
  `playoff_qualifiers_per_conf` TINYINT UNSIGNED NOT NULL DEFAULT 0,
  `playoff_round1_format` VARCHAR(8) NOT NULL DEFAULT '',
  `playoff_round2_format` VARCHAR(8) NOT NULL DEFAULT '',
  `playoff_round3_format` VARCHAR(8) NOT NULL DEFAULT '',
  `playoff_round4_format` VARCHAR(8) NOT NULL DEFAULT '',
  `team_count` TINYINT UNSIGNED NOT NULL COMMENT 'Total teams in tournament',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_team` (`season_ending_year`, `team_slot`),
  KEY `idx_season_year` (`season_ending_year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
