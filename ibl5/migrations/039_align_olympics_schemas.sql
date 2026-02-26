-- Migration 039: Align Olympics table schemas with IBL counterparts
--
-- Applies structural improvements from migrations 008, 009, and 016 to Olympics tables:
-- - Add auto-increment PKs to box score tables
-- - Add generated columns and composite indexes for query optimization
-- - Fix type mismatches and add foreign keys
-- - Add missing columns and remove legacy columns
-- - Restructure ibl_olympics_power to match slim ibl_power schema

-- ============================================================
-- 1A. ibl_olympics_box_scores: Add PK, game context columns, generated columns, indexes
-- ============================================================

-- Add auto-increment PK (matches ibl_box_scores from migration 009)
ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- Add game context columns present in ibl_box_scores but missing from Olympics
ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN `gameOfThatDay` INT DEFAULT NULL COMMENT 'Game number for that date' AFTER `homeTID`,
  ADD COLUMN `attendance` INT DEFAULT NULL COMMENT 'Game attendance' AFTER `gameOfThatDay`,
  ADD COLUMN `capacity` INT DEFAULT NULL COMMENT 'Arena capacity' AFTER `attendance`,
  ADD COLUMN `visitorWins` INT DEFAULT NULL COMMENT 'Visitor team wins before game' AFTER `capacity`,
  ADD COLUMN `visitorLosses` INT DEFAULT NULL COMMENT 'Visitor team losses before game' AFTER `visitorWins`,
  ADD COLUMN `homeWins` INT DEFAULT NULL COMMENT 'Home team wins before game' AFTER `visitorLosses`,
  ADD COLUMN `homeLosses` INT DEFAULT NULL COMMENT 'Home team losses before game' AFTER `homeWins`,
  ADD COLUMN `teamID` INT DEFAULT NULL COMMENT 'Player team ID (visitor or home)' AFTER `homeLosses`;

-- Add generated columns (from migration 008)
ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN `season_year` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN YEAR(`Date`) = 0 THEN 0
        WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
        ELSE YEAR(`Date`)
      END
    ) STORED AFTER `game_type`,

  ADD COLUMN `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `season_year`,

  ADD COLUMN `calc_rebounds` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN `calc_fg_made` TINYINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`;

-- Add composite indexes (from migration 008)
ALTER TABLE `ibl_olympics_box_scores`
  ADD INDEX `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX `idx_gt_3gm`       (`game_type`, `game3GM`),
  ADD INDEX `idx_team_id`      (`teamID`);

-- Drop duplicate idx_uuid (uuid UNIQUE KEY already provides uniqueness)
ALTER TABLE `ibl_olympics_box_scores`
  DROP INDEX `idx_uuid`;


-- ============================================================
-- 1B. ibl_olympics_box_scores_teams: Add PK, generated columns, indexes
-- ============================================================

-- Add auto-increment PK (matches ibl_box_scores_teams from migration 009)
ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- Add generated columns (from migration 008)
ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD COLUMN `game_type` TINYINT UNSIGNED
    GENERATED ALWAYS AS (
      CASE
        WHEN MONTH(`Date`) = 6 THEN 2
        WHEN MONTH(`Date`) = 10 THEN 3
        WHEN MONTH(`Date`) = 0 THEN 0
        ELSE 1
      END
    ) STORED AFTER `gamePF`,

  ADD COLUMN `calc_points` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED AFTER `game_type`,

  ADD COLUMN `calc_rebounds` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,

  ADD COLUMN `calc_fg_made` SMALLINT UNSIGNED
    GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`;

-- Add composite indexes (from migration 008)
ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD INDEX `idx_gt_points`   (`game_type`, `calc_points`),
  ADD INDEX `idx_gt_rebounds`  (`game_type`, `calc_rebounds`),
  ADD INDEX `idx_gt_fg_made`   (`game_type`, `calc_fg_made`),
  ADD INDEX `idx_gt_ast`       (`game_type`, `gameAST`),
  ADD INDEX `idx_gt_stl`       (`game_type`, `gameSTL`),
  ADD INDEX `idx_gt_blk`       (`game_type`, `gameBLK`),
  ADD INDEX `idx_gt_tov`       (`game_type`, `gameTOV`),
  ADD INDEX `idx_gt_ftm`       (`game_type`, `gameFTM`),
  ADD INDEX `idx_gt_3gm`       (`game_type`, `game3GM`);


-- ============================================================
-- 1C. ibl_olympics_schedule: Fix types, add FKs, drop duplicate index
-- ============================================================

-- Change Visitor and Home from SMALLINT to INT to match ibl_olympics_team_info.teamid (signed)
ALTER TABLE `ibl_olympics_schedule`
  MODIFY COLUMN `Visitor` INT NOT NULL COMMENT 'Visiting team ID',
  MODIFY COLUMN `Home` INT NOT NULL COMMENT 'Home team ID';

