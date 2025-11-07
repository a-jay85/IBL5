-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 7: Naming Convention Standardization
-- ============================================================================
-- This migration implements naming convention standardization:
-- 1. Standardize column naming to snake_case (Priority 2.2)
-- 2. Rename ID columns to consistent *_id pattern (Priority 2.2)
-- 3. Remove reserved word column names (Priority 2.2)
--
-- PREREQUISITES:
-- - Phase 1, Phase 2, and Phase 3 migrations must be completed
-- - InnoDB tables with foreign keys and UUIDs in place
--
-- IMPORTANT: This is a BREAKING CHANGE
-- - Requires extensive application code updates
-- - Should be deferred to API v2 release
-- - Run during extended maintenance window
-- - Estimated time: 45-90 minutes
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- ============================================================================
-- PHASE 7 OVERVIEW
-- ============================================================================
-- This migration renames 46 columns across 14 tables to follow consistent
-- naming conventions:
--
-- Naming Standards Applied:
-- 1. All columns use lowercase snake_case (no PascalCase or camelCase)
-- 2. All ID columns follow *_id pattern (not *ID, *id, or id*)
-- 3. Reserved SQL words are avoided (Date -> game_date, Year -> season_year)
-- 4. Spaces in column names are replaced with underscores
--
-- Tables Affected:
-- - ibl_schedule (8 columns) - Most impactful
-- - ibl_team_info (10 columns)
-- - ibl_plr (7 columns)
-- - ibl_box_scores (3 columns)
-- - ibl_box_scores_teams (3 columns)
-- - ibl_power (4 columns)
-- - ibl_sim_dates (3 columns)
-- - ibl_team_awards (2 columns)
-- - ibl_awards (1 column)
-- - ibl_gm_history (1 column)
-- - ibl_plr_chunk (1 column)
-- - ibl_team_defense_stats (1 column)
-- - ibl_team_offense_stats (1 column)
-- - ibl_trade_cash (1 column)
--
-- Foreign Keys Affected: 5
-- Indexes Affected: 15+
-- Database Views Affected: 1 (vw_schedule_upcoming)
--
-- ============================================================================

-- ============================================================================
-- PART 1: DROP AND RECREATE AFFECTED FOREIGN KEY CONSTRAINTS
-- ============================================================================
-- Foreign keys must be dropped before renaming columns they reference

-- ---------------------------------------------------------------------------
-- 1.1: Drop Foreign Keys from ibl_schedule
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_schedule
  DROP FOREIGN KEY fk_schedule_home,
  DROP FOREIGN KEY fk_schedule_visitor;

-- ---------------------------------------------------------------------------
-- 1.2: Drop Foreign Keys from ibl_box_scores
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores
  DROP FOREIGN KEY fk_boxscore_home,
  DROP FOREIGN KEY fk_boxscore_visitor;

-- ---------------------------------------------------------------------------
-- 1.3: Drop Foreign Keys from ibl_box_scores_teams
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores_teams
  DROP FOREIGN KEY fk_boxscoreteam_home,
  DROP FOREIGN KEY fk_boxscoreteam_visitor;

-- ---------------------------------------------------------------------------
-- 1.4: Drop Foreign Keys from ibl_power
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_power
  DROP FOREIGN KEY fk_power_team;

-- ---------------------------------------------------------------------------
-- 1.5: Drop Foreign Keys from ibl_team_offense_stats and ibl_team_defense_stats
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_offense_stats
  DROP FOREIGN KEY fk_team_offense_team;

ALTER TABLE ibl_team_defense_stats
  DROP FOREIGN KEY fk_team_defense_team;

-- ============================================================================
-- PART 2: DROP AFFECTED DATABASE VIEWS
-- ============================================================================
-- Views must be recreated after column renames

DROP VIEW IF EXISTS vw_schedule_upcoming;

-- ============================================================================
-- PART 3: RENAME COLUMNS TO SNAKE_CASE AND STANDARDIZE ID PATTERNS
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 3.1: ibl_schedule (Highest Priority - Most Used Table)
-- ---------------------------------------------------------------------------
-- This table has the most naming issues and is heavily used in queries

