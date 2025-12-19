-- ============================================================================
-- Migration: 004_olympics_tables.sql
-- Purpose: Create Olympics league database tables
-- Author: IBL Development Team
-- Date: December 18, 2025
-- Version: 1.0
-- ============================================================================
-- This migration creates dedicated tables for Olympics league support,
-- mirroring the IBL table structure but with Olympics-specific enhancements.
-- All tables use InnoDB engine for ACID compliance and utf8mb4 for proper
-- internationalization support (critical for country names/symbols).
-- ============================================================================

-- ============================================================================
-- Table: user_league_teams
-- Purpose: Maps users to their teams across different leagues (IBL/Olympics)
-- Usage: Enables multi-league support where users can manage teams in both
--        the main IBL league and Olympics league simultaneously
-- ============================================================================
DROP TABLE IF EXISTS `user_league_teams`;

CREATE TABLE `user_league_teams` (
  `user_id` INT NOT NULL COMMENT 'Foreign key to nuke_users.user_id',
  `league` VARCHAR(32) NOT NULL COMMENT 'League identifier (e.g., "ibl", "olympics")',
  `team_name` VARCHAR(32) NOT NULL COMMENT 'Team name in this league',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`, `league`),
  KEY `idx_league` (`league`),
  KEY `idx_team_name` (`team_name`),
  CONSTRAINT `fk_user_league_teams_user` 
    FOREIGN KEY (`user_id`) 
    REFERENCES `nuke_users` (`user_id`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='User-to-team mapping per league for multi-league support';

-- ============================================================================
-- Table: ibl_olympics_team_info
-- Purpose: Olympics team information and roster details
-- Based on: ibl_team_info structure
-- Olympics-specific: Added country_code for international identification
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_team_info`;

CREATE TABLE `ibl_olympics_team_info` (
  `teamid` INT NOT NULL AUTO_INCREMENT,
  `team_city` VARCHAR(24) NOT NULL DEFAULT '' COMMENT 'City/Country name',
  `team_name` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'Team nickname',
  `country_code` VARCHAR(3) NOT NULL DEFAULT '' COMMENT 'ISO 3166-1 alpha-3 country code',
  `color1` VARCHAR(6) NOT NULL DEFAULT '' COMMENT 'Primary team color (hex)',
  `color2` VARCHAR(6) NOT NULL DEFAULT '' COMMENT 'Secondary team color (hex)',
  `arena` VARCHAR(255) NOT NULL DEFAULT '' COMMENT 'Home arena/venue',
  `owner_name` VARCHAR(32) NOT NULL DEFAULT '' COMMENT 'Team owner username',
  `owner_email` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'Owner email address',
  `discordID` BIGINT UNSIGNED DEFAULT NULL COMMENT 'Discord user ID',
  `skype` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'Skype username (legacy)',
  `aim` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'AIM username (legacy)',
  `msn` VARCHAR(48) NOT NULL DEFAULT '' COMMENT 'MSN username (legacy)',
  `formerly_known_as` VARCHAR(255) DEFAULT NULL COMMENT 'Previous team names',
  `Contract_Wins` INT NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  `Contract_Losses` INT NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  `Contract_AvgW` INT NOT NULL DEFAULT 0 COMMENT 'Average wins per contract',
  `Contract_AvgL` INT NOT NULL DEFAULT 0 COMMENT 'Average losses per contract',
  `Contract_Coach` DECIMAL(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Coach rating',
  `chart` CHAR(2) NOT NULL DEFAULT '' COMMENT 'Depth chart identifier',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` CHAR(36) NOT NULL COMMENT 'Public API identifier',
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `team_name` (`team_name`),
  KEY `idx_country_code` (`country_code`),
  KEY `idx_owner_email` (`owner_email`),
  KEY `idx_discordID` (`discordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Olympics team information and configuration';

-- ============================================================================
-- Table: ibl_olympics_standings
-- Purpose: Current Olympics tournament standings and records
-- Based on: ibl_standings structure
-- Olympics-specific: Added group_name for group play, medal for final results
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_standings`;

CREATE TABLE `ibl_olympics_standings` (
  `tid` INT NOT NULL COMMENT 'Team ID - references ibl_olympics_team_info',
  `team_name` VARCHAR(16) NOT NULL DEFAULT '',
  `pct` FLOAT(4,3) UNSIGNED DEFAULT NULL COMMENT 'Win percentage',
  `leagueRecord` VARCHAR(5) DEFAULT '' COMMENT 'Overall W-L record',
  `conference` ENUM('Eastern','Western','') DEFAULT '' COMMENT 'Conference (if used)',
  `confRecord` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Conference record',
  `confGB` DECIMAL(3,1) DEFAULT NULL COMMENT 'Conference games back',
  `division` VARCHAR(16) DEFAULT '' COMMENT 'Division (if used)',
  `divRecord` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Division record',
  `divGB` DECIMAL(3,1) DEFAULT NULL COMMENT 'Division games back',
  `homeRecord` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Home game record',
  `awayRecord` VARCHAR(5) NOT NULL DEFAULT '' COMMENT 'Away game record',
  `gamesUnplayed` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Games remaining',
  `confWins` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Conference wins',
  `confLosses` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Conference losses',
  `divWins` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Division wins',
  `divLosses` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Division losses',
  `homeWins` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Home wins',
  `homeLosses` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Home losses',
  `awayWins` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Away wins',
  `awayLosses` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Away losses',
  `confMagicNumber` TINYINT DEFAULT NULL COMMENT 'Conference magic number',
  `divMagicNumber` TINYINT DEFAULT NULL COMMENT 'Division magic number',
  `clinchedConference` TINYINT(1) DEFAULT NULL COMMENT 'Clinched conference flag',
  `clinchedDivision` TINYINT(1) DEFAULT NULL COMMENT 'Clinched division flag',
  `clinchedPlayoffs` TINYINT(1) DEFAULT NULL COMMENT 'Clinched playoffs flag',
  `group_name` VARCHAR(32) DEFAULT NULL COMMENT 'Olympics group (A, B, C, etc.)',
  `medal` ENUM('gold','silver','bronze') DEFAULT NULL COMMENT 'Final tournament medal',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tid`),
  KEY `team_name` (`team_name`),
  KEY `idx_conference` (`conference`),
  KEY `idx_division` (`division`),
  KEY `idx_group` (`group_name`),
  KEY `idx_medal` (`medal`),
  CONSTRAINT `fk_olympics_standings_team` 
    FOREIGN KEY (`tid`) 
    REFERENCES `ibl_olympics_team_info` (`teamid`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `chk_olympics_standings_pct` 
    CHECK (`pct` IS NULL OR (`pct` >= 0.000 AND `pct` <= 1.000))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Olympics tournament standings and medal tracking';

-- ============================================================================
-- Table: ibl_olympics_schedule
-- Purpose: Olympics game schedule and results
-- Based on: ibl_schedule structure
-- Olympics-specific: Added round field for tournament progression tracking
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_schedule`;

CREATE TABLE `ibl_olympics_schedule` (
  `Year` SMALLINT UNSIGNED NOT NULL COMMENT 'Tournament year',
  `BoxID` INT NOT NULL DEFAULT 0 COMMENT 'Box score identifier',
  `Date` DATE NOT NULL COMMENT 'Game date',
  `Visitor` SMALLINT UNSIGNED NOT NULL COMMENT 'Visiting team ID',
  `VScore` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  `Home` SMALLINT UNSIGNED NOT NULL COMMENT 'Home team ID',
  `HScore` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Home score',
  `round` VARCHAR(32) DEFAULT NULL COMMENT 'Tournament round (Group A, Quarterfinal, Semifinal, Gold Medal, Bronze Medal)',
  `SchedID` INT NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` CHAR(36) NOT NULL COMMENT 'Public API identifier',
  PRIMARY KEY (`SchedID`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `BoxID` (`BoxID`),
  KEY `idx_year` (`Year`),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor` (`Visitor`),
  KEY `idx_home` (`Home`),
  KEY `idx_round` (`round`),
  KEY `idx_year_date` (`Year`,`Date`),
  CONSTRAINT `chk_olympics_schedule_vscore` 
    CHECK (`VScore` >= 0 AND `VScore` <= 200),
  CONSTRAINT `chk_olympics_schedule_hscore` 
    CHECK (`HScore` >= 0 AND `HScore` <= 200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Olympics game schedule with tournament round tracking';

-- ============================================================================
-- Table: ibl_olympics_box_scores
-- Purpose: Individual player statistics for Olympics games
-- Based on: ibl_box_scores structure
-- Note: References Olympics schedule instead of IBL schedule
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_box_scores`;

CREATE TABLE `ibl_olympics_box_scores` (
  `Date` DATE NOT NULL COMMENT 'Game date',
  `name` VARCHAR(16) DEFAULT '' COMMENT 'Player name',
  `pos` VARCHAR(2) DEFAULT '' COMMENT 'Position played',
  `pid` INT DEFAULT NULL COMMENT 'Player ID (references ibl_plr)',
  `visitorTID` INT DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTID` INT DEFAULT NULL COMMENT 'Home team ID',
  `gameMIN` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Minutes played',
  `game2GM` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Assists',
  `gameSTL` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Steals',
  `gameTOV` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Blocks',
  `gamePF` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` CHAR(36) NOT NULL COMMENT 'Public API identifier',
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_date` (`Date`),
  KEY `idx_pid` (`pid`),
  KEY `idx_visitor_tid` (`visitorTID`),
  KEY `idx_home_tid` (`homeTID`),
  KEY `idx_date_pid` (`Date`,`pid`),
  KEY `idx_date_home_visitor` (`Date`,`homeTID`,`visitorTID`),
  CONSTRAINT `fk_olympics_boxscore_home` 
    FOREIGN KEY (`homeTID`) 
    REFERENCES `ibl_olympics_team_info` (`teamid`) 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscore_visitor` 
    FOREIGN KEY (`visitorTID`) 
    REFERENCES `ibl_olympics_team_info` (`teamid`) 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscore_player` 
    FOREIGN KEY (`pid`) 
    REFERENCES `ibl_plr` (`pid`) 
    ON DELETE CASCADE 
    ON UPDATE CASCADE,
  CONSTRAINT `chk_olympics_box_minutes` 
    CHECK (`gameMIN` IS NULL OR (`gameMIN` >= 0 AND `gameMIN` <= 70))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Individual player statistics for Olympics games';

-- ============================================================================
-- Table: ibl_olympics_box_scores_teams
-- Purpose: Team-level statistics and scores for Olympics games
-- Based on: ibl_box_scores_teams structure
-- Note: References Olympics teams instead of IBL teams
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_box_scores_teams`;

CREATE TABLE `ibl_olympics_box_scores_teams` (
  `Date` DATE NOT NULL COMMENT 'Game date',
  `name` VARCHAR(16) DEFAULT '' COMMENT 'Arena/venue name',
  `gameOfThatDay` INT DEFAULT NULL COMMENT 'Game number for that date',
  `visitorTeamID` INT DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTeamID` INT DEFAULT NULL COMMENT 'Home team ID',
  `attendance` INT DEFAULT NULL COMMENT 'Game attendance',
  `capacity` INT DEFAULT NULL COMMENT 'Arena capacity',
  `visitorWins` INT DEFAULT NULL COMMENT 'Visitor team wins before game',
  `visitorLosses` INT DEFAULT NULL COMMENT 'Visitor team losses before game',
  `homeWins` INT DEFAULT NULL COMMENT 'Home team wins before game',
  `homeLosses` INT DEFAULT NULL COMMENT 'Home team losses before game',
  `visitorQ1points` INT DEFAULT NULL COMMENT 'Visitor Q1 points',
  `visitorQ2points` INT DEFAULT NULL COMMENT 'Visitor Q2 points',
  `visitorQ3points` INT DEFAULT NULL COMMENT 'Visitor Q3 points',
  `visitorQ4points` INT DEFAULT NULL COMMENT 'Visitor Q4 points',
  `visitorOTpoints` INT DEFAULT NULL COMMENT 'Visitor overtime points',
  `homeQ1points` INT DEFAULT NULL COMMENT 'Home Q1 points',
  `homeQ2points` INT DEFAULT NULL COMMENT 'Home Q2 points',
  `homeQ3points` INT DEFAULT NULL COMMENT 'Home Q3 points',
  `homeQ4points` INT DEFAULT NULL COMMENT 'Home Q4 points',
  `homeOTpoints` INT DEFAULT NULL COMMENT 'Home overtime points',
  `gameMIN` INT DEFAULT NULL COMMENT 'Total game minutes',
  `game2GM` INT DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` INT DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` INT DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` INT DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` INT DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` INT DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` INT DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` INT DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` INT DEFAULT NULL COMMENT 'Assists',
  `gameSTL` INT DEFAULT NULL COMMENT 'Steals',
  `gameTOV` INT DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` INT DEFAULT NULL COMMENT 'Blocks',
  `gamePF` INT DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  KEY `idx_date` (`Date`),
  KEY `idx_visitor_team` (`visitorTeamID`),
  KEY `idx_home_team` (`homeTeamID`),
  CONSTRAINT `fk_olympics_boxscoreteam_home` 
    FOREIGN KEY (`homeTeamID`) 
    REFERENCES `ibl_olympics_team_info` (`teamid`) 
    ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscoreteam_visitor` 
    FOREIGN KEY (`visitorTeamID`) 
    REFERENCES `ibl_olympics_team_info` (`teamid`) 
    ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Team-level statistics for Olympics games';

-- ============================================================================
-- Table: ibl_olympics_win_loss
-- Purpose: Historical win/loss records and medal counts for Olympics teams
-- Based on: ibl_heat_win_loss structure
-- Olympics-specific: Added gold, silver, bronze medal tracking columns
-- ============================================================================
DROP TABLE IF EXISTS `ibl_olympics_win_loss`;

CREATE TABLE `ibl_olympics_win_loss` (
  `year` INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Olympics year',
  `currentname` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'Current team name',
  `namethatyear` VARCHAR(16) NOT NULL DEFAULT '' COMMENT 'Team name during that Olympics',
  `wins` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Games won',
  `losses` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Games lost',
  `gold` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Gold medals won',
  `silver` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Silver medals won',
  `bronze` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Bronze medals won',
  `table_ID` INT NOT NULL AUTO_INCREMENT,
  `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_currentname` (`currentname`),
  KEY `idx_year_team` (`year`, `currentname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Historical Olympics win/loss records and medal counts';

-- ============================================================================
-- End of Migration: 004_olympics_tables.sql
-- ============================================================================
-- NOTES:
-- 1. All tables use InnoDB for transaction support and foreign key constraints
-- 2. utf8mb4 charset ensures proper support for international characters
-- 3. Timestamps (created_at, updated_at) enable API caching and ETags
-- 4. UUIDs provide secure public identifiers for API endpoints
-- 5. Indexes optimize common query patterns (date, team, player lookups)
-- 6. Foreign keys maintain referential integrity
-- 7. CHECK constraints ensure data validity
-- 8. Olympics-specific enhancements:
--    - country_code for international identification
--    - group_name for tournament group play tracking
--    - medal tracking for final tournament results
--    - round field for tournament progression
--    - gold/silver/bronze medal historical tracking
-- ============================================================================