-- Add FK constraints
ALTER TABLE `ibl_olympics_schedule`
  ADD CONSTRAINT `fk_olympics_schedule_visitor` FOREIGN KEY (`Visitor`)
    REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_olympics_schedule_home` FOREIGN KEY (`Home`)
    REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;

-- Drop duplicate idx_uuid (uuid UNIQUE KEY already provides uniqueness)
ALTER TABLE `ibl_olympics_schedule`
  DROP INDEX `idx_uuid`;


-- ============================================================
-- 1D. ibl_olympics_standings: Add wins/losses columns, CHECK constraints
-- ============================================================

-- Add wins and losses columns (present in ibl_standings, missing in Olympics)
ALTER TABLE `ibl_olympics_standings`
  ADD COLUMN `wins` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total wins' AFTER `leagueRecord`,
  ADD COLUMN `losses` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total losses' AFTER `wins`;

-- Add CHECK constraints matching ibl_standings
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

-- Drop legacy messaging columns (removed from ibl_team_info in migration 009)
ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN `skype`,
  DROP COLUMN `aim`,
  DROP COLUMN `msn`;

-- Drop formerly_known_as (removed from ibl_team_info in migration 016)
ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN `formerly_known_as`;

-- Drop Contract_Coach (not present in ibl_team_info)
ALTER TABLE `ibl_olympics_team_info`
  DROP COLUMN `Contract_Coach`;

-- Add capacity column (present in ibl_team_info but missing here)
ALTER TABLE `ibl_olympics_team_info`
  ADD COLUMN `capacity` INT NOT NULL DEFAULT 0 COMMENT 'Arena capacity' AFTER `arena`;

-- Drop duplicate idx_uuid (uuid UNIQUE KEY already provides uniqueness)
ALTER TABLE `ibl_olympics_team_info`
  DROP INDEX `idx_uuid`;

-- Fix uuid to NOT NULL
ALTER TABLE `ibl_olympics_team_info`
  MODIFY COLUMN `uuid` CHAR(36) NOT NULL DEFAULT (UUID()) COMMENT 'Public API identifier';


-- ============================================================
-- 1F. ibl_olympics_stats: Add generated pts column, timestamps, indexes
-- ============================================================

-- Add generated pts column (formula matches StatsFormatter::calculatePoints())
ALTER TABLE `ibl_olympics_stats`
  ADD COLUMN `pts` INT
    GENERATED ALWAYS AS (`fgm` * 2 + `ftm` + `tgm`) STORED
    COMMENT 'Calculated total points' AFTER `pf`;

-- Add updated_at timestamp (present in ibl_hist but missing here)
ALTER TABLE `ibl_olympics_stats`
  ADD COLUMN `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
    ON UPDATE CURRENT_TIMESTAMP AFTER `pts`;

-- Add composite indexes for common lookups
ALTER TABLE `ibl_olympics_stats`
  ADD INDEX `idx_pid_year` (`pid`, `year`),
  ADD INDEX `idx_year` (`year`);


-- ============================================================
-- 1G. ibl_olympics_power: Restructure to match slim ibl_power schema
-- ============================================================

-- Drop the varchar PK and legacy columns
ALTER TABLE `ibl_olympics_power`
  DROP PRIMARY KEY;

ALTER TABLE `ibl_olympics_power`
  DROP COLUMN `Team`,
  DROP COLUMN `Division`,
  DROP COLUMN `Conference`,
  DROP COLUMN `win`,
  DROP COLUMN `loss`,
  DROP COLUMN `gb`,
  DROP COLUMN `conf_win`,
  DROP COLUMN `conf_loss`,
  DROP COLUMN `div_win`,
  DROP COLUMN `div_loss`,
  DROP COLUMN `home_win`,
  DROP COLUMN `home_loss`,
  DROP COLUMN `road_win`,
  DROP COLUMN `road_loss`;

-- Change TeamID from SMALLINT to INT and set as new PK
ALTER TABLE `ibl_olympics_power`
  MODIFY COLUMN `TeamID` INT NOT NULL DEFAULT 0 COMMENT 'Team ID (PK, FK to ibl_olympics_team_info)';

ALTER TABLE `ibl_olympics_power`
  ADD PRIMARY KEY (`TeamID`);

-- Add SOS columns (matching ibl_power)
ALTER TABLE `ibl_olympics_power`
  ADD COLUMN `sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Strength of schedule' AFTER `streak`,
  ADD COLUMN `remaining_sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Remaining strength of schedule' AFTER `sos`,
  ADD COLUMN `sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'SOS league rank' AFTER `remaining_sos`,
  ADD COLUMN `remaining_sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Remaining SOS league rank' AFTER `sos_rank`;
