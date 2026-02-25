-- MySQL dump 10.13  Distrib 5.7.44, for osx11.0 (x86_64)
--
-- Host: iblhoops.net    Database: iblhoops_ibl5
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.16-MariaDB

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8 */;
/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;
/*!40103 SET TIME_ZONE='+00:00' */;
/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;
/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;
/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;
/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;

--
-- Table structure for table `auth_users`
--

DROP TABLE IF EXISTS `auth_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `email` varchar(249) NOT NULL,
  `password` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `username` varchar(100) DEFAULT NULL,
  `status` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `verified` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `resettable` tinyint(3) unsigned NOT NULL DEFAULT 1,
  `roles_mask` int(10) unsigned NOT NULL DEFAULT 0,
  `registered` int(10) unsigned NOT NULL,
  `last_login` int(10) unsigned DEFAULT NULL,
  `force_logout` mediumint(8) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=790 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_2fa`
--

DROP TABLE IF EXISTS `auth_users_2fa`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_2fa` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `mechanism` tinyint(3) unsigned NOT NULL,
  `seed` varchar(255) DEFAULT NULL,
  `created_at` int(10) unsigned NOT NULL,
  `expires_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `user_id_mechanism` (`user_id`,`mechanism`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_audit_log`
--

DROP TABLE IF EXISTS `auth_users_audit_log`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_audit_log` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned DEFAULT NULL,
  `event_at` int(10) unsigned NOT NULL,
  `event_type` varchar(128) CHARACTER SET ascii COLLATE ascii_general_ci NOT NULL,
  `admin_id` int(10) unsigned DEFAULT NULL,
  `ip_address` varchar(49) CHARACTER SET ascii COLLATE ascii_general_ci DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `details_json` text DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `event_at` (`event_at`),
  KEY `user_id_event_at` (`user_id`,`event_at`),
  KEY `user_id_event_type_event_at` (`user_id`,`event_type`,`event_at`)
) ENGINE=InnoDB AUTO_INCREMENT=101 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_confirmations`
--

DROP TABLE IF EXISTS `auth_users_confirmations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_confirmations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `email` varchar(249) NOT NULL,
  `selector` varchar(16) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `email_expires` (`email`,`expires`),
  KEY `user_id` (`user_id`)
) ENGINE=InnoDB AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_otps`
--

DROP TABLE IF EXISTS `auth_users_otps`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_otps` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(10) unsigned NOT NULL,
  `mechanism` tinyint(3) unsigned NOT NULL,
  `single_factor` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires_at` int(10) unsigned DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `user_id_mechanism` (`user_id`,`mechanism`),
  KEY `selector_user_id` (`selector`,`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_remembered`
--

DROP TABLE IF EXISTS `auth_users_remembered`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_remembered` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user` int(10) unsigned NOT NULL,
  `selector` varchar(24) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user` (`user`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_resets`
--

DROP TABLE IF EXISTS `auth_users_resets`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_resets` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `user` int(10) unsigned NOT NULL,
  `selector` varchar(20) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `token` varchar(255) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `expires` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `selector` (`selector`),
  KEY `user_expires` (`user`,`expires`)
) ENGINE=InnoDB AUTO_INCREMENT=29 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `auth_users_throttling`
--

