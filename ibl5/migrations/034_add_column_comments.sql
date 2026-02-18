-- Migration: 034_add_column_comments.sql
-- Purpose: Add descriptive COMMENT to all uncommented columns across ibl_ tables,
--          correct 2 inaccurate existing comments on ibl_plr.do and ibl_plr.dd,
--          and fix game2GM/game2GA/r_2ga/r_2gp comments (said "FG" but these are two-point only).
-- Convention: Skip commenting created_at, updated_at, uuid, and id (auto-increment PK) columns.
-- Date: 2026-02-16

-- =============================================================================
-- ibl_api_keys
-- =============================================================================
ALTER TABLE `ibl_api_keys` MODIFY COLUMN `permission_level` enum('public','team_owner','commissioner') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'public' COMMENT 'API access tier';
ALTER TABLE `ibl_api_keys` MODIFY COLUMN `rate_limit_tier` enum('standard','elevated','unlimited') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'standard' COMMENT 'Request rate limit category';
ALTER TABLE `ibl_api_keys` MODIFY COLUMN `is_active` tinyint(1) NOT NULL DEFAULT '1' COMMENT '1=active, 0=revoked';
ALTER TABLE `ibl_api_keys` MODIFY COLUMN `last_used_at` timestamp NULL DEFAULT NULL COMMENT 'Last API request timestamp';

-- =============================================================================
-- ibl_api_rate_limits
-- =============================================================================
ALTER TABLE `ibl_api_rate_limits` MODIFY COLUMN `api_key_hash` char(64) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SHA-256 hash of the API key (FK to ibl_api_keys)';
ALTER TABLE `ibl_api_rate_limits` MODIFY COLUMN `request_count` int unsigned NOT NULL DEFAULT '1' COMMENT 'Requests in current window';

-- =============================================================================
-- ibl_awards
-- =============================================================================
ALTER TABLE `ibl_awards` MODIFY COLUMN `year` int NOT NULL DEFAULT '0' COMMENT 'Season year of award';
ALTER TABLE `ibl_awards` MODIFY COLUMN `Award` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Award name (e.g., MVP, DPOY)';
ALTER TABLE `ibl_awards` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Winning player name';

-- =============================================================================
-- ibl_banners
-- =============================================================================
ALTER TABLE `ibl_banners` MODIFY COLUMN `year` int NOT NULL DEFAULT '0' COMMENT 'Championship/award season year';
ALTER TABLE `ibl_banners` MODIFY COLUMN `currentname` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Current franchise team name';
ALTER TABLE `ibl_banners` MODIFY COLUMN `bannername` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name when banner was earned';
ALTER TABLE `ibl_banners` MODIFY COLUMN `bannertype` int NOT NULL DEFAULT '0' COMMENT 'Banner category (championship, division, etc.)';

-- =============================================================================
-- ibl_box_scores (skip generated columns: game_type, season_year, calc_*)
-- =============================================================================
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `Date` date NOT NULL COMMENT 'Game date';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Player name (denormalized snapshot)';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `pos` varchar(2) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Player position at game time';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `pid` int DEFAULT NULL COMMENT 'FK to ibl_plr.pid';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `visitorTID` int DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `homeTID` int DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)';
-- Fix inaccurate comments from migration 004 (said "Field goals" but these are two-point only)
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `game2GM` tinyint unsigned DEFAULT NULL COMMENT 'Two-point field goals made';
ALTER TABLE `ibl_box_scores` MODIFY COLUMN `game2GA` tinyint unsigned DEFAULT NULL COMMENT 'Two-point field goals attempted';

-- =============================================================================
-- ibl_box_scores_teams (skip generated columns: game_type, season_year, calc_*)
-- =============================================================================
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `Date` date NOT NULL COMMENT 'Game date';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `name` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Team name (denormalized snapshot)';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameOfThatDay` int DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd, etc.)';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorTeamID` int DEFAULT NULL COMMENT 'Visiting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeTeamID` int DEFAULT NULL COMMENT 'Home team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `attendance` int DEFAULT NULL COMMENT 'Game attendance';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `capacity` int DEFAULT NULL COMMENT 'Arena capacity';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorWins` int DEFAULT NULL COMMENT 'Visitor record wins before game';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorLosses` int DEFAULT NULL COMMENT 'Visitor record losses before game';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeWins` int DEFAULT NULL COMMENT 'Home record wins before game';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeLosses` int DEFAULT NULL COMMENT 'Home record losses before game';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorQ1points` int DEFAULT NULL COMMENT 'Visitor Q1 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorQ2points` int DEFAULT NULL COMMENT 'Visitor Q2 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorQ3points` int DEFAULT NULL COMMENT 'Visitor Q3 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorQ4points` int DEFAULT NULL COMMENT 'Visitor Q4 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `visitorOTpoints` int DEFAULT NULL COMMENT 'Visitor overtime points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeQ1points` int DEFAULT NULL COMMENT 'Home Q1 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeQ2points` int DEFAULT NULL COMMENT 'Home Q2 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeQ3points` int DEFAULT NULL COMMENT 'Home Q3 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeQ4points` int DEFAULT NULL COMMENT 'Home Q4 points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `homeOTpoints` int DEFAULT NULL COMMENT 'Home overtime points';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameMIN` int DEFAULT NULL COMMENT 'Total game minutes';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `game2GM` int DEFAULT NULL COMMENT 'Two-point field goals made';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `game2GA` int DEFAULT NULL COMMENT 'Two-point field goals attempted';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameFTM` int DEFAULT NULL COMMENT 'Free throws made';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameFTA` int DEFAULT NULL COMMENT 'Free throws attempted';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `game3GM` int DEFAULT NULL COMMENT 'Three pointers made';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `game3GA` int DEFAULT NULL COMMENT 'Three pointers attempted';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameORB` int DEFAULT NULL COMMENT 'Offensive rebounds';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameDRB` int DEFAULT NULL COMMENT 'Defensive rebounds';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameAST` int DEFAULT NULL COMMENT 'Assists';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameSTL` int DEFAULT NULL COMMENT 'Steals';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameTOV` int DEFAULT NULL COMMENT 'Turnovers';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gameBLK` int DEFAULT NULL COMMENT 'Blocks';
ALTER TABLE `ibl_box_scores_teams` MODIFY COLUMN `gamePF` int DEFAULT NULL COMMENT 'Personal fouls';

-- =============================================================================
-- ibl_demands
-- =============================================================================
ALTER TABLE `ibl_demands` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (PK, FK to ibl_plr.name)';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem1` int NOT NULL DEFAULT '0' COMMENT 'FA year 1 day 1 demand';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem2` int NOT NULL DEFAULT '0' COMMENT 'FA year 2 day 1 demand';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem3` int NOT NULL DEFAULT '0' COMMENT 'FA year 3 day 1 demand';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem4` int NOT NULL DEFAULT '0' COMMENT 'FA year 4 day 1 demand';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem5` int NOT NULL DEFAULT '0' COMMENT 'FA year 5 day 1 demand';
ALTER TABLE `ibl_demands` MODIFY COLUMN `dem6` int NOT NULL DEFAULT '0' COMMENT 'FA year 6 day 1 demand';

