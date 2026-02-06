-- MySQL dump 10.13  Distrib 8.0.40, for macos12.7 (arm64)
--
-- Host: localhost    Database: iblhoops_ibl5
-- ------------------------------------------------------
-- Server version	8.0.40

/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!50503 SET NAMES utf8mb4 */;
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `cache_locks`
--

DROP TABLE IF EXISTS `cache_locks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `cache_locks` (
  `key` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `owner` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `expiration` int NOT NULL,
  PRIMARY KEY (`key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `failed_jobs`
--

DROP TABLE IF EXISTS `failed_jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `failed_jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `uuid` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `connection` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `queue` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `exception` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `failed_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `failed_jobs_uuid_unique` (`uuid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_awards`
--

DROP TABLE IF EXISTS `ibl_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_awards` (
  `year` int NOT NULL DEFAULT '0',
  `Award` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `table_ID` int NOT NULL AUTO_INCREMENT,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_banners` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL DEFAULT '0',
  `currentname` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bannername` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bannertype` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores`
--

DROP TABLE IF EXISTS `ibl_box_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_box_scores` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Date` date NOT NULL,
  `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `pos` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `pid` int DEFAULT NULL,
  `visitorTID` int DEFAULT NULL,
  `homeTID` int DEFAULT NULL,
  `gameMIN` tinyint unsigned DEFAULT NULL COMMENT 'Minutes played',
  `game2GM` tinyint unsigned DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` tinyint unsigned DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` tinyint unsigned DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` tinyint unsigned DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` tinyint unsigned DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` tinyint unsigned DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` tinyint unsigned DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` tinyint unsigned DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` tinyint unsigned DEFAULT NULL COMMENT 'Assists',
  `gameSTL` tinyint unsigned DEFAULT NULL COMMENT 'Steals',
  `gameTOV` tinyint unsigned DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` tinyint unsigned DEFAULT NULL COMMENT 'Blocks',
  `gamePF` tinyint unsigned DEFAULT NULL COMMENT 'Personal fouls',
  `game_type` tinyint unsigned GENERATED ALWAYS AS ((case when (month(`Date`) = 6) then 2 when (month(`Date`) = 10) then 3 when (month(`Date`) = 0) then 0 else 1 end)) STORED,
  `season_year` smallint unsigned GENERATED ALWAYS AS ((case when (year(`Date`) = 0) then 0 when (month(`Date`) >= 10) then (year(`Date`) + 1) else year(`Date`) end)) STORED,
  `calc_points` smallint unsigned GENERATED ALWAYS AS ((((`game2GM` * 2) + `gameFTM`) + (`game3GM` * 3))) STORED,
  `calc_rebounds` tinyint unsigned GENERATED ALWAYS AS ((`gameORB` + `gameDRB`)) STORED,
  `calc_fg_made` tinyint unsigned GENERATED ALWAYS AS ((`game2GM` + `game3GM`)) STORED,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `fk_boxscore_home` FOREIGN KEY (`homeTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscore_visitor` FOREIGN KEY (`visitorTID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `chk_box_minutes` CHECK (((`gameMIN` is null) or ((`gameMIN` >= 0) and (`gameMIN` <= 70))))
) ENGINE=InnoDB AUTO_INCREMENT=671668 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_box_scores_teams`
--

DROP TABLE IF EXISTS `ibl_box_scores_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_box_scores_teams` (
  `id` int NOT NULL AUTO_INCREMENT,
  `Date` date NOT NULL,
  `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `gameOfThatDay` int DEFAULT NULL,
  `visitorTeamID` int DEFAULT NULL,
  `homeTeamID` int DEFAULT NULL,
  `attendance` int DEFAULT NULL,
  `capacity` int DEFAULT NULL,
  `visitorWins` int DEFAULT NULL,
  `visitorLosses` int DEFAULT NULL,
  `homeWins` int DEFAULT NULL,
  `homeLosses` int DEFAULT NULL,
  `visitorQ1points` int DEFAULT NULL,
  `visitorQ2points` int DEFAULT NULL,
  `visitorQ3points` int DEFAULT NULL,
  `visitorQ4points` int DEFAULT NULL,
  `visitorOTpoints` int DEFAULT NULL,
  `homeQ1points` int DEFAULT NULL,
  `homeQ2points` int DEFAULT NULL,
  `homeQ3points` int DEFAULT NULL,
  `homeQ4points` int DEFAULT NULL,
  `homeOTpoints` int DEFAULT NULL,
  `gameMIN` int DEFAULT NULL,
  `game2GM` int DEFAULT NULL,
  `game2GA` int DEFAULT NULL,
  `gameFTM` int DEFAULT NULL,
  `gameFTA` int DEFAULT NULL,
  `game3GM` int DEFAULT NULL,
  `game3GA` int DEFAULT NULL,
  `gameORB` int DEFAULT NULL,
  `gameDRB` int DEFAULT NULL,
  `gameAST` int DEFAULT NULL,
  `gameSTL` int DEFAULT NULL,
  `gameTOV` int DEFAULT NULL,
  `gameBLK` int DEFAULT NULL,
  `gamePF` int DEFAULT NULL,
  `game_type` tinyint unsigned GENERATED ALWAYS AS ((case when (month(`Date`) = 6) then 2 when (month(`Date`) = 10) then 3 when (month(`Date`) = 0) then 0 else 1 end)) STORED,
  `calc_points` smallint unsigned GENERATED ALWAYS AS ((((`game2GM` * 2) + `gameFTM`) + (`game3GM` * 3))) STORED,
  `calc_rebounds` smallint unsigned GENERATED ALWAYS AS ((`gameORB` + `gameDRB`)) STORED,
  `calc_fg_made` smallint unsigned GENERATED ALWAYS AS ((`game2GM` + `game3GM`)) STORED,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
  CONSTRAINT `fk_boxscoreteam_home` FOREIGN KEY (`homeTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  CONSTRAINT `fk_boxscoreteam_visitor` FOREIGN KEY (`visitorTeamID`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=14536 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_demands`
--

DROP TABLE IF EXISTS `ibl_demands`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_demands` (
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `dem1` int NOT NULL DEFAULT '0',
  `dem2` int NOT NULL DEFAULT '0',
  `dem3` int NOT NULL DEFAULT '0',
  `dem4` int NOT NULL DEFAULT '0',
  `dem5` int NOT NULL DEFAULT '0',
  `dem6` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`name`),
  CONSTRAINT `fk_demands_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_draft`
--

DROP TABLE IF EXISTS `ibl_draft`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_draft` (
  `draft_id` int NOT NULL AUTO_INCREMENT,
  `year` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Draft year',
  `team` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `player` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `round` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Draft round',
  `pick` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Pick number',
  `date` datetime DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`draft_id`),
  UNIQUE KEY `uuid` (`uuid`),
  KEY `idx_year` (`year`),
  KEY `idx_team` (`team`),
  KEY `idx_player` (`player`),
  KEY `idx_year_round` (`year`,`round`),
  KEY `idx_year_round_pick` (`year`,`round`,`pick`),
  CONSTRAINT `fk_draft_team` FOREIGN KEY (`team`) REFERENCES `ibl_team_info` (`team_name`) ON UPDATE CASCADE,
  CONSTRAINT `chk_draft_pick` CHECK (((`pick` >= 0) and (`pick` <= 32))),
  CONSTRAINT `chk_draft_round` CHECK (((`round` >= 0) and (`round` <= 7)))
) ENGINE=InnoDB AUTO_INCREMENT=57 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_draft_class`
--

DROP TABLE IF EXISTS `ibl_draft_class`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_draft_class` (
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Draft prospect position',
  `age` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Player age',
  `team` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `fga` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'FG attempts rating',
  `fgp` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'FG percentage rating',
  `fta` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'FT attempts rating',
  `ftp` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'FT percentage rating',
  `tga` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '3P attempts rating',
  `tgp` tinyint unsigned NOT NULL DEFAULT '0' COMMENT '3P percentage rating',
  `orb` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Off rebounds rating',
  `drb` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Def rebounds rating',
  `ast` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Assists rating',
  `stl` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Steals rating',
  `tvr` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Turnovers rating',
  `blk` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Blocks rating',
  `oo` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Off outside rating',
  `do` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Off drive rating',
  `po` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Off post rating',
  `to` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Off transition rating',
  `od` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Def outside rating',
  `dd` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Def drive rating',
  `pd` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Def post rating',
  `td` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Def transition rating',
  `talent` int NOT NULL DEFAULT '0',
  `skill` int NOT NULL DEFAULT '0',
  `intangibles` int NOT NULL DEFAULT '0',
  `ranking` float DEFAULT '0',
  `invite` mediumtext COLLATE utf8mb4_unicode_ci,
  `drafted` int DEFAULT '0',
  `sta` int DEFAULT '0',
  `id` int NOT NULL AUTO_INCREMENT,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_draft_picks` (
  `pickid` int NOT NULL AUTO_INCREMENT,
  `ownerofpick` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `teampick` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `year` smallint unsigned NOT NULL DEFAULT '0',
  `round` tinyint unsigned NOT NULL DEFAULT '0',
  `notes` varchar(280) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_fa_offers` (
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `offer1` int NOT NULL DEFAULT '0',
  `offer2` int NOT NULL DEFAULT '0',
  `offer3` int NOT NULL DEFAULT '0',
  `offer4` int NOT NULL DEFAULT '0',
  `offer5` int NOT NULL DEFAULT '0',
  `offer6` int NOT NULL DEFAULT '0',
  `modifier` float NOT NULL DEFAULT '0',
  `random` float NOT NULL DEFAULT '0',
  `perceivedvalue` float NOT NULL DEFAULT '0',
  `MLE` int NOT NULL DEFAULT '0',
  `LLE` int NOT NULL DEFAULT '0',
  `offer_type` int NOT NULL DEFAULT '0' COMMENT 'Offer type: 0=Custom, 1-6=MLE years, 7=LLE, 8=Vet Min',
  `primary_key` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`primary_key`),
  KEY `idx_name` (`name`),
  KEY `idx_team` (`team`),
  KEY `idx_offer_type` (`offer_type`),
  CONSTRAINT `fk_faoffer_player` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_faoffer_team` FOREIGN KEY (`team`) REFERENCES `ibl_team_info` (`team_name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_history`
--

DROP TABLE IF EXISTS `ibl_gm_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_gm_history` (
  `year` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Award` varchar(350) COLLATE utf8mb4_unicode_ci NOT NULL,
  `prim` int NOT NULL,
  PRIMARY KEY (`prim`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_career_avgs`
--

DROP TABLE IF EXISTS `ibl_heat_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_heat_career_avgs` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `orb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `reb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `ast` decimal(8,2) NOT NULL DEFAULT '0.00',
  `stl` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tvr` decimal(8,2) NOT NULL DEFAULT '0.00',
  `blk` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pf` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pts` decimal(8,2) NOT NULL DEFAULT '0.00',
  `retired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_career_totals`
--

DROP TABLE IF EXISTS `ibl_heat_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_heat_career_totals` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  `pts` int NOT NULL DEFAULT '0',
  `retired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_stats`
--

DROP TABLE IF EXISTS `ibl_heat_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_heat_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL DEFAULT '0',
  `pos` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  KEY `fk_heat_stats_name` (`name`),
  CONSTRAINT `fk_heat_stats_name` FOREIGN KEY (`name`) REFERENCES `ibl_plr` (`name`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=7858 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_heat_win_loss`
--

DROP TABLE IF EXISTS `ibl_heat_win_loss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_heat_win_loss` (
  `year` int unsigned NOT NULL DEFAULT '0',
  `currentname` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `namethatyear` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `wins` tinyint unsigned NOT NULL DEFAULT '0',
  `losses` tinyint unsigned NOT NULL DEFAULT '0',
  `table_ID` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=595 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_hist`
--

DROP TABLE IF EXISTS `ibl_hist`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_hist` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `year` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Season year',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `teamid` int NOT NULL DEFAULT '0',
  `games` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Games played',
  `minutes` mediumint unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes played',
  `fgm` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Field goals made',
  `fga` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Field goals attempted',
  `ftm` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Free throws made',
  `fta` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Free throws attempted',
  `tgm` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Three pointers made',
  `tga` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Three pointers attempted',
  `orb` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Offensive rebounds',
  `reb` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Total rebounds',
  `ast` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Assists',
  `stl` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Steals',
  `blk` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Blocks',
  `tvr` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Turnovers',
  `pf` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Personal fouls',
  `pts` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Points',
  `r_2ga` int NOT NULL DEFAULT '0',
  `r_2gp` int NOT NULL DEFAULT '0',
  `r_fta` int NOT NULL DEFAULT '0',
  `r_ftp` int NOT NULL DEFAULT '0',
  `r_3ga` int NOT NULL DEFAULT '0',
  `r_3gp` int NOT NULL DEFAULT '0',
  `r_orb` int NOT NULL DEFAULT '0',
  `r_drb` int NOT NULL DEFAULT '0',
  `r_ast` int NOT NULL DEFAULT '0',
  `r_stl` int NOT NULL DEFAULT '0',
  `r_blk` int NOT NULL DEFAULT '0',
  `r_tvr` int NOT NULL DEFAULT '0',
  `r_oo` int NOT NULL DEFAULT '0',
  `r_do` int NOT NULL DEFAULT '0',
  `r_po` int NOT NULL DEFAULT '0',
  `r_to` int NOT NULL DEFAULT '0',
  `r_od` int NOT NULL DEFAULT '0',
  `r_dd` int NOT NULL DEFAULT '0',
  `r_pd` int NOT NULL DEFAULT '0',
  `r_td` int NOT NULL DEFAULT '0',
  `salary` int NOT NULL DEFAULT '0',
  `nuke_iblhist` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`nuke_iblhist`),
  UNIQUE KEY `unique_composite_key` (`pid`,`name`,`year`),
  KEY `idx_pid_year` (`pid`,`year`),
  KEY `idx_team_year` (`team`,`year`),
  KEY `idx_teamid_year` (`teamid`,`year`),
  KEY `idx_year` (`year`),
  KEY `idx_pid_year_team` (`pid`,`year`,`team`),
  CONSTRAINT `fk_hist_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `fk_hist_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB AUTO_INCREMENT=29729 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_box_scores`
--

DROP TABLE IF EXISTS `ibl_olympics_box_scores`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_box_scores` (
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Player name',
  `pos` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Position played',
  `pid` int DEFAULT NULL COMMENT 'Player ID (references ibl_plr)',
  `visitorTID` int DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTID` int DEFAULT NULL COMMENT 'Home team ID',
  `gameMIN` tinyint unsigned DEFAULT NULL COMMENT 'Minutes played',
  `game2GM` tinyint unsigned DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` tinyint unsigned DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` tinyint unsigned DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` tinyint unsigned DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` tinyint unsigned DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` tinyint unsigned DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` tinyint unsigned DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` tinyint unsigned DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` tinyint unsigned DEFAULT NULL COMMENT 'Assists',
  `gameSTL` tinyint unsigned DEFAULT NULL COMMENT 'Steals',
  `gameTOV` tinyint unsigned DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` tinyint unsigned DEFAULT NULL COMMENT 'Blocks',
  `gamePF` tinyint unsigned DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public API identifier',
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
  CONSTRAINT `chk_olympics_box_minutes` CHECK (((`gameMIN` is null) or ((`gameMIN` >= 0) and (`gameMIN` <= 70))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Individual player statistics for Olympics games';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_box_scores_teams`
--

DROP TABLE IF EXISTS `ibl_olympics_box_scores_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_box_scores_teams` (
  `Date` date NOT NULL COMMENT 'Game date',
  `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Arena/venue name',
  `gameOfThatDay` int DEFAULT NULL COMMENT 'Game number for that date',
  `visitorTeamID` int DEFAULT NULL COMMENT 'Visiting team ID',
  `homeTeamID` int DEFAULT NULL COMMENT 'Home team ID',
  `attendance` int DEFAULT NULL COMMENT 'Game attendance',
  `capacity` int DEFAULT NULL COMMENT 'Arena capacity',
  `visitorWins` int DEFAULT NULL COMMENT 'Visitor team wins before game',
  `visitorLosses` int DEFAULT NULL COMMENT 'Visitor team losses before game',
  `homeWins` int DEFAULT NULL COMMENT 'Home team wins before game',
  `homeLosses` int DEFAULT NULL COMMENT 'Home team losses before game',
  `visitorQ1points` int DEFAULT NULL COMMENT 'Visitor Q1 points',
  `visitorQ2points` int DEFAULT NULL COMMENT 'Visitor Q2 points',
  `visitorQ3points` int DEFAULT NULL COMMENT 'Visitor Q3 points',
  `visitorQ4points` int DEFAULT NULL COMMENT 'Visitor Q4 points',
  `visitorOTpoints` int DEFAULT NULL COMMENT 'Visitor overtime points',
  `homeQ1points` int DEFAULT NULL COMMENT 'Home Q1 points',
  `homeQ2points` int DEFAULT NULL COMMENT 'Home Q2 points',
  `homeQ3points` int DEFAULT NULL COMMENT 'Home Q3 points',
  `homeQ4points` int DEFAULT NULL COMMENT 'Home Q4 points',
  `homeOTpoints` int DEFAULT NULL COMMENT 'Home overtime points',
  `gameMIN` int DEFAULT NULL COMMENT 'Total game minutes',
  `game2GM` int DEFAULT NULL COMMENT 'Field goals made',
  `game2GA` int DEFAULT NULL COMMENT 'Field goals attempted',
  `gameFTM` int DEFAULT NULL COMMENT 'Free throws made',
  `gameFTA` int DEFAULT NULL COMMENT 'Free throws attempted',
  `game3GM` int DEFAULT NULL COMMENT 'Three pointers made',
  `game3GA` int DEFAULT NULL COMMENT 'Three pointers attempted',
  `gameORB` int DEFAULT NULL COMMENT 'Offensive rebounds',
  `gameDRB` int DEFAULT NULL COMMENT 'Defensive rebounds',
  `gameAST` int DEFAULT NULL COMMENT 'Assists',
  `gameSTL` int DEFAULT NULL COMMENT 'Steals',
  `gameTOV` int DEFAULT NULL COMMENT 'Turnovers',
  `gameBLK` int DEFAULT NULL COMMENT 'Blocks',
  `gamePF` int DEFAULT NULL COMMENT 'Personal fouls',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_career_avgs` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `orb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `reb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `ast` decimal(8,2) NOT NULL DEFAULT '0.00',
  `stl` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tvr` decimal(8,2) NOT NULL DEFAULT '0.00',
  `blk` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pf` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pts` decimal(8,2) NOT NULL DEFAULT '0.00',
  `retired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_career_totals`
--

DROP TABLE IF EXISTS `ibl_olympics_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_career_totals` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  `pts` int NOT NULL DEFAULT '0',
  `retired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_power`
--

DROP TABLE IF EXISTS `ibl_olympics_power`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_power` (
  `TeamID` smallint NOT NULL DEFAULT '0',
  `Team` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Division` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Conference` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ranking` decimal(6,1) NOT NULL DEFAULT '0.0',
  `win` smallint NOT NULL DEFAULT '0',
  `loss` smallint NOT NULL DEFAULT '0',
  `gb` decimal(6,1) NOT NULL DEFAULT '0.0',
  `conf_win` int NOT NULL,
  `conf_loss` int NOT NULL,
  `div_win` int NOT NULL,
  `div_loss` int NOT NULL,
  `home_win` int NOT NULL,
  `home_loss` int NOT NULL,
  `road_win` int NOT NULL,
  `road_loss` int NOT NULL,
  `last_win` int NOT NULL,
  `last_loss` int NOT NULL,
  `streak_type` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `streak` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Team`),
  CONSTRAINT `ibl_olympics_power_chk_1` CHECK (((`ranking` is null) or ((`ranking` >= 0.0) and (`ranking` <= 100.0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_schedule`
--

DROP TABLE IF EXISTS `ibl_olympics_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_schedule` (
  `Year` smallint unsigned NOT NULL COMMENT 'Tournament year',
  `BoxID` int NOT NULL DEFAULT '0' COMMENT 'Box score identifier',
  `Date` date NOT NULL COMMENT 'Game date',
  `Visitor` smallint unsigned NOT NULL COMMENT 'Visiting team ID',
  `VScore` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Visitor score',
  `Home` smallint unsigned NOT NULL COMMENT 'Home team ID',
  `HScore` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Home score',
  `round` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Tournament round (Group A, Quarterfinal, Semifinal, Gold Medal, Bronze Medal)',
  `SchedID` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Public API identifier',
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
  CONSTRAINT `chk_olympics_schedule_hscore` CHECK (((`HScore` >= 0) and (`HScore` <= 200))),
  CONSTRAINT `chk_olympics_schedule_vscore` CHECK (((`VScore` >= 0) and (`VScore` <= 200)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Olympics game schedule with tournament round tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_standings`
--

DROP TABLE IF EXISTS `ibl_olympics_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_standings` (
  `tid` int NOT NULL COMMENT 'Team ID - references ibl_olympics_team_info',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pct` float(4,3) unsigned DEFAULT NULL COMMENT 'Win percentage',
  `leagueRecord` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Overall W-L record',
  `conference` enum('Eastern','Western','') COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Conference (if used)',
  `confRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Conference record',
  `confGB` decimal(3,1) DEFAULT NULL COMMENT 'Conference games back',
  `division` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Division (if used)',
  `divRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Division record',
  `divGB` decimal(3,1) DEFAULT NULL COMMENT 'Division games back',
  `homeRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Home game record',
  `awayRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Away game record',
  `gamesUnplayed` tinyint unsigned DEFAULT NULL COMMENT 'Games remaining',
  `confWins` tinyint unsigned DEFAULT NULL COMMENT 'Conference wins',
  `confLosses` tinyint unsigned DEFAULT NULL COMMENT 'Conference losses',
  `divWins` tinyint unsigned DEFAULT NULL COMMENT 'Division wins',
  `divLosses` tinyint unsigned DEFAULT NULL COMMENT 'Division losses',
  `homeWins` tinyint unsigned DEFAULT NULL COMMENT 'Home wins',
  `homeLosses` tinyint unsigned DEFAULT NULL COMMENT 'Home losses',
  `awayWins` tinyint unsigned DEFAULT NULL COMMENT 'Away wins',
  `awayLosses` tinyint unsigned DEFAULT NULL COMMENT 'Away losses',
  `confMagicNumber` tinyint DEFAULT NULL COMMENT 'Conference magic number',
  `divMagicNumber` tinyint DEFAULT NULL COMMENT 'Division magic number',
  `clinchedConference` tinyint(1) DEFAULT NULL COMMENT 'Clinched conference flag',
  `clinchedDivision` tinyint(1) DEFAULT NULL COMMENT 'Clinched division flag',
  `clinchedPlayoffs` tinyint(1) DEFAULT NULL COMMENT 'Clinched playoffs flag',
  `group_name` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Olympics group (A, B, C, etc.)',
  `medal` enum('gold','silver','bronze') COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Final tournament medal',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tid`),
  KEY `team_name` (`team_name`),
  KEY `idx_conference` (`conference`),
  KEY `idx_division` (`division`),
  KEY `idx_group` (`group_name`),
  KEY `idx_medal` (`medal`),
  CONSTRAINT `fk_olympics_standings_team` FOREIGN KEY (`tid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_olympics_standings_pct` CHECK (((`pct` is null) or ((`pct` >= 0.000) and (`pct` <= 1.000))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='Olympics tournament standings and medal tracking';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_olympics_stats`
--

DROP TABLE IF EXISTS `ibl_olympics_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL DEFAULT '0',
  `pos` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_team_info` (
  `teamid` int NOT NULL AUTO_INCREMENT,
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'City/Country name',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team nickname',
  `color1` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Primary team color (hex)',
  `color2` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Secondary team color (hex)',
  `arena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Home arena/venue',
  `owner_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team owner username',
  `owner_email` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Owner email address',
  `discordID` bigint unsigned DEFAULT NULL COMMENT 'Discord user ID',
  `skype` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Skype username (legacy)',
  `aim` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'AIM username (legacy)',
  `msn` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'MSN username (legacy)',
  `formerly_known_as` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Previous team names',
  `Contract_Wins` int NOT NULL DEFAULT '0' COMMENT 'Contract performance tracking',
  `Contract_Losses` int NOT NULL DEFAULT '0' COMMENT 'Contract performance tracking',
  `Contract_AvgW` int NOT NULL DEFAULT '0' COMMENT 'Average wins per contract',
  `Contract_AvgL` int NOT NULL DEFAULT '0' COMMENT 'Average losses per contract',
  `Contract_Coach` decimal(3,2) NOT NULL DEFAULT '0.00' COMMENT 'Coach rating',
  `chart` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Depth chart identifier',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci DEFAULT (uuid()),
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_olympics_win_loss` (
  `year` int unsigned NOT NULL DEFAULT '0' COMMENT 'Olympics year',
  `currentname` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Current team name',
  `namethatyear` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name during that Olympics',
  `wins` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Games won',
  `losses` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Games lost',
  `gold` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Gold medals won',
  `silver` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Silver medals won',
  `bronze` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Bronze medals won',
  `table_ID` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_one_on_one` (
  `gameid` int NOT NULL DEFAULT '0',
  `playbyplay` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `winner` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `loser` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `winscore` int NOT NULL DEFAULT '0',
  `lossscore` int NOT NULL DEFAULT '0',
  `owner` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`gameid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_career_avgs`
--

DROP TABLE IF EXISTS `ibl_playoff_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_playoff_career_avgs` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `orb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `reb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `ast` decimal(8,2) NOT NULL DEFAULT '0.00',
  `stl` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tvr` decimal(8,2) NOT NULL DEFAULT '0.00',
  `blk` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pf` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pts` decimal(8,2) NOT NULL DEFAULT '0.00',
  `retired` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_career_totals`
--

DROP TABLE IF EXISTS `ibl_playoff_career_totals`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_playoff_career_totals` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  `pts` int NOT NULL DEFAULT '0',
  `retired` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_results`
--

DROP TABLE IF EXISTS `ibl_playoff_results`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_playoff_results` (
  `year` smallint unsigned NOT NULL COMMENT 'Playoff year',
  `round` tinyint unsigned NOT NULL COMMENT 'Playoff round',
  `winner` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `loser` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `loser_games` int NOT NULL DEFAULT '0',
  `id` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`id`),
  KEY `idx_year` (`year`),
  KEY `idx_round` (`round`),
  KEY `idx_winner` (`winner`),
  KEY `idx_loser` (`loser`)
) ENGINE=InnoDB AUTO_INCREMENT=281 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_playoff_stats`
--

DROP TABLE IF EXISTS `ibl_playoff_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_playoff_stats` (
  `year` int NOT NULL DEFAULT '0',
  `pos` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_plr` (
  `ordinal` int DEFAULT '0',
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name',
  `nickname` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `age` tinyint unsigned DEFAULT NULL,
  `peak` tinyint unsigned DEFAULT NULL,
  `tid` int NOT NULL DEFAULT '0' COMMENT 'Team ID (0 = free agent)',
  `teamname` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `pos` enum('PG','SG','SF','PF','C','G','F','GF','') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player position',
  `sta` tinyint unsigned DEFAULT '0' COMMENT 'Stamina rating',
  `oo` tinyint unsigned DEFAULT '0' COMMENT 'Outside offense rating',
  `od` tinyint unsigned DEFAULT '0' COMMENT 'Outside defense rating',
  `do` tinyint unsigned DEFAULT '0' COMMENT 'Inside offense rating',
  `dd` tinyint unsigned DEFAULT '0' COMMENT 'Inside defense rating',
  `po` tinyint unsigned DEFAULT '0' COMMENT 'Post offense rating',
  `pd` tinyint unsigned DEFAULT '0' COMMENT 'Post defense rating',
  `to` tinyint unsigned DEFAULT '0' COMMENT 'Transition offense rating',
  `td` tinyint unsigned DEFAULT '0' COMMENT 'Transition defense rating',
  `Clutch` tinyint DEFAULT NULL,
  `Consistency` tinyint DEFAULT NULL,
  `PGDepth` tinyint unsigned DEFAULT '0' COMMENT 'Point guard depth',
  `SGDepth` tinyint unsigned DEFAULT '0' COMMENT 'Shooting guard depth',
  `SFDepth` tinyint unsigned DEFAULT '0' COMMENT 'Small forward depth',
  `PFDepth` tinyint unsigned DEFAULT '0' COMMENT 'Power forward depth',
  `CDepth` tinyint unsigned DEFAULT '0' COMMENT 'Center depth',
  `active` tinyint(1) DEFAULT NULL,
  `dc_PGDepth` tinyint unsigned DEFAULT '0' COMMENT 'DC point guard depth',
  `dc_SGDepth` tinyint unsigned DEFAULT '0' COMMENT 'DC shooting guard depth',
  `dc_SFDepth` tinyint unsigned DEFAULT '0' COMMENT 'DC small forward depth',
  `dc_PFDepth` tinyint unsigned DEFAULT '0' COMMENT 'DC power forward depth',
  `dc_CDepth` tinyint unsigned DEFAULT '0' COMMENT 'DC center depth',
  `dc_active` tinyint unsigned DEFAULT '1' COMMENT 'DC active flag',
  `dc_minutes` tinyint unsigned DEFAULT '0' COMMENT 'DC minutes',
  `dc_of` tinyint unsigned DEFAULT '0' COMMENT 'DC offensive focus',
  `dc_df` tinyint unsigned DEFAULT '0' COMMENT 'DC defensive focus',
  `dc_oi` tinyint DEFAULT '0' COMMENT 'DC offensive importance',
  `dc_di` tinyint DEFAULT '0' COMMENT 'DC defensive importance',
  `dc_bh` tinyint DEFAULT '0' COMMENT 'DC ball handling',
  `stats_gs` smallint unsigned DEFAULT '0' COMMENT 'Games started',
  `stats_gm` smallint unsigned DEFAULT '0' COMMENT 'Games played',
  `stats_min` mediumint unsigned DEFAULT '0' COMMENT 'Total minutes played',
  `stats_fgm` smallint unsigned DEFAULT '0' COMMENT 'Field goals made',
  `stats_fga` smallint unsigned DEFAULT '0' COMMENT 'Field goals attempted',
  `stats_ftm` smallint unsigned DEFAULT '0' COMMENT 'Free throws made',
  `stats_fta` smallint unsigned DEFAULT '0' COMMENT 'Free throws attempted',
  `stats_3gm` smallint unsigned DEFAULT '0' COMMENT 'Three pointers made',
  `stats_3ga` smallint unsigned DEFAULT '0' COMMENT 'Three pointers attempted',
  `stats_orb` smallint unsigned DEFAULT '0' COMMENT 'Offensive rebounds',
  `stats_drb` smallint unsigned DEFAULT '0' COMMENT 'Defensive rebounds',
  `stats_ast` smallint unsigned DEFAULT '0' COMMENT 'Assists',
  `stats_stl` smallint unsigned DEFAULT '0' COMMENT 'Steals',
  `stats_to` smallint unsigned DEFAULT '0' COMMENT 'Turnovers',
  `stats_blk` smallint unsigned DEFAULT '0' COMMENT 'Blocks',
  `stats_pf` smallint unsigned DEFAULT '0' COMMENT 'Personal fouls',
  `talent` tinyint unsigned DEFAULT '0' COMMENT 'Overall talent rating',
  `skill` tinyint unsigned DEFAULT '0' COMMENT 'Skill rating',
  `intangibles` tinyint unsigned DEFAULT '0' COMMENT 'Intangibles rating',
  `coach` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `loyalty` tinyint DEFAULT NULL,
  `playingTime` tinyint DEFAULT NULL,
  `winner` tinyint DEFAULT NULL,
  `tradition` tinyint DEFAULT NULL,
  `security` tinyint DEFAULT NULL,
  `exp` tinyint unsigned DEFAULT '0' COMMENT 'Years of experience',
  `bird` tinyint(1) DEFAULT NULL,
  `cy` int DEFAULT '0',
  `cyt` int DEFAULT '0',
  `cy1` int DEFAULT '0',
  `cy2` int DEFAULT '0',
  `cy3` int DEFAULT '0',
  `cy4` int DEFAULT '0',
  `cy5` int DEFAULT '0',
  `cy6` int DEFAULT '0',
  `sh_pts` smallint unsigned DEFAULT '0' COMMENT 'Season high points',
  `sh_reb` smallint unsigned DEFAULT '0' COMMENT 'Season high rebounds',
  `sh_ast` smallint unsigned DEFAULT '0' COMMENT 'Season high assists',
  `sh_stl` smallint unsigned DEFAULT '0' COMMENT 'Season high steals',
  `sh_blk` smallint unsigned DEFAULT '0' COMMENT 'Season high blocks',
  `s_dd` smallint unsigned DEFAULT '0' COMMENT 'Season double doubles',
  `s_td` smallint unsigned DEFAULT '0' COMMENT 'Season triple doubles',
  `sp_pts` smallint unsigned DEFAULT '0' COMMENT 'Playoff high points',
  `sp_reb` smallint unsigned DEFAULT '0' COMMENT 'Playoff high rebounds',
  `sp_ast` smallint unsigned DEFAULT '0' COMMENT 'Playoff high assists',
  `sp_stl` smallint unsigned DEFAULT '0' COMMENT 'Playoff high steals',
  `sp_blk` smallint unsigned DEFAULT '0' COMMENT 'Playoff high blocks',
  `ch_pts` smallint unsigned DEFAULT '0' COMMENT 'Career high points',
  `ch_reb` smallint unsigned DEFAULT '0' COMMENT 'Career high rebounds',
  `ch_ast` smallint unsigned DEFAULT '0' COMMENT 'Career high assists',
  `ch_stl` smallint unsigned DEFAULT '0' COMMENT 'Career high steals',
  `ch_blk` smallint unsigned DEFAULT '0' COMMENT 'Career high blocks',
  `c_dd` smallint unsigned DEFAULT '0' COMMENT 'Career double doubles',
  `c_td` smallint unsigned DEFAULT '0' COMMENT 'Career triple doubles',
  `cp_pts` smallint unsigned DEFAULT '0' COMMENT 'Career playoff high points',
  `cp_reb` smallint unsigned DEFAULT '0' COMMENT 'Career playoff high rebounds',
  `cp_ast` smallint unsigned DEFAULT '0' COMMENT 'Career playoff high assists',
  `cp_stl` smallint unsigned DEFAULT '0' COMMENT 'Career playoff high steals',
  `cp_blk` smallint unsigned DEFAULT '0' COMMENT 'Career playoff high blocks',
  `car_gm` smallint unsigned DEFAULT '0' COMMENT 'Career games',
  `car_min` mediumint unsigned DEFAULT '0' COMMENT 'Career minutes',
  `car_fgm` mediumint unsigned DEFAULT '0' COMMENT 'Career FGM',
  `car_fga` mediumint unsigned DEFAULT '0' COMMENT 'Career FGA',
  `car_ftm` mediumint unsigned DEFAULT '0' COMMENT 'Career FTM',
  `car_fta` mediumint unsigned DEFAULT '0' COMMENT 'Career FTA',
  `car_tgm` mediumint unsigned DEFAULT '0' COMMENT 'Career 3PM',
  `car_tga` mediumint unsigned DEFAULT '0' COMMENT 'Career 3PA',
  `car_orb` mediumint unsigned DEFAULT '0' COMMENT 'Career ORB',
  `car_drb` mediumint unsigned DEFAULT '0' COMMENT 'Career DRB',
  `car_reb` mediumint unsigned DEFAULT '0' COMMENT 'Career total rebounds',
  `car_ast` mediumint unsigned DEFAULT '0' COMMENT 'Career assists',
  `car_stl` mediumint unsigned DEFAULT '0' COMMENT 'Career steals',
  `car_to` mediumint unsigned DEFAULT '0' COMMENT 'Career turnovers',
  `car_blk` mediumint unsigned DEFAULT '0' COMMENT 'Career blocks',
  `car_pf` mediumint unsigned DEFAULT '0' COMMENT 'Career fouls',
  `car_pts` mediumint unsigned DEFAULT '0' COMMENT 'Career points',
  `r_fga` smallint unsigned DEFAULT '0' COMMENT 'Rating FGA',
  `r_fgp` smallint unsigned DEFAULT '0' COMMENT 'Rating FG%',
  `r_fta` smallint unsigned DEFAULT '0' COMMENT 'Rating FTA',
  `r_ftp` smallint unsigned DEFAULT '0' COMMENT 'Rating FT%',
  `r_tga` smallint unsigned DEFAULT '0' COMMENT 'Rating 3PA',
  `r_tgp` smallint unsigned DEFAULT '0' COMMENT 'Rating 3P%',
  `r_orb` smallint unsigned DEFAULT '0' COMMENT 'Rating ORB',
  `r_drb` smallint unsigned DEFAULT '0' COMMENT 'Rating DRB',
  `r_ast` smallint unsigned DEFAULT '0' COMMENT 'Rating AST',
  `r_stl` smallint unsigned DEFAULT '0' COMMENT 'Rating STL',
  `r_to` smallint unsigned DEFAULT '0' COMMENT 'Rating TO',
  `r_blk` smallint unsigned DEFAULT '0' COMMENT 'Rating BLK',
  `r_foul` smallint unsigned DEFAULT '0' COMMENT 'Rating fouls',
  `draftround` tinyint unsigned DEFAULT '0' COMMENT 'Draft round (1-7)',
  `draftedby` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `draftedbycurrentname` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `draftyear` smallint unsigned DEFAULT '0' COMMENT 'Draft year',
  `draftpickno` tinyint unsigned DEFAULT '0' COMMENT 'Pick number in round',
  `injured` tinyint unsigned DEFAULT NULL,
  `htft` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Height feet',
  `htin` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Height inches',
  `wt` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Weight in pounds',
  `retired` tinyint(1) DEFAULT NULL,
  `college` varchar(48) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `car_playoff_min` mediumint unsigned DEFAULT '0' COMMENT 'Career playoff minutes',
  `car_preseason_min` mediumint unsigned DEFAULT '0' COMMENT 'Career preseason minutes',
  `droptime` int DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `chk_plr_cy` CHECK (((`cy` >= 0) and (`cy` <= 6))),
  CONSTRAINT `chk_plr_cy1` CHECK (((`cy1` >= -(7000)) and (`cy1` <= 7000))),
  CONSTRAINT `chk_plr_cy2` CHECK (((`cy2` >= -(7000)) and (`cy2` <= 7000))),
  CONSTRAINT `chk_plr_cy3` CHECK (((`cy3` >= -(7000)) and (`cy3` <= 7000))),
  CONSTRAINT `chk_plr_cy4` CHECK (((`cy4` >= -(7000)) and (`cy4` <= 7000))),
  CONSTRAINT `chk_plr_cy5` CHECK (((`cy5` >= -(7000)) and (`cy5` <= 7000))),
  CONSTRAINT `chk_plr_cy6` CHECK (((`cy6` >= -(7000)) and (`cy6` <= 7000))),
  CONSTRAINT `chk_plr_cyt` CHECK (((`cyt` >= 0) and (`cyt` <= 6)))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_plr_chunk`
--

DROP TABLE IF EXISTS `ibl_plr_chunk`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_plr_chunk` (
  `active` int NOT NULL DEFAULT '0',
  `pid` int NOT NULL DEFAULT '0',
  `ordinal` int NOT NULL,
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tid` int NOT NULL DEFAULT '0',
  `teamname` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pos` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL,
  `altpos` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `stats_gs` int NOT NULL DEFAULT '0',
  `stats_gm` int NOT NULL DEFAULT '0',
  `stats_min` int NOT NULL DEFAULT '0',
  `stats_fgm` int NOT NULL DEFAULT '0',
  `stats_fga` int NOT NULL DEFAULT '0',
  `stats_ftm` int NOT NULL DEFAULT '0',
  `stats_fta` int NOT NULL DEFAULT '0',
  `stats_3gm` int NOT NULL DEFAULT '0',
  `stats_3ga` int NOT NULL DEFAULT '0',
  `stats_orb` int NOT NULL DEFAULT '0',
  `stats_drb` int NOT NULL DEFAULT '0',
  `stats_ast` int NOT NULL DEFAULT '0',
  `stats_stl` int NOT NULL DEFAULT '0',
  `stats_to` int NOT NULL DEFAULT '0',
  `stats_blk` int NOT NULL DEFAULT '0',
  `stats_pf` int NOT NULL DEFAULT '0',
  `chunk` int DEFAULT NULL,
  `qa` decimal(11,2) NOT NULL DEFAULT '0.00',
  `Season` int NOT NULL,
  KEY `pid` (`pid`),
  CONSTRAINT `fk_plr_chunk_player` FOREIGN KEY (`pid`) REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_power`
--

DROP TABLE IF EXISTS `ibl_power`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_power` (
  `TeamID` smallint NOT NULL DEFAULT '0',
  `Team` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Division` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `Conference` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ranking` decimal(6,1) NOT NULL DEFAULT '0.0',
  `win` smallint NOT NULL DEFAULT '0',
  `loss` smallint NOT NULL DEFAULT '0',
  `gb` decimal(6,1) NOT NULL DEFAULT '0.0',
  `conf_win` int NOT NULL,
  `conf_loss` int NOT NULL,
  `div_win` int NOT NULL,
  `div_loss` int NOT NULL,
  `home_win` int NOT NULL,
  `home_loss` int NOT NULL,
  `road_win` int NOT NULL,
  `road_loss` int NOT NULL,
  `last_win` int NOT NULL,
  `last_loss` int NOT NULL,
  `streak_type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `streak` int NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`Team`),
  CONSTRAINT `fk_power_team` FOREIGN KEY (`Team`) REFERENCES `ibl_team_info` (`team_name`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_power_ranking` CHECK (((`ranking` is null) or ((`ranking` >= 0.0) and (`ranking` <= 100.0))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_schedule`
--

DROP TABLE IF EXISTS `ibl_schedule`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_schedule` (
  `Year` smallint unsigned NOT NULL COMMENT 'Season year',
  `BoxID` int NOT NULL DEFAULT '0',
  `Date` date NOT NULL,
  `Visitor` int NOT NULL DEFAULT '0',
  `VScore` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Visitor score',
  `Home` int NOT NULL DEFAULT '0',
  `HScore` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Home score',
  `SchedID` int NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
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
  CONSTRAINT `chk_schedule_hscore` CHECK (((`HScore` >= 0) and (`HScore` <= 200))),
  CONSTRAINT `chk_schedule_vscore` CHECK (((`VScore` >= 0) and (`VScore` <= 200)))
) ENGINE=InnoDB AUTO_INCREMENT=1149 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_season_career_avgs`
--

DROP TABLE IF EXISTS `ibl_season_career_avgs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_season_career_avgs` (
  `pid` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` decimal(8,2) NOT NULL DEFAULT '0.00',
  `fgm` decimal(8,2) NOT NULL,
  `fga` decimal(8,2) NOT NULL,
  `fgpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `ftm` decimal(8,2) NOT NULL,
  `fta` decimal(8,2) NOT NULL,
  `ftpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `tgm` decimal(8,2) NOT NULL,
  `tga` decimal(8,2) NOT NULL,
  `tpct` decimal(8,3) NOT NULL DEFAULT '0.000',
  `orb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `reb` decimal(8,2) NOT NULL DEFAULT '0.00',
  `ast` decimal(8,2) NOT NULL DEFAULT '0.00',
  `stl` decimal(8,2) NOT NULL DEFAULT '0.00',
  `tvr` decimal(8,2) NOT NULL DEFAULT '0.00',
  `blk` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pf` decimal(8,2) NOT NULL DEFAULT '0.00',
  `pts` decimal(8,2) NOT NULL DEFAULT '0.00',
  `retired` int NOT NULL DEFAULT '0',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_settings`
--

DROP TABLE IF EXISTS `ibl_settings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_settings` (
  `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  `value` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_sim_dates`
--

DROP TABLE IF EXISTS `ibl_sim_dates`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_sim_dates` (
  `Sim` int unsigned NOT NULL AUTO_INCREMENT,
  `Start Date` date DEFAULT NULL,
  `End Date` date DEFAULT NULL,
  PRIMARY KEY (`Sim`)
) ENGINE=InnoDB AUTO_INCREMENT=682 DEFAULT CHARSET=utf8mb3 COLLATE=utf8mb3_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_standings`
--

DROP TABLE IF EXISTS `ibl_standings`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_standings` (
  `tid` int NOT NULL,
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pct` float(4,3) unsigned DEFAULT NULL,
  `leagueRecord` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `conference` enum('Eastern','Western','') COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Conference affiliation',
  `confRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `confGB` decimal(3,1) DEFAULT NULL,
  `division` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `divRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `divGB` decimal(3,1) DEFAULT NULL,
  `homeRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `awayRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `gamesUnplayed` tinyint unsigned DEFAULT NULL COMMENT 'Games remaining',
  `confWins` tinyint unsigned DEFAULT NULL COMMENT 'Conference wins',
  `confLosses` tinyint unsigned DEFAULT NULL COMMENT 'Conference losses',
  `divWins` tinyint unsigned DEFAULT NULL COMMENT 'Division wins',
  `divLosses` tinyint unsigned DEFAULT NULL COMMENT 'Division losses',
  `homeWins` tinyint unsigned DEFAULT NULL COMMENT 'Home wins',
  `homeLosses` tinyint unsigned DEFAULT NULL COMMENT 'Home losses',
  `awayWins` tinyint unsigned DEFAULT NULL COMMENT 'Away wins',
  `awayLosses` tinyint unsigned DEFAULT NULL COMMENT 'Away losses',
  `confMagicNumber` tinyint DEFAULT NULL COMMENT 'Conf magic number',
  `divMagicNumber` tinyint DEFAULT NULL COMMENT 'Div magic number',
  `clinchedConference` tinyint(1) DEFAULT NULL,
  `clinchedDivision` tinyint(1) DEFAULT NULL,
  `clinchedPlayoffs` tinyint(1) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`tid`),
  KEY `team_name` (`team_name`),
  KEY `idx_conference` (`conference`),
  KEY `idx_division` (`division`),
  CONSTRAINT `fk_standings_team` FOREIGN KEY (`tid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
  CONSTRAINT `chk_standings_away_losses` CHECK (((`awayLosses` is null) or (`awayLosses` <= 41))),
  CONSTRAINT `chk_standings_away_wins` CHECK (((`awayWins` is null) or (`awayWins` <= 41))),
  CONSTRAINT `chk_standings_conf_losses` CHECK (((`confLosses` is null) or (`confLosses` <= 82))),
  CONSTRAINT `chk_standings_conf_wins` CHECK (((`confWins` is null) or (`confWins` <= 82))),
  CONSTRAINT `chk_standings_games_unplayed` CHECK (((`gamesUnplayed` is null) or ((`gamesUnplayed` >= 0) and (`gamesUnplayed` <= 82)))),
  CONSTRAINT `chk_standings_home_losses` CHECK (((`homeLosses` is null) or (`homeLosses` <= 41))),
  CONSTRAINT `chk_standings_home_wins` CHECK (((`homeWins` is null) or (`homeWins` <= 41))),
  CONSTRAINT `chk_standings_pct` CHECK (((`pct` is null) or ((`pct` >= 0.000) and (`pct` <= 1.000))))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_awards`
--

DROP TABLE IF EXISTS `ibl_team_awards`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_awards` (
  `year` smallint unsigned NOT NULL DEFAULT '0',
  `name` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL,
  `Award` varchar(350) COLLATE utf8mb4_unicode_ci NOT NULL,
  `ID` int NOT NULL,
  PRIMARY KEY (`ID`),
  KEY `idx_award` (`Award`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_defense_stats`
--

DROP TABLE IF EXISTS `ibl_team_defense_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_defense_stats` (
  `teamID` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  `minutes` int DEFAULT '0',
  KEY `idx_teamID` (`teamID`),
  CONSTRAINT `fk_team_defense_team` FOREIGN KEY (`teamID`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_history`
--

DROP TABLE IF EXISTS `ibl_team_history`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_history` (
  `teamid` int NOT NULL DEFAULT '0',
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color1` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color2` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `depth` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `sim_depth` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `asg_vote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `eoy_vote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `totwins` int NOT NULL,
  `totloss` int NOT NULL,
  `winpct` float(4,3) NOT NULL,
  `playoffs` int NOT NULL,
  `div_titles` int NOT NULL,
  `conf_titles` int NOT NULL,
  `ibl_titles` int NOT NULL,
  `heat_titles` int NOT NULL,
  PRIMARY KEY (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_info`
--

DROP TABLE IF EXISTS `ibl_team_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_info` (
  `teamid` int NOT NULL DEFAULT '0',
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color1` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `color2` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `arena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `capacity` int NOT NULL DEFAULT '0',
  `owner_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `owner_email` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `discordID` bigint unsigned DEFAULT NULL,
  `formerly_known_as` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Contract_Wins` int NOT NULL DEFAULT '0',
  `Contract_Losses` int NOT NULL DEFAULT '0',
  `Contract_AvgW` int NOT NULL DEFAULT '0',
  `Contract_AvgL` int NOT NULL DEFAULT '0',
  `Contract_Coach` decimal(3,2) NOT NULL DEFAULT '0.00',
  `Used_Extension_This_Chunk` int NOT NULL DEFAULT '0',
  `Used_Extension_This_Season` int DEFAULT '0',
  `HasMLE` int NOT NULL DEFAULT '0',
  `HasLLE` int NOT NULL DEFAULT '0',
  `chart` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `uuid` char(36) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`teamid`),
  UNIQUE KEY `uuid` (`uuid`),
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_offense_stats` (
  `teamID` int NOT NULL DEFAULT '0',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  `minutes` int DEFAULT '0',
  KEY `idx_teamID` (`teamID`),
  CONSTRAINT `fk_team_offense_team` FOREIGN KEY (`teamID`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_team_win_loss`
--

DROP TABLE IF EXISTS `ibl_team_win_loss`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_team_win_loss` (
  `year` smallint unsigned NOT NULL DEFAULT '0',
  `currentname` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `namethatyear` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `wins` smallint unsigned NOT NULL DEFAULT '0',
  `losses` smallint unsigned NOT NULL DEFAULT '0',
  `table_ID` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`table_ID`)
) ENGINE=InnoDB AUTO_INCREMENT=859 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_autocounter`
--

DROP TABLE IF EXISTS `ibl_trade_autocounter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_trade_autocounter` (
  `counter` int NOT NULL AUTO_INCREMENT,
  PRIMARY KEY (`counter`)
) ENGINE=InnoDB AUTO_INCREMENT=11998 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_cash`
--

DROP TABLE IF EXISTS `ibl_trade_cash`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_trade_cash` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tradeOfferID` int NOT NULL,
  `sendingTeam` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `receivingTeam` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cy1` int DEFAULT NULL,
  `cy2` int DEFAULT NULL,
  `cy3` int DEFAULT NULL,
  `cy4` int DEFAULT NULL,
  `cy5` int DEFAULT NULL,
  `cy6` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_info`
--

DROP TABLE IF EXISTS `ibl_trade_info`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_trade_info` (
  `id` int NOT NULL AUTO_INCREMENT,
  `tradeofferid` int NOT NULL DEFAULT '0',
  `itemid` int NOT NULL DEFAULT '0',
  `itemtype` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `from` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `to` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `approval` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_tradeofferid` (`tradeofferid`),
  KEY `idx_from` (`from`),
  KEY `idx_to` (`to`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_trade_queue`
--

DROP TABLE IF EXISTS `ibl_trade_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_trade_queue` (
  `id` int NOT NULL AUTO_INCREMENT,
  `query` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tradeline` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=58 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_votes_ASG`
--

DROP TABLE IF EXISTS `ibl_votes_ASG`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_votes_ASG` (
  `teamid` int NOT NULL DEFAULT '0',
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `East_F1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_F2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_F3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_F4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_B1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_B2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_B3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `East_B4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_F1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_F2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_F3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_F4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_B1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_B2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_B3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `West_B4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  KEY `fk_asg_votes_team` (`teamid`),
  CONSTRAINT `fk_asg_votes_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_votes_EOY`
--

DROP TABLE IF EXISTS `ibl_votes_EOY`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_votes_EOY` (
  `teamid` int NOT NULL DEFAULT '0',
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `MVP_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MVP_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `MVP_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Six_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Six_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `Six_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ROY_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ROY_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ROY_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `GM_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `GM_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `GM_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  PRIMARY KEY (`teamid`),
  CONSTRAINT `fk_eoy_votes_team` FOREIGN KEY (`teamid`) REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `job_batches`
--

DROP TABLE IF EXISTS `job_batches`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `job_batches` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `total_jobs` int NOT NULL,
  `pending_jobs` int NOT NULL,
  `failed_jobs` int NOT NULL,
  `failed_job_ids` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `options` mediumtext COLLATE utf8mb4_unicode_ci,
  `cancelled_at` int DEFAULT NULL,
  `created_at` int NOT NULL,
  `finished_at` int DEFAULT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `jobs`
--

DROP TABLE IF EXISTS `jobs`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `jobs` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `queue` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `attempts` tinyint unsigned NOT NULL,
  `reserved_at` int unsigned DEFAULT NULL,
  `available_at` int unsigned NOT NULL,
  `created_at` int unsigned NOT NULL,
  PRIMARY KEY (`id`),
  KEY `jobs_queue_index` (`queue`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `migrations`
--

DROP TABLE IF EXISTS `migrations`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `migrations` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `migration` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `batch` int NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_antiflood`
--

DROP TABLE IF EXISTS `nuke_antiflood`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_antiflood` (
  `ip_addr` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  KEY `ip_addr` (`ip_addr`),
  KEY `time` (`time`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_authors`
--

DROP TABLE IF EXISTS `nuke_authors`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_authors` (
  `aid` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `pwd` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counter` int NOT NULL DEFAULT '0',
  `radminsuper` tinyint(1) NOT NULL DEFAULT '1',
  `admlanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`aid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_autonews`
--

DROP TABLE IF EXISTS `nuke_autonews`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_autonews` (
  `anid` int NOT NULL AUTO_INCREMENT,
  `catid` int NOT NULL DEFAULT '0',
  `aid` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` varchar(19) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hometext` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `bodytext` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `topic` int NOT NULL DEFAULT '1',
  `informant` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `ihome` int NOT NULL DEFAULT '0',
  `alanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `acomm` int NOT NULL DEFAULT '0',
  `associated` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`anid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banned_ip`
--

DROP TABLE IF EXISTS `nuke_banned_ip`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banned_ip` (
  `id` int NOT NULL AUTO_INCREMENT,
  `ip_address` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `reason` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` date NOT NULL DEFAULT '0000-00-00',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner`
--

DROP TABLE IF EXISTS `nuke_banner`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banner` (
  `bid` int NOT NULL AUTO_INCREMENT,
  `cid` int NOT NULL DEFAULT '0',
  `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `imptotal` int NOT NULL DEFAULT '0',
  `impmade` int NOT NULL DEFAULT '0',
  `clicks` int NOT NULL DEFAULT '0',
  `imageurl` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `clickurl` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alttext` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` datetime DEFAULT NULL,
  `dateend` datetime DEFAULT NULL,
  `position` int NOT NULL DEFAULT '0',
  `active` tinyint(1) NOT NULL DEFAULT '1',
  `ad_class` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ad_code` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `ad_width` int DEFAULT '0',
  `ad_height` int DEFAULT '0',
  PRIMARY KEY (`bid`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_clients`
--

DROP TABLE IF EXISTS `nuke_banner_clients`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banner_clients` (
  `cid` int NOT NULL AUTO_INCREMENT,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `contact` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `login` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `passwd` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `extrainfo` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_plans`
--

DROP TABLE IF EXISTS `nuke_banner_plans`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banner_plans` (
  `pid` int NOT NULL AUTO_INCREMENT,
  `active` tinyint(1) NOT NULL DEFAULT '0',
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `delivery` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `delivery_type` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `price` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `buy_links` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`pid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_positions`
--

DROP TABLE IF EXISTS `nuke_banner_positions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banner_positions` (
  `apid` int NOT NULL AUTO_INCREMENT,
  `position_number` int NOT NULL DEFAULT '0',
  `position_name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`apid`),
  KEY `position_number` (`position_number`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_banner_terms`
--

DROP TABLE IF EXISTS `nuke_banner_terms`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_banner_terms` (
  `terms_body` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `country` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_blocks`
--

DROP TABLE IF EXISTS `nuke_blocks`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_blocks` (
  `bid` int NOT NULL AUTO_INCREMENT,
  `bkey` varchar(15) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `url` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `bposition` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `weight` int NOT NULL DEFAULT '1',
  `active` int NOT NULL DEFAULT '1',
  `refresh` int NOT NULL DEFAULT '0',
  `time` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `blanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `blockfile` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `view` int NOT NULL DEFAULT '0',
  `expire` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '0',
  `action` char(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subscription` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`bid`),
  KEY `title` (`title`)
) ENGINE=MyISAM AUTO_INCREMENT=38 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_cities`
--

DROP TABLE IF EXISTS `nuke_cities`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_cities` (
  `id` mediumint NOT NULL DEFAULT '0',
  `local_id` mediumint NOT NULL DEFAULT '0',
  `city` varchar(65) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cc` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `country` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_comments`
--

DROP TABLE IF EXISTS `nuke_comments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_comments` (
  `tid` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0',
  `sid` int NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(85) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `reason` tinyint NOT NULL DEFAULT '0',
  `last_moderation_ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_comments_moderated` (
  `tid` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0',
  `sid` int NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(85) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `reason` tinyint NOT NULL DEFAULT '0',
  `last_moderation_ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_config` (
  `sitename` varchar(255) NOT NULL DEFAULT '',
  `nukeurl` varchar(255) NOT NULL DEFAULT '',
  `site_logo` varchar(255) NOT NULL DEFAULT '',
  `slogan` varchar(255) NOT NULL DEFAULT '',
  `startdate` varchar(50) NOT NULL DEFAULT '',
  `adminmail` varchar(255) NOT NULL DEFAULT '',
  `anonpost` tinyint(1) NOT NULL DEFAULT '0',
  `Default_Theme` varchar(255) NOT NULL DEFAULT '',
  `overwrite_theme` tinyint(1) NOT NULL DEFAULT '1',
  `foot1` text NOT NULL,
  `foot2` text NOT NULL,
  `foot3` text NOT NULL,
  `commentlimit` int NOT NULL DEFAULT '4096',
  `anonymous` varchar(255) NOT NULL DEFAULT '',
  `minpass` tinyint(1) NOT NULL DEFAULT '5',
  `pollcomm` tinyint(1) NOT NULL DEFAULT '1',
  `articlecomm` tinyint(1) NOT NULL DEFAULT '1',
  `broadcast_msg` tinyint(1) NOT NULL DEFAULT '1',
  `my_headlines` tinyint(1) NOT NULL DEFAULT '1',
  `top` int NOT NULL DEFAULT '10',
  `storyhome` int NOT NULL DEFAULT '10',
  `user_news` tinyint(1) NOT NULL DEFAULT '1',
  `oldnum` int NOT NULL DEFAULT '30',
  `ultramode` tinyint(1) NOT NULL DEFAULT '0',
  `banners` tinyint(1) NOT NULL DEFAULT '1',
  `backend_title` varchar(255) NOT NULL DEFAULT '',
  `backend_language` varchar(10) NOT NULL DEFAULT '',
  `language` varchar(100) NOT NULL DEFAULT '',
  `locale` varchar(10) NOT NULL DEFAULT '',
  `multilingual` tinyint(1) NOT NULL DEFAULT '0',
  `useflags` tinyint(1) NOT NULL DEFAULT '0',
  `notify` tinyint(1) NOT NULL DEFAULT '0',
  `notify_email` varchar(255) NOT NULL DEFAULT '',
  `notify_subject` varchar(255) NOT NULL DEFAULT '',
  `notify_message` varchar(255) NOT NULL DEFAULT '',
  `notify_from` varchar(255) NOT NULL DEFAULT '',
  `moderate` tinyint(1) NOT NULL DEFAULT '0',
  `admingraphic` tinyint(1) NOT NULL DEFAULT '1',
  `httpref` tinyint(1) NOT NULL DEFAULT '1',
  `httprefmax` int NOT NULL DEFAULT '1000',
  `httprefmode` tinyint(1) NOT NULL DEFAULT '1',
  `CensorMode` tinyint(1) NOT NULL DEFAULT '3',
  `CensorReplace` varchar(10) NOT NULL DEFAULT '',
  `copyright` text NOT NULL,
  `Version_Num` varchar(10) NOT NULL DEFAULT '',
  `gfx_chk` tinyint(1) NOT NULL DEFAULT '0',
  `nuke_editor` tinyint(1) NOT NULL DEFAULT '1',
  `display_errors` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`sitename`)
) ENGINE=MyISAM DEFAULT CHARSET=latin1;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_confirm`
--

DROP TABLE IF EXISTS `nuke_confirm`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_confirm` (
  `confirm_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `session_id` char(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `code` char(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`session_id`,`confirm_id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_counter`
--

DROP TABLE IF EXISTS `nuke_counter`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_counter` (
  `type` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `var` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `count` int unsigned NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_faqanswer`
--

DROP TABLE IF EXISTS `nuke_faqanswer`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_faqanswer` (
  `id` tinyint NOT NULL AUTO_INCREMENT,
  `id_cat` tinyint NOT NULL DEFAULT '0',
  `question` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT '',
  `answer` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`id`),
  KEY `id_cat` (`id_cat`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_faqcategories`
--

DROP TABLE IF EXISTS `nuke_faqcategories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_faqcategories` (
  `id_cat` tinyint NOT NULL AUTO_INCREMENT,
  `categories` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `flanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`id_cat`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_groups`
--

DROP TABLE IF EXISTS `nuke_groups`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_groups` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `points` int NOT NULL DEFAULT '0',
  KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_groups_points`
--

DROP TABLE IF EXISTS `nuke_groups_points`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_groups_points` (
  `id` int NOT NULL AUTO_INCREMENT,
  `points` int NOT NULL DEFAULT '0',
  KEY `id` (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=22 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_headlines`
--

DROP TABLE IF EXISTS `nuke_headlines`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_headlines` (
  `hid` int NOT NULL AUTO_INCREMENT,
  `sitename` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `headlinesurl` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`hid`)
) ENGINE=MyISAM AUTO_INCREMENT=27 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_categories`
--

DROP TABLE IF EXISTS `nuke_links_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_categories` (
  `cid` int NOT NULL AUTO_INCREMENT,
  `title` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `cdescription` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `parentid` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_editorials`
--

DROP TABLE IF EXISTS `nuke_links_editorials`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_editorials` (
  `linkid` int NOT NULL DEFAULT '0',
  `adminid` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `editorialtimestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `editorialtext` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `editorialtitle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`linkid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_links`
--

DROP TABLE IF EXISTS `nuke_links_links`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_links` (
  `lid` int NOT NULL AUTO_INCREMENT,
  `cid` int NOT NULL DEFAULT '0',
  `sid` int NOT NULL DEFAULT '0',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `hits` int NOT NULL DEFAULT '0',
  `submitter` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `linkratingsummary` double(6,4) NOT NULL DEFAULT '0.0000',
  `totalvotes` int NOT NULL DEFAULT '0',
  `totalcomments` int NOT NULL DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_modrequest` (
  `requestid` int NOT NULL AUTO_INCREMENT,
  `lid` int NOT NULL DEFAULT '0',
  `cid` int NOT NULL DEFAULT '0',
  `sid` int NOT NULL DEFAULT '0',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `modifysubmitter` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `brokenlink` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`requestid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_links_newlink`
--

DROP TABLE IF EXISTS `nuke_links_newlink`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_newlink` (
  `lid` int NOT NULL AUTO_INCREMENT,
  `cid` int NOT NULL DEFAULT '0',
  `sid` int NOT NULL DEFAULT '0',
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `submitter` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_links_votedata` (
  `ratingdbid` int NOT NULL AUTO_INCREMENT,
  `ratinglid` int NOT NULL DEFAULT '0',
  `ratinguser` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `rating` int NOT NULL DEFAULT '0',
  `ratinghostname` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `ratingcomments` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `ratingtimestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  PRIMARY KEY (`ratingdbid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_main`
--

DROP TABLE IF EXISTS `nuke_main`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_main` (
  `main_module` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT ''
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_message`
--

DROP TABLE IF EXISTS `nuke_message`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_message` (
  `mid` int NOT NULL AUTO_INCREMENT,
  `title` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `content` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `expire` int NOT NULL DEFAULT '0',
  `active` int NOT NULL DEFAULT '1',
  `view` int NOT NULL DEFAULT '1',
  `mlanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_modules`
--

DROP TABLE IF EXISTS `nuke_modules`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_modules` (
  `mid` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `custom_title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `active` int NOT NULL DEFAULT '0',
  `view` int NOT NULL DEFAULT '0',
  `inmenu` tinyint(1) NOT NULL DEFAULT '1',
  `mod_group` int DEFAULT '0',
  `admins` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_optimize_gain` (
  `gain` decimal(10,3) DEFAULT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pages`
--

DROP TABLE IF EXISTS `nuke_pages`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_pages` (
  `pid` int NOT NULL AUTO_INCREMENT,
  `cid` int NOT NULL DEFAULT '0',
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subtitle` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `active` int NOT NULL DEFAULT '0',
  `page_header` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `text` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `page_footer` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `signature` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `date` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `counter` int NOT NULL DEFAULT '0',
  `clanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`pid`),
  KEY `cid` (`cid`)
) ENGINE=MyISAM AUTO_INCREMENT=47 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pages_categories`
--

DROP TABLE IF EXISTS `nuke_pages_categories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_pages_categories` (
  `cid` int NOT NULL AUTO_INCREMENT,
  `title` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `description` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`cid`)
) ENGINE=MyISAM AUTO_INCREMENT=7 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_poll_desc`
--

DROP TABLE IF EXISTS `nuke_poll_desc`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_poll_desc` (
  `pollID` int NOT NULL AUTO_INCREMENT,
  `pollTitle` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `timeStamp` int NOT NULL DEFAULT '0',
  `voters` mediumint NOT NULL DEFAULT '0',
  `planguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `artid` int NOT NULL DEFAULT '0',
  `comments` int DEFAULT '0',
  PRIMARY KEY (`pollID`)
) ENGINE=MyISAM AUTO_INCREMENT=53 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_pollcomments`
--

DROP TABLE IF EXISTS `nuke_pollcomments`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_pollcomments` (
  `tid` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0',
  `pollID` int NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `reason` tinyint NOT NULL DEFAULT '0',
  `last_moderation_ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_pollcomments_moderated` (
  `tid` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL DEFAULT '0',
  `pollID` int NOT NULL DEFAULT '0',
  `date` datetime DEFAULT NULL,
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `email` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `url` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `host_name` varchar(60) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `subject` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `comment` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `score` tinyint NOT NULL DEFAULT '0',
  `reason` tinyint NOT NULL DEFAULT '0',
  `last_moderation_ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '0',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_public_messages` (
  `mid` int NOT NULL AUTO_INCREMENT,
  `content` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `date` varchar(14) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `who` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`mid`)
) ENGINE=MyISAM AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_queue`
--

DROP TABLE IF EXISTS `nuke_queue`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_queue` (
  `qid` smallint unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint NOT NULL DEFAULT '0',
  `uname` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `subject` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `story` mediumtext COLLATE utf8mb4_unicode_ci,
  `storyext` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `timestamp` datetime NOT NULL DEFAULT '0000-00-00 00:00:00',
  `topic` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `alanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
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
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_referer` (
  `rid` int NOT NULL AUTO_INCREMENT,
  `url` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rid`)
) ENGINE=MyISAM AUTO_INCREMENT=40415 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_related`
--

DROP TABLE IF EXISTS `nuke_related`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_related` (
  `rid` int NOT NULL AUTO_INCREMENT,
  `tid` int NOT NULL DEFAULT '0',
  `name` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `url` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`rid`),
  KEY `tid` (`tid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_session`
--

DROP TABLE IF EXISTS `nuke_session`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_session` (
  `uname` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `host_addr` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `guest` int NOT NULL DEFAULT '0',
  KEY `time` (`time`),
  KEY `guest` (`guest`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_date`
--

DROP TABLE IF EXISTS `nuke_stats_date`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stats_date` (
  `year` smallint NOT NULL DEFAULT '0',
  `month` tinyint NOT NULL DEFAULT '0',
  `date` tinyint NOT NULL DEFAULT '0',
  `hits` bigint NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_hour`
--

DROP TABLE IF EXISTS `nuke_stats_hour`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stats_hour` (
  `year` smallint NOT NULL DEFAULT '0',
  `month` tinyint NOT NULL DEFAULT '0',
  `date` tinyint NOT NULL DEFAULT '0',
  `hour` tinyint NOT NULL DEFAULT '0',
  `hits` int NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_month`
--

DROP TABLE IF EXISTS `nuke_stats_month`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stats_month` (
  `year` smallint NOT NULL DEFAULT '0',
  `month` tinyint NOT NULL DEFAULT '0',
  `hits` bigint NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stats_year`
--

DROP TABLE IF EXISTS `nuke_stats_year`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stats_year` (
  `year` smallint NOT NULL DEFAULT '0',
  `hits` bigint NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stories`
--

DROP TABLE IF EXISTS `nuke_stories`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stories` (
  `sid` int NOT NULL AUTO_INCREMENT,
  `catid` int NOT NULL DEFAULT '0',
  `aid` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `title` varchar(80) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `time` datetime DEFAULT NULL,
  `hometext` mediumtext COLLATE utf8mb4_unicode_ci,
  `bodytext` mediumtext COLLATE utf8mb4_unicode_ci,
  `comments` int DEFAULT '0',
  `counter` mediumint unsigned DEFAULT NULL,
  `topic` int NOT NULL DEFAULT '1',
  `informant` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `notes` mediumtext COLLATE utf8mb4_unicode_ci,
  `ihome` int NOT NULL DEFAULT '0',
  `alanguage` varchar(30) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `acomm` int NOT NULL DEFAULT '0',
  `haspoll` int NOT NULL DEFAULT '0',
  `pollID` int NOT NULL DEFAULT '0',
  `score` int NOT NULL DEFAULT '0',
  `ratings` int NOT NULL DEFAULT '0',
  `rating_ip` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT '0',
  `associated` mediumtext COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`sid`),
  KEY `catid` (`catid`),
  KEY `counter` (`counter`),
  KEY `topic` (`topic`)
) ENGINE=MyISAM AUTO_INCREMENT=4251 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_stories_cat`
--

DROP TABLE IF EXISTS `nuke_stories_cat`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_stories_cat` (
  `catid` int NOT NULL AUTO_INCREMENT,
  `title` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `counter` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`catid`)
) ENGINE=MyISAM AUTO_INCREMENT=15 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_subscriptions`
--

DROP TABLE IF EXISTS `nuke_subscriptions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_subscriptions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `userid` int DEFAULT '0',
  `subscription_expire` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  KEY `id` (`id`,`userid`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_topics`
--

DROP TABLE IF EXISTS `nuke_topics`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_topics` (
  `topicid` int NOT NULL AUTO_INCREMENT,
  `topicname` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `topicimage` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `topictext` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `counter` int NOT NULL DEFAULT '0',
  `id` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `topicid` (`topicid`) USING BTREE
) ENGINE=MyISAM AUTO_INCREMENT=45 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_users`
--

DROP TABLE IF EXISTS `nuke_users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_users` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `date_started` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `username` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_ibl_team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `discordID` bigint unsigned DEFAULT NULL,
  `femail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_website` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_avatar` tinytext COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_regdate` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_icq` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_occ` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_from` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_interests` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_sig` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_viewemail` tinyint DEFAULT NULL,
  `user_theme` int DEFAULT NULL,
  `user_aim` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_yim` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_msnm` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_password` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `storynum` tinyint NOT NULL DEFAULT '10',
  `umode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uorder` tinyint(1) NOT NULL DEFAULT '0',
  `thold` tinyint(1) NOT NULL DEFAULT '0',
  `noscore` tinyint(1) NOT NULL DEFAULT '0',
  `bio` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ublockon` tinyint(1) NOT NULL DEFAULT '0',
  `ublock` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `commentmax` int NOT NULL DEFAULT '4096',
  `counter` int NOT NULL DEFAULT '0',
  `newsletter` int NOT NULL DEFAULT '0',
  `user_posts` int NOT NULL DEFAULT '0',
  `user_attachsig` int NOT NULL DEFAULT '0',
  `user_rank` int NOT NULL DEFAULT '0',
  `user_level` int NOT NULL DEFAULT '1',
  `broadcast` tinyint(1) NOT NULL DEFAULT '1',
  `popmeson` tinyint(1) NOT NULL DEFAULT '0',
  `user_active` tinyint(1) DEFAULT '1',
  `user_session_time` int NOT NULL DEFAULT '0',
  `user_session_page` smallint NOT NULL DEFAULT '0',
  `user_lastvisit` int NOT NULL DEFAULT '0',
  `last_ip` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_timezone` tinyint NOT NULL DEFAULT '10',
  `user_style` tinyint DEFAULT NULL,
  `user_lang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'english',
  `user_dateformat` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'D M d, Y g:i a',
  `user_emailtime` int DEFAULT NULL,
  `user_allowhtml` tinyint(1) DEFAULT '1',
  `user_allowbbcode` tinyint(1) DEFAULT '1',
  `user_allowsmile` tinyint(1) DEFAULT '1',
  `user_allowavatar` tinyint(1) NOT NULL DEFAULT '1',
  `user_allow_viewonline` tinyint(1) NOT NULL DEFAULT '1',
  `user_notify` tinyint(1) NOT NULL DEFAULT '0',
  `user_popup_pm` tinyint(1) NOT NULL DEFAULT '0',
  `user_avatar_type` tinyint NOT NULL DEFAULT '3',
  `user_sig_bbcode_uid` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_actkey` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_newpasswd` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int DEFAULT '0',
  `karma` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  KEY `uname` (`username`),
  KEY `user_session_time` (`user_session_time`),
  KEY `karma` (`karma`),
  KEY `user_email` (`user_email`(250))
) ENGINE=MyISAM AUTO_INCREMENT=779 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_users_backup`
--

DROP TABLE IF EXISTS `nuke_users_backup`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_users_backup` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `date_started` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(60) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `username` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_ibl_team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `discordID` bigint unsigned DEFAULT NULL,
  `femail` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_website` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_avatar` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_regdate` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_icq` varchar(15) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_occ` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_from` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_interests` varchar(150) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_sig` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_viewemail` tinyint DEFAULT NULL,
  `user_theme` int DEFAULT NULL,
  `user_aim` varchar(18) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_yim` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_msnm` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_password` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `storynum` tinyint NOT NULL DEFAULT '10',
  `umode` varchar(10) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `uorder` tinyint(1) NOT NULL DEFAULT '0',
  `thold` tinyint(1) NOT NULL DEFAULT '0',
  `noscore` tinyint(1) NOT NULL DEFAULT '0',
  `bio` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `ublockon` tinyint(1) NOT NULL DEFAULT '0',
  `ublock` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `theme` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `commentmax` int NOT NULL DEFAULT '4096',
  `counter` int NOT NULL DEFAULT '0',
  `newsletter` int NOT NULL DEFAULT '0',
  `user_posts` int NOT NULL DEFAULT '0',
  `user_attachsig` int NOT NULL DEFAULT '0',
  `user_rank` int NOT NULL DEFAULT '0',
  `user_level` int NOT NULL DEFAULT '1',
  `broadcast` tinyint(1) NOT NULL DEFAULT '1',
  `popmeson` tinyint(1) NOT NULL DEFAULT '0',
  `user_active` tinyint(1) DEFAULT '1',
  `user_session_time` int NOT NULL DEFAULT '0',
  `user_session_page` smallint NOT NULL DEFAULT '0',
  `user_lastvisit` int NOT NULL DEFAULT '0',
  `last_ip` varchar(25) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_timezone` tinyint NOT NULL DEFAULT '10',
  `user_style` tinyint DEFAULT NULL,
  `user_lang` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'english',
  `user_dateformat` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'D M d, Y g:i a',
  `user_emailtime` int DEFAULT NULL,
  `user_allowhtml` tinyint(1) DEFAULT '1',
  `user_allowbbcode` tinyint(1) DEFAULT '1',
  `user_allowsmile` tinyint(1) DEFAULT '1',
  `user_allowavatar` tinyint(1) NOT NULL DEFAULT '1',
  `user_allow_viewonline` tinyint(1) NOT NULL DEFAULT '1',
  `user_notify` tinyint(1) NOT NULL DEFAULT '0',
  `user_popup_pm` tinyint(1) NOT NULL DEFAULT '0',
  `user_avatar_type` tinyint NOT NULL DEFAULT '3',
  `user_sig_bbcode_uid` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_actkey` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_newpasswd` varchar(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `points` int DEFAULT '0',
  `karma` tinyint(1) DEFAULT '0',
  PRIMARY KEY (`user_id`),
  KEY `uname` (`username`),
  KEY `user_session_time` (`user_session_time`),
  KEY `karma` (`karma`),
  KEY `user_email` (`user_email`(250))
) ENGINE=MyISAM AUTO_INCREMENT=779 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `nuke_users_temp`
--

DROP TABLE IF EXISTS `nuke_users_temp`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `nuke_users_temp` (
  `user_id` int NOT NULL AUTO_INCREMENT,
  `username` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_password` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `user_regdate` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `check_num` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` varchar(14) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  PRIMARY KEY (`user_id`)
) ENGINE=MyISAM AUTO_INCREMENT=11592 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `olympic_stats`
--

DROP TABLE IF EXISTS `olympic_stats`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `olympic_stats` (
  `id` int NOT NULL AUTO_INCREMENT,
  `year` int NOT NULL DEFAULT '0',
  `pos` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `games` int NOT NULL DEFAULT '0',
  `minutes` int NOT NULL DEFAULT '0',
  `fgm` int NOT NULL DEFAULT '0',
  `fga` int NOT NULL DEFAULT '0',
  `ftm` int NOT NULL DEFAULT '0',
  `fta` int NOT NULL DEFAULT '0',
  `tgm` int NOT NULL DEFAULT '0',
  `tga` int NOT NULL DEFAULT '0',
  `orb` int NOT NULL DEFAULT '0',
  `reb` int NOT NULL DEFAULT '0',
  `ast` int NOT NULL DEFAULT '0',
  `stl` int NOT NULL DEFAULT '0',
  `tvr` int NOT NULL DEFAULT '0',
  `blk` int NOT NULL DEFAULT '0',
  `pf` int NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `id` (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `online`
--

DROP TABLE IF EXISTS `online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `online` (
  `username` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `timeout` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `password_reset_tokens`
--

DROP TABLE IF EXISTS `password_reset_tokens`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `password_reset_tokens` (
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `token` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `poll`
--

DROP TABLE IF EXISTS `poll`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `poll` (
  `id` int NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=3 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `questions`
--

DROP TABLE IF EXISTS `questions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `questions` (
  `id` int NOT NULL AUTO_INCREMENT,
  `pid` int NOT NULL,
  `question` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM AUTO_INCREMENT=8 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `responses`
--

DROP TABLE IF EXISTS `responses`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `responses` (
  `id` int NOT NULL AUTO_INCREMENT,
  `qid` int NOT NULL,
  `ip` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`)
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `sessions`
--

DROP TABLE IF EXISTS `sessions`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `sessions` (
  `id` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `user_id` bigint unsigned DEFAULT NULL,
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` text COLLATE utf8mb4_unicode_ci,
  `payload` longtext COLLATE utf8mb4_unicode_ci NOT NULL,
  `last_activity` int NOT NULL,
  PRIMARY KEY (`id`),
  KEY `sessions_user_id_index` (`user_id`),
  KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_league_teams`
--

DROP TABLE IF EXISTS `user_league_teams`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_league_teams` (
  `user_id` int NOT NULL COMMENT 'Foreign key to nuke_users.user_id',
  `league` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'League identifier (e.g., "ibl", "olympics")',
  `team_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Team name in this league',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`user_id`,`league`),
  KEY `idx_league` (`league`),
  KEY `idx_team_name` (`team_name`),
  CONSTRAINT `fk_user_league_teams_user` FOREIGN KEY (`user_id`) REFERENCES `nuke_users` (`user_id`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='User-to-team mapping per league for multi-league support';
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `user_online`
--

DROP TABLE IF EXISTS `user_online`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `user_online` (
  `session` char(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '',
  `time` int NOT NULL DEFAULT '0'
) ENGINE=MyISAM DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `users`
--

DROP TABLE IF EXISTS `users`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `users` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email_verified_at` timestamp NULL DEFAULT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `remember_token` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NULL DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `users_email_unique` (`email`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_franchise_seasons`
--

DROP TABLE IF EXISTS `ibl_franchise_seasons`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_franchise_seasons` (
  `id` int NOT NULL AUTO_INCREMENT,
  `franchise_id` int NOT NULL,
  `season_year` smallint unsigned NOT NULL,
  `season_ending_year` smallint unsigned NOT NULL,
  `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL,
  `team_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_franchise_season` (`franchise_id`,`season_year`),
  KEY `idx_team_name` (`team_name`),
  KEY `idx_season_year` (`season_year`),
  KEY `idx_ending_year` (`season_ending_year`),
  CONSTRAINT `fk_fs_franchise` FOREIGN KEY (`franchise_id`) REFERENCES `ibl_team_info` (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Table structure for table `ibl_gm_tenures`
--

DROP TABLE IF EXISTS `ibl_gm_tenures`;
/*!40101 SET @saved_cs_client     = @@character_set_client */;
/*!50503 SET character_set_client = utf8mb4 */;
CREATE TABLE `ibl_gm_tenures` (
  `id` int NOT NULL AUTO_INCREMENT,
  `franchise_id` int NOT NULL,
  `gm_username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `start_season_year` smallint unsigned NOT NULL,
  `end_season_year` smallint unsigned DEFAULT NULL,
  `is_mid_season_start` tinyint(1) NOT NULL DEFAULT '0',
  `is_mid_season_end` tinyint(1) NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tenure` (`franchise_id`,`gm_username`,`start_season_year`),
  KEY `idx_gm` (`gm_username`),
  KEY `idx_franchise` (`franchise_id`),
  CONSTRAINT `fk_gt_franchise` FOREIGN KEY (`franchise_id`) REFERENCES `ibl_team_info` (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
/*!40101 SET character_set_client = @saved_cs_client */;

--
-- Temporary view structure for view `vw_career_totals`
--

DROP TABLE IF EXISTS `vw_career_totals`;
/*!50001 DROP VIEW IF EXISTS `vw_career_totals`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
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
-- Temporary view structure for view `vw_current_salary`
--

DROP TABLE IF EXISTS `vw_current_salary`;
/*!50001 DROP VIEW IF EXISTS `vw_current_salary`*/;
SET @saved_cs_client     = @@character_set_client;
/*!50503 SET character_set_client = utf8mb4 */;
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
-- Temporary view structure for view `vw_free_agency_offers`
--

DROP TABLE IF EXISTS `vw_free_agency_offers`;