ALTER TABLE ibl_schedule
  CHANGE COLUMN `Year` season_year YEAR(4) NOT NULL DEFAULT 0000,
  CHANGE COLUMN `BoxID` box_score_id INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `Date` game_date DATE NOT NULL,
  CHANGE COLUMN `Visitor` visitor_team_id INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `VScore` visitor_score INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `Home` home_team_id INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `HScore` home_score INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `SchedID` schedule_id INT(11) NOT NULL AUTO_INCREMENT;

-- ---------------------------------------------------------------------------
-- 3.2: ibl_box_scores
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores
  CHANGE COLUMN `Date` game_date DATE NOT NULL,
  CHANGE COLUMN `homeTID` home_team_id INT(11) DEFAULT NULL,
  CHANGE COLUMN `visitorTID` visitor_team_id INT(11) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3.3: ibl_box_scores_teams
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores_teams
  CHANGE COLUMN `Date` game_date DATE NOT NULL,
  CHANGE COLUMN `homeTeamID` home_team_id INT(11) DEFAULT NULL,
  CHANGE COLUMN `visitorTeamID` visitor_team_id INT(11) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3.4: ibl_plr (Player Table)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_plr
  CHANGE COLUMN `Clutch` clutch VARCHAR(32) DEFAULT '',
  CHANGE COLUMN `Consistency` consistency VARCHAR(32) DEFAULT '',
  CHANGE COLUMN `PGDepth` pg_depth INT(11) DEFAULT 0,
  CHANGE COLUMN `SGDepth` sg_depth INT(11) DEFAULT 0,
  CHANGE COLUMN `SFDepth` sf_depth INT(11) DEFAULT 0,
  CHANGE COLUMN `PFDepth` pf_depth INT(11) DEFAULT 0,
  CHANGE COLUMN `CDepth` c_depth INT(11) DEFAULT 0;