-- =============================================================================
-- ibl_draft
-- =============================================================================
ALTER TABLE `ibl_draft` MODIFY COLUMN `team` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Drafting team name (FK to ibl_team_info)';
ALTER TABLE `ibl_draft` MODIFY COLUMN `player` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Drafted player name';
ALTER TABLE `ibl_draft` MODIFY COLUMN `date` datetime DEFAULT NULL COMMENT 'Date and time of pick';

-- =============================================================================
-- ibl_draft_class
-- =============================================================================
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Prospect name';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `team` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'College or club team';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `talent` int NOT NULL DEFAULT '0' COMMENT 'Talent off-season progression rating';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `skill` int NOT NULL DEFAULT '0' COMMENT 'Skill  off-season progression rating';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `intangibles` int NOT NULL DEFAULT '0' COMMENT 'Intangibles off-season progression rating';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `ranking` float DEFAULT '0' COMMENT 'Combined draft ranking';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `invite` mediumtext COLLATE utf8mb4_unicode_ci COMMENT 'Combine/tryout invite details';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `drafted` int DEFAULT '0' COMMENT '0=undrafted, 1=drafted';
ALTER TABLE `ibl_draft_class` MODIFY COLUMN `sta` int DEFAULT '0' COMMENT 'Stamina rating';

-- =============================================================================
-- ibl_draft_picks
-- =============================================================================
ALTER TABLE `ibl_draft_picks` MODIFY COLUMN `ownerofpick` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team currently owning pick (FK to ibl_team_info)';
ALTER TABLE `ibl_draft_picks` MODIFY COLUMN `teampick` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Original team the pick belongs to (FK to ibl_team_info)';
ALTER TABLE `ibl_draft_picks` MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Draft year';
ALTER TABLE `ibl_draft_picks` MODIFY COLUMN `round` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Draft round';
ALTER TABLE `ibl_draft_picks` MODIFY COLUMN `notes` varchar(280) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Trade/transaction notes';

-- =============================================================================
-- ibl_fa_offers
-- =============================================================================
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (FK to ibl_plr.name)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Offering team name (FK to ibl_team_info)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer1` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 1 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer2` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 2 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer3` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 3 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer4` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 4 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer5` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 5 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `offer6` int NOT NULL DEFAULT '0' COMMENT 'Salary offer year 6 (thousands)';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `modifier` float NOT NULL DEFAULT '0' COMMENT 'FA decision weight modifier';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `random` float NOT NULL DEFAULT '0' COMMENT 'Random factor in FA decision';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `perceivedvalue` float NOT NULL DEFAULT '0' COMMENT 'Calculated perceived value of offer';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `MLE` int NOT NULL DEFAULT '0' COMMENT '1=offer uses Mid-Level Exception';
ALTER TABLE `ibl_fa_offers` MODIFY COLUMN `LLE` int NOT NULL DEFAULT '0' COMMENT '1=offer uses Lower-Level Exception';

-- =============================================================================
-- ibl_franchise_seasons
-- =============================================================================
ALTER TABLE `ibl_franchise_seasons` MODIFY COLUMN `franchise_id` int NOT NULL COMMENT 'FK to ibl_team_info.teamid';
ALTER TABLE `ibl_franchise_seasons` MODIFY COLUMN `season_year` smallint unsigned NOT NULL COMMENT 'Season starting year';
ALTER TABLE `ibl_franchise_seasons` MODIFY COLUMN `season_ending_year` smallint unsigned NOT NULL COMMENT 'Season ending year';
ALTER TABLE `ibl_franchise_seasons` MODIFY COLUMN `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'City name during that season';
ALTER TABLE `ibl_franchise_seasons` MODIFY COLUMN `team_name` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Team name during that season';

