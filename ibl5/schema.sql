-- MySQL dump 10.13  Distrib 5.7.44, for osx11.0 (x86_64)
--
-- Host: iblhoops.net    Database: iblhoops_ibl5
-- ------------------------------------------------------
-- Server version	5.5.5-10.11.15-MariaDB

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
-- Table structure for table `ibl_awards`
--

DROP TABLE IF EXISTS `ibl_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_awards` (
  `year` int(11) NOT NULL DEFAULT 0,
  `Award` varchar(128) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`),
  KEY `idx_year` (`year`),
  KEY `idx_name` (`name`)
) ENGINE=InnoDB AUTO_INCREMENT=3982 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_banners`
--

DROP TABLE IF EXISTS `ibl_banners`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_banners` (
  `year` int(11) NOT NULL DEFAULT 0,
  `currentname` varchar(16) NOT NULL DEFAULT '',
  `bannername` varchar(16) NOT NULL DEFAULT '',
  `bannertype` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores`
--

DROP TABLE IF EXISTS `ibl_box_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_box_scores` (
  `Date` date NOT NULL,
  `name` varchar(16) DEFAULT '',
  `pos` varchar(2) DEFAULT '',
  `pid` int(11) DEFAULT NULL,
  `visitorTID` int(11) DEFAULT NULL,
  `homeTID` int(11) DEFAULT NULL,
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
  `uuid` char(36) NOT NULL,
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `idx_date` (`Date`),
  KEY `idx_pid` (`pid`),
  KEY `idx_visitor_tid` (`visitorTID`),
  KEY `idx_home_tid` (`homeTID`),
  KEY `idx_date_pid` (`Date`,`pid`),
  KEY `idx_date_home_visitor` (`Date`,`homeTID`,`visitorTID`),
  CONSTRAINT `fk_boxscore_home` FOREIGN KEY (`homeTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_visitor` FOREIGN KEY (`visitorTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_box_minutes` CHECK (`gameMIN` is null or `gameMIN` >= 0 and `gameMIN` <= 70)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores_teams`
--

DROP TABLE IF EXISTS `ibl_box_scores_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_box_scores_teams` (
  `Date` date NOT NULL,
  `name` varchar(16) DEFAULT '',
  `gameOfThatDay` int(11) DEFAULT NULL,
  `visitorTeamID` int(11) DEFAULT NULL,
  `homeTeamID` int(11) DEFAULT NULL,
  `attendance` int(11) DEFAULT NULL,
  `capacity` int(11) DEFAULT NULL,
  `visitorWins` int(11) DEFAULT NULL,
  `visitorLosses` int(11) DEFAULT NULL,
  `homeWins` int(11) DEFAULT NULL,
  `homeLosses` int(11) DEFAULT NULL,
  `visitorQ1points` int(11) DEFAULT NULL,
  `visitorQ2points` int(11) DEFAULT NULL,
  `visitorQ3points` int(11) DEFAULT NULL,
  `visitorQ4points` int(11) DEFAULT NULL,
  `visitorOTpoints` int(11) DEFAULT NULL,
  `homeQ1points` int(11) DEFAULT NULL,
  `homeQ2points` int(11) DEFAULT NULL,
  `homeQ3points` int(11) DEFAULT NULL,
  `homeQ4points` int(11) DEFAULT NULL,
  `homeOTpoints` int(11) DEFAULT NULL,
  `gameMIN` int(11) DEFAULT NULL,
  `game2GM` int(11) DEFAULT NULL,
  `game2GA` int(11) DEFAULT NULL,
  `gameFTM` int(11) DEFAULT NULL,
  `gameFTA` int(11) DEFAULT NULL,
  `game3GM` int(11) DEFAULT NULL,
  `game3GA` int(11) DEFAULT NULL,
  `gameORB` int(11) DEFAULT NULL,
  `gameDRB` int(11) DEFAULT NULL,
  `gameAST` int(11) DEFAULT NULL,
  `gameSTL` int(11) DEFAULT NULL,
  `gameTOV` int(11) DEFAULT NULL,
  `gameBLK` int(11) DEFAULT NULL,
  `gamePF` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor_team` (`visitorTeamID`),
  KEY `idx_home_team` (`homeTeamID`),
  CONSTRAINT `fk_boxscoreteam_home` FOREIGN KEY (`homeTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscoreteam_visitor` FOREIGN KEY (`visitorTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_demands`
--

DROP TABLE IF EXISTS `ibl_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_demands` (
  `name` varchar(32) NOT NULL DEFAULT '',
  `dem1` int(11) NOT NULL DEFAULT 0,
  `dem2` int(11) NOT NULL DEFAULT 0,
  `dem3` int(11) NOT NULL DEFAULT 0,
  `dem4` int(11) NOT NULL DEFAULT 0,
  `dem5` int(11) NOT NULL DEFAULT 0,
  `dem6` int(11) NOT NULL DEFAULT 0,
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
  `team` varchar(255) NOT NULL DEFAULT '',
  `player` varchar(255) NOT NULL DEFAULT '',
  `round` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Draft round',
  `pick` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Pick number',
  `date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  UNIQUE KEY `draft_id` (`draft_id`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
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
  `name` varchar(32) NOT NULL DEFAULT '',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') NOT NULL DEFAULT '' COMMENT 'Draft prospect position',
  `age` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Player age',
  `team` varchar(128) NOT NULL DEFAULT '',
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
  `offo` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off outside rating',
  `offd` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off drive rating',
  `offp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off post rating',
  `offt` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Off transition rating',
  `defo` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def outside rating',
  `defd` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def drive rating',
  `defp` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def post rating',
  `deft` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Def transition rating',
  `tal` int(11) NOT NULL DEFAULT 0,
  `skl` int(11) NOT NULL DEFAULT 0,
  `int` int(11) NOT NULL DEFAULT 0,
  `ranking` float DEFAULT 0,
  `invite` mediumtext DEFAULT NULL,
  `drafted` int(11) DEFAULT 0,
  `sta` int(11) DEFAULT 0,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `idx_ranking` (`ranking`),
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
  `ownerofpick` varchar(32) NOT NULL DEFAULT '',
  `teampick` varchar(32) NOT NULL DEFAULT '',
  `year` varchar(4) NOT NULL DEFAULT '',
  `round` char(1) NOT NULL DEFAULT '',
  `notes` varchar(280) DEFAULT NULL,
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
  `name` varchar(32) NOT NULL DEFAULT '',
  `team` varchar(32) NOT NULL DEFAULT '',
  `offer1` int(11) NOT NULL DEFAULT 0,
  `offer2` int(11) NOT NULL DEFAULT 0,
  `offer3` int(11) NOT NULL DEFAULT 0,
  `offer4` int(11) NOT NULL DEFAULT 0,
  `offer5` int(11) NOT NULL DEFAULT 0,
  `offer6` int(11) NOT NULL DEFAULT 0,
  `modifier` float NOT NULL DEFAULT 0,
  `random` float NOT NULL DEFAULT 0,
  `perceivedvalue` float NOT NULL DEFAULT 0,
  `MLE` int(11) NOT NULL DEFAULT 0,
  `LLE` int(11) NOT NULL DEFAULT 0,
  `primary_key` int(11) unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`primary_key`),
  KEY `idx_name` (`name`),
  KEY `idx_team` (`team`),
  CONSTRAINT `fk_faoffer_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_faoffer_team` FOREIGN KEY (`team`) REFERENCES `ibl_team_info` (`team_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_history`
--

DROP TABLE IF EXISTS `ibl_gm_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_gm_history` (
  `year` varchar(35) NOT NULL,
  `name` varchar(50) NOT NULL,
  `Award` varchar(350) NOT NULL,
  `prim` int(11) NOT NULL,
  PRIMARY KEY (`prim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_career_avgs`
--

DROP TABLE IF EXISTS `ibl_heat_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_heat_career_avgs` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` decimal(8,2) NOT NULL DEFAULT 0.00,
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `orb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `reb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `ast` decimal(8,2) NOT NULL DEFAULT 0.00,
  `stl` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tvr` decimal(8,2) NOT NULL DEFAULT 0.00,
  `blk` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pf` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(8,2) NOT NULL DEFAULT 0.00,
  `retired` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_career_totals`
--

DROP TABLE IF EXISTS `ibl_heat_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_heat_career_totals` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `pts` int(11) NOT NULL DEFAULT 0,
  `retired` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_stats`
--

DROP TABLE IF EXISTS `ibl_heat_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_heat_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL DEFAULT 0,
  `pos` char(2) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `team` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `fk_heat_stats_name` (`name`),
  CONSTRAINT `fk_heat_stats_name` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7481 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_win_loss`
--

DROP TABLE IF EXISTS `ibl_heat_win_loss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_heat_win_loss` (
  `year` int(4) unsigned NOT NULL DEFAULT 0,
  `currentname` varchar(16) NOT NULL DEFAULT '',
  `namethatyear` varchar(16) NOT NULL DEFAULT '',
  `wins` tinyint(2) unsigned NOT NULL DEFAULT 0,
  `losses` tinyint(2) unsigned NOT NULL DEFAULT 0,
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=539 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_hist`
--

DROP TABLE IF EXISTS `ibl_hist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_hist` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `year` smallint(5) unsigned NOT NULL DEFAULT 0 COMMENT 'Season year',
  `team` varchar(32) NOT NULL DEFAULT '',
  `teamid` int(11) NOT NULL DEFAULT 0,
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
  `r_2ga` int(11) NOT NULL DEFAULT 0,
  `r_2gp` int(11) NOT NULL DEFAULT 0,
  `r_fta` int(11) NOT NULL DEFAULT 0,
  `r_ftp` int(11) NOT NULL DEFAULT 0,
  `r_3ga` int(11) NOT NULL DEFAULT 0,
  `r_3gp` int(11) NOT NULL DEFAULT 0,
  `r_orb` int(11) NOT NULL DEFAULT 0,
  `r_drb` int(11) NOT NULL DEFAULT 0,
  `r_ast` int(11) NOT NULL DEFAULT 0,
  `r_stl` int(11) NOT NULL DEFAULT 0,
  `r_blk` int(11) NOT NULL DEFAULT 0,
  `r_tvr` int(11) NOT NULL DEFAULT 0,
  `r_oo` int(11) NOT NULL DEFAULT 0,
  `r_do` int(11) NOT NULL DEFAULT 0,
  `r_po` int(11) NOT NULL DEFAULT 0,
  `r_to` int(11) NOT NULL DEFAULT 0,
  `r_od` int(11) NOT NULL DEFAULT 0,
  `r_dd` int(11) NOT NULL DEFAULT 0,
  `r_pd` int(11) NOT NULL DEFAULT 0,
  `r_td` int(11) NOT NULL DEFAULT 0,
  `salary` int(11) NOT NULL DEFAULT 0,
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
  CONSTRAINT `fk_hist_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7003 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_career_avgs`
--

DROP TABLE IF EXISTS `ibl_olympics_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_career_avgs` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` decimal(8,2) NOT NULL DEFAULT 0.00,
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `orb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `reb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `ast` decimal(8,2) NOT NULL DEFAULT 0.00,
  `stl` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tvr` decimal(8,2) NOT NULL DEFAULT 0.00,
  `blk` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pf` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(8,2) NOT NULL DEFAULT 0.00,
  `retired` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_career_totals`
--

DROP TABLE IF EXISTS `ibl_olympics_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_career_totals` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `pts` int(11) NOT NULL DEFAULT 0,
  `retired` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_stats`
--

DROP TABLE IF EXISTS `ibl_olympics_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_olympics_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL DEFAULT 0,
  `pos` char(2) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `team` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`),
  KEY `fk_olympics_stats_name` (`name`),
  CONSTRAINT `fk_olympics_stats_name` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=97 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_one_on_one`
--

DROP TABLE IF EXISTS `ibl_one_on_one`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_one_on_one` (
  `gameid` int(11) NOT NULL DEFAULT 0,
  `playbyplay` mediumtext NOT NULL,
  `winner` varchar(32) NOT NULL DEFAULT '',
  `loser` varchar(32) NOT NULL DEFAULT '',
  `winscore` int(11) NOT NULL DEFAULT 0,
  `lossscore` int(11) NOT NULL DEFAULT 0,
  `owner` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`gameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_career_avgs`
--

DROP TABLE IF EXISTS `ibl_playoff_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_playoff_career_avgs` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` decimal(8,2) NOT NULL DEFAULT 0.00,
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `orb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `reb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `ast` decimal(8,2) NOT NULL DEFAULT 0.00,
  `stl` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tvr` decimal(8,2) NOT NULL DEFAULT 0.00,
  `blk` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pf` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(8,2) NOT NULL DEFAULT 0.00,
  `retired` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_career_totals`
--

DROP TABLE IF EXISTS `ibl_playoff_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_playoff_career_totals` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `pts` int(11) NOT NULL DEFAULT 0,
  `retired` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_results`
--

DROP TABLE IF EXISTS `ibl_playoff_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_playoff_results` (
  `year` smallint(5) unsigned NOT NULL COMMENT 'Playoff year',
  `round` tinyint(3) unsigned NOT NULL COMMENT 'Playoff round',
  `winner` varchar(32) NOT NULL DEFAULT '',
  `loser` varchar(32) NOT NULL DEFAULT '',
  `loser_games` int(11) NOT NULL DEFAULT 0,
  `id` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `idx_year` (`year`),
  KEY `idx_round` (`round`)
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_stats`
--

DROP TABLE IF EXISTS `ibl_playoff_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_playoff_stats` (
  `year` int(11) NOT NULL DEFAULT 0,
  `pos` char(2) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `team` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  KEY `idx_year` (`year`),
  KEY `idx_team` (`team`),
  KEY `idx_name` (`name`),
  CONSTRAINT `fk_playoff_stats_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_plr`
--

DROP TABLE IF EXISTS `ibl_plr`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_plr` (
  `ordinal` int(11) DEFAULT 0,
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '' COMMENT 'Player name',
  `nickname` varchar(64) DEFAULT '',
  `age` tinyint(3) unsigned DEFAULT NULL,
  `peak` tinyint(3) unsigned DEFAULT NULL,
  `tid` int(11) NOT NULL DEFAULT 0 COMMENT 'Team ID (0 = free agent)',
  `teamname` varchar(32) DEFAULT '',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') NOT NULL DEFAULT '' COMMENT 'Player position',
  `sta` tinyint(3) unsigned DEFAULT 0 COMMENT 'Stamina rating',
  `oo` tinyint(3) unsigned DEFAULT 0 COMMENT 'Outside offense rating',
  `od` tinyint(3) unsigned DEFAULT 0 COMMENT 'Outside defense rating',
  `do` tinyint(3) unsigned DEFAULT 0 COMMENT 'Inside offense rating',
  `dd` tinyint(3) unsigned DEFAULT 0 COMMENT 'Inside defense rating',
  `po` tinyint(3) unsigned DEFAULT 0 COMMENT 'Post offense rating',
  `pd` tinyint(3) unsigned DEFAULT 0 COMMENT 'Post defense rating',
  `to` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition offense rating',
  `td` tinyint(3) unsigned DEFAULT 0 COMMENT 'Transition defense rating',
  `Clutch` varchar(32) DEFAULT '',
  `Consistency` varchar(32) DEFAULT '',
  `PGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Point guard depth',
  `SGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Shooting guard depth',
  `SFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Small forward depth',
  `PFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Power forward depth',
  `CDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'Center depth',
  `active` tinyint(1) DEFAULT NULL,
  `dc_PGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC point guard depth',
  `dc_SGDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC shooting guard depth',
  `dc_SFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC small forward depth',
  `dc_PFDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC power forward depth',
  `dc_CDepth` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC center depth',
  `dc_active` tinyint(3) unsigned DEFAULT 1 COMMENT 'DC active flag',
  `dc_minutes` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC minutes',
  `dc_of` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC offensive focus',
  `dc_df` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC defensive focus',
  `dc_oi` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC offensive importance',
  `dc_di` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC defensive importance',
  `dc_bh` tinyint(3) unsigned DEFAULT 0 COMMENT 'DC ball handling',
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
  `coach` varchar(16) DEFAULT '',
  `loyalty` varchar(16) DEFAULT '',
  `playingTime` varchar(16) DEFAULT '',
  `winner` varchar(16) DEFAULT '',
  `tradition` varchar(16) DEFAULT '',
  `security` varchar(16) DEFAULT '',
  `exp` tinyint(3) unsigned DEFAULT 0 COMMENT 'Years of experience',
  `bird` tinyint(1) DEFAULT NULL,
  `cy` int(11) DEFAULT 0,
  `cyt` int(11) DEFAULT 0,
  `cy1` int(11) DEFAULT 0,
  `cy2` int(11) DEFAULT 0,
  `cy3` int(11) DEFAULT 0,
  `cy4` int(11) DEFAULT 0,
  `cy5` int(11) DEFAULT 0,
  `cy6` int(11) DEFAULT 0,
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
  `draftedby` varchar(32) DEFAULT '',
  `draftedbycurrentname` varchar(32) DEFAULT '',
  `draftyear` smallint(5) unsigned DEFAULT 0 COMMENT 'Draft year',
  `draftpickno` tinyint(3) unsigned DEFAULT 0 COMMENT 'Pick number in round',
  `injured` tinyint(1) DEFAULT NULL,
  `htft` varchar(8) DEFAULT '',
  `htin` varchar(8) DEFAULT '',
  `wt` varchar(8) DEFAULT '',
  `retired` tinyint(1) DEFAULT NULL,
  `college` varchar(48) DEFAULT '',
  `car_playoff_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career playoff minutes',
  `car_preseason_min` mediumint(8) unsigned DEFAULT 0 COMMENT 'Career preseason minutes',
  `droptime` int(11) DEFAULT 0,
  `temp` int(11) DEFAULT 0 COMMENT '2028 Playoff Mins',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`pid`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
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
  CONSTRAINT `chk_plr_cy` CHECK (`cy` >= 0 and `cy` <= 6),
  CONSTRAINT `chk_plr_cyt` CHECK (`cyt` >= 0 and `cyt` <= 6),
  CONSTRAINT `chk_plr_cy1` CHECK (`cy1` >= -7000 and `cy1` <= 7000),
  CONSTRAINT `chk_plr_cy2` CHECK (`cy2` >= -7000 and `cy2` <= 7000),
  CONSTRAINT `chk_plr_cy3` CHECK (`cy3` >= -7000 and `cy3` <= 7000),
  CONSTRAINT `chk_plr_cy4` CHECK (`cy4` >= -7000 and `cy4` <= 7000),
  CONSTRAINT `chk_plr_cy5` CHECK (`cy5` >= -7000 and `cy5` <= 7000),
  CONSTRAINT `chk_plr_cy6` CHECK (`cy6` >= -7000 and `cy6` <= 7000),
  CONSTRAINT `chk_plr_tid` CHECK (`tid` >= 0 and `tid` <= 32)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_plr_chunk`
--

DROP TABLE IF EXISTS `ibl_plr_chunk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_plr_chunk` (
  `active` int(11) NOT NULL DEFAULT 0,
  `pid` int(11) NOT NULL DEFAULT 0,
  `ordinal` int(11) NOT NULL,
  `name` varchar(32) NOT NULL,
  `tid` int(11) NOT NULL DEFAULT 0,
  `teamname` varchar(32) NOT NULL DEFAULT '',
  `pos` varchar(4) NOT NULL,
  `altpos` varchar(4) NOT NULL DEFAULT '',
  `stats_gs` int(11) NOT NULL DEFAULT 0,
  `stats_gm` int(11) NOT NULL DEFAULT 0,
  `stats_min` int(11) NOT NULL DEFAULT 0,
  `stats_fgm` int(11) NOT NULL DEFAULT 0,
  `stats_fga` int(11) NOT NULL DEFAULT 0,
  `stats_ftm` int(11) NOT NULL DEFAULT 0,
  `stats_fta` int(11) NOT NULL DEFAULT 0,
  `stats_3gm` int(11) NOT NULL DEFAULT 0,
  `stats_3ga` int(11) NOT NULL DEFAULT 0,
  `stats_orb` int(11) NOT NULL DEFAULT 0,
  `stats_drb` int(11) NOT NULL DEFAULT 0,
  `stats_ast` int(11) NOT NULL DEFAULT 0,
  `stats_stl` int(11) NOT NULL DEFAULT 0,
  `stats_to` int(11) NOT NULL DEFAULT 0,
  `stats_blk` int(11) NOT NULL DEFAULT 0,
  `stats_pf` int(11) NOT NULL DEFAULT 0,
  `chunk` int(11) DEFAULT NULL,
  `qa` decimal(11,2) NOT NULL DEFAULT 0.00,
  `Season` int(11) NOT NULL,
  KEY `pid` (`pid`),
  KEY `pid_2` (`pid`) USING BTREE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_power`
--

DROP TABLE IF EXISTS `ibl_power`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_power` (
  `TeamID` smallint(6) NOT NULL DEFAULT 0,
  `Team` varchar(20) NOT NULL DEFAULT '',
  `Division` varchar(20) NOT NULL DEFAULT '',
  `Conference` varchar(20) NOT NULL DEFAULT '',
  `ranking` decimal(6,1) NOT NULL DEFAULT 0.0,
  `win` smallint(6) NOT NULL DEFAULT 0,
  `loss` smallint(6) NOT NULL DEFAULT 0,
  `gb` decimal(6,1) NOT NULL DEFAULT 0.0,
  `conf_win` int(11) NOT NULL,
  `conf_loss` int(11) NOT NULL,
  `div_win` int(11) NOT NULL,
  `div_loss` int(11) NOT NULL,
  `home_win` int(11) NOT NULL,
  `home_loss` int(11) NOT NULL,
  `road_win` int(11) NOT NULL,
  `road_loss` int(11) NOT NULL,
  `last_win` int(11) NOT NULL,
  `last_loss` int(11) NOT NULL,
  `streak_type` varchar(1) NOT NULL DEFAULT '',
  `streak` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`Team`),
  CONSTRAINT `fk_power_team` FOREIGN KEY (`Team`) REFERENCES `ibl_team_info` (`team_name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_power_ranking` CHECK (`ranking` is null or `ranking` >= 0.0 and `ranking` <= 100.0)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_schedule`
--

DROP TABLE IF EXISTS `ibl_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_schedule` (
  `Year` smallint(5) unsigned NOT NULL COMMENT 'Season year',
  `BoxID` int(11) NOT NULL DEFAULT 0,
  `Date` date NOT NULL,
  `Visitor` smallint(5) unsigned NOT NULL COMMENT 'Visiting team ID',
  `VScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  `Home` smallint(5) unsigned NOT NULL COMMENT 'Home team ID',
  `HScore` tinyint(3) unsigned NOT NULL DEFAULT 0 COMMENT 'Home score',
  `SchedID` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`SchedID`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `BoxID` (`BoxID`),
  KEY `idx_year` (`Year`),
  KEY `idx_date` (`Date`),
  KEY `idx_visitor` (`Visitor`),
  KEY `idx_home` (`Home`),
  KEY `idx_year_date` (`Year`,`Date`),
  CONSTRAINT `chk_schedule_visitor_id` CHECK (`Visitor` >= 1 and `Visitor` <= 32),
  CONSTRAINT `chk_schedule_home_id` CHECK (`Home` >= 1 and `Home` <= 32),
  CONSTRAINT `chk_schedule_vscore` CHECK (`VScore` >= 0 and `VScore` <= 200),
  CONSTRAINT `chk_schedule_hscore` CHECK (`HScore` >= 0 and `HScore` <= 200)
) ENGINE=InnoDB AUTO_INCREMENT=1252 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_season_career_avgs`
--

DROP TABLE IF EXISTS `ibl_season_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_season_career_avgs` (
  `pid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` decimal(8,2) NOT NULL DEFAULT 0.00,
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT 0.000,
  `orb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `reb` decimal(8,2) NOT NULL DEFAULT 0.00,
  `ast` decimal(8,2) NOT NULL DEFAULT 0.00,
  `stl` decimal(8,2) NOT NULL DEFAULT 0.00,
  `tvr` decimal(8,2) NOT NULL DEFAULT 0.00,
  `blk` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pf` decimal(8,2) NOT NULL DEFAULT 0.00,
  `pts` decimal(8,2) NOT NULL DEFAULT 0.00,
  `retired` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_settings`
--

DROP TABLE IF EXISTS `ibl_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_settings` (
  `name` varchar(128) NOT NULL,
  `value` varchar(128) NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_sim_dates`
--

DROP TABLE IF EXISTS `ibl_sim_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_sim_dates` (
  `Sim` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `Start Date` varchar(11) DEFAULT NULL,
  `End Date` varchar(11) DEFAULT NULL,
  PRIMARY KEY (`Sim`)
) ENGINE=InnoDB AUTO_INCREMENT=663 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_standings`
--

DROP TABLE IF EXISTS `ibl_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_standings` (
  `tid` int(11) NOT NULL,
  `team_name` varchar(16) NOT NULL DEFAULT '',
  `pct` float(4,3) unsigned DEFAULT NULL,
  `leagueRecord` varchar(5) DEFAULT '',
  `conference` enum('Eastern','Western','') DEFAULT '' COMMENT 'Conference affiliation',
  `confRecord` varchar(5) NOT NULL DEFAULT '',
  `confGB` decimal(3,1) DEFAULT NULL,
  `division` varchar(16) DEFAULT '',
  `divRecord` varchar(5) NOT NULL DEFAULT '',
  `divGB` decimal(3,1) DEFAULT NULL,
  `homeRecord` varchar(5) NOT NULL DEFAULT '',
  `awayRecord` varchar(5) NOT NULL DEFAULT '',
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
  `clinchedConference` tinyint(1) DEFAULT NULL,
  `clinchedDivision` tinyint(1) DEFAULT NULL,
  `clinchedPlayoffs` tinyint(1) DEFAULT NULL,
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
  `year` varchar(35) NOT NULL,
  `name` varchar(35) NOT NULL,
  `Award` varchar(350) NOT NULL,
  `ID` int(11) NOT NULL,
  PRIMARY KEY (`ID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_defense_stats`
--

DROP TABLE IF EXISTS `ibl_team_defense_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_defense_stats` (
  `teamID` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) DEFAULT 0,
  KEY `idx_teamID` (`teamID`),
  CONSTRAINT `fk_team_defense_team` FOREIGN KEY (`teamID`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_history`
--

DROP TABLE IF EXISTS `ibl_team_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_history` (
  `teamid` int(11) NOT NULL DEFAULT 0,
  `team_city` varchar(24) NOT NULL DEFAULT '',
  `team_name` varchar(16) NOT NULL DEFAULT '',
  `color1` varchar(6) NOT NULL DEFAULT '',
  `color2` varchar(6) NOT NULL DEFAULT '',
  `depth` varchar(100) NOT NULL,
  `sim_depth` varchar(100) NOT NULL,
  `asg_vote` varchar(100) NOT NULL,
  `eoy_vote` varchar(100) NOT NULL,
  `totwins` int(11) NOT NULL,
  `totloss` int(11) NOT NULL,
  `winpct` float(4,3) NOT NULL,
  `playoffs` int(11) NOT NULL,
  `div_titles` int(11) NOT NULL,
  `conf_titles` int(11) NOT NULL,
  `ibl_titles` int(11) NOT NULL,
  `heat_titles` int(11) NOT NULL,
  PRIMARY KEY (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_info`
--

DROP TABLE IF EXISTS `ibl_team_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_info` (
  `teamid` int(11) NOT NULL DEFAULT 0,
  `team_city` varchar(24) NOT NULL DEFAULT '',
  `team_name` varchar(16) NOT NULL DEFAULT '',
  `color1` varchar(6) NOT NULL DEFAULT '',
  `color2` varchar(6) NOT NULL DEFAULT '',
  `arena` varchar(255) NOT NULL DEFAULT '',
  `owner_name` varchar(32) NOT NULL DEFAULT '',
  `owner_email` varchar(48) NOT NULL DEFAULT '',
  `discordID` bigint(20) unsigned DEFAULT NULL,
  `skype` varchar(16) NOT NULL,
  `aim` varchar(48) NOT NULL DEFAULT '',
  `msn` varchar(48) NOT NULL DEFAULT '',
  `formerly_known_as` varchar(255) DEFAULT NULL,
  `Contract_Wins` int(11) NOT NULL DEFAULT 0,
  `Contract_Losses` int(11) NOT NULL DEFAULT 0,
  `Contract_AvgW` int(11) NOT NULL DEFAULT 0,
  `Contract_AvgL` int(11) NOT NULL DEFAULT 0,
  `Contract_Coach` decimal(3,2) NOT NULL DEFAULT 0.00,
  `Used_Extension_This_Chunk` int(11) NOT NULL DEFAULT 0,
  `Used_Extension_This_Season` int(11) DEFAULT 0,
  `HasMLE` int(11) NOT NULL DEFAULT 0,
  `HasLLE` int(11) NOT NULL DEFAULT 0,
  `chart` char(2) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `uuid` char(36) NOT NULL,
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `uuid` (`uuid`),
  UNIQUE KEY `idx_uuid` (`uuid`),
  KEY `team_name` (`team_name`),
  KEY `idx_owner_email` (`owner_email`),
  KEY `idx_discordID` (`discordID`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_offense_stats`
--

DROP TABLE IF EXISTS `ibl_team_offense_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_offense_stats` (
  `teamID` int(11) NOT NULL DEFAULT 0,
  `name` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) DEFAULT 0,
  KEY `idx_teamID` (`teamID`),
  CONSTRAINT `fk_team_offense_team` FOREIGN KEY (`teamID`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_win_loss`
--

DROP TABLE IF EXISTS `ibl_team_win_loss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_team_win_loss` (
  `year` varchar(75) NOT NULL DEFAULT '0',
  `currentname` varchar(16) NOT NULL DEFAULT '',
  `namethatyear` varchar(40) NOT NULL,
  `wins` varchar(75) NOT NULL DEFAULT '0',
  `losses` varchar(75) NOT NULL DEFAULT '0',
  `table_ID` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=775 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_autocounter`
--

DROP TABLE IF EXISTS `ibl_trade_autocounter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_autocounter` (
  `counter` int(11) NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`counter`)
) ENGINE=InnoDB AUTO_INCREMENT=11925 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_cash`
--

DROP TABLE IF EXISTS `ibl_trade_cash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_cash` (
  `tradeOfferID` int(11) NOT NULL,
  `sendingTeam` varchar(16) NOT NULL DEFAULT '',
  `receivingTeam` varchar(16) NOT NULL DEFAULT '',
  `cy1` int(11) DEFAULT NULL,
  `cy2` int(11) DEFAULT NULL,
  `cy3` int(11) DEFAULT NULL,
  `cy4` int(11) DEFAULT NULL,
  `cy5` int(11) DEFAULT NULL,
  `cy6` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_info`
--

DROP TABLE IF EXISTS `ibl_trade_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_info` (
  `tradeofferid` int(11) NOT NULL DEFAULT 0,
  `itemid` int(11) NOT NULL DEFAULT 0,
  `itemtype` varchar(128) NOT NULL DEFAULT '',
  `from` varchar(128) NOT NULL DEFAULT '',
  `to` varchar(128) NOT NULL DEFAULT '',
  `approval` varchar(128) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  KEY `idx_tradeofferid` (`tradeofferid`),
  KEY `idx_from` (`from`),
  KEY `idx_to` (`to`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_queue`
--

DROP TABLE IF EXISTS `ibl_trade_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_trade_queue` (
  `query` text NOT NULL,
  `tradeline` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_votes_ASG`
--

DROP TABLE IF EXISTS `ibl_votes_ASG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `ibl_votes_ASG` (
  `teamid` int(11) NOT NULL DEFAULT 0,
  `team_city` varchar(24) NOT NULL DEFAULT '',
  `team_name` varchar(16) NOT NULL DEFAULT '',
  `East_F1` varchar(255) DEFAULT NULL,
  `East_F2` varchar(255) DEFAULT NULL,
  `East_F3` varchar(255) DEFAULT NULL,
  `East_F4` varchar(255) DEFAULT NULL,
  `East_B1` varchar(255) DEFAULT NULL,
  `East_B2` varchar(255) DEFAULT NULL,
  `East_B3` varchar(255) DEFAULT NULL,
  `East_B4` varchar(255) DEFAULT NULL,
  `West_F1` varchar(255) DEFAULT NULL,
  `West_F2` varchar(255) DEFAULT NULL,
  `West_F3` varchar(255) DEFAULT NULL,
  `West_F4` varchar(255) DEFAULT NULL,
  `West_B1` varchar(255) DEFAULT NULL,
  `West_B2` varchar(255) DEFAULT NULL,
  `West_B3` varchar(255) DEFAULT NULL,
  `West_B4` varchar(255) DEFAULT NULL,
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
  `teamid` int(11) NOT NULL DEFAULT 0,
  `team_city` varchar(24) NOT NULL DEFAULT '',
  `team_name` varchar(16) NOT NULL DEFAULT '',
  `MVP_1` varchar(255) DEFAULT NULL,
  `MVP_2` varchar(255) DEFAULT NULL,
  `MVP_3` varchar(255) DEFAULT NULL,
  `Six_1` varchar(255) DEFAULT NULL,
  `Six_2` varchar(255) DEFAULT NULL,
  `Six_3` varchar(255) DEFAULT NULL,
  `ROY_1` varchar(255) DEFAULT NULL,
  `ROY_2` varchar(255) DEFAULT NULL,
  `ROY_3` varchar(255) DEFAULT NULL,
  `GM_1` varchar(255) DEFAULT NULL,
  `GM_2` varchar(255) DEFAULT NULL,
  `GM_3` varchar(255) DEFAULT NULL,
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
-- Table structure for table `nuke_banner`
--

DROP TABLE IF EXISTS `nuke_banner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banner` (
  `bid` int(11) NOT NULL AUTO_INCREMENT,
  `cid` int(11) NOT NULL DEFAULT 0,
  `name` varchar(50) NOT NULL DEFAULT '',
  `imptotal` int(11) NOT NULL DEFAULT 0,
  `impmade` int(11) NOT NULL DEFAULT 0,
  `clicks` int(11) NOT NULL DEFAULT 0,
  `imageurl` varchar(100) NOT NULL DEFAULT '',
  `clickurl` varchar(200) NOT NULL DEFAULT '',
  `alttext` varchar(255) NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `dateend` datetime DEFAULT NULL,
  `position` int(11) NOT NULL DEFAULT 0,
  `active` tinyint(1) NOT NULL DEFAULT 1,
  `ad_class` varchar(5) NOT NULL DEFAULT '',
  `ad_code` mediumtext NOT NULL,
  `ad_width` int(11) DEFAULT 0,
  `ad_height` int(11) DEFAULT 0,
  PRIMARY KEY (`bid`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_clients`
--

DROP TABLE IF EXISTS `nuke_banner_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banner_clients` (
  `cid` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(60) NOT NULL DEFAULT '',
  `contact` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(60) NOT NULL DEFAULT '',
  `login` varchar(10) NOT NULL DEFAULT '',
  `passwd` varchar(10) NOT NULL DEFAULT '',
  `extrainfo` mediumtext NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_plans`
--

DROP TABLE IF EXISTS `nuke_banner_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banner_plans` (
  `pid` int(11) NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT 0,
  `name` varchar(255) NOT NULL DEFAULT '',
  `description` mediumtext NOT NULL,
  `delivery` varchar(10) NOT NULL DEFAULT '',
  `delivery_type` varchar(25) NOT NULL DEFAULT '',
  `price` varchar(25) NOT NULL DEFAULT '0',
  `buy_links` mediumtext NOT NULL,
  PRIMARY KEY (`pid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_positions`
--

DROP TABLE IF EXISTS `nuke_banner_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banner_positions` (
  `apid` int(11) NOT NULL AUTO_INCREMENT,
  `position_number` int(11) NOT NULL DEFAULT 0,
  `position_name` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`apid`),
  KEY `position_number` (`position_number`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_terms`
--

DROP TABLE IF EXISTS `nuke_banner_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_banner_terms` (
  `terms_body` mediumtext NOT NULL,
  `country` varchar(255) NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Table structure for table `nuke_cities`
--

DROP TABLE IF EXISTS `nuke_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_cities` (
  `id` mediumint(9) NOT NULL DEFAULT 0,
  `local_id` mediumint(9) NOT NULL DEFAULT 0,
  `city` varchar(65) NOT NULL DEFAULT '',
  `cc` char(2) NOT NULL DEFAULT '',
  `country` varchar(35) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Table structure for table `nuke_comments_moderated`
--

DROP TABLE IF EXISTS `nuke_comments_moderated`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_comments_moderated` (
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
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Table structure for table `nuke_confirm`
--

DROP TABLE IF EXISTS `nuke_confirm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_confirm` (
  `confirm_id` char(32) NOT NULL DEFAULT '',
  `session_id` char(32) NOT NULL DEFAULT '',
  `code` char(6) NOT NULL DEFAULT '',
  PRIMARY KEY (`session_id`,`confirm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Table structure for table `nuke_faqanswer`
--

DROP TABLE IF EXISTS `nuke_faqanswer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_faqanswer` (
  `id` tinyint(4) NOT NULL AUTO_INCREMENT,
  `id_cat` tinyint(4) NOT NULL DEFAULT 0,
  `question` varchar(255) DEFAULT '',
  `answer` mediumtext DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `id_cat` (`id_cat`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_faqcategories`
--

DROP TABLE IF EXISTS `nuke_faqcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_faqcategories` (
  `id_cat` tinyint(4) NOT NULL AUTO_INCREMENT,
  `categories` varchar(255) DEFAULT NULL,
  `flanguage` varchar(30) NOT NULL DEFAULT '',
  PRIMARY KEY (`id_cat`)
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
-- Table structure for table `nuke_links_editorials`
--

DROP TABLE IF EXISTS `nuke_links_editorials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_editorials` (
  `linkid` int(11) NOT NULL DEFAULT 0,
  `adminid` varchar(60) NOT NULL DEFAULT '',
  `editorialtimestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `editorialtext` mediumtext NOT NULL,
  `editorialtitle` varchar(100) NOT NULL DEFAULT '',
  PRIMARY KEY (`linkid`)
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
-- Table structure for table `nuke_links_votedata`
--

DROP TABLE IF EXISTS `nuke_links_votedata`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_links_votedata` (
  `ratingdbid` int(11) NOT NULL AUTO_INCREMENT,
  `ratinglid` int(11) NOT NULL DEFAULT 0,
  `ratinguser` varchar(60) NOT NULL DEFAULT '',
  `rating` int(11) NOT NULL DEFAULT 0,
  `ratinghostname` varchar(60) NOT NULL DEFAULT '',
  `ratingcomments` mediumtext NOT NULL,
  `ratingtimestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ratingdbid`)
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
) ENGINE=MyISAM AUTO_INCREMENT=140 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
-- Table structure for table `nuke_pollcomments`
--

DROP TABLE IF EXISTS `nuke_pollcomments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_pollcomments` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `pollID` int(11) NOT NULL DEFAULT 0,
  `date` datetime DEFAULT NULL,
  `name` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(60) DEFAULT NULL,
  `url` varchar(60) DEFAULT NULL,
  `host_name` varchar(60) DEFAULT NULL,
  `subject` varchar(60) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `score` tinyint(4) NOT NULL DEFAULT 0,
  `reason` tinyint(4) NOT NULL DEFAULT 0,
  `last_moderation_ip` varchar(15) DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `pid` (`pid`),
  KEY `pollID` (`pollID`)
) ENGINE=MyISAM AUTO_INCREMENT=64 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pollcomments_moderated`
--

DROP TABLE IF EXISTS `nuke_pollcomments_moderated`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_pollcomments_moderated` (
  `tid` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL DEFAULT 0,
  `pollID` int(11) NOT NULL DEFAULT 0,
  `date` datetime DEFAULT NULL,
  `name` varchar(60) NOT NULL DEFAULT '',
  `email` varchar(60) DEFAULT NULL,
  `url` varchar(60) DEFAULT NULL,
  `host_name` varchar(60) DEFAULT NULL,
  `subject` varchar(60) NOT NULL DEFAULT '',
  `comment` mediumtext NOT NULL,
  `score` tinyint(4) NOT NULL DEFAULT 0,
  `reason` tinyint(4) NOT NULL DEFAULT 0,
  `last_moderation_ip` varchar(15) DEFAULT '0',
  PRIMARY KEY (`tid`),
  KEY `pid` (`pid`),
  KEY `pollID` (`pollID`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_public_messages`
--

DROP TABLE IF EXISTS `nuke_public_messages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_public_messages` (
  `mid` int(11) NOT NULL AUTO_INCREMENT,
  `content` varchar(255) NOT NULL DEFAULT '',
  `date` varchar(14) DEFAULT NULL,
  `who` varchar(25) NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=MyISAM AUTO_INCREMENT=40201 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
) ENGINE=MyISAM AUTO_INCREMENT=4166 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
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
  `user_avatar` varchar(255) NOT NULL DEFAULT '',
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
  `user_password` varchar(40) NOT NULL DEFAULT '',
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
) ENGINE=MyISAM AUTO_INCREMENT=774 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_users_temp`
--

DROP TABLE IF EXISTS `nuke_users_temp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `nuke_users_temp` (
  `user_id` int(11) NOT NULL AUTO_INCREMENT,
  `username` varchar(25) NOT NULL DEFAULT '',
  `user_email` varchar(255) NOT NULL DEFAULT '',
  `user_password` varchar(40) NOT NULL DEFAULT '',
  `user_regdate` varchar(20) NOT NULL DEFAULT '',
  `check_num` varchar(50) NOT NULL DEFAULT '',
  `time` varchar(14) NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=10948 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olympic_stats`
--

DROP TABLE IF EXISTS `olympic_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `olympic_stats` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `year` int(11) NOT NULL DEFAULT 0,
  `pos` char(2) NOT NULL DEFAULT '',
  `name` varchar(32) NOT NULL DEFAULT '',
  `team` varchar(32) NOT NULL DEFAULT '',
  `games` int(11) NOT NULL DEFAULT 0,
  `minutes` int(11) NOT NULL DEFAULT 0,
  `fgm` int(11) NOT NULL DEFAULT 0,
  `fga` int(11) NOT NULL DEFAULT 0,
  `ftm` int(11) NOT NULL DEFAULT 0,
  `fta` int(11) NOT NULL DEFAULT 0,
  `tgm` int(11) NOT NULL DEFAULT 0,
  `tga` int(11) NOT NULL DEFAULT 0,
  `orb` int(11) NOT NULL DEFAULT 0,
  `reb` int(11) NOT NULL DEFAULT 0,
  `ast` int(11) NOT NULL DEFAULT 0,
  `stl` int(11) NOT NULL DEFAULT 0,
  `tvr` int(11) NOT NULL DEFAULT 0,
  `blk` int(11) NOT NULL DEFAULT 0,
  `pf` int(11) NOT NULL DEFAULT 0,
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `online`
--

DROP TABLE IF EXISTS `online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `online` (
  `username` mediumtext NOT NULL,
  `timeout` mediumtext NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

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
-- Table structure for table `poll`
--

DROP TABLE IF EXISTS `poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `poll` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `questions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `pid` int(11) NOT NULL,
  `question` varchar(255) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `responses`
--

DROP TABLE IF EXISTS `responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `responses` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `qid` int(11) NOT NULL,
  `ip` varchar(16) NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `sessions` (
  `id` varchar(255) NOT NULL,
  `user_id` bigint(20) unsigned DEFAULT NULL,
  `ip_address` varchar(45) DEFAULT NULL,
  `user_agent` text DEFAULT NULL,
  `payload` longtext NOT NULL,
  `last_activity` int(11) NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_online`
--

DROP TABLE IF EXISTS `user_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `user_online` (
  `session` char(100) NOT NULL DEFAULT '',
  `time` int(11) NOT NULL DEFAULT 0
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!40101 SET character_set_client = utf8 */;
CREATE TABLE `users` (
  `id` bigint(20) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) NOT NULL,
  `remember_token` varchar(100) DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Dumping routines for database 'iblhoops_ibl5'
--
/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;

/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;
/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;
/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;
/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;
/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;

-- Dump completed on 2025-11-12 21:27:11