-- ---------------------------------------------------------------------------
-- 3.5: ibl_team_info (Team Information)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_info
  CHANGE COLUMN `Contract_Wins` contract_wins INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `Contract_Losses` contract_losses INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `Contract_AvgW` contract_avg_wins DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  CHANGE COLUMN `Contract_AvgL` contract_avg_losses DECIMAL(4,1) NOT NULL DEFAULT 0.0,
  CHANGE COLUMN `Contract_Coach` contract_coach VARCHAR(100) DEFAULT NULL,
  CHANGE COLUMN `HasMLE` has_mle TINYINT(1) NOT NULL DEFAULT 1,
  CHANGE COLUMN `HasLLE` has_lle TINYINT(1) NOT NULL DEFAULT 1,
  CHANGE COLUMN `Used_Extension_This_Season` used_extension_this_season TINYINT(1) DEFAULT 0,
  CHANGE COLUMN `Used_Extension_This_Chunk` used_extension_this_chunk TINYINT(1) DEFAULT 0,
  CHANGE COLUMN `discordID` discord_id BIGINT(20) UNSIGNED DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3.6: ibl_power (Power Rankings)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_power
  CHANGE COLUMN `TeamID` team_id INT(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `Team` team_name VARCHAR(16) NOT NULL DEFAULT '',
  CHANGE COLUMN `Conference` conference VARCHAR(8) NOT NULL DEFAULT '',
  CHANGE COLUMN `Division` division VARCHAR(9) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- 3.7: ibl_team_offense_stats
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_offense_stats
  CHANGE COLUMN `teamID` team_id INT(11) NOT NULL;

-- ---------------------------------------------------------------------------
-- 3.8: ibl_team_defense_stats
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_defense_stats
  CHANGE COLUMN `teamID` team_id INT(11) NOT NULL;

-- ---------------------------------------------------------------------------
-- 3.9: ibl_awards (Season Awards)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_awards
  CHANGE COLUMN `Award` award_name VARCHAR(128) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- 3.10: ibl_team_awards (Team Awards)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_awards
  CHANGE COLUMN `Award` award_name VARCHAR(128) NOT NULL DEFAULT '',
  CHANGE COLUMN `ID` award_id INT(11) NOT NULL AUTO_INCREMENT;

-- ---------------------------------------------------------------------------
-- 3.11: ibl_gm_history (GM Awards History)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_gm_history
  CHANGE COLUMN `Award` award_name VARCHAR(128) NOT NULL DEFAULT '';

-- ---------------------------------------------------------------------------
-- 3.12: ibl_plr_chunk (Player Season Chunk)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_plr_chunk
  CHANGE COLUMN `Season` season_chunk VARCHAR(255) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3.13: ibl_sim_dates (Simulation Dates - Columns with Spaces)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_sim_dates
  CHANGE COLUMN `Sim` sim_number VARCHAR(11) DEFAULT NULL,
  CHANGE COLUMN `Start Date` start_date VARCHAR(11) DEFAULT NULL,
  CHANGE COLUMN `End Date` end_date VARCHAR(11) DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- 3.14: ibl_trade_cash (Trade Cash Offers)
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_trade_cash
  CHANGE COLUMN `tradeOfferID` trade_offer_id INT(11) NOT NULL;

-- ============================================================================
-- PART 4: RECREATE FOREIGN KEY CONSTRAINTS WITH NEW COLUMN NAMES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 4.1: Recreate Foreign Keys for ibl_schedule
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_home 
    FOREIGN KEY (home_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_schedule_visitor 
    FOREIGN KEY (visitor_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 4.2: Recreate Foreign Keys for ibl_box_scores
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_home 
    FOREIGN KEY (home_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_boxscore_visitor 
    FOREIGN KEY (visitor_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 4.3: Recreate Foreign Keys for ibl_box_scores_teams
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores_teams
  ADD CONSTRAINT fk_boxscoreteam_home 
    FOREIGN KEY (home_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE,
  ADD CONSTRAINT fk_boxscoreteam_visitor 
    FOREIGN KEY (visitor_team_id) REFERENCES ibl_team_info (teamid) ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 4.4: Recreate Foreign Keys for ibl_power
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_power
  ADD CONSTRAINT fk_power_team 
    FOREIGN KEY (team_name) REFERENCES ibl_team_info (team_name) ON DELETE CASCADE ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- 4.5: Recreate Foreign Keys for ibl_team_offense_stats and ibl_team_defense_stats
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_offense_stats
  ADD CONSTRAINT fk_team_offense_team 
    FOREIGN KEY (team_id) REFERENCES ibl_team_info (teamid) ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE ibl_team_defense_stats
  ADD CONSTRAINT fk_team_defense_team 
    FOREIGN KEY (team_id) REFERENCES ibl_team_info (teamid) ON DELETE CASCADE ON UPDATE CASCADE;

-- ============================================================================
-- PART 5: UPDATE INDEXES TO USE NEW COLUMN NAMES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 5.1: Update ibl_schedule Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_schedule
  DROP INDEX `BoxID`,
  DROP INDEX idx_year,
  DROP INDEX idx_date,
  DROP INDEX idx_visitor,
  DROP INDEX idx_home,
  DROP INDEX idx_year_date;

ALTER TABLE ibl_schedule
  ADD INDEX idx_box_score_id (box_score_id),
  ADD INDEX idx_season_year (season_year),
  ADD INDEX idx_game_date (game_date),
  ADD INDEX idx_visitor_team_id (visitor_team_id),
  ADD INDEX idx_home_team_id (home_team_id),
  ADD INDEX idx_season_year_game_date (season_year, game_date);

-- ---------------------------------------------------------------------------
-- 5.2: Update ibl_box_scores Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores
  DROP INDEX idx_date,
  DROP INDEX idx_visitor_tid,
  DROP INDEX idx_home_tid,
  DROP INDEX idx_date_pid,
  DROP INDEX idx_date_home_visitor;

ALTER TABLE ibl_box_scores
  ADD INDEX idx_game_date (game_date),
  ADD INDEX idx_visitor_team_id (visitor_team_id),
  ADD INDEX idx_home_team_id (home_team_id),
  ADD INDEX idx_game_date_pid (game_date, pid),
  ADD INDEX idx_game_date_home_visitor (game_date, home_team_id, visitor_team_id);

-- ---------------------------------------------------------------------------
-- 5.3: Update ibl_box_scores_teams Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores_teams
  DROP INDEX idx_date;

ALTER TABLE ibl_box_scores_teams
  ADD INDEX idx_game_date (game_date);

-- ---------------------------------------------------------------------------
-- 5.4: Update ibl_power Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_power
  DROP INDEX idx_team;

ALTER TABLE ibl_power
  ADD INDEX idx_team_name (team_name);

-- ---------------------------------------------------------------------------
-- 5.5: Update ibl_team_offense_stats Index
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_offense_stats
  DROP INDEX idx_teamid;

ALTER TABLE ibl_team_offense_stats
  ADD INDEX idx_team_id (team_id);

-- ---------------------------------------------------------------------------
-- 5.6: Update ibl_team_defense_stats Index
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_defense_stats
  DROP INDEX idx_teamid;

ALTER TABLE ibl_team_defense_stats
  ADD INDEX idx_team_id (team_id);

-- ============================================================================
-- PART 6: RECREATE DATABASE VIEWS WITH NEW COLUMN NAMES
-- ============================================================================

-- ---------------------------------------------------------------------------
-- 6.1: Recreate vw_schedule_upcoming View
-- ---------------------------------------------------------------------------
CREATE OR REPLACE VIEW vw_schedule_upcoming AS
SELECT
  sch.uuid AS game_uuid,
  sch.schedule_id,
  sch.season_year,
  sch.game_date,
  sch.box_score_id,
  -- Visitor Team
  tv.uuid AS visitor_uuid,
  tv.teamid AS visitor_team_id,
  tv.team_city AS visitor_city,
  tv.team_name AS visitor_name,
  CONCAT(tv.team_city, ' ', tv.team_name) AS visitor_full_name,
  sch.visitor_score,
  -- Home Team
  th.uuid AS home_uuid,
  th.teamid AS home_team_id,
  th.team_city AS home_city,
  th.team_name AS home_name,
  CONCAT(th.team_city, ' ', th.team_name) AS home_full_name,
  sch.home_score,
  -- Game Status
  CASE 
    WHEN sch.visitor_score = 0 AND sch.home_score = 0 THEN 'scheduled'
    ELSE 'completed'
  END AS game_status,
  -- Timestamps
  sch.created_at,
  sch.updated_at
FROM ibl_schedule sch
INNER JOIN ibl_team_info tv ON sch.visitor_team_id = tv.teamid
INNER JOIN ibl_team_info th ON sch.home_team_id = th.teamid;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify success

-- Check that all renames were successful
-- SELECT TABLE_NAME, COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND TABLE_NAME IN ('ibl_schedule', 'ibl_plr', 'ibl_team_info', 'ibl_box_scores')
-- ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Verify foreign keys were recreated
-- SELECT 
--   TABLE_NAME,
--   CONSTRAINT_NAME,
--   COLUMN_NAME,
--   REFERENCED_TABLE_NAME,
--   REFERENCED_COLUMN_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND REFERENCED_TABLE_NAME IS NOT NULL
--   AND TABLE_NAME IN ('ibl_schedule', 'ibl_box_scores', 'ibl_box_scores_teams', 'ibl_power')
-- ORDER BY TABLE_NAME;

-- Verify indexes were updated
-- SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME 
-- FROM information_schema.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND TABLE_NAME IN ('ibl_schedule', 'ibl_box_scores')
-- ORDER BY TABLE_NAME, INDEX_NAME;

-- Test the recreated view
-- SELECT * FROM vw_schedule_upcoming LIMIT 5;

-- ============================================================================
-- ROLLBACK PROCEDURE (if needed)
-- ============================================================================
-- If issues arise, you can rollback by reversing the CHANGE COLUMN statements
-- NOTE: This requires stopping the application and restoring from backup
-- is the safest approach. Do not attempt manual rollback on production.

-- Example rollback for ibl_schedule (DO NOT RUN unless absolutely necessary):
-- ALTER TABLE ibl_schedule
--   CHANGE COLUMN season_year `Year` YEAR(4) NOT NULL DEFAULT 0000,
--   CHANGE COLUMN box_score_id `BoxID` INT(11) NOT NULL DEFAULT 0,
--   CHANGE COLUMN game_date `Date` DATE NOT NULL,
--   CHANGE COLUMN visitor_team_id `Visitor` INT(11) NOT NULL DEFAULT 0,
--   CHANGE COLUMN visitor_score `VScore` INT(11) NOT NULL DEFAULT 0,
--   CHANGE COLUMN home_team_id `Home` INT(11) NOT NULL DEFAULT 0,
--   CHANGE COLUMN home_score `HScore` INT(11) NOT NULL DEFAULT 0,
--   CHANGE COLUMN schedule_id `SchedID` INT(11) NOT NULL AUTO_INCREMENT;

-- ============================================================================
-- END OF MIGRATION
-- ============================================================================