-- =============================================================================
-- ibl_gm_awards
-- =============================================================================
ALTER TABLE `ibl_gm_awards` MODIFY COLUMN `year` int NOT NULL DEFAULT '0' COMMENT 'Season year of award';
ALTER TABLE `ibl_gm_awards` MODIFY COLUMN `Award` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Award name';
ALTER TABLE `ibl_gm_awards` MODIFY COLUMN `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Winning GM username';

-- =============================================================================
-- ibl_gm_history
-- =============================================================================
ALTER TABLE `ibl_gm_history` MODIFY COLUMN `year` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Season year';
ALTER TABLE `ibl_gm_history` MODIFY COLUMN `name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'GM username';
ALTER TABLE `ibl_gm_history` MODIFY COLUMN `Award` varchar(350) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Award name/description';
ALTER TABLE `ibl_gm_history` MODIFY COLUMN `prim` int NOT NULL COMMENT 'Primary key';

-- =============================================================================
-- ibl_gm_tenures
-- =============================================================================
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `franchise_id` int NOT NULL COMMENT 'FK to ibl_team_info.teamid';
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `gm_username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'GM site username';
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `start_season_year` smallint unsigned NOT NULL COMMENT 'First season of tenure';
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `end_season_year` smallint unsigned DEFAULT NULL COMMENT 'Last season of tenure (NULL=current)';
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `is_mid_season_start` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=took over mid-season';
ALTER TABLE `ibl_gm_tenures` MODIFY COLUMN `is_mid_season_end` tinyint(1) NOT NULL DEFAULT '0' COMMENT '1=left mid-season';

-- =============================================================================
-- ibl_hist
-- =============================================================================
ALTER TABLE `ibl_hist` MODIFY COLUMN `pid` int NOT NULL DEFAULT '0' COMMENT 'Player ID (FK to ibl_plr)';
ALTER TABLE `ibl_hist` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (denormalized snapshot)';
ALTER TABLE `ibl_hist` MODIFY COLUMN `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name (denormalized snapshot)';
ALTER TABLE `ibl_hist` MODIFY COLUMN `teamid` int NOT NULL DEFAULT '0' COMMENT 'Team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_2ga` int NOT NULL DEFAULT '0' COMMENT 'Rating: 2P attempts';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_2gp` int NOT NULL DEFAULT '0' COMMENT 'Rating: 2P percentage';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_fta` int NOT NULL DEFAULT '0' COMMENT 'Rating: FT attempts';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_ftp` int NOT NULL DEFAULT '0' COMMENT 'Rating: FT percentage';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_3ga` int NOT NULL DEFAULT '0' COMMENT 'Rating: 3P attempts';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_3gp` int NOT NULL DEFAULT '0' COMMENT 'Rating: 3P percentage';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_orb` int NOT NULL DEFAULT '0' COMMENT 'Rating: offensive rebounds';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_drb` int NOT NULL DEFAULT '0' COMMENT 'Rating: defensive rebounds';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_ast` int NOT NULL DEFAULT '0' COMMENT 'Rating: assists';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_stl` int NOT NULL DEFAULT '0' COMMENT 'Rating: steals';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_blk` int NOT NULL DEFAULT '0' COMMENT 'Rating: blocks';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_tvr` int NOT NULL DEFAULT '0' COMMENT 'Rating: turnovers';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_oo` int NOT NULL DEFAULT '0' COMMENT 'Rating: outside offense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_do` int NOT NULL DEFAULT '0' COMMENT 'Rating: drive offense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_po` int NOT NULL DEFAULT '0' COMMENT 'Rating: post offense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_to` int NOT NULL DEFAULT '0' COMMENT 'Rating: transition offense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_od` int NOT NULL DEFAULT '0' COMMENT 'Rating: outside defense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_dd` int NOT NULL DEFAULT '0' COMMENT 'Rating: drive defense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_pd` int NOT NULL DEFAULT '0' COMMENT 'Rating: post defense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `r_td` int NOT NULL DEFAULT '0' COMMENT 'Rating: transition defense';
ALTER TABLE `ibl_hist` MODIFY COLUMN `salary` int NOT NULL DEFAULT '0' COMMENT 'Salary that season (thousands)';