DROP TABLE IF EXISTS `auth_users_throttling`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `auth_users_throttling` (
  `bucket` varchar(44) CHARACTER SET latin1 COLLATE latin1_general_cs NOT NULL,
  `tokens` float NOT NULL,
  `replenished_at` int(10) unsigned NOT NULL,
  `expires_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`bucket`),
  KEY `expires_at` (`expires_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache`
--

DROP TABLE IF EXISTS `cache`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache` (
  `key` varchar(255) NOT NULL,
  `value` mediumtext NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) NOT NULL,
  `owner` varchar(255) NOT NULL,
  `expiration` int(11) NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `failed_jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) NOT NULL,
  `connection` text NOT NULL,
  `queue` text NOT NULL,
  `payload` longtext NOT NULL,
  `exception` longtext NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_api_keys`
--

DROP TABLE IF EXISTS `ibl_api_keys`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_api_keys` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `key_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the API key',
  `key_prefix` char(8) NOT NULL COMMENT 'First 8 chars of key for log identification',
  `owner_name` varchar(64) NOT NULL COMMENT 'Human-readable owner (e.g. Discord Bot - MJ)',
  `permission_level` enum('public','team_owner','commissioner') NOT NULL DEFAULT 'public' COMMENT 'API access tier',
  `rate_limit_tier` enum('standard','elevated','unlimited') NOT NULL DEFAULT 'standard' COMMENT 'Request rate limit category',
  `is_active` tinyint(1) NOT NULL DEFAULT 1 COMMENT '1=active, 0=revoked',
  `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last API request timestamp',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `key_hash` (`key_hash`),
  KEY `idx_key_hash` (`key_hash`),
  KEY `idx_is_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_api_rate_limits`
--

DROP TABLE IF EXISTS `ibl_api_rate_limits`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_api_rate_limits` (
  `api_key_hash` char(64) NOT NULL COMMENT 'SHA-256 hash of the API key (FK to ibl_api_keys)',
  `window_start` timestamp NOT NULL COMMENT 'Start of the 1-minute window',
  `request_count` int(10) unsigned NOT NULL DEFAULT 1 COMMENT 'Requests in current window',
  PRIMARY KEY (`api_key_hash`,`window_start`),
  KEY `idx_window_start` (`window_start`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_awards`
--

DROP TABLE IF EXISTS `ibl_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_awards` (
  `year` int(11) NOT NULL DEFAULT 0 COMMENT 'Season year of award',
  `Award` varchar(128) NOT NULL DEFAULT '' COMMENT 'Award name (e.g., MVP, DPOY)',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Winning player name',
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3981 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_banners`
--

DROP TABLE IF EXISTS `ibl_banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_banners` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL DEFAULT 0 COMMENT 'Championship/award season year',
  `currentname` varchar(16) NOT NULL DEFAULT '' COMMENT 'Current franchise team name',
  `bannername` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team name when banner was earned',
  `bannertype` int(11) NOT NULL DEFAULT 0 COMMENT 'Banner category (championship, division, etc.)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores`
--

DROP TABLE IF EXISTS `ibl_box_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_box_scores` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) DEFAULT '' COMMENT 'Player name (denormalized snapshot)',
  `pos` varchar(2) DEFAULT '' COMMENT 'Player position at game time',
  `pid` int(11) DEFAULT NULL COMMENT 'FK to ibl_plr.pid',
  `visitorTID` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)',
  `homeTID` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)',
  `gameMIN` tinyint(3) unsigned DEFAULT NULL COMMENT 'Minutes played',
  `game2GM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Two-point field goals made',
  `game2GA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Two-point field goals attempted',
  `gameFTM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` tinyint(3) unsigned DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` tinyint(3) unsigned DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` tinyint(3) unsigned DEFAULT NULL COMMENT 'Assists',
  `gameSTL` tinyint(3) unsigned DEFAULT NULL COMMENT 'Steals',
  `gameTOV` tinyint(3) unsigned DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` tinyint(3) unsigned DEFAULT NULL COMMENT 'Blocks',
  `gamePF` tinyint(3) unsigned DEFAULT NULL COMMENT 'Personal fouls',
  `game_type` tinyint(3) unsigned GENERATED ALWAYS AS (case when month(`Date`) = 6 then 2 when month(`Date`) = 10 then 3 when month(`Date`) = 0 then 0 else 1 end) STORED,
  `season_year` smallint(5) unsigned GENERATED ALWAYS AS (case when year(`Date`) = 0 then 0 when month(`Date`) >= 10 then year(`Date`) + 1 else year(`Date`) end) STORED,
  `calc_points` smallint(5) unsigned GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED,
  `calc_rebounds` tinyint(3) unsigned GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED,
  `calc_fg_made` tinyint(3) unsigned GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  `gameOfThatDay` tinyint(3) unsigned DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd game)',
  `attendance` int(11) DEFAULT NULL COMMENT 'Attendance at the game',
  `capacity` int(11) DEFAULT NULL COMMENT 'Arena capacity',
  `visitorWins` smallint(5) unsigned DEFAULT NULL COMMENT 'Visitor team wins before this game',
  `visitorLosses` smallint(5) unsigned DEFAULT NULL COMMENT 'Visitor team losses before this game',
  `homeWins` smallint(5) unsigned DEFAULT NULL COMMENT 'Home team wins before this game',
  `homeLosses` smallint(5) unsigned DEFAULT NULL COMMENT 'Home team losses before this game',
  `teamID` int(11) DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_date` (`Date`),
  KEY `idx_pid` (`pid`),
  KEY `idx_visitor_tid` (`visitorTID`),
  KEY `idx_home_tid` (`homeTID`),
  KEY `idx_date_pid` (`Date`,`pid`),
  KEY `idx_date_home_visitor` (`Date`,`homeTID`,`visitorTID`),
  KEY `idx_gt_points` (`game_type`,`calc_points`),
  KEY `idx_gt_rebounds` (`game_type`,`calc_rebounds`),
  KEY `idx_gt_fg_made` (`game_type`,`calc_fg_made`),
  KEY `idx_gt_ast` (`game_type`,`gameAST`),
  KEY `idx_gt_stl` (`game_type`,`gameSTL`),
  KEY `idx_gt_blk` (`game_type`,`gameBLK`),
  KEY `idx_gt_tov` (`game_type`,`gameTOV`),
  KEY `idx_gt_ftm` (`game_type`,`gameFTM`),
  KEY `idx_gt_3gm` (`game_type`,`game3GM`),
  KEY `idx_team_id` (`teamID`),
  KEY `idx_gt_pid` (`game_type`,`pid`),
  CONSTRAINT `fk_boxscore_home` FOREIGN KEY (`homeTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_visitor` FOREIGN KEY (`visitorTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_box_minutes` CHECK (`gameMIN` is null or `gameMIN` >= 0 and `gameMIN` <= 70)
) ENGINE=InnoDB AUTO_INCREMENT=585922 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores_teams`
--

DROP TABLE IF EXISTS `ibl_box_scores_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_box_scores_teams` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) DEFAULT '' COMMENT 'Team name (denormalized snapshot)',
  `gameOfThatDay` int(11) DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd, etc.)',
  `visitorTeamID` int(11) DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)',
  `homeTeamID` int(11) DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)',
  `attendance` int(11) DEFAULT NULL COMMENT 'Game attendance',
  `capacity` int(11) DEFAULT NULL COMMENT 'Arena capacity',
  `visitorWins` int(11) DEFAULT NULL COMMENT 'Visitor record wins before game',
  `visitorLosses` int(11) DEFAULT NULL COMMENT 'Visitor record losses before game',
  `homeWins` int(11) DEFAULT NULL COMMENT 'Home record wins before game',
  `homeLosses` int(11) DEFAULT NULL COMMENT 'Home record losses before game',
  `visitorQ1points` int(11) DEFAULT NULL COMMENT 'Visitor Q1 points',
  `visitorQ2points` int(11) DEFAULT NULL COMMENT 'Visitor Q2 points',
  `visitorQ3points` int(11) DEFAULT NULL COMMENT 'Visitor Q3 points',
  `visitorQ4points` int(11) DEFAULT NULL COMMENT 'Visitor Q4 points',
  `visitorOTpoints` int(11) DEFAULT NULL COMMENT 'Visitor overtime points',
  `homeQ1points` int(11) DEFAULT NULL COMMENT 'Home Q1 points',
  `homeQ2points` int(11) DEFAULT NULL COMMENT 'Home Q2 points',
  `homeQ3points` int(11) DEFAULT NULL COMMENT 'Home Q3 points',
  `homeQ4points` int(11) DEFAULT NULL COMMENT 'Home Q4 points',
  `homeOTpoints` int(11) DEFAULT NULL COMMENT 'Home overtime points',
  `gameMIN` int(11) DEFAULT NULL COMMENT 'Total game minutes',
  `game2GM` int(11) DEFAULT NULL COMMENT 'Two-point field goals made',
  `game2GA` int(11) DEFAULT NULL COMMENT 'Two-point field goals attempted',
  `gameFTM` int(11) DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` int(11) DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` int(11) DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` int(11) DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` int(11) DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` int(11) DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` int(11) DEFAULT NULL COMMENT 'Assists',
  `gameSTL` int(11) DEFAULT NULL COMMENT 'Steals',
  `gameTOV` int(11) DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` int(11) DEFAULT NULL COMMENT 'Blocks',
  `gamePF` int(11) DEFAULT NULL COMMENT 'Personal fouls',
  `game_type` tinyint(3) unsigned GENERATED ALWAYS AS (case when month(`Date`) = 6 then 2 when month(`Date`) = 10 then 3 when month(`Date`) = 0 then 0 else 1 end) STORED,
  `season_year` smallint(5) unsigned GENERATED ALWAYS AS (case when year(`Date`) = 0 then 0 when month(`Date`) >= 10 then year(`Date`) + 1 else year(`Date`) end) STORED,
  `calc_points` smallint(5) unsigned GENERATED ALWAYS AS (`game2GM` * 2 + `gameFTM` + `game3GM` * 3) STORED,
  `calc_rebounds` smallint(5) unsigned GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED,
  `calc_fg_made` smallint(5) unsigned GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor_team` (`visitorTeamID`),
  KEY `idx_home_team` (`homeTeamID`),
  KEY `idx_gt_points` (`game_type`,`calc_points`),
  KEY `idx_gt_rebounds` (`game_type`,`calc_rebounds`),
  KEY `idx_gt_fg_made` (`game_type`,`calc_fg_made`),
  KEY `idx_gt_ast` (`game_type`,`gameAST`),
  KEY `idx_gt_stl` (`game_type`,`gameSTL`),
  KEY `idx_gt_blk` (`game_type`,`gameBLK`),
  KEY `idx_gt_tov` (`game_type`,`gameTOV`),
  KEY `idx_gt_ftm` (`game_type`,`gameFTM`),
  KEY `idx_gt_3gm` (`game_type`,`game3GM`),
  KEY `idx_name` (`name`),
  KEY `idx_gt_date_teams` (`game_type`,`Date`,`visitorTeamID`,`homeTeamID`),
  KEY `idx_gt_name_season` (`game_type`,`name`,`season_year`),
  KEY `idx_date_visitor_home_gotd` (`Date`,`visitorTeamID`,`homeTeamID`,`gameOfThatDay`),
  CONSTRAINT `fk_boxscoreteam_home` FOREIGN KEY (`homeTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscoreteam_visitor` FOREIGN KEY (`visitorTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=49902 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_demands`
--

DROP TABLE IF EXISTS `ibl_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_demands` (
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (PK, FK to ibl_plr.name)',
  `dem1` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 1 day 1 demand',
  `dem2` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 2 day 1 demand',
  `dem3` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 3 day 1 demand',
  `dem4` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 4 day 1 demand',
  `dem5` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 5 day 1 demand',
  `dem6` int(11) NOT NULL DEFAULT 0 COMMENT 'FA year 6 day 1 demand',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`name`),
  CONSTRAINT `fk_demands_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_draft`
--

DROP TABLE IF EXISTS `ibl_draft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_draft` (
  `draft_id` int(11) NOT NULL AUTO_INCREMENT,
  `year` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Draft year',
  `team` varchar(255) NOT NULL DEFAULT '' COMMENT 'Drafting team name (FK to ibl_team_info)',
  `player` varchar(255) NOT NULL DEFAULT '' COMMENT 'Drafted player name',
  `round` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Draft round',
  `pick` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Pick number',
  `date` datetime DEFAULT NULL COMMENT 'Date and time of pick',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`draft_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_year` (`year`),
  KEY `idx_team` (`team`),
  KEY `idx_player` (`player`),
  KEY `idx_year_round` (`year`,`round`),
  KEY `idx_year_round_pick` (`year`,`round`,`pick`),
  CONSTRAINT `fk_draft_team` FOREIGN KEY (`team`) REFERENCES `ibl_team_info` (`team_name`) ON UPDATE CASCADE,
  CONSTRAINT `chk_draft_round` CHECK (`round` >= 0 and `round` <= 7),
  CONSTRAINT `chk_draft_pick` CHECK (`pick` >= 0 and `pick` <= 32)
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_draft_class`
--

DROP TABLE IF EXISTS `ibl_draft_class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_draft_class` (
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Prospect name',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') NOT NULL DEFAULT '' COMMENT 'Draft prospect position',
  `age` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Player age',
  `team` varchar(128) NOT NULL DEFAULT '' COMMENT 'College or club team',
  `fga` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'FG attempts rating',
  `fgp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'FG percentage rating',
  `fta` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'FT attempts rating',
  `ftp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'FT percentage rating',
  `tga` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '3P attempts rating',
  `tgp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT '3P percentage rating',
  `orb` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off rebounds rating',
  `drb` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def rebounds rating',
  `ast` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Assists rating',
  `stl` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Steals rating',
  `tvr` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Turnovers rating',
  `blk` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Blocks rating',
  `oo` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off outside rating',
  `do` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off drive rating',
  `po` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off post rating',
  `to` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off transition rating',
  `od` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def outside rating',
  `dd` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def drive rating',
  `pd` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def post rating',
  `td` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def transition rating',
  `talent` int(11) NOT NULL DEFAULT 0 COMMENT 'Talent off-season progression rating',
  `skill` int(11) NOT NULL DEFAULT 0 COMMENT 'Skill off-season progression rating',
  `intangibles` int(11) NOT NULL DEFAULT 0 COMMENT 'Intangibles off-season progression rating',
  `drafted` int(11) DEFAULT 0 COMMENT '0=undrafted, 1=drafted',
  `sta` int(11) DEFAULT 0 COMMENT 'Stamina rating',
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `idx_drafted` (`drafted`),
  KEY `idx_pos` (`pos`)
) ENGINE=InnoDB AUTO_INCREMENT=66 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_draft_picks`
--

DROP TABLE IF EXISTS `ibl_draft_picks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_draft_picks` (
  `pickid` int(11) NOT NULL AUTO_INCREMENT,
  `ownerofpick` varchar(32) NOT NULL DEFAULT '' COMMENT 'Team currently owning pick (FK to ibl_team_info)',
  `teampick` varchar(32) NOT NULL DEFAULT '' COMMENT 'Original team the pick belongs to (FK to ibl_team_info)',
  `year` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Draft year',
  `round` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Draft round',
  `notes` varchar(280) DEFAULT NULL COMMENT 'Trade/transaction notes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`pickid`),
  KEY `idx_ownerofpick` (`ownerofpick`),
  KEY `idx_year` (`year`),
  KEY `idx_year_round` (`year`,`round`),
  KEY `fk_draftpick_team` (`teampick`),
  CONSTRAINT `fk_draftpick_owner` FOREIGN KEY (`ownerofpick`) REFERENCES `ibl_team_info` (`team_name`) ON UPDATE CASCADE,
  CONSTRAINT `fk_draftpick_team` FOREIGN KEY (`teampick`) REFERENCES `ibl_team_info` (`team_name`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=1359 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_fa_offers`
--

DROP TABLE IF EXISTS `ibl_fa_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_fa_offers` (
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (FK to ibl_plr.name)',
  `team` varchar(32) NOT NULL DEFAULT '' COMMENT 'Offering team name (FK to ibl_team_info)',
  `offer1` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 1 (thousands)',
  `offer2` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 2 (thousands)',
  `offer3` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 3 (thousands)',
  `offer4` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 4 (thousands)',
  `offer5` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 5 (thousands)',
  `offer6` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary offer year 6 (thousands)',
  `modifier` float NOT NULL DEFAULT 0 COMMENT 'FA decision weight modifier',
  `random` float NOT NULL DEFAULT 0 COMMENT 'Random factor in FA decision',
  `perceivedvalue` float NOT NULL DEFAULT 0 COMMENT 'Calculated perceived value of offer',
  `MLE` int(11) NOT NULL DEFAULT 0 COMMENT '1=offer uses Mid-Level Exception',
  `LLE` int(11) NOT NULL DEFAULT 0 COMMENT '1=offer uses Lower-Level Exception',
  `offer_type` int(11) NOT NULL DEFAULT 0 COMMENT 'Offer type: 0=Custom, 1-6=MLE years, 7=LLE, 8=Vet Min',
  `primary_key` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`primary_key`),
  KEY `idx_name` (`name`),
  KEY `idx_team` (`team`),
  KEY `idx_offer_type` (`offer_type`),
  CONSTRAINT `fk_faoffer_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_faoffer_team` FOREIGN KEY (`team`) REFERENCES `ibl_team_info` (`team_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_franchise_seasons`
--

DROP TABLE IF EXISTS `ibl_franchise_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_franchise_seasons` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `franchise_id` int(11) NOT NULL COMMENT 'FK to ibl_team_info.teamid',
  `season_year` smallint(5) unsigned NOT NULL COMMENT 'Season starting year',
  `season_ending_year` smallint(5) unsigned NOT NULL COMMENT 'Season ending year',
  `team_city` varchar(24) NOT NULL COMMENT 'City name during that season',
  `team_name` varchar(40) NOT NULL COMMENT 'Team name during that season',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_franchise_season` (`franchise_id`,`season_year`),
  KEY `idx_team_name` (`team_name`),
  KEY `idx_season_year` (`season_year`),
  KEY `idx_ending_year` (`season_ending_year`),
  CONSTRAINT `fk_fs_franchise` FOREIGN KEY (`franchise_id`) REFERENCES `ibl_team_info` (`teamid`)
) ENGINE=InnoDB AUTO_INCREMENT=513 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_awards`
--

DROP TABLE IF EXISTS `ibl_gm_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_gm_awards` (
  `year` int(11) NOT NULL DEFAULT 0 COMMENT 'Season year of award',
  `Award` varchar(128) NOT NULL DEFAULT '' COMMENT 'Award name',
  `name` varchar(50) NOT NULL DEFAULT '' COMMENT 'Winning GM username',
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=121 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_history`
--

DROP TABLE IF EXISTS `ibl_gm_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_gm_history` (
  `year` varchar(35) NOT NULL COMMENT 'Season year',
  `name` varchar(50) NOT NULL COMMENT 'GM username',
  `Award` varchar(350) NOT NULL COMMENT 'Award name/description',
  `prim` int(11) NOT NULL COMMENT 'Primary key',
  PRIMARY KEY (`prim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_tenures`
--

DROP TABLE IF EXISTS `ibl_gm_tenures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_gm_tenures` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `franchise_id` int(11) NOT NULL COMMENT 'FK to ibl_team_info.teamid',
  `gm_username` varchar(50) NOT NULL COMMENT 'GM site username',
  `start_season_year` smallint(5) unsigned NOT NULL COMMENT 'First season of tenure',
  `end_season_year` smallint(5) unsigned DEFAULT NULL COMMENT 'Last season of tenure (NULL=current)',
  `is_mid_season_start` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=took over mid-season',
  `is_mid_season_end` tinyint(1) NOT NULL DEFAULT 0 COMMENT '1=left mid-season',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenure` (`franchise_id`,`gm_username`,`start_season_year`),
  KEY `idx_gm` (`gm_username`),
  KEY `idx_franchise` (`franchise_id`),
  CONSTRAINT `fk_gt_franchise` FOREIGN KEY (`franchise_id`) REFERENCES `ibl_team_info` (`teamid`)
) ENGINE=InnoDB AUTO_INCREMENT=197 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `ibl_heat_career_avgs`
--

DROP TABLE IF EXISTS `ibl_heat_career_avgs`;
/*!50001 DROP VIEW IF EXISTS `ibl_heat_career_avgs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_heat_career_avgs` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `fgpct`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `ftpct`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `tpct`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`,
 1 AS `retired`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_heat_career_totals`
--

DROP TABLE IF EXISTS `ibl_heat_career_totals`;
/*!50001 DROP VIEW IF EXISTS `ibl_heat_career_totals`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_heat_career_totals` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`,
 1 AS `retired`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_heat_stats`
--

DROP TABLE IF EXISTS `ibl_heat_stats`;
/*!50001 DROP VIEW IF EXISTS `ibl_heat_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_heat_stats` AS SELECT 
 1 AS `year`,
 1 AS `pos`,
 1 AS `pid`,
 1 AS `name`,
 1 AS `team`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_heat_win_loss`
--

DROP TABLE IF EXISTS `ibl_heat_win_loss`;
/*!50001 DROP VIEW IF EXISTS `ibl_heat_win_loss`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_heat_win_loss` AS SELECT 
 1 AS `year`,
 1 AS `currentname`,
 1 AS `namethatyear`,
 1 AS `wins`,
 1 AS `losses`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ibl_hist`
--

DROP TABLE IF EXISTS `ibl_hist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_hist` (
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT 'Player ID (FK to ibl_plr)',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (denormalized snapshot)',
  `year` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Season year',
  `team` varchar(32) NOT NULL DEFAULT '' COMMENT 'Team name (denormalized snapshot)',
  `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (FK to ibl_team_info)',
  `games` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Games played',
  `minutes` mediumint(8) unsigned NOT NULL DEFAULT 0 COMMENT 'Minutes played',
  `fgm` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Field goals made',
  `fga` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Field goals attempted',
  `ftm` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Free throws made',
  `fta` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Free throws attempted',
  `tgm` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Three pointers made',
  `tga` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Three pointers attempted',
  `orb` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Offensive rebounds',
  `reb` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Total rebounds',
  `ast` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Assists',
  `stl` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Steals',
  `blk` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Blocks',
  `tvr` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Turnovers',
  `pf` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Personal fouls',
  `pts` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Points',
  `r_2ga` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: 2P attempts',
  `r_2gp` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: 2P percentage',
  `r_fta` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: FT attempts',
  `r_ftp` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: FT percentage',
  `r_3ga` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: 3P attempts',
  `r_3gp` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: 3P percentage',
  `r_orb` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: offensive rebounds',
  `r_drb` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: defensive rebounds',
  `r_ast` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: assists',
  `r_stl` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: steals',
  `r_blk` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: blocks',
  `r_tvr` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: turnovers',
  `r_oo` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: outside offense',
  `r_do` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: drive offense',
  `r_po` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: post offense',
  `r_to` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: transition offense',
  `r_od` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: outside defense',
  `r_dd` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: drive defense',
  `r_pd` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: post defense',
  `r_td` int(11) NOT NULL DEFAULT 0 COMMENT 'Rating: transition defense',
  `salary` int(11) NOT NULL DEFAULT 0 COMMENT 'Salary that season (thousands)',
  `nuke_iblhist` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`nuke_iblhist`),
  UNIQUE KEY `unique_composite_key` (`pid`,`name`,`year`),
  KEY `idx_pid_year` (`pid`,`year`),
  KEY `idx_team_year` (`team`,`year`),
  KEY `idx_teamid_year` (`teamid`,`year`),
  KEY `idx_year` (`year`),
  KEY `idx_pid_year_team` (`pid`,`year`,`team`),
  CONSTRAINT `fk_hist_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hist_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=19217 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_jsb_allstar_rosters`
--

DROP TABLE IF EXISTS `ibl_jsb_allstar_rosters`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_jsb_allstar_rosters` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint(5) unsigned NOT NULL,
  `event_type` enum('allstar_1','allstar_2','rookie_1','rookie_2','three_point','dunk_contest') NOT NULL,
  `roster_slot` tinyint(3) unsigned NOT NULL,
  `pid` int(11) DEFAULT NULL COMMENT 'FK to ibl_plr.pid',
  `player_name` varchar(32) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_event_slot` (`season_year`,`event_type`,`roster_slot`),
  KEY `idx_pid` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=601 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_jsb_allstar_scores`
--

DROP TABLE IF EXISTS `ibl_jsb_allstar_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_jsb_allstar_scores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint(5) unsigned NOT NULL,
  `contest_type` enum('three_point','dunk_contest') NOT NULL,
  `round` tinyint(3) unsigned NOT NULL COMMENT '1=round1, 2=semifinals, 3=finals',
  `participant_slot` tinyint(3) unsigned NOT NULL,
  `pid` int(11) DEFAULT NULL,
  `score` int(11) NOT NULL COMMENT '3pt: raw count. Dunk: score*10 (932=93.2)',
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_contest_round_slot` (`season_year`,`contest_type`,`round`,`participant_slot`),
  KEY `idx_pid` (`pid`)
) ENGINE=InnoDB AUTO_INCREMENT=251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_jsb_history`
--

DROP TABLE IF EXISTS `ibl_jsb_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_jsb_history` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint(5) unsigned NOT NULL,
  `team_name` varchar(32) NOT NULL,
  `teamid` int(11) DEFAULT NULL COMMENT 'FK to ibl_team_info.teamid',
  `wins` smallint(5) unsigned NOT NULL DEFAULT 0,
  `losses` smallint(5) unsigned NOT NULL DEFAULT 0,
  `made_playoffs` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `playoff_result` text DEFAULT NULL COMMENT 'Full result text from .his',
  `playoff_round_reached` varchar(32) DEFAULT NULL COMMENT 'first round, quarter-finals, semi-finals, finals, championship',
  `won_championship` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `source_file` varchar(128) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_team` (`season_year`,`team_name`),
  KEY `idx_teamid` (`teamid`),
  KEY `idx_season` (`season_year`),
  KEY `idx_champion` (`won_championship`)
) ENGINE=InnoDB AUTO_INCREMENT=4841 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_jsb_transactions`
--

DROP TABLE IF EXISTS `ibl_jsb_transactions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_jsb_transactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `season_year` smallint(5) unsigned NOT NULL,
  `transaction_month` tinyint(3) unsigned NOT NULL,
  `transaction_day` tinyint(3) unsigned NOT NULL,
  `transaction_type` tinyint(3) unsigned NOT NULL COMMENT '1=injury, 2=trade, 3=waiver_claim, 4=waiver_release',
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to ibl_plr.pid; 0 = no player (e.g. draft pick trade)',
  `player_name` varchar(32) DEFAULT NULL,
  `from_teamid` int(11) NOT NULL DEFAULT 0 COMMENT '0 = not applicable',
  `to_teamid` int(11) NOT NULL DEFAULT 0 COMMENT '0 = not applicable',
  `injury_games_missed` smallint(5) unsigned DEFAULT NULL,
  `injury_description` varchar(64) DEFAULT NULL,
  `trade_group_id` int(10) unsigned DEFAULT NULL COMMENT 'Groups items in same trade',
  `is_draft_pick` tinyint(3) unsigned NOT NULL DEFAULT 0,
  `draft_pick_year` smallint(5) unsigned DEFAULT NULL,
  `source_file` varchar(128) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_record` (`season_year`,`transaction_month`,`transaction_day`,`transaction_type`,`pid`,`from_teamid`,`to_teamid`),
  KEY `idx_season` (`season_year`),
  KEY `idx_type` (`transaction_type`),
  KEY `idx_pid` (`pid`),
  KEY `idx_trade_group` (`trade_group_id`)
) ENGINE=InnoDB AUTO_INCREMENT=2011 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_league_config`
--

DROP TABLE IF EXISTS `ibl_league_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_league_config` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `season_ending_year` smallint(5) unsigned NOT NULL COMMENT 'Season ending year',
  `team_slot` tinyint(3) unsigned NOT NULL COMMENT 'Team position in conference bracket',
  `team_name` varchar(32) NOT NULL COMMENT 'Team name (FK to ibl_team_info)',
  `conference` varchar(16) NOT NULL COMMENT 'Conference name (Eastern/Western)',
  `division` varchar(16) NOT NULL COMMENT 'Division name',
  `playoff_qualifiers_per_conf` tinyint(3) unsigned NOT NULL COMMENT 'Playoff teams per conference',
  `playoff_round1_format` varchar(8) NOT NULL COMMENT 'Round 1 series format (e.g., bo7)',
  `playoff_round2_format` varchar(8) NOT NULL COMMENT 'Round 2 series format',
  `playoff_round3_format` varchar(8) NOT NULL COMMENT 'Round 3 series format',
  `playoff_round4_format` varchar(8) NOT NULL COMMENT 'Round 4 series format (finals)',
  `team_count` tinyint(3) unsigned NOT NULL COMMENT 'Total teams in league that season',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_season_team` (`season_ending_year`,`team_slot`),
  KEY `idx_season_year` (`season_ending_year`)
) ENGINE=InnoDB AUTO_INCREMENT=2086 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_box_scores`
--

DROP TABLE IF EXISTS `ibl_olympics_box_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_box_scores` (
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) DEFAULT '' COMMENT 'Player name',
  `pos` varchar(2) DEFAULT '' COMMENT 'Position played',
  `pid` int(11) DEFAULT NULL COMMENT 'Player ID (references ibl_plr)',
  `visitorTID` int(11) DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTID` int(11) DEFAULT NULL COMMENT 'Home team ID',
  `gameMIN` tinyint(3) unsigned DEFAULT NULL COMMENT 'Minutes played',
  `game2GM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` tinyint(3) unsigned DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` tinyint(3) unsigned DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` tinyint(3) unsigned DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` tinyint(3) unsigned DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` tinyint(3) unsigned DEFAULT NULL COMMENT 'Assists',
  `gameSTL` tinyint(3) unsigned DEFAULT NULL COMMENT 'Steals',
  `gameTOV` tinyint(3) unsigned DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` tinyint(3) unsigned DEFAULT NULL COMMENT 'Blocks',
  `gamePF` tinyint(3) unsigned DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL COMMENT 'Public API identifier',
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_date` (`Date`),
  KEY `idx_pid` (`pid`),
  KEY `idx_visitor_tid` (`visitorTID`),
  KEY `idx_home_tid` (`homeTID`),
  KEY `idx_date_pid` (`Date`,`pid`),
  KEY `idx_date_home_visitor` (`Date`,`homeTID`,`visitorTID`),
  CONSTRAINT `fk_olympics_boxscore_home` FOREIGN KEY (`homeTID`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscore_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscore_visitor` FOREIGN KEY (`visitorTID`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_olympics_box_minutes` CHECK (`gameMIN` is null or `gameMIN` >= 0 and `gameMIN` <= 70)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual player statistics for Olympics games';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_box_scores_teams`
--

DROP TABLE IF EXISTS `ibl_olympics_box_scores_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_box_scores_teams` (
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) DEFAULT '' COMMENT 'Arena/venue name',
  `gameOfThatDay` int(11) DEFAULT NULL COMMENT 'Game number for that date',
  `visitorTeamID` int(11) DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTeamID` int(11) DEFAULT NULL COMMENT 'Home team ID',
  `attendance` int(11) DEFAULT NULL COMMENT 'Game attendance',
  `capacity` int(11) DEFAULT NULL COMMENT 'Arena capacity',
  `visitorWins` int(11) DEFAULT NULL COMMENT 'Visitor team wins before game',
  `visitorLosses` int(11) DEFAULT NULL COMMENT 'Visitor team losses before game',
  `homeWins` int(11) DEFAULT NULL COMMENT 'Home team wins before game',
  `homeLosses` int(11) DEFAULT NULL COMMENT 'Home team losses before game',
  `visitorQ1points` int(11) DEFAULT NULL COMMENT 'Visitor Q1 points',
  `visitorQ2points` int(11) DEFAULT NULL COMMENT 'Visitor Q2 points',
  `visitorQ3points` int(11) DEFAULT NULL COMMENT 'Visitor Q3 points',
  `visitorQ4points` int(11) DEFAULT NULL COMMENT 'Visitor Q4 points',
  `visitorOTpoints` int(11) DEFAULT NULL COMMENT 'Visitor overtime points',
  `homeQ1points` int(11) DEFAULT NULL COMMENT 'Home Q1 points',
  `homeQ2points` int(11) DEFAULT NULL COMMENT 'Home Q2 points',
  `homeQ3points` int(11) DEFAULT NULL COMMENT 'Home Q3 points',
  `homeQ4points` int(11) DEFAULT NULL COMMENT 'Home Q4 points',
  `homeOTpoints` int(11) DEFAULT NULL COMMENT 'Home overtime points',
  `gameMIN` int(11) DEFAULT NULL COMMENT 'Total game minutes',
  `game2GM` int(11) DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` int(11) DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` int(11) DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` int(11) DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` int(11) DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` int(11) DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` int(11) DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` int(11) DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` int(11) DEFAULT NULL COMMENT 'Assists',
  `gameSTL` int(11) DEFAULT NULL COMMENT 'Steals',
  `gameTOV` int(11) DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` int(11) DEFAULT NULL COMMENT 'Blocks',
  `gamePF` int(11) DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor_team` (`visitorTeamID`),
  KEY `idx_home_team` (`homeTeamID`),
  CONSTRAINT `fk_olympics_boxscoreteam_home` FOREIGN KEY (`homeTeamID`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_olympics_boxscoreteam_visitor` FOREIGN KEY (`visitorTeamID`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Team-level statistics for Olympics games';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_career_avgs`
--

DROP TABLE IF EXISTS `ibl_olympics_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_career_avgs` (
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT 'Player ID (FK to ibl_plr)',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (denormalized)',
  `games` int(11) NOT NULL DEFAULT 0 COMMENT 'Olympic games played',
  `minutes` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg minutes per game',
  `fgm` decimal(8,2) NOT NULL COMMENT 'Avg field goals made',
  `fga` decimal(8,2) NOT NULL COMMENT 'Avg field goals attempted',
  `fgpct` decimal(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Field goal percentage',
  `ftm` decimal(8,2) NOT NULL COMMENT 'Avg free throws made',
  `fta` decimal(8,2) NOT NULL COMMENT 'Avg free throws attempted',
  `ftpct` decimal(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Free throw percentage',
  `tgm` decimal(8,2) NOT NULL COMMENT 'Avg three pointers made',
  `tga` decimal(8,2) NOT NULL COMMENT 'Avg three pointers attempted',
  `tpct` decimal(8,3) NOT NULL DEFAULT 0.000 COMMENT 'Three point percentage',
  `orb` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg offensive rebounds',
  `reb` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg total rebounds',
  `ast` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg assists',
  `stl` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg steals',
  `tvr` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg turnovers',
  `blk` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg blocks',
  `pf` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg personal fouls',
  `pts` decimal(8,2) NOT NULL DEFAULT 0.00 COMMENT 'Avg points',
  `retired` int(11) NOT NULL DEFAULT 0 COMMENT '1=retired from league',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_career_totals`
--

DROP TABLE IF EXISTS `ibl_olympics_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_career_totals` (
  `pid` int(11) NOT NULL DEFAULT 0 COMMENT 'Player ID (FK to ibl_plr)',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (denormalized)',
  `games` int(11) NOT NULL DEFAULT 0 COMMENT 'Total Olympic games played',
  `minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Total minutes played',
  `fgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total field goals made',
  `fga` int(11) NOT NULL DEFAULT 0 COMMENT 'Total field goals attempted',
  `ftm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total free throws made',
  `fta` int(11) NOT NULL DEFAULT 0 COMMENT 'Total free throws attempted',
  `tgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Total three pointers made',
  `tga` int(11) NOT NULL DEFAULT 0 COMMENT 'Total three pointers attempted',
  `orb` int(11) NOT NULL DEFAULT 0 COMMENT 'Total offensive rebounds',
  `reb` int(11) NOT NULL DEFAULT 0 COMMENT 'Total rebounds',
  `ast` int(11) NOT NULL DEFAULT 0 COMMENT 'Total assists',
  `stl` int(11) NOT NULL DEFAULT 0 COMMENT 'Total steals',
  `tvr` int(11) NOT NULL DEFAULT 0 COMMENT 'Total turnovers',
  `blk` int(11) NOT NULL DEFAULT 0 COMMENT 'Total blocks',
  `pf` int(11) NOT NULL DEFAULT 0 COMMENT 'Total personal fouls',
  `pts` int(11) NOT NULL DEFAULT 0 COMMENT 'Total points',
  `retired` int(11) NOT NULL DEFAULT 0 COMMENT '1=retired from league',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_power`
--

DROP TABLE IF EXISTS `ibl_olympics_power`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_power` (
  `TeamID` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Team ID (FK to ibl_olympics_team_info)',
  `Team` varchar(20) NOT NULL DEFAULT '' COMMENT 'Team name (PK)',
  `Division` varchar(20) NOT NULL DEFAULT '' COMMENT 'Division/group name',
  `Conference` varchar(20) NOT NULL DEFAULT '' COMMENT 'Conference name',
  `ranking` decimal(6,1) NOT NULL DEFAULT 0.0 COMMENT 'Power ranking score (0.0-100.0)',
  `win` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Overall wins',
  `loss` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Overall losses',
  `gb` decimal(6,1) NOT NULL DEFAULT 0.0 COMMENT 'Games behind leader',
  `conf_win` int(11) NOT NULL COMMENT 'Conference wins',
  `conf_loss` int(11) NOT NULL COMMENT 'Conference losses',
  `div_win` int(11) NOT NULL COMMENT 'Division wins',
  `div_loss` int(11) NOT NULL COMMENT 'Division losses',
  `home_win` int(11) NOT NULL COMMENT 'Home wins',
  `home_loss` int(11) NOT NULL COMMENT 'Home losses',
  `road_win` int(11) NOT NULL COMMENT 'Road wins',
  `road_loss` int(11) NOT NULL COMMENT 'Road losses',
  `last_win` int(11) NOT NULL COMMENT 'Last 10 games wins',
  `last_loss` int(11) NOT NULL COMMENT 'Last 10 games losses',
  `streak_type` varchar(1) NOT NULL DEFAULT '' COMMENT 'W=winning, L=losing',
  `streak` int(11) NOT NULL COMMENT 'Current streak length',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`Team`),
  CONSTRAINT `ibl_olympics_power_chk_1` CHECK (`ranking` is null or `ranking` >= 0.0 and `ranking` <= 100.0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_schedule`
--

DROP TABLE IF EXISTS `ibl_olympics_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_schedule` (
  `Year` smallint(5) unsigned NOT NULL COMMENT 'Tournament year',
  `BoxID` int(11) NOT NULL DEFAULT 0 COMMENT 'Box score identifier',
  `Date` date NOT NULL COMMENT 'Game date',
  `Visitor` smallint(5) unsigned NOT NULL COMMENT 'Visiting team ID',
  `VScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  `Home` smallint(5) unsigned NOT NULL COMMENT 'Home team ID',
  `HScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Home score',
  `round` varchar(32) DEFAULT NULL COMMENT 'Tournament round (Group A, Quarterfinal, Semifinal, Gold Medal, Bronze Medal)',
  `SchedID` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL COMMENT 'Public API identifier',
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
  CONSTRAINT `chk_olympics_schedule_hscore` CHECK (`HScore` >= 0 and `HScore` <= 200),
  CONSTRAINT `chk_olympics_schedule_vscore` CHECK (`VScore` >= 0 and `VScore` <= 200)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Olympics game schedule with tournament round tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_standings`
--

DROP TABLE IF EXISTS `ibl_olympics_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_standings` (
  `tid` int(11) NOT NULL COMMENT 'Team ID - references ibl_olympics_team_info',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team name (denormalized)',
  `pct` float(4,3) unsigned DEFAULT NULL COMMENT 'Win percentage',
  `leagueRecord` varchar(5) DEFAULT '' COMMENT 'Overall W-L record',
  `conference` enum('Eastern','Western','') DEFAULT '' COMMENT 'Conference (if used)',
  `confRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Conference record',
  `confGB` decimal(3,1) DEFAULT NULL COMMENT 'Conference games back',
  `division` varchar(16) DEFAULT '' COMMENT 'Division (if used)',
  `divRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Division record',
  `divGB` decimal(3,1) DEFAULT NULL COMMENT 'Division games back',
  `homeRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Home game record',
  `awayRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Away game record',
  `gamesUnplayed` tinyint(3) unsigned DEFAULT NULL COMMENT 'Games remaining',
  `confWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Conference wins',
  `confLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Conference losses',
  `divWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Division wins',
  `divLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Division losses',
  `homeWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Home wins',
  `homeLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Home losses',
  `awayWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Away wins',
  `awayLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Away losses',
  `confMagicNumber` tinyint(4) DEFAULT NULL COMMENT 'Conference magic number',
  `divMagicNumber` tinyint(4) DEFAULT NULL COMMENT 'Division magic number',
  `clinchedConference` tinyint(1) DEFAULT NULL COMMENT 'Clinched conference flag',
  `clinchedDivision` tinyint(1) DEFAULT NULL COMMENT 'Clinched division flag',
  `clinchedPlayoffs` tinyint(1) DEFAULT NULL COMMENT 'Clinched playoffs flag',
  `group_name` varchar(32) DEFAULT NULL COMMENT 'Olympics group (A, B, C, etc.)',
  `medal` enum('gold','silver','bronze') DEFAULT NULL COMMENT 'Final tournament medal',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tid`),
  KEY `team_name` (`team_name`),
  KEY `idx_conference` (`conference`),
  KEY `idx_division` (`division`),
  KEY `idx_group` (`group_name`),
  KEY `idx_medal` (`medal`),
  CONSTRAINT `fk_olympics_standings_team` FOREIGN KEY (`tid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_olympics_standings_pct` CHECK (`pct` is null or `pct` >= 0.000 and `pct` <= 1.000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Olympics tournament standings and medal tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_stats`
--

DROP TABLE IF EXISTS `ibl_olympics_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL DEFAULT 0 COMMENT 'Olympic tournament year',
  `pos` char(2) NOT NULL DEFAULT '' COMMENT 'Player position',
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name (FK to ibl_plr)',
  `team` varchar(32) NOT NULL DEFAULT '' COMMENT 'National team represented',
  `games` int(11) NOT NULL DEFAULT 0 COMMENT 'Games played',
  `minutes` int(11) NOT NULL DEFAULT 0 COMMENT 'Total minutes played',
  `fgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Field goals made',
  `fga` int(11) NOT NULL DEFAULT 0 COMMENT 'Field goals attempted',
  `ftm` int(11) NOT NULL DEFAULT 0 COMMENT 'Free throws made',
  `fta` int(11) NOT NULL DEFAULT 0 COMMENT 'Free throws attempted',
  `tgm` int(11) NOT NULL DEFAULT 0 COMMENT 'Three pointers made',
  `tga` int(11) NOT NULL DEFAULT 0 COMMENT 'Three pointers attempted',
  `orb` int(11) NOT NULL DEFAULT 0 COMMENT 'Offensive rebounds',
  `reb` int(11) NOT NULL DEFAULT 0 COMMENT 'Total rebounds',
  `ast` int(11) NOT NULL DEFAULT 0 COMMENT 'Assists',
  `stl` int(11) NOT NULL DEFAULT 0 COMMENT 'Steals',
  `tvr` int(11) NOT NULL DEFAULT 0 COMMENT 'Turnovers',
  `blk` int(11) NOT NULL DEFAULT 0 COMMENT 'Blocks',
  `pf` int(11) NOT NULL DEFAULT 0 COMMENT 'Personal fouls',
  PRIMARY KEY (`id`),
  KEY `fk_olympics_stats_name` (`name`),
  CONSTRAINT `fk_olympics_stats_name` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_team_info`
--

DROP TABLE IF EXISTS `ibl_olympics_team_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_team_info` (
  `teamid` int(11) NOT NULL AUTO_INCREMENT,
  `team_city` varchar(24) NOT NULL DEFAULT '' COMMENT 'City/Country name',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team nickname',
  `color1` varchar(6) NOT NULL DEFAULT '' COMMENT 'Primary team color (hex)',
  `color2` varchar(6) NOT NULL DEFAULT '' COMMENT 'Secondary team color (hex)',
  `arena` varchar(255) NOT NULL DEFAULT '' COMMENT 'Home arena/venue',
  `owner_name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Team owner username',
  `owner_email` varchar(48) NOT NULL DEFAULT '' COMMENT 'Owner email address',
  `discordID` bigint(20) unsigned DEFAULT NULL COMMENT 'Discord user ID',
  `skype` varchar(16) NOT NULL DEFAULT '' COMMENT 'Skype username (legacy)',
  `aim` varchar(48) NOT NULL DEFAULT '' COMMENT 'AIM username (legacy)',
  `msn` varchar(48) NOT NULL DEFAULT '' COMMENT 'MSN username (legacy)',
  `formerly_known_as` varchar(255) DEFAULT NULL COMMENT 'Previous team names',
  `Contract_Wins` int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  `Contract_Losses` int(11) NOT NULL DEFAULT 0 COMMENT 'Contract performance tracking',
  `Contract_AvgW` int(11) NOT NULL DEFAULT 0 COMMENT 'Average wins per contract',
  `Contract_AvgL` int(11) NOT NULL DEFAULT 0 COMMENT 'Average losses per contract',
  `Contract_Coach` decimal(3,2) NOT NULL DEFAULT 0.00 COMMENT 'Coach rating',
  `chart` char(2) NOT NULL DEFAULT '' COMMENT 'Depth chart identifier',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) DEFAULT uuid(),
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `team_name` (`team_name`),
  KEY `idx_owner_email` (`owner_email`),
  KEY `idx_discordID` (`discordID`)
) ENGINE=InnoDB AUTO_INCREMENT=9 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Olympics team information and configuration';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_win_loss`
--

DROP TABLE IF EXISTS `ibl_olympics_win_loss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_win_loss` (
  `year` int(10) unsigned NOT NULL DEFAULT 0 COMMENT 'Olympics year',
  `currentname` varchar(16) NOT NULL DEFAULT '' COMMENT 'Current team name',
  `namethatyear` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team name during that Olympics',
  `wins` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Games won',
  `losses` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Games lost',
  `gold` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Gold medals won',
  `silver` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Silver medals won',
  `bronze` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Bronze medals won',
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_currentname` (`currentname`),
  KEY `idx_year_team` (`year`,`currentname`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Historical Olympics win/loss records and medal counts';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_one_on_one`
--

DROP TABLE IF EXISTS `ibl_one_on_one`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_one_on_one` (
  `gameid` int(11) NOT NULL DEFAULT 0 COMMENT 'Game identifier (PK)',
  `playbyplay` mediumtext NOT NULL COMMENT 'Full play-by-play text',
  `winner` varchar(32) NOT NULL DEFAULT '' COMMENT 'Winning player name',
  `loser` varchar(32) NOT NULL DEFAULT '' COMMENT 'Losing player name',
  `winscore` int(11) NOT NULL DEFAULT 0 COMMENT 'Winner final score',
  `lossscore` int(11) NOT NULL DEFAULT 0 COMMENT 'Loser final score',
  `owner` varchar(25) NOT NULL DEFAULT '' COMMENT 'GM who submitted the matchup',
  PRIMARY KEY (`gameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `ibl_playoff_career_avgs`
--

DROP TABLE IF EXISTS `ibl_playoff_career_avgs`;
/*!50001 DROP VIEW IF EXISTS `ibl_playoff_career_avgs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_playoff_career_avgs` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `fgpct`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `ftpct`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `tpct`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`,
 1 AS `retired`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_playoff_career_totals`
--

DROP TABLE IF EXISTS `ibl_playoff_career_totals`;
/*!50001 DROP VIEW IF EXISTS `ibl_playoff_career_totals`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_playoff_career_totals` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`,
 1 AS `retired`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_playoff_stats`
--

DROP TABLE IF EXISTS `ibl_playoff_stats`;
/*!50001 DROP VIEW IF EXISTS `ibl_playoff_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_playoff_stats` AS SELECT 
 1 AS `year`,
 1 AS `pos`,
 1 AS `pid`,
 1 AS `name`,
 1 AS `team`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ibl_plr`
--

DROP TABLE IF EXISTS `ibl_plr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_plr` (
  `ordinal` int(11) DEFAULT 0 COMMENT 'Roster sort order (0-800=rostered, 1000=waivers)',
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name',
  `nickname` varchar(64) DEFAULT '' COMMENT 'Player nickname',
  `age` tinyint(3) unsigned DEFAULT NULL COMMENT 'Player age in years',
  `peak` tinyint(3) unsigned DEFAULT NULL COMMENT 'Peak development age',
  `tid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (0 = free agent)',
  `teamname` varchar(16) DEFAULT '' COMMENT 'Team name (denormalized from ibl_team_info)',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') NOT NULL DEFAULT '' COMMENT 'Player position',
  `sta` tinyint(3) unsigned DEFAULT 0 COMMENT 'Stamina rating',
  `oo` tinyint(3) unsigned DEFAULT 0 COMMENT 'Outside offense rating',
  `od` tinyint(3) unsigned DEFAULT 0 COMMENT 'Outside defense rating',
  `do` tinyint(3) unsigned DEFAULT 0 COMMENT 'Drive offense rating',
  `dd` tinyint(3) unsigned DEFAULT 0 COMMENT 'Drive defense rating',
  `po` tinyint(3) unsigned DEFAULT 0 COMMENT 'Post offense rating',
  `pd` tinyint(3) unsigned DEFAULT 0 COMMENT 'Post defense rating',
  `to` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition offense rating',
  `td` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition defense rating',
  `Clutch` tinyint(4) DEFAULT NULL COMMENT 'Clutch performance rating',
  `Consistency` tinyint(4) DEFAULT NULL COMMENT 'Consistency rating',
  `PGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Point guard depth',
  `SGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Shooting guard depth',
  `SFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Small forward depth',
  `PFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Power forward depth',
  `CDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Center depth',
  `active` tinyint(1) DEFAULT NULL COMMENT 'On depth chart (1=yes, NOT retired status)',
  `dc_PGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC point guard depth',
  `dc_SGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC shooting guard depth',
  `dc_SFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC small forward depth',
  `dc_PFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC power forward depth',
  `dc_CDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC center depth',
  `dc_active` tinyint(3) unsigned DEFAULT 1 COMMENT 'DC active flag',
  `dc_minutes` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC minutes',
  `dc_of` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC offensive focus',
  `dc_df` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC defensive focus',
  `dc_oi` tinyint(3) DEFAULT 0 COMMENT 'DC offensive importance',
  `dc_di` tinyint(3) DEFAULT 0 COMMENT 'DC defensive importance',
  `dc_bh` tinyint(3) DEFAULT 0 COMMENT 'DC ball handling',
  `stats_gs` smallint(5) unsigned DEFAULT 0 COMMENT 'Games started',
  `stats_gm` smallint(5) unsigned DEFAULT 0 COMMENT 'Games played',
  `stats_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Total minutes played',
  `stats_fgm` smallint(5) unsigned DEFAULT 0 COMMENT 'Field goals made',
  `stats_fga` smallint(5) unsigned DEFAULT 0 COMMENT 'Field goals attempted',
  `stats_ftm` smallint(5) unsigned DEFAULT 0 COMMENT 'Free throws made',
  `stats_fta` smallint(5) unsigned DEFAULT 0 COMMENT 'Free throws attempted',
  `stats_3gm` smallint(5) unsigned DEFAULT 0 COMMENT 'Three pointers made',
  `stats_3ga` smallint(5) unsigned DEFAULT 0 COMMENT 'Three pointers attempted',
  `stats_orb` smallint(5) unsigned DEFAULT 0 COMMENT 'Offensive rebounds',
  `stats_drb` smallint(5) unsigned DEFAULT 0 COMMENT 'Defensive rebounds',
  `stats_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Assists',
  `stats_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Steals',
  `stats_to` smallint(5) unsigned DEFAULT 0 COMMENT 'Turnovers',
  `stats_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Blocks',
  `stats_pf` smallint(5) unsigned DEFAULT 0 COMMENT 'Personal fouls',
  `talent` tinyint(3) unsigned DEFAULT 0 COMMENT 'Overall talent rating',
  `skill` tinyint(3) unsigned DEFAULT 0 COMMENT 'Skill rating',
  `intangibles` tinyint(3) unsigned DEFAULT 0 COMMENT 'Intangibles rating',
  `coach` tinyint(3) unsigned DEFAULT 0 COMMENT 'FA pref: playoff performance weight (currently unused)',
  `loyalty` tinyint(4) DEFAULT NULL COMMENT 'FA pref: team loyalty weight',
  `playingTime` tinyint(4) DEFAULT NULL COMMENT 'FA pref: playing time weight',
  `winner` tinyint(4) DEFAULT NULL COMMENT 'FA pref: winning culture weight',
  `tradition` tinyint(4) DEFAULT NULL COMMENT 'FA pref: franchise tradition weight',
  `security` tinyint(4) DEFAULT NULL COMMENT 'FA pref: contract security weight',
  `exp` tinyint(3) unsigned DEFAULT 0 COMMENT 'Years of experience',
  `bird` tinyint(1) DEFAULT NULL COMMENT 'Consecutive years with team (Bird Rights)',
  `cy` tinyint(3) unsigned DEFAULT 0 COMMENT 'Current contract year (0=unsigned, 1-6)',
  `cyt` tinyint(3) unsigned DEFAULT 0 COMMENT 'Contract total years (1-6)',
  `cy1` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 1 (thousands, negative=cash from other team)',
  `cy2` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 2 (thousands, negative=cash from other team)',
  `cy3` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 3 (thousands, negative=cash from other team)',
  `cy4` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 4 (thousands, negative=cash from other team)',
  `cy5` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 5 (thousands, negative=cash from other team)',
  `cy6` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 6 (thousands, negative=cash from other team)',
  `sh_pts` smallint(5) unsigned DEFAULT 0 COMMENT 'Season high points',
  `sh_reb` smallint(5) unsigned DEFAULT 0 COMMENT 'Season high rebounds',
  `sh_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Season high assists',
  `sh_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Season high steals',
  `sh_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Season high blocks',
  `s_dd` smallint(5) unsigned DEFAULT 0 COMMENT 'Season double doubles',
  `s_td` smallint(5) unsigned DEFAULT 0 COMMENT 'Season triple doubles',
  `sp_pts` smallint(5) unsigned DEFAULT 0 COMMENT 'Playoff high points',
  `sp_reb` smallint(5) unsigned DEFAULT 0 COMMENT 'Playoff high rebounds',
  `sp_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Playoff high assists',
  `sp_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Playoff high steals',
  `sp_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Playoff high blocks',
  `ch_pts` smallint(5) unsigned DEFAULT 0 COMMENT 'Career high points',
  `ch_reb` smallint(5) unsigned DEFAULT 0 COMMENT 'Career high rebounds',
  `ch_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Career high assists',
  `ch_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Career high steals',
  `ch_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Career high blocks',
  `c_dd` smallint(5) unsigned DEFAULT 0 COMMENT 'Career double doubles',
  `c_td` smallint(5) unsigned DEFAULT 0 COMMENT 'Career triple doubles',
  `cp_pts` smallint(5) unsigned DEFAULT 0 COMMENT 'Career playoff high points',
  `cp_reb` smallint(5) unsigned DEFAULT 0 COMMENT 'Career playoff high rebounds',
  `cp_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Career playoff high assists',
  `cp_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Career playoff high steals',
  `cp_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Career playoff high blocks',
  `car_gm` smallint(5) unsigned DEFAULT 0 COMMENT 'Career games',
  `car_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career minutes',
  `car_fgm` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career FGM',
  `car_fga` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career FGA',
  `car_ftm` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career FTM',
  `car_fta` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career FTA',
  `car_tgm` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PM',
  `car_tga` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career 3PA',
  `car_orb` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career ORB',
  `car_drb` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career DRB',
  `car_reb` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career total rebounds',
  `car_ast` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career assists',
  `car_stl` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career steals',
  `car_to` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career turnovers',
  `car_blk` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career blocks',
  `car_pf` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career fouls',
  `car_pts` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career points',
  `r_fga` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating FGA',
  `r_fgp` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating FG%',
  `r_fta` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating FTA',
  `r_ftp` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating FT%',
  `r_tga` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating 3PA',
  `r_tgp` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating 3P%',
  `r_orb` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating ORB',
  `r_drb` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating DRB',
  `r_ast` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating AST',
  `r_stl` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating STL',
  `r_to` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating TO',
  `r_blk` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating BLK',
  `r_foul` smallint(5) unsigned DEFAULT 0 COMMENT 'Rating fouls',
  `draftround` tinyint(3) unsigned DEFAULT 0 COMMENT 'Draft round (1-7)',
  `draftedby` varchar(16) DEFAULT '' COMMENT 'Original drafting team name',
  `draftedbycurrentname` varchar(16) DEFAULT '' COMMENT 'Drafting team current name',
  `draftyear` smallint(5) unsigned DEFAULT 0 COMMENT 'Draft year',
  `draftpickno` tinyint(3) unsigned DEFAULT 0 COMMENT 'Pick number in round',
  `injured` tinyint(3) unsigned DEFAULT NULL COMMENT '1=currently injured',
  `htft` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Height feet',
  `htin` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Height inches',
  `wt` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Weight in pounds',
  `retired` tinyint(1) DEFAULT NULL COMMENT '1=retired from league',
  `college` varchar(40) DEFAULT '' COMMENT 'College or amateur team',
  `car_playoff_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career playoff minutes',
  `car_preseason_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career preseason minutes',
  `droptime` int(11) DEFAULT 0 COMMENT 'Unix timestamp when placed on waivers (0=not on waivers)',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`pid`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `name` (`name`),
  KEY `teamname` (`teamname`),
  KEY `idx_tid` (`tid`),
  KEY `idx_active` (`active`),
  KEY `idx_retired` (`retired`),
  KEY `idx_tid_active` (`tid`,`active`),
  KEY `idx_pos` (`pos`),
  KEY `idx_draftyear` (`draftyear`),
  KEY `idx_draftround` (`draftround`),
  KEY `idx_tid_pos_active` (`tid`,`pos`,`active`),
  CONSTRAINT `fk_plr_team` FOREIGN KEY (`tid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_plr_cy` CHECK (`cy` >= 0 and `cy` <= 6),
  CONSTRAINT `chk_plr_cyt` CHECK (`cyt` >= 0 and `cyt` <= 6),
  CONSTRAINT `chk_plr_cy1` CHECK (`cy1` >= -7000 and `cy1` <= 7000),
  CONSTRAINT `chk_plr_cy2` CHECK (`cy2` >= -7000 and `cy2` <= 7000),
  CONSTRAINT `chk_plr_cy3` CHECK (`cy3` >= -7000 and `cy3` <= 7000),
  CONSTRAINT `chk_plr_cy4` CHECK (`cy4` >= -7000 and `cy4` <= 7000),
  CONSTRAINT `chk_plr_cy5` CHECK (`cy5` >= -7000 and `cy5` <= 7000),
  CONSTRAINT `chk_plr_cy6` CHECK (`cy6` >= -7000 and `cy6` <= 7000)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`iblhoops_chibul`@`71.145.211.164`*/ /*!50003 TRIGGER `ibl_plr_before_insert_uuid` BEFORE INSERT ON `ibl_plr` FOR EACH ROW BEGIN
  IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
    SET NEW.uuid = UUID();
  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `ibl_power`
--

DROP TABLE IF EXISTS `ibl_power`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_power` (
  `TeamID` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Team ID (PK, FK to ibl_team_info)',
  `ranking` decimal(6,1) NOT NULL DEFAULT 0.0 COMMENT 'Power ranking score (0.0-100.0)',
  `last_win` int(11) NOT NULL COMMENT 'Last 10 games wins',
  `last_loss` int(11) NOT NULL COMMENT 'Last 10 games losses',
  `streak_type` varchar(1) NOT NULL DEFAULT '' COMMENT 'W=winning, L=losing',
  `streak` int(11) NOT NULL COMMENT 'Current streak length',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `sos` decimal(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Strength of schedule',
  `remaining_sos` decimal(4,3) NOT NULL DEFAULT 0.000 COMMENT 'Remaining strength of schedule',
  `sos_rank` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'SOS league rank',
  `remaining_sos_rank` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Remaining SOS league rank',
  PRIMARY KEY (`TeamID`),
  CONSTRAINT `chk_power_ranking` CHECK (`ranking` is null or `ranking` >= 0.0 and `ranking` <= 100.0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_saved_depth_chart_players`
--

DROP TABLE IF EXISTS `ibl_saved_depth_chart_players`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_saved_depth_chart_players` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `depth_chart_id` int(10) unsigned NOT NULL COMMENT 'FK to ibl_saved_depth_charts.id',
  `pid` int(11) NOT NULL COMMENT 'Player ID at save time',
  `player_name` varchar(64) NOT NULL COMMENT 'Snapshot for historical display',
  `ordinal` int(11) NOT NULL DEFAULT 0 COMMENT 'Roster sort order at save time',
  `dc_PGDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Point guard depth setting',
  `dc_SGDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Shooting guard depth setting',
  `dc_SFDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Small forward depth setting',
  `dc_PFDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Power forward depth setting',
  `dc_CDepth` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Center depth setting',
  `dc_active` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT 'Active flag at save time',
  `dc_minutes` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Minutes setting at save time',
  `dc_of` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Offensive focus at save time',
  `dc_df` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Defensive focus at save time',
  `dc_oi` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Offensive importance at save time',
  `dc_di` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Defensive importance at save time',
  `dc_bh` tinyint(4) NOT NULL DEFAULT 0 COMMENT 'Ball handling at save time',
  PRIMARY KEY (`id`),
  KEY `idx_depth_chart_id` (`depth_chart_id`),
  KEY `idx_pid` (`pid`),
  CONSTRAINT `fk_saved_dc_header` FOREIGN KEY (`depth_chart_id`) REFERENCES `ibl_saved_depth_charts` (`id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=718 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_saved_depth_charts`
--

DROP TABLE IF EXISTS `ibl_saved_depth_charts`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_saved_depth_charts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL COMMENT 'Team ID (FK to ibl_team_info)',
  `username` varchar(25) NOT NULL COMMENT 'GM username who saved',
  `name` varchar(100) DEFAULT NULL COMMENT 'User-assigned label',
  `phase` varchar(30) NOT NULL COMMENT 'Season phase at save time',
  `season_year` smallint(5) unsigned NOT NULL COMMENT 'Season ending year',
  `sim_start_date` date NOT NULL COMMENT 'Next sim start date when saved',
  `sim_end_date` date DEFAULT NULL COMMENT 'Extended as sims run',
  `sim_number_start` int(10) unsigned NOT NULL COMMENT 'Sim number when chart was saved',
  `sim_number_end` int(10) unsigned DEFAULT NULL COMMENT 'Latest sim number chart was active',
  `is_active` tinyint(3) unsigned NOT NULL DEFAULT 1 COMMENT '1=currently active depth chart',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tid_active` (`tid`,`is_active`),
  KEY `idx_tid_created` (`tid`,`created_at` DESC),
  KEY `idx_active` (`is_active`)
) ENGINE=InnoDB AUTO_INCREMENT=44 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_schedule`
--

DROP TABLE IF EXISTS `ibl_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_schedule` (
  `Year` smallint(5) unsigned NOT NULL COMMENT 'Season year',
  `BoxID` int(11) NOT NULL DEFAULT 0 COMMENT 'Link to box score data',
  `Date` date NOT NULL COMMENT 'Game date',
  `Visitor` int(11) NOT NULL DEFAULT 0 COMMENT 'Visiting team ID (FK to ibl_team_info)',
  `VScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  `Home` int(11) NOT NULL DEFAULT 0 COMMENT 'Home team ID (FK to ibl_team_info)',
  `HScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Home score',
  `SchedID` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`SchedID`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `BoxID` (`BoxID`),
  KEY `idx_year` (`Year`),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor` (`Visitor`),
  KEY `idx_home` (`Home`),
  KEY `idx_year_date` (`Year`,`Date`),
  KEY `idx_date_visitor_home` (`Date`,`Visitor`,`Home`),
  CONSTRAINT `fk_schedule_home` FOREIGN KEY (`Home`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_schedule_visitor` FOREIGN KEY (`Visitor`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_schedule_vscore` CHECK (`VScore` >= 0 and `VScore` <= 200),
  CONSTRAINT `chk_schedule_hscore` CHECK (`HScore` >= 0 and `HScore` <= 200)
) ENGINE=InnoDB AUTO_INCREMENT=1149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `ibl_season_career_avgs`
--

DROP TABLE IF EXISTS `ibl_season_career_avgs`;
/*!50001 DROP VIEW IF EXISTS `ibl_season_career_avgs`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_season_career_avgs` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `fgpct`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `ftpct`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `tpct`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`,
 1 AS `pts`,
 1 AS `retired`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ibl_settings`
--

DROP TABLE IF EXISTS `ibl_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_settings` (
  `name` varchar(128) NOT NULL COMMENT 'Setting key',
  `value` varchar(128) NOT NULL COMMENT 'Setting value',
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`iblhoops_chibul`@`71.145.211.164`*/ /*!50003 TRIGGER trg_season_rollover
AFTER UPDATE ON ibl_settings
FOR EACH ROW
BEGIN
  DECLARE v_new_ending_year    SMALLINT UNSIGNED;
  DECLARE v_new_beginning_year SMALLINT UNSIGNED;

  IF NEW.name = 'Current Season Ending Year' AND OLD.value <> NEW.value THEN

    SET v_new_ending_year    = CAST(NEW.value AS UNSIGNED);
    SET v_new_beginning_year = v_new_ending_year - 1;

    INSERT IGNORE INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    SELECT teamid, v_new_beginning_year, v_new_ending_year, team_city, team_name
      FROM ibl_team_info;

  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `ibl_sim_dates`
--

DROP TABLE IF EXISTS `ibl_sim_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_sim_dates` (
  `Sim` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Start Date` date DEFAULT NULL COMMENT 'First date in sim range',
  `End Date` date DEFAULT NULL COMMENT 'Last date in sim range',
  PRIMARY KEY (`Sim`)
) ENGINE=InnoDB AUTO_INCREMENT=690 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_standings`
--

DROP TABLE IF EXISTS `ibl_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_standings` (
  `tid` int(11) NOT NULL COMMENT 'Team ID (PK, FK to ibl_team_info)',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team name (denormalized)',
  `pct` float(4,3) unsigned DEFAULT NULL COMMENT 'Winning percentage (0.000-1.000)',
  `leagueRecord` varchar(5) DEFAULT '' COMMENT 'Overall W-L as string',
  `wins` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Total wins',
  `losses` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Total losses',
  `conference` enum('Eastern','Western','') DEFAULT '' COMMENT 'Conference affiliation',
  `confRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Conference W-L as string',
  `confGB` decimal(3,1) DEFAULT NULL COMMENT 'Games behind conference leader',
  `division` varchar(16) DEFAULT '' COMMENT 'Division name',
  `divRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Division W-L as string',
  `divGB` decimal(3,1) DEFAULT NULL COMMENT 'Games behind division leader',
  `homeRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Home W-L as string',
  `awayRecord` varchar(5) NOT NULL DEFAULT '' COMMENT 'Away W-L as string',
  `gamesUnplayed` tinyint(3) unsigned DEFAULT NULL COMMENT 'Games remaining',
  `confWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Conference wins',
  `confLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Conference losses',
  `divWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Division wins',
  `divLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Division losses',
  `homeWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Home wins',
  `homeLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Home losses',
  `awayWins` tinyint(3) unsigned DEFAULT NULL COMMENT 'Away wins',
  `awayLosses` tinyint(3) unsigned DEFAULT NULL COMMENT 'Away losses',
  `confMagicNumber` tinyint(4) DEFAULT NULL COMMENT 'Conf magic number',
  `divMagicNumber` tinyint(4) DEFAULT NULL COMMENT 'Div magic number',
  `clinchedConference` tinyint(1) DEFAULT NULL COMMENT '1=clinched conference seed',
  `clinchedDivision` tinyint(1) DEFAULT NULL COMMENT '1=clinched division title',
  `clinchedPlayoffs` tinyint(1) DEFAULT NULL COMMENT '1=clinched playoff berth',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`tid`),
  KEY `team_name` (`team_name`),
  KEY `idx_conference` (`conference`),
  KEY `idx_division` (`division`),
  CONSTRAINT `fk_standings_team` FOREIGN KEY (`tid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_standings_pct` CHECK (`pct` is null or `pct` >= 0.000 and `pct` <= 1.000),
  CONSTRAINT `chk_standings_games_unplayed` CHECK (`gamesUnplayed` is null or `gamesUnplayed` >= 0 and `gamesUnplayed` <= 82),
  CONSTRAINT `chk_standings_conf_wins` CHECK (`confWins` is null or `confWins` <= 82),
  CONSTRAINT `chk_standings_conf_losses` CHECK (`confLosses` is null or `confLosses` <= 82),
  CONSTRAINT `chk_standings_home_wins` CHECK (`homeWins` is null or `homeWins` <= 41),
  CONSTRAINT `chk_standings_home_losses` CHECK (`homeLosses` is null or `homeLosses` <= 41),
  CONSTRAINT `chk_standings_away_wins` CHECK (`awayWins` is null or `awayWins` <= 41),
  CONSTRAINT `chk_standings_away_losses` CHECK (`awayLosses` is null or `awayLosses` <= 41)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_awards`
--

DROP TABLE IF EXISTS `ibl_team_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_awards` (
  `year` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Season year of award',
  `name` varchar(35) NOT NULL COMMENT 'Team name',
  `Award` varchar(350) NOT NULL COMMENT 'Award description',
  `ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_award` (`Award`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `ibl_team_defense_stats`
--

DROP TABLE IF EXISTS `ibl_team_defense_stats`;
/*!50001 DROP VIEW IF EXISTS `ibl_team_defense_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_team_defense_stats` AS SELECT 
 1 AS `teamID`,
 1 AS `name`,
 1 AS `season_year`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ibl_team_info`
--

DROP TABLE IF EXISTS `ibl_team_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_info` (
  `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (PK)',
  `team_city` varchar(24) NOT NULL DEFAULT '' COMMENT 'Franchise city',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Franchise name',
  `color1` varchar(6) NOT NULL DEFAULT '' COMMENT 'Primary hex color (no #)',
  `color2` varchar(6) NOT NULL DEFAULT '' COMMENT 'Secondary hex color (no #)',
  `arena` varchar(255) NOT NULL DEFAULT '' COMMENT 'Home arena name',
  `capacity` int(11) NOT NULL DEFAULT 0 COMMENT 'Arena seating capacity',
  `owner_name` varchar(32) NOT NULL DEFAULT '' COMMENT 'GM display name',
  `owner_email` varchar(48) NOT NULL DEFAULT '' COMMENT 'GM email address',
  `discordID` bigint(20) unsigned DEFAULT NULL COMMENT 'GM Discord user ID',
  `Contract_Wins` int(11) NOT NULL DEFAULT 0 COMMENT 'Wins from last season for FA Play for Winner weight',
  `Contract_Losses` int(11) NOT NULL DEFAULT 0 COMMENT 'Losses from last season for FA Play for Winner weight',
  `Contract_AvgW` int(11) NOT NULL DEFAULT 0 COMMENT 'Avg wins from last five seasons for FA Tradition weight',
  `Contract_AvgL` int(11) NOT NULL DEFAULT 0 COMMENT 'Avg losses from last five seasons for FA Tradition weight',
  `Used_Extension_This_Chunk` int(11) NOT NULL DEFAULT 0 COMMENT '1=used extension in current sim chunk',
  `Used_Extension_This_Season` int(11) DEFAULT 0 COMMENT '1=used extension this season',
  `HasMLE` int(11) NOT NULL DEFAULT 0 COMMENT '1=Mid-Level Exception already used',
  `HasLLE` int(11) NOT NULL DEFAULT 0 COMMENT '1=Lower-Level Exception already used',
  `chart` char(2) NOT NULL DEFAULT '' COMMENT 'Depth chart format code',
  `depth` varchar(100) NOT NULL DEFAULT '' COMMENT 'Depth chart submission status',
  `sim_depth` varchar(100) NOT NULL DEFAULT 'No Depth Chart' COMMENT 'Depth chart status at last sim',
  `asg_vote` varchar(100) NOT NULL DEFAULT 'No Vote' COMMENT 'All-Star voting status',
  `eoy_vote` varchar(100) NOT NULL DEFAULT 'No Vote' COMMENT 'End-of-year voting status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `team_name` (`team_name`),
  KEY `idx_owner_email` (`owner_email`),
  KEY `idx_discordID` (`discordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`iblhoops_chibul`@`71.145.211.164`*/ /*!50003 TRIGGER trg_team_identity_sync
AFTER UPDATE ON ibl_team_info
FOR EACH ROW
BEGIN
  DECLARE v_ending_year    SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;

  IF OLD.team_city <> NEW.team_city OR OLD.team_name <> NEW.team_name THEN

    SELECT CAST(value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE name = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    INSERT INTO ibl_franchise_seasons
      (franchise_id, season_year, season_ending_year, team_city, team_name)
    VALUES
      (NEW.teamid, v_beginning_year, v_ending_year, NEW.team_city, NEW.team_name)
    ON DUPLICATE KEY UPDATE
      team_city = NEW.team_city,
      team_name = NEW.team_name;

  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Temporary table structure for view `ibl_team_offense_stats`
--

DROP TABLE IF EXISTS `ibl_team_offense_stats`;
/*!50001 DROP VIEW IF EXISTS `ibl_team_offense_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_team_offense_stats` AS SELECT 
 1 AS `teamID`,
 1 AS `name`,
 1 AS `season_year`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `tvr`,
 1 AS `blk`,
 1 AS `pf`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `ibl_team_win_loss`
--

DROP TABLE IF EXISTS `ibl_team_win_loss`;
/*!50001 DROP VIEW IF EXISTS `ibl_team_win_loss`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `ibl_team_win_loss` AS SELECT 
 1 AS `year`,
 1 AS `currentname`,
 1 AS `namethatyear`,
 1 AS `wins`,
 1 AS `losses`*/;
SET character_set_client = @saved_cs_client;

--
-- Table structure for table `ibl_trade_cash`
--

DROP TABLE IF EXISTS `ibl_trade_cash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_cash` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tradeOfferID` int(11) NOT NULL COMMENT 'FK to ibl_trade_offers.id',
  `sendingTeam` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team sending cash',
  `receivingTeam` varchar(16) NOT NULL DEFAULT '' COMMENT 'Team receiving cash',
  `cy1` int(11) DEFAULT NULL COMMENT 'Cash amount year 1 (thousands)',
  `cy2` int(11) DEFAULT NULL COMMENT 'Cash amount year 2 (thousands)',
  `cy3` int(11) DEFAULT NULL COMMENT 'Cash amount year 3 (thousands)',
  `cy4` int(11) DEFAULT NULL COMMENT 'Cash amount year 4 (thousands)',
  `cy5` int(11) DEFAULT NULL COMMENT 'Cash amount year 5 (thousands)',
  `cy6` int(11) DEFAULT NULL COMMENT 'Cash amount year 6 (thousands)',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=26 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_info`
--

DROP TABLE IF EXISTS `ibl_trade_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_info` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tradeofferid` int(11) NOT NULL DEFAULT 0 COMMENT 'FK to ibl_trade_offers.id',
  `itemid` int(11) NOT NULL DEFAULT 0 COMMENT 'ID of traded item (player pid or draft pick id)',
  `itemtype` varchar(128) NOT NULL DEFAULT '' COMMENT 'Item category: 0=draft pick, 1=player, cash=cash',
  `from` varchar(128) NOT NULL DEFAULT '' COMMENT 'Sending team name',
  `to` varchar(128) NOT NULL DEFAULT '' COMMENT 'Receiving team name',
  `approval` varchar(128) NOT NULL DEFAULT '' COMMENT 'Team approval status',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  KEY `idx_tradeofferid` (`tradeofferid`),
  KEY `idx_from` (`from`),
  KEY `idx_to` (`to`)
) ENGINE=InnoDB AUTO_INCREMENT=147 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_offers`
--

DROP TABLE IF EXISTS `ibl_trade_offers`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12051 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_queue`
--

DROP TABLE IF EXISTS `ibl_trade_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_queue` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `query` text NOT NULL COMMENT 'SQL query to execute for trade processing',
  `tradeline` text DEFAULT NULL COMMENT 'Human-readable trade summary line',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_votes_ASG`
--

DROP TABLE IF EXISTS `ibl_votes_ASG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_votes_ASG` (
  `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Voting team ID (FK to ibl_team_info)',
  `team_city` varchar(24) NOT NULL DEFAULT '' COMMENT 'Voting team city (denormalized)',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Voting team name (denormalized)',
  `East_F1` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 1st pick',
  `East_F2` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 2nd pick',
  `East_F3` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 3rd pick',
  `East_F4` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 4th pick',
  `East_B1` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 1st pick',
  `East_B2` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 2nd pick',
  `East_B3` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 3rd pick',
  `East_B4` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 4th pick',
  `West_F1` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 1st pick',
  `West_F2` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 2nd pick',
  `West_F3` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 3rd pick',
  `West_F4` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 4th pick',
  `West_B1` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 1st pick',
  `West_B2` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 2nd pick',
  `West_B3` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 3rd pick',
  `West_B4` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 4th pick',
  KEY `fk_asg_votes_team` (`teamid`),
  CONSTRAINT `fk_asg_votes_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_votes_EOY`
--

DROP TABLE IF EXISTS `ibl_votes_EOY`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_votes_EOY` (
  `teamid` int(11) NOT NULL DEFAULT 0 COMMENT 'Voting team ID (FK to ibl_team_info)',
  `team_city` varchar(24) NOT NULL DEFAULT '' COMMENT 'Voting team city (denormalized)',
  `team_name` varchar(16) NOT NULL DEFAULT '' COMMENT 'Voting team name (denormalized)',
  `MVP_1` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 1st place',
  `MVP_2` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 2nd place',
  `MVP_3` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 3rd place',
  `Six_1` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 1st place',
  `Six_2` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 2nd place',
  `Six_3` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 3rd place',
  `ROY_1` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 1st place',
  `ROY_2` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 2nd place',
  `ROY_3` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 3rd place',
  `GM_1` varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 1st place',
  `GM_2` varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 2nd place',
  `GM_3` varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 3rd place',
  PRIMARY KEY (`teamid`),
  CONSTRAINT `fk_eoy_votes_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `total_jobs` int(11) NOT NULL,
  `pending_jobs` int(11) NOT NULL,
  `failed_jobs` int(11) NOT NULL,
  `failed_job_ids` longtext NOT NULL,
  `options` mediumtext DEFAULT NULL,
  `cancelled_at` int(11) DEFAULT NULL,
  `created_at` int(11) NOT NULL,
  `finished_at` int(11) DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `jobs` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) NOT NULL,
  `payload` longtext NOT NULL,
  `attempts` tinyint(3) unsigned NOT NULL,
  `reserved_at` int(10) unsigned DEFAULT NULL,
  `available_at` int(10) unsigned NOT NULL,
  `created_at` int(10) unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `migrations` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) NOT NULL,
  `batch` int(11) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_antiflood`
--

DROP TABLE IF EXISTS `nuke_antiflood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_antiflood` (
  `ip_addr` varchar(48) NOT NULL DEFAULT '',
  `time` varchar(14) NOT NULL DEFAULT '',
  KEY `ip_addr` (`ip_addr`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_authors`
--

DROP TABLE IF EXISTS `nuke_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_authors` (
  `aid` varchar(25) NOT NULL DEFAULT '',
  `name` varchar(50) DEFAULT NULL,
  `url` varchar(255) NOT NULL DEFAULT '',
  `email` varchar(255) NOT NULL DEFAULT '',
  `pwd` varchar(40) DEFAULT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `radminsuper` tinyint(1) NOT NULL DEFAULT 1,
  `admlanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_autonews`
--

DROP TABLE IF EXISTS `nuke_autonews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_autonews` (
  `anid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `aid` varchar(30) NOT NULL DEFAULT '',
  `title` varchar(80) NOT NULL DEFAULT '',
  `time` varchar(19) NOT NULL DEFAULT '',
  `hometext` mediumtext NOT NULL,
  `bodytext` mediumtext NOT NULL,
  `topic` int(11) NOT NULL DEFAULT 1,
  `informant` varchar(20) NOT NULL DEFAULT '',
  `notes` mediumtext NOT NULL,
  `ihome` int(11) NOT NULL DEFAULT 0,
  `alanguage` varchar(30) NOT NULL DEFAULT '',
  `acomm` int(11) NOT NULL DEFAULT 0,
  `associated` mediumtext NOT NULL,
  PRIMARY KEY (`anid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banned_ip`
--

DROP TABLE IF EXISTS `nuke_banned_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banned_ip` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(15) NOT NULL DEFAULT '',
  `reason` varchar(255) NOT NULL DEFAULT '',
  `date` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_blocks`
--

DROP TABLE IF EXISTS `nuke_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_blocks` (
  `bid` int(11) NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) NOT NULL DEFAULT '',
  `title` varchar(60) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `url` varchar(200) NOT NULL DEFAULT '',
  `bposition` char(1) NOT NULL DEFAULT '',
  `weight` int(11) NOT NULL DEFAULT 1,
  `active` int(11) NOT NULL DEFAULT 1,
  `refresh` int(11) NOT NULL DEFAULT 0,
  `time` varchar(14) NOT NULL DEFAULT '0',
  `blanguage` varchar(30) NOT NULL DEFAULT '',
  `blockfile` varchar(255) NOT NULL DEFAULT '',
  `view` int(11) NOT NULL DEFAULT 0,
  `expire` varchar(14) NOT NULL DEFAULT '0',
  `action` char(1) NOT NULL DEFAULT '',
  `subscription` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`bid`),
  KEY `title` (`title`)
) ENGINE=MyISAM AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_comments`
--

DROP TABLE IF EXISTS `nuke_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_comments` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `sid` int(11) NOT NULL DEFAULT 0,
  `date` datetime DEFAULT NULL,
  `name` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(60) DEFAULT NULL,
  `url` varchar(60) DEFAULT NULL,
  `host_name` varchar(60) DEFAULT NULL,
  `subject` varchar(85) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `score` tinyint(4) NOT NULL DEFAULT 0,
  `reason` tinyint(4) NOT NULL DEFAULT 0,
  `last_moderation_ip` varchar(15) DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `pid` (`pid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM AUTO_INCREMENT=62 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_config`
--

DROP TABLE IF EXISTS `nuke_config`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_config` (
  `sitename` varchar(255) NOT NULL DEFAULT '',
  `nukeurl` varchar(255) NOT NULL DEFAULT '',
  `site_logo` varchar(255) NOT NULL DEFAULT '',
  `slogan` varchar(255) NOT NULL DEFAULT '',
  `startdate` varchar(50) NOT NULL DEFAULT '',
  `adminmail` varchar(255) NOT NULL DEFAULT '',
  `anonpost` tinyint(1) NOT NULL DEFAULT 0,
  `Default_Theme` varchar(255) NOT NULL DEFAULT '',
  `overwrite_theme` tinyint(1) NOT NULL DEFAULT 1,
  `foot1` text NOT NULL,
  `foot2` text NOT NULL,
  `foot3` text NOT NULL,
  `commentlimit` int(11) NOT NULL DEFAULT 4096,
  `anonymous` varchar(255) NOT NULL DEFAULT '',
  `minpass` tinyint(1) NOT NULL DEFAULT 5,
  `pollcomm` tinyint(1) NOT NULL DEFAULT 1,
  `articlecomm` tinyint(1) NOT NULL DEFAULT 1,
  `broadcast_msg` tinyint(1) NOT NULL DEFAULT 1,
  `my_headlines` tinyint(1) NOT NULL DEFAULT 1,
  `top` int(11) NOT NULL DEFAULT 10,
  `storyhome` int(11) NOT NULL DEFAULT 10,
  `user_news` tinyint(1) NOT NULL DEFAULT 1,
  `oldnum` int(11) NOT NULL DEFAULT 30,
  `ultramode` tinyint(1) NOT NULL DEFAULT 0,
  `banners` tinyint(1) NOT NULL DEFAULT 1,
  `backend_title` varchar(255) NOT NULL DEFAULT '',
  `backend_language` varchar(10) NOT NULL DEFAULT '',
  `language` varchar(100) NOT NULL DEFAULT '',
  `locale` varchar(10) NOT NULL DEFAULT '',
  `multilingual` tinyint(1) NOT NULL DEFAULT 0,
  `useflags` tinyint(1) NOT NULL DEFAULT 0,
  `notify` tinyint(1) NOT NULL DEFAULT 0,
  `notify_email` varchar(255) NOT NULL DEFAULT '',
  `notify_subject` varchar(255) NOT NULL DEFAULT '',
  `notify_message` varchar(255) NOT NULL DEFAULT '',
  `notify_from` varchar(255) NOT NULL DEFAULT '',
  `moderate` tinyint(1) NOT NULL DEFAULT 0,
  `admingraphic` tinyint(1) NOT NULL DEFAULT 1,
  `httpref` tinyint(1) NOT NULL DEFAULT 1,
  `httprefmax` int(11) NOT NULL DEFAULT 1000,
  `httprefmode` tinyint(1) NOT NULL DEFAULT 1,
  `CensorMode` tinyint(1) NOT NULL DEFAULT 3,
  `CensorReplace` varchar(10) NOT NULL DEFAULT '',
  `copyright` text NOT NULL,
  `Version_Num` varchar(10) NOT NULL DEFAULT '',
  `gfx_chk` tinyint(1) NOT NULL DEFAULT 0,
  `nuke_editor` tinyint(1) NOT NULL DEFAULT 1,
  `display_errors` tinyint(1) NOT NULL DEFAULT 0,
  PRIMARY KEY (`sitename`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1 COLLATE=latin1_swedish_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_counter`
--

DROP TABLE IF EXISTS `nuke_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_counter` (
  `type` varchar(80) NOT NULL DEFAULT '',
  `var` varchar(80) NOT NULL DEFAULT '',
  `count` int(10) unsigned NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_groups`
--

DROP TABLE IF EXISTS `nuke_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_groups` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `points` int(11) NOT NULL DEFAULT 0,
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_groups_points`
--

DROP TABLE IF EXISTS `nuke_groups_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_groups_points` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `points` int(11) NOT NULL DEFAULT 0,
  KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_headlines`
--

DROP TABLE IF EXISTS `nuke_headlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_headlines` (
  `hid` int(11) NOT NULL AUTO_INCREMENT,
  `sitename` varchar(30) NOT NULL DEFAULT '',
  `headlinesurl` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`hid`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_categories`
--

DROP TABLE IF EXISTS `nuke_links_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_categories` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(50) NOT NULL DEFAULT '',
  `cdescription` mediumtext NOT NULL,
  `parentid` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_links`
--

DROP TABLE IF EXISTS `nuke_links_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_links` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `sid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `date` datetime DEFAULT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `hits` int(11) NOT NULL DEFAULT 0,
  `submitter` varchar(60) NOT NULL DEFAULT '',
  `linkratingsummary` double(6,4) NOT NULL DEFAULT 0.0000,
  `totalvotes` int(11) NOT NULL DEFAULT 0,
  `totalcomments` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_modrequest`
--

DROP TABLE IF EXISTS `nuke_links_modrequest`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_modrequest` (
  `requestid` int(11) NOT NULL AUTO_INCREMENT,
  `lid` int(11) NOT NULL DEFAULT 0,
  `cid` int(11) NOT NULL DEFAULT 0,
  `sid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `modifysubmitter` varchar(60) NOT NULL DEFAULT '',
  `brokenlink` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`requestid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_newlink`
--

DROP TABLE IF EXISTS `nuke_links_newlink`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_newlink` (
  `lid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `sid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(100) NOT NULL DEFAULT '',
  `url` varchar(100) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `name` varchar(100) NOT NULL DEFAULT '',
  `email` varchar(100) NOT NULL DEFAULT '',
  `submitter` varchar(60) NOT NULL DEFAULT '',
  PRIMARY KEY (`lid`),
  KEY `cid` (`cid`),
  KEY `sid` (`sid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_main`
--

DROP TABLE IF EXISTS `nuke_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_main` (
  `main_module` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_message`
--

DROP TABLE IF EXISTS `nuke_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_message` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(100) NOT NULL DEFAULT '',
  `content` mediumtext NOT NULL,
  `date` varchar(14) NOT NULL DEFAULT '',
  `expire` int(11) NOT NULL DEFAULT 0,
  `active` int(11) NOT NULL DEFAULT 1,
  `view` int(11) NOT NULL DEFAULT 1,
  `mlanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_modules`
--

DROP TABLE IF EXISTS `nuke_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_modules` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `custom_title` varchar(255) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 0,
  `view` int(11) NOT NULL DEFAULT 0,
  `inmenu` tinyint(1) NOT NULL DEFAULT 1,
  `mod_group` int(11) DEFAULT 0,
  `admins` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`),
  KEY `title` (`title`(250)),
  KEY `custom_title` (`custom_title`(250))
) ENGINE=MyISAM AUTO_INCREMENT=155 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_optimize_gain`
--

DROP TABLE IF EXISTS `nuke_optimize_gain`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_optimize_gain` (
  `gain` decimal(10,3) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pages`
--

DROP TABLE IF EXISTS `nuke_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_pages` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `title` varchar(255) NOT NULL DEFAULT '',
  `subtitle` varchar(255) NOT NULL DEFAULT '',
  `active` int(11) NOT NULL DEFAULT 0,
  `page_header` mediumtext NOT NULL,
  `text` mediumtext NOT NULL,
  `page_footer` mediumtext NOT NULL,
  `signature` mediumtext NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `counter` int(11) NOT NULL DEFAULT 0,
  `clanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`pid`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pages_categories`
--

DROP TABLE IF EXISTS `nuke_pages_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_pages_categories` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_poll_desc`
--

DROP TABLE IF EXISTS `nuke_poll_desc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_poll_desc` (
  `pollID` int(11) NOT NULL AUTO_INCREMENT,
  `pollTitle` varchar(100) NOT NULL DEFAULT '',
  `timeStamp` int(11) NOT NULL DEFAULT 0,
  `voters` mediumint(9) NOT NULL DEFAULT 0,
  `planguage` varchar(30) NOT NULL DEFAULT '',
  `artid` int(11) NOT NULL DEFAULT 0,
  `comments` int(11) DEFAULT 0,
  PRIMARY KEY (`pollID`)
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_queue`
--

DROP TABLE IF EXISTS `nuke_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_queue` (
  `qid` smallint(5) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(9) NOT NULL DEFAULT 0,
  `uname` varchar(40) NOT NULL DEFAULT '',
  `subject` varchar(100) NOT NULL DEFAULT '',
  `story` mediumtext DEFAULT NULL,
  `storyext` mediumtext NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `topic` varchar(20) NOT NULL DEFAULT '',
  `alanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`qid`),
  KEY `uid` (`uid`),
  KEY `uname` (`uname`)
) ENGINE=MyISAM AUTO_INCREMENT=80 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_referer`
--

DROP TABLE IF EXISTS `nuke_referer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_referer` (
  `rid` int(11) NOT NULL AUTO_INCREMENT,
  `url` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`rid`)
) ENGINE=MyISAM AUTO_INCREMENT=40467 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_related`
--

DROP TABLE IF EXISTS `nuke_related`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_related` (
  `rid` int(11) NOT NULL AUTO_INCREMENT,
  `tid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(30) NOT NULL DEFAULT '',
  `url` varchar(200) NOT NULL DEFAULT '',
  PRIMARY KEY (`rid`),
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_session`
--

DROP TABLE IF EXISTS `nuke_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_session` (
  `uname` varchar(25) NOT NULL DEFAULT '',
  `time` varchar(14) NOT NULL DEFAULT '',
  `host_addr` varchar(48) NOT NULL DEFAULT '',
  `guest` int(11) NOT NULL DEFAULT 0,
  KEY `time` (`time`),
  KEY `guest` (`guest`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_date`
--

DROP TABLE IF EXISTS `nuke_stats_date`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stats_date` (
  `year` smallint(6) NOT NULL DEFAULT 0,
  `month` tinyint(4) NOT NULL DEFAULT 0,
  `date` tinyint(4) NOT NULL DEFAULT 0,
  `hits` bigint(20) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_hour`
--

DROP TABLE IF EXISTS `nuke_stats_hour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stats_hour` (
  `year` smallint(6) NOT NULL DEFAULT 0,
  `month` tinyint(4) NOT NULL DEFAULT 0,
  `date` tinyint(4) NOT NULL DEFAULT 0,
  `hour` tinyint(4) NOT NULL DEFAULT 0,
  `hits` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_month`
--

DROP TABLE IF EXISTS `nuke_stats_month`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stats_month` (
  `year` smallint(6) NOT NULL DEFAULT 0,
  `month` tinyint(4) NOT NULL DEFAULT 0,
  `hits` bigint(20) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_year`
--

DROP TABLE IF EXISTS `nuke_stats_year`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stats_year` (
  `year` smallint(6) NOT NULL DEFAULT 0,
  `hits` bigint(20) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stories`
--

DROP TABLE IF EXISTS `nuke_stories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stories` (
  `sid` int(11) NOT NULL AUTO_INCREMENT,
  `catid` int(11) NOT NULL DEFAULT 0,
  `aid` varchar(30) NOT NULL DEFAULT '',
  `title` varchar(80) DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext DEFAULT NULL,
  `bodytext` mediumtext DEFAULT NULL,
  `comments` int(11) DEFAULT 0,
  `counter` mediumint(8) unsigned DEFAULT NULL,
  `topic` int(11) NOT NULL DEFAULT 1,
  `informant` varchar(20) NOT NULL DEFAULT '',
  `notes` mediumtext DEFAULT NULL,
  `ihome` int(11) NOT NULL DEFAULT 0,
  `alanguage` varchar(30) NOT NULL DEFAULT '',
  `acomm` int(11) NOT NULL DEFAULT 0,
  `haspoll` int(11) NOT NULL DEFAULT 0,
  `pollID` int(11) NOT NULL DEFAULT 0,
  `score` int(11) NOT NULL DEFAULT 0,
  `ratings` int(11) NOT NULL DEFAULT 0,
  `rating_ip` varchar(15) DEFAULT '0',
  `associated` mediumtext DEFAULT NULL,
  PRIMARY KEY (`sid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `topic` (`topic`)
) ENGINE=MyISAM AUTO_INCREMENT=4270 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stories_cat`
--

DROP TABLE IF EXISTS `nuke_stories_cat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_stories_cat` (
  `catid` int(11) NOT NULL AUTO_INCREMENT,
  `title` varchar(20) NOT NULL DEFAULT '',
  `counter` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`catid`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_subscriptions`
--

DROP TABLE IF EXISTS `nuke_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_subscriptions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `userid` int(11) DEFAULT 0,
  `subscription_expire` varchar(50) NOT NULL DEFAULT '',
  KEY `id` (`id`,`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_topics`
--

DROP TABLE IF EXISTS `nuke_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_topics` (
  `topicid` int(11) NOT NULL AUTO_INCREMENT,
  `topicname` varchar(20) DEFAULT NULL,
  `topicimage` varchar(100) NOT NULL DEFAULT '',
  `topictext` varchar(40) DEFAULT NULL,
  `counter` int(11) NOT NULL DEFAULT 0,
  `id` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `topicid` (`topicid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_users`
--

DROP TABLE IF EXISTS `nuke_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_users` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `date_started` varchar(4) NOT NULL DEFAULT '',
  `name` varchar(60) NOT NULL DEFAULT '',
  `username` varchar(25) NOT NULL DEFAULT '',
  `user_email` varchar(255) NOT NULL DEFAULT '',
  `user_ibl_team` varchar(32) NOT NULL DEFAULT '',
  `discordID` bigint(20) unsigned DEFAULT NULL,
  `femail` varchar(255) NOT NULL DEFAULT '',
  `user_website` varchar(255) NOT NULL DEFAULT '',
  `user_avatar` tinytext NOT NULL,
  `user_regdate` varchar(20) NOT NULL DEFAULT '',
  `user_icq` varchar(15) DEFAULT NULL,
  `user_occ` varchar(100) DEFAULT NULL,
  `user_from` varchar(100) DEFAULT NULL,
  `user_interests` varchar(150) NOT NULL DEFAULT '',
  `user_sig` varchar(255) DEFAULT NULL,
  `user_viewemail` tinyint(4) DEFAULT NULL,
  `user_theme` int(11) DEFAULT NULL,
  `user_aim` varchar(18) DEFAULT NULL,
  `user_yim` varchar(25) DEFAULT NULL,
  `user_msnm` varchar(25) DEFAULT NULL,
  `user_password` varchar(255) NOT NULL DEFAULT '',
  `storynum` tinyint(4) NOT NULL DEFAULT 10,
  `umode` varchar(10) NOT NULL DEFAULT '',
  `uorder` tinyint(1) NOT NULL DEFAULT 0,
  `thold` tinyint(1) NOT NULL DEFAULT 0,
  `noscore` tinyint(1) NOT NULL DEFAULT 0,
  `bio` text NOT NULL,
  `ublockon` tinyint(1) NOT NULL DEFAULT 0,
  `ublock` text NOT NULL,
  `theme` varchar(255) NOT NULL DEFAULT '',
  `commentmax` int(11) NOT NULL DEFAULT 4096,
  `counter` int(11) NOT NULL DEFAULT 0,
  `newsletter` int(11) NOT NULL DEFAULT 0,
  `user_posts` int(11) NOT NULL DEFAULT 0,
  `user_attachsig` int(11) NOT NULL DEFAULT 0,
  `user_rank` int(11) NOT NULL DEFAULT 0,
  `user_level` int(11) NOT NULL DEFAULT 1,
  `broadcast` tinyint(1) NOT NULL DEFAULT 1,
  `popmeson` tinyint(1) NOT NULL DEFAULT 0,
  `user_active` tinyint(1) DEFAULT 1,
  `user_session_time` int(11) NOT NULL DEFAULT 0,
  `user_session_page` smallint(6) NOT NULL DEFAULT 0,
  `user_lastvisit` int(11) NOT NULL DEFAULT 0,
  `last_ip` varchar(25) DEFAULT NULL,
  `user_timezone` tinyint(4) NOT NULL DEFAULT 10,
  `user_style` tinyint(4) DEFAULT NULL,
  `user_lang` varchar(255) NOT NULL DEFAULT 'english',
  `user_dateformat` varchar(14) NOT NULL DEFAULT 'D M d, Y g:i a',
  `user_emailtime` int(11) DEFAULT NULL,
  `user_allowhtml` tinyint(1) DEFAULT 1,
  `user_allowbbcode` tinyint(1) DEFAULT 1,
  `user_allowsmile` tinyint(1) DEFAULT 1,
  `user_allowavatar` tinyint(1) NOT NULL DEFAULT 1,
  `user_allow_viewonline` tinyint(1) NOT NULL DEFAULT 1,
  `user_notify` tinyint(1) NOT NULL DEFAULT 0,
  `user_popup_pm` tinyint(1) NOT NULL DEFAULT 0,
  `user_avatar_type` tinyint(4) NOT NULL DEFAULT 3,
  `user_sig_bbcode_uid` varchar(10) DEFAULT NULL,
  `user_actkey` varchar(32) DEFAULT NULL,
  `user_newpasswd` varchar(32) DEFAULT NULL,
  `points` int(11) DEFAULT 0,
  `karma` tinyint(1) DEFAULT 0,
  PRIMARY KEY (`user_id`),
  KEY `uname` (`username`),
  KEY `user_session_time` (`user_session_time`),
  KEY `karma` (`karma`),
  KEY `user_email` (`user_email`(250))
) ENGINE=MyISAM AUTO_INCREMENT=773 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;
/*!50003 SET @saved_cs_client      = @@character_set_client */ ;
/*!50003 SET @saved_cs_results     = @@character_set_results */ ;
/*!50003 SET @saved_col_connection = @@collation_connection */ ;
/*!50003 SET character_set_client  = utf8mb4 */ ;
/*!50003 SET character_set_results = utf8mb4 */ ;
/*!50003 SET collation_connection  = utf8mb4_general_ci */ ;
/*!50003 SET @saved_sql_mode       = @@sql_mode */ ;
/*!50003 SET sql_mode              = '' */ ;
DELIMITER ;;
/*!50003 CREATE*/ /*!50017 DEFINER=`iblhoops_chibul`@`71.145.211.164`*/ /*!50003 TRIGGER trg_gm_tenure_track
AFTER UPDATE ON nuke_users
FOR EACH ROW
BEGIN
  DECLARE v_ending_year   SMALLINT UNSIGNED;
  DECLARE v_beginning_year SMALLINT UNSIGNED;
  DECLARE v_phase         VARCHAR(128);
  DECLARE v_is_mid_season TINYINT(1);
  DECLARE v_old_franchise INT;
  DECLARE v_new_franchise INT;

  IF OLD.user_ibl_team <> NEW.user_ibl_team THEN

    -- Read current season context
    SELECT CAST(value AS UNSIGNED) INTO v_ending_year
      FROM ibl_settings
     WHERE name = 'Current Season Ending Year'
     LIMIT 1;

    SET v_beginning_year = v_ending_year - 1;

    SELECT value INTO v_phase
      FROM ibl_settings
     WHERE name = 'Current Season Phase'
     LIMIT 1;

    SET v_is_mid_season = (v_phase IN ('Regular Season', 'Playoffs', 'HEAT'));

    -- Close the old tenure (if the user was on a real team)
    IF OLD.user_ibl_team <> '' THEN
      SELECT teamid INTO v_old_franchise
        FROM ibl_team_info
       WHERE team_name = OLD.user_ibl_team
       LIMIT 1;

      IF v_old_franchise IS NOT NULL THEN
        UPDATE ibl_gm_tenures
           SET end_season_year   = v_beginning_year,
               is_mid_season_end = v_is_mid_season
         WHERE franchise_id   = v_old_franchise
           AND gm_username    = OLD.username
           AND end_season_year IS NULL;
      END IF;
    END IF;

    -- Open a new tenure (if the user is assigned to a real team)
    IF NEW.user_ibl_team <> '' THEN
      SELECT teamid INTO v_new_franchise
        FROM ibl_team_info
       WHERE team_name = NEW.user_ibl_team
       LIMIT 1;

      IF v_new_franchise IS NOT NULL THEN
        INSERT INTO ibl_gm_tenures
          (franchise_id, gm_username, start_season_year, is_mid_season_start)
        VALUES
          (v_new_franchise, NEW.username, v_beginning_year, v_is_mid_season);
      END IF;
    END IF;

  END IF;
END */;;
DELIMITER ;
/*!50003 SET sql_mode              = @saved_sql_mode */ ;
/*!50003 SET character_set_client  = @saved_cs_client */ ;
/*!50003 SET character_set_results = @saved_cs_results */ ;
/*!50003 SET collation_connection  = @saved_col_connection */ ;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) NOT NULL,
  `token` varchar(255) NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary table structure for view `vw_career_totals`
--

DROP TABLE IF EXISTS `vw_career_totals`;
/*!50001 DROP VIEW IF EXISTS `vw_career_totals`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_career_totals` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `seasons`,
 1 AS `games`,
 1 AS `minutes`,
 1 AS `fgm`,
 1 AS `fga`,
 1 AS `ftm`,
 1 AS `fta`,
 1 AS `tgm`,
 1 AS `tga`,
 1 AS `orb`,
 1 AS `reb`,
 1 AS `ast`,
 1 AS `stl`,
 1 AS `blk`,
 1 AS `tvr`,
 1 AS `pf`,
 1 AS `pts`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_current_salary`
--

DROP TABLE IF EXISTS `vw_current_salary`;
/*!50001 DROP VIEW IF EXISTS `vw_current_salary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_current_salary` AS SELECT 
 1 AS `pid`,
 1 AS `name`,
 1 AS `tid`,
 1 AS `teamname`,
 1 AS `pos`,
 1 AS `cy`,
 1 AS `cyt`,
 1 AS `cy1`,
 1 AS `cy2`,
 1 AS `cy3`,
 1 AS `cy4`,
 1 AS `cy5`,
 1 AS `cy6`,
 1 AS `current_salary`,
 1 AS `next_year_salary`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_franchise_summary`
--

DROP TABLE IF EXISTS `vw_franchise_summary`;
/*!50001 DROP VIEW IF EXISTS `vw_franchise_summary`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_franchise_summary` AS SELECT 
 1 AS `teamid`,
 1 AS `totwins`,
 1 AS `totloss`,
 1 AS `winpct`,
 1 AS `playoffs`,
 1 AS `div_titles`,
 1 AS `conf_titles`,
 1 AS `ibl_titles`,
 1 AS `heat_titles`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_free_agency_offers`
--

DROP TABLE IF EXISTS `vw_free_agency_offers`;
/*!50001 DROP VIEW IF EXISTS `vw_free_agency_offers`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_free_agency_offers` AS SELECT 
 1 AS `offer_id`,
 1 AS `player_uuid`,
 1 AS `pid`,
 1 AS `player_name`,
 1 AS `position`,
 1 AS `age`,
 1 AS `team_uuid`,
 1 AS `teamid`,
 1 AS `team_city`,
 1 AS `team_name`,
 1 AS `full_team_name`,
 1 AS `year1_amount`,
 1 AS `year2_amount`,
 1 AS `year3_amount`,
 1 AS `year4_amount`,
 1 AS `year5_amount`,
 1 AS `year6_amount`,
 1 AS `total_contract_value`,
 1 AS `modifier`,
 1 AS `random`,
 1 AS `perceived_value`,
 1 AS `is_mle`,
 1 AS `is_lle`,
 1 AS `created_at`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_player_career_stats`
--

DROP TABLE IF EXISTS `vw_player_career_stats`;
/*!50001 DROP VIEW IF EXISTS `vw_player_career_stats`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_player_career_stats` AS SELECT 
 1 AS `player_uuid`,
 1 AS `pid`,
 1 AS `name`,
 1 AS `career_games`,
 1 AS `career_minutes`,
 1 AS `career_points`,
 1 AS `career_rebounds`,
 1 AS `career_assists`,
 1 AS `career_steals`,
 1 AS `career_blocks`,
 1 AS `ppg_career`,
 1 AS `rpg_career`,
 1 AS `apg_career`,
 1 AS `fg_pct_career`,
 1 AS `ft_pct_career`,
 1 AS `three_pt_pct_career`,
 1 AS `playoff_minutes`,
 1 AS `draft_year`,
 1 AS `draft_round`,
 1 AS `draft_pick`,
 1 AS `drafted_by_team`,
 1 AS `created_at`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_player_current`
--

DROP TABLE IF EXISTS `vw_player_current`;
/*!50001 DROP VIEW IF EXISTS `vw_player_current`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_player_current` AS SELECT 
 1 AS `player_uuid`,
 1 AS `pid`,
 1 AS `name`,
 1 AS `nickname`,
 1 AS `age`,
 1 AS `position`,
 1 AS `htft`,
 1 AS `htin`,
 1 AS `active`,
 1 AS `retired`,
 1 AS `experience`,
 1 AS `bird_rights`,
 1 AS `team_uuid`,
 1 AS `teamid`,
 1 AS `team_city`,
 1 AS `team_name`,
 1 AS `owner_name`,
 1 AS `full_team_name`,
 1 AS `contract_year`,
 1 AS `current_salary`,
 1 AS `year1_salary`,
 1 AS `year2_salary`,
 1 AS `games_played`,
 1 AS `minutes_played`,
 1 AS `field_goals_made`,
 1 AS `field_goals_attempted`,
 1 AS `free_throws_made`,
 1 AS `free_throws_attempted`,
 1 AS `three_pointers_made`,
 1 AS `three_pointers_attempted`,
 1 AS `offensive_rebounds`,
 1 AS `defensive_rebounds`,
 1 AS `assists`,
 1 AS `steals`,
 1 AS `turnovers`,
 1 AS `blocks`,
 1 AS `personal_fouls`,
 1 AS `fg_percentage`,
 1 AS `ft_percentage`,
 1 AS `three_pt_percentage`,
 1 AS `points_per_game`,
 1 AS `created_at`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_playoff_series_results`
--

DROP TABLE IF EXISTS `vw_playoff_series_results`;
/*!50001 DROP VIEW IF EXISTS `vw_playoff_series_results`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_playoff_series_results` AS SELECT 
 1 AS `year`,
 1 AS `round`,
 1 AS `winner_tid`,
 1 AS `loser_tid`,
 1 AS `winner`,
 1 AS `loser`,
 1 AS `winner_games`,
 1 AS `loser_games`,
 1 AS `total_games`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_schedule_upcoming`
--

DROP TABLE IF EXISTS `vw_schedule_upcoming`;
/*!50001 DROP VIEW IF EXISTS `vw_schedule_upcoming`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_schedule_upcoming` AS SELECT 
 1 AS `game_uuid`,
 1 AS `schedule_id`,
 1 AS `season_year`,
 1 AS `game_date`,
 1 AS `box_score_id`,
 1 AS `game_of_that_day`,
 1 AS `visitor_uuid`,
 1 AS `visitor_team_id`,
 1 AS `visitor_city`,
 1 AS `visitor_name`,
 1 AS `visitor_full_name`,
 1 AS `visitor_score`,
 1 AS `home_uuid`,
 1 AS `home_team_id`,
 1 AS `home_city`,
 1 AS `home_name`,
 1 AS `home_full_name`,
 1 AS `home_score`,
 1 AS `game_status`,
 1 AS `created_at`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_series_records`
--

DROP TABLE IF EXISTS `vw_series_records`;
/*!50001 DROP VIEW IF EXISTS `vw_series_records`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_series_records` AS SELECT 
 1 AS `self`,
 1 AS `opponent`,
 1 AS `wins`,
 1 AS `losses`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_team_awards`
--

DROP TABLE IF EXISTS `vw_team_awards`;
/*!50001 DROP VIEW IF EXISTS `vw_team_awards`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_team_awards` AS SELECT 
 1 AS `year`,
 1 AS `name`,
 1 AS `Award`,
 1 AS `ID`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_team_standings`
--

DROP TABLE IF EXISTS `vw_team_standings`;
/*!50001 DROP VIEW IF EXISTS `vw_team_standings`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_team_standings` AS SELECT 
 1 AS `team_uuid`,
 1 AS `teamid`,
 1 AS `team_city`,
 1 AS `team_name`,
 1 AS `full_team_name`,
 1 AS `owner_name`,
 1 AS `league_record`,
 1 AS `win_percentage`,
 1 AS `conference`,
 1 AS `conference_record`,
 1 AS `conference_games_back`,
 1 AS `division`,
 1 AS `division_record`,
 1 AS `division_games_back`,
 1 AS `home_wins`,
 1 AS `home_losses`,
 1 AS `away_wins`,
 1 AS `away_losses`,
 1 AS `home_record`,
 1 AS `away_record`,
 1 AS `games_remaining`,
 1 AS `conference_wins`,
 1 AS `conference_losses`,
 1 AS `division_wins`,
 1 AS `division_losses`,
 1 AS `clinched_conference`,
 1 AS `clinched_division`,
 1 AS `clinched_playoffs`,
 1 AS `conference_magic_number`,
 1 AS `division_magic_number`,
 1 AS `created_at`,
 1 AS `updated_at`*/;
SET character_set_client = @saved_cs_client;

--
-- Temporary table structure for view `vw_team_total_score`
--

DROP TABLE IF EXISTS `vw_team_total_score`;
/*!50001 DROP VIEW IF EXISTS `vw_team_total_score`*/;
SET @saved_cs_client     = @@character_set_client;
SET character_set_client = utf8;
/*!50001 CREATE VIEW `vw_team_total_score` AS SELECT 
 1 AS `Date`,
 1 AS `visitorTeamID`,
 1 AS `homeTeamID`,
 1 AS `game_type`,
 1 AS `visitorScore`,
 1 AS `homeScore`*/;
SET character_set_client = @saved_cs_client;

--
-- Dumping routines for database 'iblhoops_ibl5'
--

--
-- Final view structure for view `ibl_heat_career_avgs`
--

/*!50001 DROP VIEW IF EXISTS `ibl_heat_career_avgs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_heat_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`gameMIN`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game2GA` + `bs`.`game3GA`),2) AS `fga`,case when sum(`bs`.`game2GA` + `bs`.`game3GA`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game2GA` + `bs`.`game3GA`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`gameFTM`),2) AS `ftm`,round(avg(`bs`.`gameFTA`),2) AS `fta`,case when sum(`bs`.`gameFTA`) > 0 then round(sum(`bs`.`gameFTM`) / sum(`bs`.`gameFTA`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game3GM`),2) AS `tgm`,round(avg(`bs`.`game3GA`),2) AS `tga`,case when sum(`bs`.`game3GA`) > 0 then round(sum(`bs`.`game3GM`) / sum(`bs`.`game3GA`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`gameORB`),2) AS `orb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`gameAST`),2) AS `ast`,round(avg(`bs`.`gameSTL`),2) AS `stl`,round(avg(`bs`.`gameTOV`),2) AS `tvr`,round(avg(`bs`.`gameBLK`),2) AS `blk`,round(avg(`bs`.`gamePF`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`p`.`retired` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_heat_career_totals`
--

/*!50001 DROP VIEW IF EXISTS `ibl_heat_career_totals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_heat_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`p`.`retired` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_heat_stats`
--

/*!50001 DROP VIEW IF EXISTS `ibl_heat_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_heat_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `ibl_franchise_seasons` `fs` on(`bs`.`teamID` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_heat_win_loss`
--

/*!50001 DROP VIEW IF EXISTS `ibl_heat_win_loss`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_heat_win_loss` AS with unique_games as (select `ibl_box_scores_teams`.`Date` AS `Date`,`ibl_box_scores_teams`.`visitorTeamID` AS `visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID` AS `homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`ibl_box_scores_teams`.`visitorQ1points` + `ibl_box_scores_teams`.`visitorQ2points` + `ibl_box_scores_teams`.`visitorQ3points` + `ibl_box_scores_teams`.`visitorQ4points` + coalesce(`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitor_total`,`ibl_box_scores_teams`.`homeQ1points` + `ibl_box_scores_teams`.`homeQ2points` + `ibl_box_scores_teams`.`homeQ3points` + `ibl_box_scores_teams`.`homeQ4points` + coalesce(`ibl_box_scores_teams`.`homeOTpoints`,0) AS `home_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 3 and year(`ibl_box_scores_teams`.`Date`) < 9000 group by `ibl_box_scores_teams`.`Date`,`ibl_box_scores_teams`.`visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay`), team_games as (select `unique_games`.`visitorTeamID` AS `team_id`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`homeTeamID` AS `team_id`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select year(`tg`.`Date`) AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`team_id`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`team_id` and `fs`.`season_ending_year` = year(`tg`.`Date`) + 1)) group by `tg`.`team_id`,year(`tg`.`Date`),`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_playoff_career_avgs`
--

/*!50001 DROP VIEW IF EXISTS `ibl_playoff_career_avgs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_playoff_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`gameMIN`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game2GA` + `bs`.`game3GA`),2) AS `fga`,case when sum(`bs`.`game2GA` + `bs`.`game3GA`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game2GA` + `bs`.`game3GA`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`gameFTM`),2) AS `ftm`,round(avg(`bs`.`gameFTA`),2) AS `fta`,case when sum(`bs`.`gameFTA`) > 0 then round(sum(`bs`.`gameFTM`) / sum(`bs`.`gameFTA`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game3GM`),2) AS `tgm`,round(avg(`bs`.`game3GA`),2) AS `tga`,case when sum(`bs`.`game3GA`) > 0 then round(sum(`bs`.`game3GM`) / sum(`bs`.`game3GA`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`gameORB`),2) AS `orb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`gameAST`),2) AS `ast`,round(avg(`bs`.`gameSTL`),2) AS `stl`,round(avg(`bs`.`gameTOV`),2) AS `tvr`,round(avg(`bs`.`gameBLK`),2) AS `blk`,round(avg(`bs`.`gamePF`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`p`.`retired` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_playoff_career_totals`
--

/*!50001 DROP VIEW IF EXISTS `ibl_playoff_career_totals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_playoff_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`p`.`retired` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_playoff_stats`
--

/*!50001 DROP VIEW IF EXISTS `ibl_playoff_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_playoff_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game2GA` + `bs`.`game3GA`) as signed) AS `fga`,cast(sum(`bs`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bs`.`gameFTA`) as signed) AS `fta`,cast(sum(`bs`.`game3GM`) as signed) AS `tgm`,cast(sum(`bs`.`game3GA`) as signed) AS `tga`,cast(sum(`bs`.`gameORB`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`gameAST`) as signed) AS `ast`,cast(sum(`bs`.`gameSTL`) as signed) AS `stl`,cast(sum(`bs`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bs`.`gameBLK`) as signed) AS `blk`,cast(sum(`bs`.`gamePF`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `ibl_franchise_seasons` `fs` on(`bs`.`teamID` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_season_career_avgs`
--

/*!50001 DROP VIEW IF EXISTS `ibl_season_career_avgs`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_season_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`gameMIN`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game2GA` + `bs`.`game3GA`),2) AS `fga`,case when sum(`bs`.`game2GA` + `bs`.`game3GA`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game2GA` + `bs`.`game3GA`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`gameFTM`),2) AS `ftm`,round(avg(`bs`.`gameFTA`),2) AS `fta`,case when sum(`bs`.`gameFTA`) > 0 then round(sum(`bs`.`gameFTM`) / sum(`bs`.`gameFTA`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game3GM`),2) AS `tgm`,round(avg(`bs`.`game3GA`),2) AS `tga`,case when sum(`bs`.`game3GA`) > 0 then round(sum(`bs`.`game3GM`) / sum(`bs`.`game3GA`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`gameORB`),2) AS `orb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`gameAST`),2) AS `ast`,round(avg(`bs`.`gameSTL`),2) AS `stl`,round(avg(`bs`.`gameTOV`),2) AS `tvr`,round(avg(`bs`.`gameBLK`),2) AS `blk`,round(avg(`bs`.`gamePF`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 1 group by `bs`.`pid`,`p`.`name`,`p`.`retired` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_team_defense_stats`
--

/*!50001 DROP VIEW IF EXISTS `ibl_team_defense_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_team_defense_stats` AS select `fs`.`franchise_id` AS `teamID`,`fs`.`team_name` AS `name`,`my`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`opp`.`gameMIN`) as signed) AS `minutes`,cast(sum(`opp`.`game2GM` + `opp`.`game3GM`) as signed) AS `fgm`,cast(sum(`opp`.`game2GA` + `opp`.`game3GA`) as signed) AS `fga`,cast(sum(`opp`.`gameFTM`) as signed) AS `ftm`,cast(sum(`opp`.`gameFTA`) as signed) AS `fta`,cast(sum(`opp`.`game3GM`) as signed) AS `tgm`,cast(sum(`opp`.`game3GA`) as signed) AS `tga`,cast(sum(`opp`.`gameORB`) as signed) AS `orb`,cast(sum(`opp`.`gameORB` + `opp`.`gameDRB`) as signed) AS `reb`,cast(sum(`opp`.`gameAST`) as signed) AS `ast`,cast(sum(`opp`.`gameSTL`) as signed) AS `stl`,cast(sum(`opp`.`gameTOV`) as signed) AS `tvr`,cast(sum(`opp`.`gameBLK`) as signed) AS `blk`,cast(sum(`opp`.`gamePF`) as signed) AS `pf` from ((`ibl_box_scores_teams` `my` join `ibl_box_scores_teams` `opp` on(`my`.`Date` = `opp`.`Date` and `my`.`visitorTeamID` = `opp`.`visitorTeamID` and `my`.`homeTeamID` = `opp`.`homeTeamID` and `my`.`gameOfThatDay` = `opp`.`gameOfThatDay` and `my`.`name` <> `opp`.`name`)) join `ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `my`.`name` and `fs`.`season_ending_year` = `my`.`season_year`)) where `my`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`my`.`season_year` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_team_offense_stats`
--

/*!50001 DROP VIEW IF EXISTS `ibl_team_offense_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_team_offense_stats` AS select `fs`.`franchise_id` AS `teamID`,`fs`.`team_name` AS `name`,`bst`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`bst`.`gameMIN`) as signed) AS `minutes`,cast(sum(`bst`.`game2GM` + `bst`.`game3GM`) as signed) AS `fgm`,cast(sum(`bst`.`game2GA` + `bst`.`game3GA`) as signed) AS `fga`,cast(sum(`bst`.`gameFTM`) as signed) AS `ftm`,cast(sum(`bst`.`gameFTA`) as signed) AS `fta`,cast(sum(`bst`.`game3GM`) as signed) AS `tgm`,cast(sum(`bst`.`game3GA`) as signed) AS `tga`,cast(sum(`bst`.`gameORB`) as signed) AS `orb`,cast(sum(`bst`.`gameORB` + `bst`.`gameDRB`) as signed) AS `reb`,cast(sum(`bst`.`gameAST`) as signed) AS `ast`,cast(sum(`bst`.`gameSTL`) as signed) AS `stl`,cast(sum(`bst`.`gameTOV`) as signed) AS `tvr`,cast(sum(`bst`.`gameBLK`) as signed) AS `blk`,cast(sum(`bst`.`gamePF`) as signed) AS `pf` from (`ibl_box_scores_teams` `bst` join `ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `bst`.`name` and `fs`.`season_ending_year` = `bst`.`season_year`)) where `bst`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`bst`.`season_year` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `ibl_team_win_loss`
--

/*!50001 DROP VIEW IF EXISTS `ibl_team_win_loss`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `ibl_team_win_loss` AS with unique_games as (select `ibl_box_scores_teams`.`Date` AS `Date`,`ibl_box_scores_teams`.`visitorTeamID` AS `visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID` AS `homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`ibl_box_scores_teams`.`visitorQ1points` + `ibl_box_scores_teams`.`visitorQ2points` + `ibl_box_scores_teams`.`visitorQ3points` + `ibl_box_scores_teams`.`visitorQ4points` + coalesce(`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitor_total`,`ibl_box_scores_teams`.`homeQ1points` + `ibl_box_scores_teams`.`homeQ2points` + `ibl_box_scores_teams`.`homeQ3points` + `ibl_box_scores_teams`.`homeQ4points` + coalesce(`ibl_box_scores_teams`.`homeOTpoints`,0) AS `home_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 1 group by `ibl_box_scores_teams`.`Date`,`ibl_box_scores_teams`.`visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay`), team_games as (select `unique_games`.`visitorTeamID` AS `team_id`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`homeTeamID` AS `team_id`,`unique_games`.`Date` AS `Date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`team_id`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`team_id` and `fs`.`season_ending_year` = case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end)) group by `tg`.`team_id`,case when month(`tg`.`Date`) >= 10 then year(`tg`.`Date`) + 1 else year(`tg`.`Date`) end,`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_career_totals`
--

/*!50001 DROP VIEW IF EXISTS `vw_career_totals`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_career_totals` AS select `ibl_hist`.`pid` AS `pid`,`ibl_hist`.`name` AS `name`,count(0) AS `seasons`,sum(`ibl_hist`.`games`) AS `games`,sum(`ibl_hist`.`minutes`) AS `minutes`,sum(`ibl_hist`.`fgm`) AS `fgm`,sum(`ibl_hist`.`fga`) AS `fga`,sum(`ibl_hist`.`ftm`) AS `ftm`,sum(`ibl_hist`.`fta`) AS `fta`,sum(`ibl_hist`.`tgm`) AS `tgm`,sum(`ibl_hist`.`tga`) AS `tga`,sum(`ibl_hist`.`orb`) AS `orb`,sum(`ibl_hist`.`reb`) AS `reb`,sum(`ibl_hist`.`ast`) AS `ast`,sum(`ibl_hist`.`stl`) AS `stl`,sum(`ibl_hist`.`blk`) AS `blk`,sum(`ibl_hist`.`tvr`) AS `tvr`,sum(`ibl_hist`.`pf`) AS `pf`,sum(`ibl_hist`.`pts`) AS `pts` from `ibl_hist` group by `ibl_hist`.`pid`,`ibl_hist`.`name` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_current_salary`
--

/*!50001 DROP VIEW IF EXISTS `vw_current_salary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_current_salary` AS select `ibl_plr`.`pid` AS `pid`,`ibl_plr`.`name` AS `name`,`ibl_plr`.`tid` AS `tid`,`ibl_plr`.`teamname` AS `teamname`,`ibl_plr`.`pos` AS `pos`,`ibl_plr`.`cy` AS `cy`,`ibl_plr`.`cyt` AS `cyt`,`ibl_plr`.`cy1` AS `cy1`,`ibl_plr`.`cy2` AS `cy2`,`ibl_plr`.`cy3` AS `cy3`,`ibl_plr`.`cy4` AS `cy4`,`ibl_plr`.`cy5` AS `cy5`,`ibl_plr`.`cy6` AS `cy6`,case `ibl_plr`.`cy` when 1 then `ibl_plr`.`cy1` when 2 then `ibl_plr`.`cy2` when 3 then `ibl_plr`.`cy3` when 4 then `ibl_plr`.`cy4` when 5 then `ibl_plr`.`cy5` when 6 then `ibl_plr`.`cy6` else 0 end AS `current_salary`,case `ibl_plr`.`cy` when 0 then `ibl_plr`.`cy1` when 1 then `ibl_plr`.`cy2` when 2 then `ibl_plr`.`cy3` when 3 then `ibl_plr`.`cy4` when 4 then `ibl_plr`.`cy5` when 5 then `ibl_plr`.`cy6` else 0 end AS `next_year_salary` from `ibl_plr` where `ibl_plr`.`retired` = 0 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_franchise_summary`
--

/*!50001 DROP VIEW IF EXISTS `vw_franchise_summary`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_franchise_summary` AS select `ti`.`teamid` AS `teamid`,coalesce(`wl`.`totwins`,0) AS `totwins`,coalesce(`wl`.`totloss`,0) AS `totloss`,case when coalesce(`wl`.`totwins`,0) + coalesce(`wl`.`totloss`,0) = 0 then 0.000 else round(coalesce(`wl`.`totwins`,0) / (coalesce(`wl`.`totwins`,0) + coalesce(`wl`.`totloss`,0)),3) end AS `winpct`,coalesce(`po`.`playoffs`,0) AS `playoffs`,coalesce(`tc`.`div_titles`,0) AS `div_titles`,coalesce(`tc`.`conf_titles`,0) AS `conf_titles`,coalesce(`tc`.`ibl_titles`,0) AS `ibl_titles`,coalesce(`tc`.`heat_titles`,0) AS `heat_titles` from (((`ibl_team_info` `ti` left join (select `ibl_team_win_loss`.`currentname` AS `currentname`,sum(`ibl_team_win_loss`.`wins`) AS `totwins`,sum(`ibl_team_win_loss`.`losses`) AS `totloss` from `ibl_team_win_loss` group by `ibl_team_win_loss`.`currentname`) `wl` on(`wl`.`currentname` = `ti`.`team_name`)) left join (select `po_inner`.`team_name` AS `team_name`,count(distinct `po_inner`.`year`) AS `playoffs` from (select `vw_playoff_series_results`.`winner` AS `team_name`,`vw_playoff_series_results`.`year` AS `year` from `vw_playoff_series_results` where `vw_playoff_series_results`.`round` = 1 union select `vw_playoff_series_results`.`loser` AS `team_name`,`vw_playoff_series_results`.`year` AS `year` from `vw_playoff_series_results` where `vw_playoff_series_results`.`round` = 1) `po_inner` group by `po_inner`.`team_name`) `po` on(`po`.`team_name` = `ti`.`team_name`)) left join (select `vw_team_awards`.`name` AS `name`,sum(case when `vw_team_awards`.`Award` like '%Division%' then 1 else 0 end) AS `div_titles`,sum(case when `vw_team_awards`.`Award` like '%Conference%' then 1 else 0 end) AS `conf_titles`,sum(case when `vw_team_awards`.`Award` like '%IBL Champions%' then 1 else 0 end) AS `ibl_titles`,sum(case when `vw_team_awards`.`Award` like '%HEAT%' then 1 else 0 end) AS `heat_titles` from `vw_team_awards` group by `vw_team_awards`.`name`) `tc` on(`tc`.`name` = `ti`.`team_name`)) where `ti`.`teamid` between 1 and 30 */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_free_agency_offers`
--

/*!50001 DROP VIEW IF EXISTS `vw_free_agency_offers`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_free_agency_offers` AS select `fa`.`primary_key` AS `offer_id`,`p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `player_name`,`p`.`pos` AS `position`,`p`.`age` AS `age`,`t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`fa`.`offer1` AS `year1_amount`,`fa`.`offer2` AS `year2_amount`,`fa`.`offer3` AS `year3_amount`,`fa`.`offer4` AS `year4_amount`,`fa`.`offer5` AS `year5_amount`,`fa`.`offer6` AS `year6_amount`,`fa`.`offer1` + `fa`.`offer2` + `fa`.`offer3` + `fa`.`offer4` + `fa`.`offer5` + `fa`.`offer6` AS `total_contract_value`,`fa`.`modifier` AS `modifier`,`fa`.`random` AS `random`,`fa`.`perceivedvalue` AS `perceived_value`,`fa`.`MLE` AS `is_mle`,`fa`.`LLE` AS `is_lle`,`fa`.`created_at` AS `created_at`,`fa`.`updated_at` AS `updated_at` from ((`ibl_fa_offers` `fa` join `ibl_plr` `p` on(`fa`.`name` = `p`.`name`)) join `ibl_team_info` `t` on(`fa`.`team` = `t`.`team_name`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_player_career_stats`
--

/*!50001 DROP VIEW IF EXISTS `vw_player_career_stats`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_player_career_stats` AS select `p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `name`,`p`.`car_gm` AS `career_games`,`p`.`car_min` AS `career_minutes`,round(`p`.`car_fgm` * 2 + `p`.`car_tgm` + `p`.`car_ftm`,0) AS `career_points`,`p`.`car_orb` + `p`.`car_drb` AS `career_rebounds`,`p`.`car_ast` AS `career_assists`,`p`.`car_stl` AS `career_steals`,`p`.`car_blk` AS `career_blocks`,round((`p`.`car_fgm` * 2 + `p`.`car_tgm` + `p`.`car_ftm`) / nullif(`p`.`car_gm`,0),1) AS `ppg_career`,round((`p`.`car_orb` + `p`.`car_drb`) / nullif(`p`.`car_gm`,0),1) AS `rpg_career`,round(`p`.`car_ast` / nullif(`p`.`car_gm`,0),1) AS `apg_career`,round(`p`.`car_fgm` / nullif(`p`.`car_fga`,0),3) AS `fg_pct_career`,round(`p`.`car_ftm` / nullif(`p`.`car_fta`,0),3) AS `ft_pct_career`,round(`p`.`car_tgm` / nullif(`p`.`car_tga`,0),3) AS `three_pt_pct_career`,`p`.`car_playoff_min` AS `playoff_minutes`,`p`.`draftyear` AS `draft_year`,`p`.`draftround` AS `draft_round`,`p`.`draftpickno` AS `draft_pick`,`p`.`draftedby` AS `drafted_by_team`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at` from `ibl_plr` `p` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_player_current`
--

/*!50001 DROP VIEW IF EXISTS `vw_player_current`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_player_current` AS select `p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `name`,`p`.`nickname` AS `nickname`,`p`.`age` AS `age`,`p`.`pos` AS `position`,`p`.`htft` AS `htft`,`p`.`htin` AS `htin`,`p`.`active` AS `active`,`p`.`retired` AS `retired`,`p`.`exp` AS `experience`,`p`.`bird` AS `bird_rights`,`t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,`t`.`owner_name` AS `owner_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`p`.`cy` AS `contract_year`,case `p`.`cy` when 1 then `p`.`cy1` when 2 then `p`.`cy2` when 3 then `p`.`cy3` when 4 then `p`.`cy4` when 5 then `p`.`cy5` when 6 then `p`.`cy6` else 0 end AS `current_salary`,`p`.`cy1` AS `year1_salary`,`p`.`cy2` AS `year2_salary`,`p`.`stats_gm` AS `games_played`,`p`.`stats_min` AS `minutes_played`,`p`.`stats_fgm` AS `field_goals_made`,`p`.`stats_fga` AS `field_goals_attempted`,`p`.`stats_ftm` AS `free_throws_made`,`p`.`stats_fta` AS `free_throws_attempted`,`p`.`stats_3gm` AS `three_pointers_made`,`p`.`stats_3ga` AS `three_pointers_attempted`,`p`.`stats_orb` AS `offensive_rebounds`,`p`.`stats_drb` AS `defensive_rebounds`,`p`.`stats_ast` AS `assists`,`p`.`stats_stl` AS `steals`,`p`.`stats_to` AS `turnovers`,`p`.`stats_blk` AS `blocks`,`p`.`stats_pf` AS `personal_fouls`,round(`p`.`stats_fgm` / nullif(`p`.`stats_fga`,0),3) AS `fg_percentage`,round(`p`.`stats_ftm` / nullif(`p`.`stats_fta`,0),3) AS `ft_percentage`,round(`p`.`stats_3gm` / nullif(`p`.`stats_3ga`,0),3) AS `three_pt_percentage`,round((`p`.`stats_fgm` * 2 + `p`.`stats_3gm` + `p`.`stats_ftm`) / nullif(`p`.`stats_gm`,0),1) AS `points_per_game`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at` from (`ibl_plr` `p` left join `ibl_team_info` `t` on(`p`.`tid` = `t`.`teamid`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_playoff_series_results`
--

/*!50001 DROP VIEW IF EXISTS `vw_playoff_series_results`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_playoff_series_results` AS with playoff_games as (select `ibl_box_scores_teams`.`Date` AS `Date`,year(`ibl_box_scores_teams`.`Date`) AS `year`,`ibl_box_scores_teams`.`visitorTeamID` AS `visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID` AS `homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay` AS `gameOfThatDay`,`ibl_box_scores_teams`.`visitorQ1points` + `ibl_box_scores_teams`.`visitorQ2points` + `ibl_box_scores_teams`.`visitorQ3points` + `ibl_box_scores_teams`.`visitorQ4points` + coalesce(`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `v_total`,`ibl_box_scores_teams`.`homeQ1points` + `ibl_box_scores_teams`.`homeQ2points` + `ibl_box_scores_teams`.`homeQ3points` + `ibl_box_scores_teams`.`homeQ4points` + coalesce(`ibl_box_scores_teams`.`homeOTpoints`,0) AS `h_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 2 group by `ibl_box_scores_teams`.`Date`,`ibl_box_scores_teams`.`visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID`,`ibl_box_scores_teams`.`gameOfThatDay`), game_results as (select `playoff_games`.`Date` AS `Date`,`playoff_games`.`year` AS `year`,`playoff_games`.`visitorTeamID` AS `visitorTeamID`,`playoff_games`.`homeTeamID` AS `homeTeamID`,`playoff_games`.`gameOfThatDay` AS `gameOfThatDay`,`playoff_games`.`v_total` AS `v_total`,`playoff_games`.`h_total` AS `h_total`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`visitorTeamID` else `playoff_games`.`homeTeamID` end AS `winner_tid`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`homeTeamID` else `playoff_games`.`visitorTeamID` end AS `loser_tid` from `playoff_games`), team_wins as (select `game_results`.`year` AS `year`,least(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`) AS `team_a`,greatest(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`) AS `team_b`,`game_results`.`winner_tid` AS `winner_tid`,count(0) AS `wins`,row_number() over ( partition by `game_results`.`year`,least(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`),greatest(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`) order by count(0) desc) AS `rn` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`),greatest(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`),`game_results`.`winner_tid`), series_meta as (select `game_results`.`year` AS `year`,least(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`) AS `team_a`,greatest(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`) AS `team_b`,count(0) AS `total_games`,min(`game_results`.`Date`) AS `series_start`,dense_rank() over ( partition by `game_results`.`year` order by min(`game_results`.`Date`)) AS `round` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`),greatest(`game_results`.`visitorTeamID`,`game_results`.`homeTeamID`))select `sm`.`year` AS `year`,`sm`.`round` AS `round`,`tw`.`winner_tid` AS `winner_tid`,case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end AS `loser_tid`,`w`.`team_name` AS `winner`,`l`.`team_name` AS `loser`,`tw`.`wins` AS `winner_games`,`sm`.`total_games` - `tw`.`wins` AS `loser_games`,`sm`.`total_games` AS `total_games` from (((`series_meta` `sm` join `team_wins` `tw` on(`tw`.`year` = `sm`.`year` and `tw`.`team_a` = `sm`.`team_a` and `tw`.`team_b` = `sm`.`team_b` and `tw`.`rn` = 1)) join `ibl_team_info` `w` on(`w`.`teamid` = `tw`.`winner_tid`)) join `ibl_team_info` `l` on(`l`.`teamid` = case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end)) order by `sm`.`year` desc,`sm`.`round` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_schedule_upcoming`
--

/*!50001 DROP VIEW IF EXISTS `vw_schedule_upcoming`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_schedule_upcoming` AS select `sch`.`uuid` AS `game_uuid`,`sch`.`SchedID` AS `schedule_id`,`sch`.`Year` AS `season_year`,`sch`.`Date` AS `game_date`,`sch`.`BoxID` AS `box_score_id`,coalesce(`bst`.`gameOfThatDay`,0) AS `game_of_that_day`,`tv`.`uuid` AS `visitor_uuid`,`tv`.`teamid` AS `visitor_team_id`,`tv`.`team_city` AS `visitor_city`,`tv`.`team_name` AS `visitor_name`,concat(`tv`.`team_city`,' ',`tv`.`team_name`) AS `visitor_full_name`,`sch`.`VScore` AS `visitor_score`,`th`.`uuid` AS `home_uuid`,`th`.`teamid` AS `home_team_id`,`th`.`team_city` AS `home_city`,`th`.`team_name` AS `home_name`,concat(`th`.`team_city`,' ',`th`.`team_name`) AS `home_full_name`,`sch`.`HScore` AS `home_score`,case when `sch`.`VScore` = 0 and `sch`.`HScore` = 0 then 'scheduled' else 'completed' end AS `game_status`,`sch`.`created_at` AS `created_at`,`sch`.`updated_at` AS `updated_at` from (((`ibl_schedule` `sch` join `ibl_team_info` `tv` on(`sch`.`Visitor` = `tv`.`teamid`)) join `ibl_team_info` `th` on(`sch`.`Home` = `th`.`teamid`)) left join (select `ibl_box_scores_teams`.`Date` AS `Date`,`ibl_box_scores_teams`.`visitorTeamID` AS `visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID` AS `homeTeamID`,min(`ibl_box_scores_teams`.`gameOfThatDay`) AS `gameOfThatDay` from `ibl_box_scores_teams` group by `ibl_box_scores_teams`.`Date`,`ibl_box_scores_teams`.`visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID`) `bst` on(`bst`.`Date` = `sch`.`Date` and `bst`.`visitorTeamID` = `sch`.`Visitor` and `bst`.`homeTeamID` = `sch`.`Home`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_series_records`
--

/*!50001 DROP VIEW IF EXISTS `vw_series_records`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_series_records` AS select `t`.`self` AS `self`,`t`.`opponent` AS `opponent`,sum(`t`.`wins`) AS `wins`,sum(`t`.`losses`) AS `losses` from (select `ibl_schedule`.`Home` AS `self`,`ibl_schedule`.`Visitor` AS `opponent`,count(0) AS `wins`,0 AS `losses` from `ibl_schedule` where `ibl_schedule`.`HScore` > `ibl_schedule`.`VScore` group by `ibl_schedule`.`Home`,`ibl_schedule`.`Visitor` union all select `ibl_schedule`.`Visitor` AS `self`,`ibl_schedule`.`Home` AS `opponent`,count(0) AS `wins`,0 AS `losses` from `ibl_schedule` where `ibl_schedule`.`VScore` > `ibl_schedule`.`HScore` group by `ibl_schedule`.`Visitor`,`ibl_schedule`.`Home` union all select `ibl_schedule`.`Home` AS `self`,`ibl_schedule`.`Visitor` AS `opponent`,0 AS `wins`,count(0) AS `losses` from `ibl_schedule` where `ibl_schedule`.`HScore` < `ibl_schedule`.`VScore` group by `ibl_schedule`.`Home`,`ibl_schedule`.`Visitor` union all select `ibl_schedule`.`Visitor` AS `self`,`ibl_schedule`.`Home` AS `opponent`,0 AS `wins`,count(0) AS `losses` from `ibl_schedule` where `ibl_schedule`.`VScore` < `ibl_schedule`.`HScore` group by `ibl_schedule`.`Visitor`,`ibl_schedule`.`Home`) `t` group by `t`.`self`,`t`.`opponent` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_team_awards`
--

/*!50001 DROP VIEW IF EXISTS `vw_team_awards`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_team_awards` AS select `ibl_team_awards`.`year` AS `year`,`ibl_team_awards`.`name` AS `name`,`ibl_team_awards`.`Award` AS `Award`,`ibl_team_awards`.`ID` AS `ID` from `ibl_team_awards` union all select `psr`.`year` AS `year`,`psr`.`winner` AS `name`,'IBL Champions' AS `Award`,0 AS `ID` from `vw_playoff_series_results` `psr` where `psr`.`round` = (select max(`psr2`.`round`) from `vw_playoff_series_results` `psr2` where `psr2`.`year` = `psr`.`year`) union all select `hc`.`year` AS `year`,`ti`.`team_name` AS `name`,'IBL HEAT Champions' AS `Award`,0 AS `ID` from ((select year(`bst`.`Date`) AS `year`,case when `bst`.`homeQ1points` + `bst`.`homeQ2points` + `bst`.`homeQ3points` + `bst`.`homeQ4points` + coalesce(`bst`.`homeOTpoints`,0) > `bst`.`visitorQ1points` + `bst`.`visitorQ2points` + `bst`.`visitorQ3points` + `bst`.`visitorQ4points` + coalesce(`bst`.`visitorOTpoints`,0) then `bst`.`homeTeamID` else `bst`.`visitorTeamID` end AS `winner_tid` from (`ibl_box_scores_teams` `bst` join (select year(`ibl_box_scores_teams`.`Date`) AS `yr`,max(`ibl_box_scores_teams`.`Date`) AS `last_date` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 3 group by year(`ibl_box_scores_teams`.`Date`)) `ld` on(`bst`.`Date` = `ld`.`last_date` and year(`bst`.`Date`) = `ld`.`yr`)) where `bst`.`game_type` = 3 and `bst`.`gameOfThatDay` = (select min(`bst2`.`gameOfThatDay`) from `ibl_box_scores_teams` `bst2` where `bst2`.`Date` = `ld`.`last_date` and `bst2`.`game_type` = 3) group by year(`bst`.`Date`)) `hc` join `ibl_team_info` `ti` on(`ti`.`teamid` = `hc`.`winner_tid`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_team_standings`
--

/*!50001 DROP VIEW IF EXISTS `vw_team_standings`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY INVOKER */
/*!50001 VIEW `vw_team_standings` AS select `t`.`uuid` AS `team_uuid`,`t`.`teamid` AS `teamid`,`t`.`team_city` AS `team_city`,`t`.`team_name` AS `team_name`,concat(`t`.`team_city`,' ',`t`.`team_name`) AS `full_team_name`,`t`.`owner_name` AS `owner_name`,`s`.`leagueRecord` AS `league_record`,`s`.`pct` AS `win_percentage`,`s`.`conference` AS `conference`,`s`.`confRecord` AS `conference_record`,`s`.`confGB` AS `conference_games_back`,`s`.`division` AS `division`,`s`.`divRecord` AS `division_record`,`s`.`divGB` AS `division_games_back`,`s`.`homeWins` AS `home_wins`,`s`.`homeLosses` AS `home_losses`,`s`.`awayWins` AS `away_wins`,`s`.`awayLosses` AS `away_losses`,concat(`s`.`homeWins`,'-',`s`.`homeLosses`) AS `home_record`,concat(`s`.`awayWins`,'-',`s`.`awayLosses`) AS `away_record`,`s`.`gamesUnplayed` AS `games_remaining`,`s`.`confWins` AS `conference_wins`,`s`.`confLosses` AS `conference_losses`,`s`.`divWins` AS `division_wins`,`s`.`divLosses` AS `division_losses`,`s`.`clinchedConference` AS `clinched_conference`,`s`.`clinchedDivision` AS `clinched_division`,`s`.`clinchedPlayoffs` AS `clinched_playoffs`,`s`.`confMagicNumber` AS `conference_magic_number`,`s`.`divMagicNumber` AS `division_magic_number`,`s`.`created_at` AS `created_at`,`s`.`updated_at` AS `updated_at` from (`ibl_team_info` `t` join `ibl_standings` `s` on(`t`.`teamid` = `s`.`tid`)) */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;

--
-- Final view structure for view `vw_team_total_score`
--

/*!50001 DROP VIEW IF EXISTS `vw_team_total_score`*/;
/*!50001 SET @saved_cs_client          = @@character_set_client */;
/*!50001 SET @saved_cs_results         = @@character_set_results */;
/*!50001 SET @saved_col_connection     = @@collation_connection */;
/*!50001 SET character_set_client      = utf8mb4 */;
/*!50001 SET character_set_results     = utf8mb4 */;
/*!50001 SET collation_connection      = utf8mb4_general_ci */;
/*!50001 CREATE ALGORITHM=UNDEFINED */
/*!50013 DEFINER=`iblhoops_chibul`@`71.145.211.164` SQL SECURITY DEFINER */
/*!50001 VIEW `vw_team_total_score` AS select `ibl_box_scores_teams`.`Date` AS `Date`,`ibl_box_scores_teams`.`visitorTeamID` AS `visitorTeamID`,`ibl_box_scores_teams`.`homeTeamID` AS `homeTeamID`,`ibl_box_scores_teams`.`game_type` AS `game_type`,`ibl_box_scores_teams`.`visitorQ1points` + `ibl_box_scores_teams`.`visitorQ2points` + `ibl_box_scores_teams`.`visitorQ3points` + `ibl_box_scores_teams`.`visitorQ4points` + coalesce(`ibl_box_scores_teams`.`visitorOTpoints`,0) AS `visitorScore`,`ibl_box_scores_teams`.`homeQ1points` + `ibl_box_scores_teams`.`homeQ2points` + `ibl_box_scores_teams`.`homeQ3points` + `ibl_box_scores_teams`.`homeQ4points` + coalesce(`ibl_box_scores_teams`.`homeOTpoints`,0) AS `homeScore` from `ibl_box_scores_teams` */;
/*!50001 SET character_set_client      = @saved_cs_client */;
/*!50001 SET character_set_results     = @saved_cs_results */;
/*!50001 SET collation_connection      = @saved_col_connection */;
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2026-02-25  1:23:26