-- =============================================================================
-- ibl_league_config
-- =============================================================================
ALTER TABLE `ibl_league_config` MODIFY COLUMN `season_ending_year` smallint unsigned NOT NULL COMMENT 'Season ending year';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `team_slot` tinyint unsigned NOT NULL COMMENT 'Team position in conference bracket';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `team_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Team name (FK to ibl_team_info)';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `conference` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Conference name (Eastern/Western)';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `division` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Division name';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `playoff_qualifiers_per_conf` tinyint unsigned NOT NULL COMMENT 'Playoff teams per conference';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `playoff_round1_format` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Round 1 series format (e.g., bo7)';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `playoff_round2_format` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Round 2 series format';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `playoff_round3_format` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Round 3 series format';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `playoff_round4_format` varchar(8) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Round 4 series format (finals)';
ALTER TABLE `ibl_league_config` MODIFY COLUMN `team_count` tinyint unsigned NOT NULL COMMENT 'Total teams in league that season';

-- =============================================================================
-- ibl_olympics_career_avgs
-- =============================================================================
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `pid` int NOT NULL DEFAULT '0' COMMENT 'Player ID (FK to ibl_plr)';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (denormalized)';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `games` int NOT NULL DEFAULT '0' COMMENT 'Olympic games played';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `minutes` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg minutes per game';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `fgm` decimal(8,2) NOT NULL COMMENT 'Avg field goals made';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `fga` decimal(8,2) NOT NULL COMMENT 'Avg field goals attempted';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `fgpct` decimal(8,3) NOT NULL DEFAULT '0.000' COMMENT 'Field goal percentage';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `ftm` decimal(8,2) NOT NULL COMMENT 'Avg free throws made';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `fta` decimal(8,2) NOT NULL COMMENT 'Avg free throws attempted';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `ftpct` decimal(8,3) NOT NULL DEFAULT '0.000' COMMENT 'Free throw percentage';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `tgm` decimal(8,2) NOT NULL COMMENT 'Avg three pointers made';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `tga` decimal(8,2) NOT NULL COMMENT 'Avg three pointers attempted';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `tpct` decimal(8,3) NOT NULL DEFAULT '0.000' COMMENT 'Three point percentage';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `orb` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg offensive rebounds';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `reb` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg total rebounds';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `ast` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg assists';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `stl` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg steals';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `tvr` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg turnovers';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `blk` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg blocks';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `pf` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg personal fouls';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `pts` decimal(8,2) NOT NULL DEFAULT '0.00' COMMENT 'Avg points';
ALTER TABLE `ibl_olympics_career_avgs` MODIFY COLUMN `retired` int NOT NULL DEFAULT '0' COMMENT '1=retired from league';

-- =============================================================================
-- ibl_olympics_career_totals
-- =============================================================================
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `pid` int NOT NULL DEFAULT '0' COMMENT 'Player ID (FK to ibl_plr)';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (denormalized)';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `games` int NOT NULL DEFAULT '0' COMMENT 'Total Olympic games played';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `minutes` int NOT NULL DEFAULT '0' COMMENT 'Total minutes played';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `fgm` int NOT NULL DEFAULT '0' COMMENT 'Total field goals made';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `fga` int NOT NULL DEFAULT '0' COMMENT 'Total field goals attempted';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `ftm` int NOT NULL DEFAULT '0' COMMENT 'Total free throws made';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `fta` int NOT NULL DEFAULT '0' COMMENT 'Total free throws attempted';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `tgm` int NOT NULL DEFAULT '0' COMMENT 'Total three pointers made';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `tga` int NOT NULL DEFAULT '0' COMMENT 'Total three pointers attempted';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `orb` int NOT NULL DEFAULT '0' COMMENT 'Total offensive rebounds';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `reb` int NOT NULL DEFAULT '0' COMMENT 'Total rebounds';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `ast` int NOT NULL DEFAULT '0' COMMENT 'Total assists';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `stl` int NOT NULL DEFAULT '0' COMMENT 'Total steals';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `tvr` int NOT NULL DEFAULT '0' COMMENT 'Total turnovers';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `blk` int NOT NULL DEFAULT '0' COMMENT 'Total blocks';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `pf` int NOT NULL DEFAULT '0' COMMENT 'Total personal fouls';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `pts` int NOT NULL DEFAULT '0' COMMENT 'Total points';
ALTER TABLE `ibl_olympics_career_totals` MODIFY COLUMN `retired` int NOT NULL DEFAULT '0' COMMENT '1=retired from league';

-- =============================================================================
-- ibl_olympics_power
-- =============================================================================
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `TeamID` smallint NOT NULL DEFAULT '0' COMMENT 'Team ID (FK to ibl_olympics_team_info)';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `Team` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name (PK)';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `Division` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Division/group name';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `Conference` varchar(20) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Conference name';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `ranking` decimal(6,1) NOT NULL DEFAULT '0.0' COMMENT 'Power ranking score (0.0-100.0)';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `win` smallint NOT NULL DEFAULT '0' COMMENT 'Overall wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `loss` smallint NOT NULL DEFAULT '0' COMMENT 'Overall losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `gb` decimal(6,1) NOT NULL DEFAULT '0.0' COMMENT 'Games behind leader';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `conf_win` int NOT NULL COMMENT 'Conference wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `conf_loss` int NOT NULL COMMENT 'Conference losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `div_win` int NOT NULL COMMENT 'Division wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `div_loss` int NOT NULL COMMENT 'Division losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `home_win` int NOT NULL COMMENT 'Home wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `home_loss` int NOT NULL COMMENT 'Home losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `road_win` int NOT NULL COMMENT 'Road wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `road_loss` int NOT NULL COMMENT 'Road losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `last_win` int NOT NULL COMMENT 'Last 10 games wins';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `last_loss` int NOT NULL COMMENT 'Last 10 games losses';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `streak_type` varchar(1) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'W=winning, L=losing';
ALTER TABLE `ibl_olympics_power` MODIFY COLUMN `streak` int NOT NULL COMMENT 'Current streak length';

-- =============================================================================
-- ibl_olympics_stats
-- =============================================================================
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `year` int NOT NULL DEFAULT '0' COMMENT 'Olympic tournament year';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `pos` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player position';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Player name (FK to ibl_plr)';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `team` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'National team represented';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `games` int NOT NULL DEFAULT '0' COMMENT 'Games played';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `minutes` int NOT NULL DEFAULT '0' COMMENT 'Total minutes played';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `fgm` int NOT NULL DEFAULT '0' COMMENT 'Field goals made';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `fga` int NOT NULL DEFAULT '0' COMMENT 'Field goals attempted';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `ftm` int NOT NULL DEFAULT '0' COMMENT 'Free throws made';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `fta` int NOT NULL DEFAULT '0' COMMENT 'Free throws attempted';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `tgm` int NOT NULL DEFAULT '0' COMMENT 'Three pointers made';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `tga` int NOT NULL DEFAULT '0' COMMENT 'Three pointers attempted';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `orb` int NOT NULL DEFAULT '0' COMMENT 'Offensive rebounds';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `reb` int NOT NULL DEFAULT '0' COMMENT 'Total rebounds';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `ast` int NOT NULL DEFAULT '0' COMMENT 'Assists';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `stl` int NOT NULL DEFAULT '0' COMMENT 'Steals';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `tvr` int NOT NULL DEFAULT '0' COMMENT 'Turnovers';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `blk` int NOT NULL DEFAULT '0' COMMENT 'Blocks';
ALTER TABLE `ibl_olympics_stats` MODIFY COLUMN `pf` int NOT NULL DEFAULT '0' COMMENT 'Personal fouls';

-- =============================================================================
-- ibl_olympics_standings (one column missed from initial pass)
-- =============================================================================
ALTER TABLE `ibl_olympics_standings` MODIFY COLUMN `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name (denormalized)';

-- =============================================================================
-- ibl_one_on_one
-- =============================================================================
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `gameid` int NOT NULL DEFAULT '0' COMMENT 'Game identifier (PK)';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `playbyplay` mediumtext COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Full play-by-play text';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `winner` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Winning player name';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `loser` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Losing player name';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `winscore` int NOT NULL DEFAULT '0' COMMENT 'Winner final score';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `lossscore` int NOT NULL DEFAULT '0' COMMENT 'Loser final score';
ALTER TABLE `ibl_one_on_one` MODIFY COLUMN `owner` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'GM who submitted the matchup';

-- =============================================================================
-- ibl_playoff_results
-- =============================================================================
ALTER TABLE `ibl_playoff_results` MODIFY COLUMN `winner` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Series-winning team name';
ALTER TABLE `ibl_playoff_results` MODIFY COLUMN `loser` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Series-losing team name';
ALTER TABLE `ibl_playoff_results` MODIFY COLUMN `loser_games` int NOT NULL DEFAULT '0' COMMENT 'Games won by losing team in series';

-- =============================================================================
-- ibl_plr — Corrections to existing inaccurate comments
-- =============================================================================
ALTER TABLE `ibl_plr` MODIFY COLUMN `do` tinyint unsigned DEFAULT '0' COMMENT 'Drive offense rating';
ALTER TABLE `ibl_plr` MODIFY COLUMN `dd` tinyint unsigned DEFAULT '0' COMMENT 'Drive defense rating';

-- =============================================================================
-- ibl_plr — New comments on previously uncommented columns
-- =============================================================================
ALTER TABLE `ibl_plr` MODIFY COLUMN `ordinal` int DEFAULT '0' COMMENT 'Roster sort order (0-800=rostered, 1000=waivers)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `nickname` varchar(64) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Player nickname';
ALTER TABLE `ibl_plr` MODIFY COLUMN `age` tinyint unsigned DEFAULT NULL COMMENT 'Player age in years';
ALTER TABLE `ibl_plr` MODIFY COLUMN `peak` tinyint unsigned DEFAULT NULL COMMENT 'Peak development age';
ALTER TABLE `ibl_plr` MODIFY COLUMN `teamname` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Team name (denormalized from ibl_team_info)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `Clutch` tinyint DEFAULT NULL COMMENT 'Clutch performance rating';
ALTER TABLE `ibl_plr` MODIFY COLUMN `Consistency` tinyint DEFAULT NULL COMMENT 'Consistency rating';
ALTER TABLE `ibl_plr` MODIFY COLUMN `active` tinyint(1) DEFAULT NULL COMMENT 'On depth chart (1=yes, NOT retired status)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `coach` tinyint unsigned DEFAULT '0' COMMENT 'Coaching compatibility rating';
ALTER TABLE `ibl_plr` MODIFY COLUMN `loyalty` tinyint DEFAULT NULL COMMENT 'FA pref: team loyalty weight';
ALTER TABLE `ibl_plr` MODIFY COLUMN `playingTime` tinyint DEFAULT NULL COMMENT 'FA pref: playing time weight';
ALTER TABLE `ibl_plr` MODIFY COLUMN `winner` tinyint DEFAULT NULL COMMENT 'FA pref: winning culture weight';
ALTER TABLE `ibl_plr` MODIFY COLUMN `tradition` tinyint DEFAULT NULL COMMENT 'FA pref: franchise tradition weight';
ALTER TABLE `ibl_plr` MODIFY COLUMN `security` tinyint DEFAULT NULL COMMENT 'FA pref: contract security weight';
ALTER TABLE `ibl_plr` MODIFY COLUMN `bird` tinyint(1) DEFAULT NULL COMMENT 'Consecutive years with team (Bird Rights)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy` tinyint unsigned DEFAULT '0' COMMENT 'Current contract year (0=unsigned, 1-6)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cyt` tinyint unsigned DEFAULT '0' COMMENT 'Contract total years (1-6)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy1` smallint DEFAULT '0' COMMENT 'Salary for contract year 1 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy2` smallint DEFAULT '0' COMMENT 'Salary for contract year 2 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy3` smallint DEFAULT '0' COMMENT 'Salary for contract year 3 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy4` smallint DEFAULT '0' COMMENT 'Salary for contract year 4 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy5` smallint DEFAULT '0' COMMENT 'Salary for contract year 5 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `cy6` smallint DEFAULT '0' COMMENT 'Salary for contract year 6 (thousands, negative=team option)';
ALTER TABLE `ibl_plr` MODIFY COLUMN `draftedby` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Original drafting team name';
ALTER TABLE `ibl_plr` MODIFY COLUMN `draftedbycurrentname` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Drafting team current name';
ALTER TABLE `ibl_plr` MODIFY COLUMN `injured` tinyint unsigned DEFAULT NULL COMMENT '1=currently injured';
ALTER TABLE `ibl_plr` MODIFY COLUMN `retired` tinyint(1) DEFAULT NULL COMMENT '1=retired from league';
ALTER TABLE `ibl_plr` MODIFY COLUMN `college` varchar(40) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'College or amateur team';
ALTER TABLE `ibl_plr` MODIFY COLUMN `droptime` int DEFAULT '0' COMMENT 'Unix timestamp when placed on waivers (0=not on waivers)';

-- =============================================================================
-- ibl_plr_chunk
-- =============================================================================
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `active` int NOT NULL DEFAULT '0' COMMENT 'On depth chart at chunk time';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `pid` int NOT NULL DEFAULT '0' COMMENT 'FK to ibl_plr.pid';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `ordinal` int NOT NULL COMMENT 'Roster sort order';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Player name (snapshot)';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `tid` int NOT NULL DEFAULT '0' COMMENT 'Team ID';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `teamname` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name (snapshot)';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `pos` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Primary position';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `altpos` varchar(4) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Alternate position';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_gs` int NOT NULL DEFAULT '0' COMMENT 'Games started in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_gm` int NOT NULL DEFAULT '0' COMMENT 'Games played in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_min` int NOT NULL DEFAULT '0' COMMENT 'Minutes played in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_fgm` int NOT NULL DEFAULT '0' COMMENT 'Field goals made in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_fga` int NOT NULL DEFAULT '0' COMMENT 'Field goals attempted in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_ftm` int NOT NULL DEFAULT '0' COMMENT 'Free throws made in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_fta` int NOT NULL DEFAULT '0' COMMENT 'Free throws attempted in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_3gm` int NOT NULL DEFAULT '0' COMMENT 'Three pointers made in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_3ga` int NOT NULL DEFAULT '0' COMMENT 'Three pointers attempted in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_orb` int NOT NULL DEFAULT '0' COMMENT 'Offensive rebounds in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_drb` int NOT NULL DEFAULT '0' COMMENT 'Defensive rebounds in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_ast` int NOT NULL DEFAULT '0' COMMENT 'Assists in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_stl` int NOT NULL DEFAULT '0' COMMENT 'Steals in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_to` int NOT NULL DEFAULT '0' COMMENT 'Turnovers in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_blk` int NOT NULL DEFAULT '0' COMMENT 'Blocks in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `stats_pf` int NOT NULL DEFAULT '0' COMMENT 'Personal fouls in chunk';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `chunk` int DEFAULT NULL COMMENT 'Sim chunk number';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `qa` decimal(11,2) NOT NULL DEFAULT '0.00' COMMENT 'Quality assessment score';
ALTER TABLE `ibl_plr_chunk` MODIFY COLUMN `Season` int NOT NULL COMMENT 'Season year';

-- =============================================================================
-- ibl_power
-- =============================================================================
ALTER TABLE `ibl_power` MODIFY COLUMN `TeamID` smallint NOT NULL DEFAULT '0' COMMENT 'Team ID (PK, FK to ibl_team_info)';
ALTER TABLE `ibl_power` MODIFY COLUMN `ranking` decimal(6,1) NOT NULL DEFAULT '0.0' COMMENT 'Power ranking score (0.0-100.0)';
ALTER TABLE `ibl_power` MODIFY COLUMN `last_win` int NOT NULL COMMENT 'Last 10 games wins';
ALTER TABLE `ibl_power` MODIFY COLUMN `last_loss` int NOT NULL COMMENT 'Last 10 games losses';
ALTER TABLE `ibl_power` MODIFY COLUMN `streak_type` varchar(1) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'W=winning, L=losing';
ALTER TABLE `ibl_power` MODIFY COLUMN `streak` int NOT NULL COMMENT 'Current streak length';
ALTER TABLE `ibl_power` MODIFY COLUMN `sos` decimal(4,3) NOT NULL DEFAULT '0.000' COMMENT 'Strength of schedule';
ALTER TABLE `ibl_power` MODIFY COLUMN `remaining_sos` decimal(4,3) NOT NULL DEFAULT '0.000' COMMENT 'Remaining strength of schedule';
ALTER TABLE `ibl_power` MODIFY COLUMN `sos_rank` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'SOS league rank';
ALTER TABLE `ibl_power` MODIFY COLUMN `remaining_sos_rank` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Remaining SOS league rank';

-- =============================================================================
-- ibl_saved_depth_chart_players
-- =============================================================================
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `depth_chart_id` int unsigned NOT NULL COMMENT 'FK to ibl_saved_depth_charts.id';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `pid` int NOT NULL COMMENT 'Player ID at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `ordinal` int NOT NULL DEFAULT '0' COMMENT 'Roster sort order at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_PGDepth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Point guard depth setting';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_SGDepth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Shooting guard depth setting';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_SFDepth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Small forward depth setting';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_PFDepth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Power forward depth setting';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_CDepth` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Center depth setting';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_active` tinyint unsigned NOT NULL DEFAULT '1' COMMENT 'Active flag at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_minutes` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Minutes setting at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_of` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Offensive focus at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_df` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Defensive focus at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_oi` tinyint NOT NULL DEFAULT '0' COMMENT 'Offensive importance at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_di` tinyint NOT NULL DEFAULT '0' COMMENT 'Defensive importance at save time';
ALTER TABLE `ibl_saved_depth_chart_players` MODIFY COLUMN `dc_bh` tinyint NOT NULL DEFAULT '0' COMMENT 'Ball handling at save time';

-- =============================================================================
-- ibl_saved_depth_charts
-- =============================================================================
ALTER TABLE `ibl_saved_depth_charts` MODIFY COLUMN `tid` int NOT NULL COMMENT 'Team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_saved_depth_charts` MODIFY COLUMN `username` varchar(25) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'GM username who saved';
ALTER TABLE `ibl_saved_depth_charts` MODIFY COLUMN `sim_number_start` int unsigned NOT NULL COMMENT 'Sim number when chart was saved';
ALTER TABLE `ibl_saved_depth_charts` MODIFY COLUMN `sim_number_end` int unsigned DEFAULT NULL COMMENT 'Latest sim number chart was active';
ALTER TABLE `ibl_saved_depth_charts` MODIFY COLUMN `is_active` tinyint unsigned NOT NULL DEFAULT '1' COMMENT '1=currently active depth chart';

-- =============================================================================
-- ibl_schedule
-- =============================================================================
ALTER TABLE `ibl_schedule` MODIFY COLUMN `BoxID` int NOT NULL DEFAULT '0' COMMENT 'Link to box score data';
ALTER TABLE `ibl_schedule` MODIFY COLUMN `Date` date NOT NULL COMMENT 'Game date';
ALTER TABLE `ibl_schedule` MODIFY COLUMN `Visitor` int NOT NULL DEFAULT '0' COMMENT 'Visiting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_schedule` MODIFY COLUMN `Home` int NOT NULL DEFAULT '0' COMMENT 'Home team ID (FK to ibl_team_info)';

-- =============================================================================
-- ibl_settings
-- =============================================================================
ALTER TABLE `ibl_settings` MODIFY COLUMN `name` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Setting key';
ALTER TABLE `ibl_settings` MODIFY COLUMN `value` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Setting value';

-- =============================================================================
-- ibl_sim_dates
-- =============================================================================
ALTER TABLE `ibl_sim_dates` MODIFY COLUMN `Start Date` date DEFAULT NULL COMMENT 'First date in sim range';
ALTER TABLE `ibl_sim_dates` MODIFY COLUMN `End Date` date DEFAULT NULL COMMENT 'Last date in sim range';

-- =============================================================================
-- ibl_standings
-- =============================================================================
ALTER TABLE `ibl_standings` MODIFY COLUMN `tid` int NOT NULL COMMENT 'Team ID (PK, FK to ibl_team_info)';
ALTER TABLE `ibl_standings` MODIFY COLUMN `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team name (denormalized)';
ALTER TABLE `ibl_standings` MODIFY COLUMN `pct` float(4,3) unsigned DEFAULT NULL COMMENT 'Winning percentage (0.000-1.000)';
ALTER TABLE `ibl_standings` MODIFY COLUMN `leagueRecord` varchar(5) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Overall W-L as string';
ALTER TABLE `ibl_standings` MODIFY COLUMN `wins` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Total wins';
ALTER TABLE `ibl_standings` MODIFY COLUMN `losses` tinyint unsigned NOT NULL DEFAULT '0' COMMENT 'Total losses';
ALTER TABLE `ibl_standings` MODIFY COLUMN `confRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Conference W-L as string';
ALTER TABLE `ibl_standings` MODIFY COLUMN `confGB` decimal(3,1) DEFAULT NULL COMMENT 'Games behind conference leader';
ALTER TABLE `ibl_standings` MODIFY COLUMN `division` varchar(16) COLLATE utf8mb4_unicode_ci DEFAULT '' COMMENT 'Division name';
ALTER TABLE `ibl_standings` MODIFY COLUMN `divRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Division W-L as string';
ALTER TABLE `ibl_standings` MODIFY COLUMN `divGB` decimal(3,1) DEFAULT NULL COMMENT 'Games behind division leader';
ALTER TABLE `ibl_standings` MODIFY COLUMN `homeRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Home W-L as string';
ALTER TABLE `ibl_standings` MODIFY COLUMN `awayRecord` varchar(5) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Away W-L as string';
ALTER TABLE `ibl_standings` MODIFY COLUMN `clinchedConference` tinyint(1) DEFAULT NULL COMMENT '1=clinched conference seed';
ALTER TABLE `ibl_standings` MODIFY COLUMN `clinchedDivision` tinyint(1) DEFAULT NULL COMMENT '1=clinched division title';
ALTER TABLE `ibl_standings` MODIFY COLUMN `clinchedPlayoffs` tinyint(1) DEFAULT NULL COMMENT '1=clinched playoff berth';

-- =============================================================================
-- ibl_team_awards
-- =============================================================================
ALTER TABLE `ibl_team_awards` MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT '0' COMMENT 'Season year of award';
ALTER TABLE `ibl_team_awards` MODIFY COLUMN `name` varchar(35) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Team name';
ALTER TABLE `ibl_team_awards` MODIFY COLUMN `Award` varchar(350) COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'Award description';

-- =============================================================================
-- ibl_team_info
-- =============================================================================
ALTER TABLE `ibl_team_info` MODIFY COLUMN `teamid` int NOT NULL DEFAULT '0' COMMENT 'Team ID (PK)';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Franchise city';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Franchise name';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `color1` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Primary hex color (no #)';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `color2` varchar(6) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Secondary hex color (no #)';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `arena` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Home arena name';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `capacity` int NOT NULL DEFAULT '0' COMMENT 'Arena seating capacity';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `owner_name` varchar(32) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'GM display name';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `owner_email` varchar(48) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'GM email address';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `discordID` bigint unsigned DEFAULT NULL COMMENT 'GM Discord user ID';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Contract_Wins` int NOT NULL DEFAULT '0' COMMENT 'Wins during GM contract period';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Contract_Losses` int NOT NULL DEFAULT '0' COMMENT 'Losses during GM contract period';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Contract_AvgW` int NOT NULL DEFAULT '0' COMMENT 'Avg wins per season in GM contract';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Contract_AvgL` int NOT NULL DEFAULT '0' COMMENT 'Avg losses per season in GM contract';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Contract_Coach` decimal(3,2) NOT NULL DEFAULT '0.00' COMMENT 'Coaching quality factor (0.00-9.99)';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Used_Extension_This_Chunk` int NOT NULL DEFAULT '0' COMMENT '1=used extension in current sim chunk';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `Used_Extension_This_Season` int DEFAULT '0' COMMENT '1=used extension this season';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `HasMLE` int NOT NULL DEFAULT '0' COMMENT '1=Mid-Level Exception already used';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `HasLLE` int NOT NULL DEFAULT '0' COMMENT '1=Lower-Level Exception already used';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `chart` char(2) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Depth chart format code';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `depth` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Depth chart submission status';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `sim_depth` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Depth Chart' COMMENT 'Depth chart status at last sim';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `asg_vote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Vote' COMMENT 'All-Star voting status';
ALTER TABLE `ibl_team_info` MODIFY COLUMN `eoy_vote` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'No Vote' COMMENT 'End-of-year voting status';

-- =============================================================================
-- ibl_trade_cash
-- =============================================================================
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `tradeOfferID` int NOT NULL COMMENT 'FK to ibl_trade_offers.id';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `sendingTeam` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team sending cash';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `receivingTeam` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team receiving cash';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy1` int DEFAULT NULL COMMENT 'Cash amount year 1 (thousands)';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy2` int DEFAULT NULL COMMENT 'Cash amount year 2 (thousands)';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy3` int DEFAULT NULL COMMENT 'Cash amount year 3 (thousands)';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy4` int DEFAULT NULL COMMENT 'Cash amount year 4 (thousands)';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy5` int DEFAULT NULL COMMENT 'Cash amount year 5 (thousands)';
ALTER TABLE `ibl_trade_cash` MODIFY COLUMN `cy6` int DEFAULT NULL COMMENT 'Cash amount year 6 (thousands)';

-- =============================================================================
-- ibl_trade_info
-- =============================================================================
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `tradeofferid` int NOT NULL DEFAULT '0' COMMENT 'FK to ibl_trade_offers.id';
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `itemid` int NOT NULL DEFAULT '0' COMMENT 'ID of traded item (player pid or draft pick id)';
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `itemtype` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Item category: 0=draft pick, 1=player, cash=cash';
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `from` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Sending team name';
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `to` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Receiving team name';
ALTER TABLE `ibl_trade_info` MODIFY COLUMN `approval` varchar(128) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Team approval status';

-- =============================================================================
-- ibl_trade_queue
-- =============================================================================
ALTER TABLE `ibl_trade_queue` MODIFY COLUMN `query` text COLLATE utf8mb4_unicode_ci NOT NULL COMMENT 'SQL query to execute for trade processing';
ALTER TABLE `ibl_trade_queue` MODIFY COLUMN `tradeline` text COLLATE utf8mb4_unicode_ci COMMENT 'Human-readable trade summary line';

-- =============================================================================
-- ibl_votes_ASG
-- =============================================================================
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `teamid` int NOT NULL DEFAULT '0' COMMENT 'Voting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Voting team city (denormalized)';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Voting team name (denormalized)';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_F1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 1st pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_F2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 2nd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_F3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 3rd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_F4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern frontcourt 4th pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_B1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 1st pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_B2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 2nd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_B3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 3rd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `East_B4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Eastern backcourt 4th pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_F1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 1st pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_F2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 2nd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_F3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 3rd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_F4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western frontcourt 4th pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_B1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 1st pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_B2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 2nd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_B3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 3rd pick';
ALTER TABLE `ibl_votes_ASG` MODIFY COLUMN `West_B4` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Western backcourt 4th pick';

-- =============================================================================
-- ibl_votes_EOY
-- =============================================================================
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `teamid` int NOT NULL DEFAULT '0' COMMENT 'Voting team ID (FK to ibl_team_info)';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `team_city` varchar(24) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Voting team city (denormalized)';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `team_name` varchar(16) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT 'Voting team name (denormalized)';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `MVP_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 1st place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `MVP_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 2nd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `MVP_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'MVP ballot 3rd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `Six_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 1st place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `Six_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 2nd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `Six_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Sixth Man ballot 3rd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `ROY_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 1st place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `ROY_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 2nd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `ROY_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'Rookie of Year ballot 3rd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `GM_1` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 1st place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `GM_2` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 2nd place';
ALTER TABLE `ibl_votes_EOY` MODIFY COLUMN `GM_3` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT 'GM of Year ballot 3rd place';
