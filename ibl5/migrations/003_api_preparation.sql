-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 3: API Preparation
-- ============================================================================
-- This migration implements critical API-readiness improvements:
-- 1. Complete timestamp columns for audit trails and caching (Priority 3.3)
-- 2. Add UUID support for secure public identifiers (Priority 4.1)
-- 3. Create API-friendly database views (Priority 4.2)
--
-- PREREQUISITES:
-- - Phase 1 and Phase 2 migrations must be completed
-- - InnoDB tables with foreign keys in place
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 30-45 minutes
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- ============================================================================
-- PART 1: COMPLETE TIMESTAMP COLUMNS (Priority 3.3)
-- ============================================================================
-- Add created_at and updated_at timestamps to remaining core tables
-- Enables audit trails, API caching (ETags), and change tracking

-- Historical Stats Tables
ALTER TABLE ibl_hist
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Box Scores and Game Data
ALTER TABLE ibl_box_scores
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_box_scores_teams
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Standings and Rankings
ALTER TABLE ibl_standings
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_power
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Draft System
ALTER TABLE ibl_draft
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_draft_picks
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Free Agency and Contract Management
ALTER TABLE ibl_fa_offers
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_demands
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Trade System
ALTER TABLE ibl_trade_info
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Career Stats Tables (for change tracking)
ALTER TABLE ibl_season_career_avgs
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_playoff_career_avgs
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ============================================================================
-- PART 2: ADD UUID SUPPORT (Priority 4.1)
-- ============================================================================
-- Add UUID columns for secure public API identifiers
-- UUIDs prevent exposure of sequential IDs and enable distributed systems

-- ---------------------------------------------------------------------------
-- Step 2.1: Add UUID Columns (initially nullable)
-- ---------------------------------------------------------------------------

-- Players Table
ALTER TABLE ibl_plr
  ADD COLUMN uuid CHAR(36) UNIQUE DEFAULT NULL;

-- Teams Table  
ALTER TABLE ibl_team_info
  ADD COLUMN uuid CHAR(36) UNIQUE DEFAULT NULL;

-- Schedule/Games Table
ALTER TABLE ibl_schedule
  ADD COLUMN uuid CHAR(36) UNIQUE DEFAULT NULL;

-- Draft Table
ALTER TABLE ibl_draft
  ADD COLUMN uuid CHAR(36) UNIQUE DEFAULT NULL;

-- Box Scores Table
ALTER TABLE ibl_box_scores
  ADD COLUMN uuid CHAR(36) UNIQUE DEFAULT NULL;

-- ---------------------------------------------------------------------------
-- Step 2.2: Generate UUIDs for Existing Records
-- ---------------------------------------------------------------------------
-- Run these updates to populate UUIDs for all existing records

-- Generate UUIDs for players
UPDATE ibl_plr SET uuid = UUID() WHERE uuid IS NULL;

-- Generate UUIDs for teams
UPDATE ibl_team_info SET uuid = UUID() WHERE uuid IS NULL;

-- Generate UUIDs for schedule
UPDATE ibl_schedule SET uuid = UUID() WHERE uuid IS NULL;

-- Generate UUIDs for draft picks
UPDATE ibl_draft SET uuid = UUID() WHERE uuid IS NULL;

-- Generate UUIDs for box scores
UPDATE ibl_box_scores SET uuid = UUID() WHERE uuid IS NULL;

-- ---------------------------------------------------------------------------
-- Step 2.3: Make UUID Columns NOT NULL (after population)
-- ---------------------------------------------------------------------------
-- Once all records have UUIDs, enforce NOT NULL constraint

ALTER TABLE ibl_plr MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_team_info MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_schedule MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_draft MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_box_scores MODIFY uuid CHAR(36) NOT NULL;

-- ---------------------------------------------------------------------------
-- Step 2.4: Add Indexes on UUID Columns
-- ---------------------------------------------------------------------------
-- Ensure fast lookups by UUID for API queries

ALTER TABLE ibl_plr ADD UNIQUE INDEX idx_uuid (uuid);
ALTER TABLE ibl_team_info ADD UNIQUE INDEX idx_uuid (uuid);
ALTER TABLE ibl_schedule ADD UNIQUE INDEX idx_uuid (uuid);
ALTER TABLE ibl_draft ADD UNIQUE INDEX idx_uuid (uuid);
ALTER TABLE ibl_box_scores ADD UNIQUE INDEX idx_uuid (uuid);

-- ============================================================================
-- PART 3: CREATE API-FRIENDLY VIEWS (Priority 4.2)
-- ============================================================================
-- Database views simplify complex queries and provide consistent API responses

-- ---------------------------------------------------------------------------
-- View 1: Active Players with Team Information
-- ---------------------------------------------------------------------------
-- Provides complete player roster with team details for API endpoints
-- Usage: GET /api/v1/players, GET /api/v1/teams/{id}/roster

CREATE OR REPLACE VIEW vw_player_current AS
SELECT 
  p.uuid AS player_uuid,
  p.pid,
  p.name,
  p.nickname,
  p.age,
  p.pos AS position,
  p.active,
  p.retired,
  p.exp AS experience,
  p.bird AS bird_rights,
  -- Team Information
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  t.owner_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  -- Current Contract
  p.cy AS current_salary,
  p.cy1 AS year1_salary,
  p.cy2 AS year2_salary,
  -- Current Season Stats
  p.stats_gm AS games_played,
  p.stats_min AS minutes_played,
  p.stats_fgm AS field_goals_made,
  p.stats_fga AS field_goals_attempted,
  p.stats_ftm AS free_throws_made,
  p.stats_fta AS free_throws_attempted,
  p.stats_3gm AS three_pointers_made,
  p.stats_3ga AS three_pointers_attempted,
  p.stats_orb AS offensive_rebounds,
  p.stats_drb AS defensive_rebounds,
  p.stats_ast AS assists,
  p.stats_stl AS steals,
  p.stats_to AS turnovers,
  p.stats_blk AS blocks,
  p.stats_pf AS personal_fouls,
  -- Calculated Percentages (avoid division by zero)
  ROUND(p.stats_fgm / NULLIF(p.stats_fga, 0), 3) AS fg_percentage,
  ROUND(p.stats_ftm / NULLIF(p.stats_fta, 0), 3) AS ft_percentage,
  ROUND(p.stats_3gm / NULLIF(p.stats_3ga, 0), 3) AS three_pt_percentage,
  -- Points Per Game
  ROUND((p.stats_fgm * 2 + p.stats_3gm + p.stats_ftm) / NULLIF(p.stats_gm, 0), 1) AS points_per_game,
  -- Timestamps
  p.created_at,
  p.updated_at
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.active = 1 AND p.retired = 0;

-- ---------------------------------------------------------------------------
-- View 2: Team Standings with Calculated Fields
-- ---------------------------------------------------------------------------
-- Provides complete standings information with formatted records
-- Usage: GET /api/v1/standings, GET /api/v1/standings/{conference}

CREATE OR REPLACE VIEW vw_team_standings AS
SELECT
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  t.owner_name,
  -- Standings Data
  s.leagueRecord AS league_record,
  s.pct AS win_percentage,
  s.conference,
  s.confRecord AS conference_record,
  s.confGB AS conference_games_back,
  s.division,
  s.divRecord AS division_record,
  s.divGB AS division_games_back,
  -- Home/Away Records
  s.homeWins AS home_wins,
  s.homeLosses AS home_losses,
  s.awayWins AS away_wins,
  s.awayLosses AS away_losses,
  CONCAT(s.homeWins, '-', s.homeLosses) AS home_record,
  CONCAT(s.awayWins, '-', s.awayLosses) AS away_record,
  -- Additional Stats
  s.gamesUnplayed AS games_remaining,
  s.confWins AS conference_wins,
  s.confLosses AS conference_losses,
  s.divWins AS division_wins,
  s.divLosses AS division_losses,
  -- Playoff Status
  s.clinchedConference AS clinched_conference,
  s.clinchedDivision AS clinched_division,
  s.clinchedPlayoffs AS clinched_playoffs,
  s.confMagicNumber AS conference_magic_number,
  s.divMagicNumber AS division_magic_number,
  -- Timestamps
  s.created_at,
  s.updated_at
FROM ibl_team_info t
INNER JOIN ibl_standings s ON t.teamid = s.tid;

-- ---------------------------------------------------------------------------
-- View 3: Upcoming Games Schedule
-- ---------------------------------------------------------------------------
-- Provides schedule with team names for easier API consumption
-- Usage: GET /api/v1/schedule, GET /api/v1/schedule/upcoming

CREATE OR REPLACE VIEW vw_schedule_upcoming AS
SELECT
  sch.uuid AS game_uuid,
  sch.SchedID AS schedule_id,
  sch.Year AS season_year,
  sch.Date AS game_date,
  sch.BoxID AS box_score_id,
  -- Visitor Team
  tv.uuid AS visitor_uuid,
  tv.teamid AS visitor_team_id,
  tv.team_city AS visitor_city,
  tv.team_name AS visitor_name,
  CONCAT(tv.team_city, ' ', tv.team_name) AS visitor_full_name,
  sch.VScore AS visitor_score,
  -- Home Team
  th.uuid AS home_uuid,
  th.teamid AS home_team_id,
  th.team_city AS home_city,
  th.team_name AS home_name,
  CONCAT(th.team_city, ' ', th.team_name) AS home_full_name,
  sch.HScore AS home_score,
  -- Game Status
  CASE 
    WHEN sch.VScore = 0 AND sch.HScore = 0 THEN 'scheduled'
    ELSE 'completed'
  END AS game_status,
  -- Timestamps
  sch.created_at,
  sch.updated_at
FROM ibl_schedule sch
INNER JOIN ibl_team_info tv ON sch.Visitor = tv.teamid
INNER JOIN ibl_team_info th ON sch.Home = th.teamid;

-- ---------------------------------------------------------------------------
-- View 4: Player Career Statistics Summary
-- ---------------------------------------------------------------------------
-- Aggregates career stats for player profiles
-- Usage: GET /api/v1/players/{uuid}/stats

CREATE OR REPLACE VIEW vw_player_career_stats AS
SELECT
  p.uuid AS player_uuid,
  p.pid,
  p.name,
  -- Career Totals
  p.car_gm AS career_games,
  p.car_min AS career_minutes,
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm), 0) AS career_points,
  p.car_orb + p.car_drb AS career_rebounds,
  p.car_ast AS career_assists,
  p.car_stl AS career_steals,
  p.car_blk AS career_blocks,
  -- Career Averages
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm) / NULLIF(p.car_gm, 0), 1) AS ppg_career,
  ROUND((p.car_orb + p.car_drb) / NULLIF(p.car_gm, 0), 1) AS rpg_career,
  ROUND(p.car_ast / NULLIF(p.car_gm, 0), 1) AS apg_career,
  -- Career Percentages
  ROUND(p.car_fgm / NULLIF(p.car_fga, 0), 3) AS fg_pct_career,
  ROUND(p.car_ftm / NULLIF(p.car_fta, 0), 3) AS ft_pct_career,
  ROUND(p.car_tgm / NULLIF(p.car_tga, 0), 3) AS three_pt_pct_career,
  -- Playoff Career Stats
  p.car_playoff_min AS playoff_minutes,
  -- Draft Information
  p.draftyear AS draft_year,
  p.draftround AS draft_round,
  p.draftpickno AS draft_pick,
  p.draftedby AS drafted_by_team,
  -- Timestamps
  p.created_at,
  p.updated_at
FROM ibl_plr p;

-- ---------------------------------------------------------------------------
-- View 5: Free Agency Market Overview
-- ---------------------------------------------------------------------------
-- Shows current free agent offers for API display
-- Usage: GET /api/v1/free-agency, GET /api/v1/free-agency/offers

CREATE OR REPLACE VIEW vw_free_agency_offers AS
SELECT
  fa.primary_key AS offer_id,
  -- Player Information
  p.uuid AS player_uuid,
  p.pid,
  p.name AS player_name,
  p.pos AS position,
  p.age,
  -- Team Making Offer
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  -- Offer Details
  fa.offer1 AS year1_amount,
  fa.offer2 AS year2_amount,
  fa.offer3 AS year3_amount,
  fa.offer4 AS year4_amount,
  fa.offer5 AS year5_amount,
  fa.offer6 AS year6_amount,
  (fa.offer1 + fa.offer2 + fa.offer3 + fa.offer4 + fa.offer5 + fa.offer6) AS total_contract_value,
  -- Offer Modifiers
  fa.modifier,
  fa.random,
  fa.perceivedvalue AS perceived_value,
  fa.MLE AS is_mle,
  fa.LLE AS is_lle,
  -- Timestamps (will be added by earlier part of this migration)
  fa.created_at,
  fa.updated_at
FROM ibl_fa_offers fa
INNER JOIN ibl_plr p ON fa.name = p.name
INNER JOIN ibl_team_info t ON fa.team = t.team_name;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries to verify successful migration

-- Verify timestamp columns were added
-- SELECT TABLE_NAME, COLUMN_NAME 
-- FROM INFORMATION_SCHEMA.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
--   AND COLUMN_NAME IN ('created_at', 'updated_at')
--   AND TABLE_NAME LIKE 'ibl_%'
-- ORDER BY TABLE_NAME;

-- Verify UUID columns were added and populated
-- SELECT 'ibl_plr' AS table_name, COUNT(*) AS total, COUNT(uuid) AS with_uuid FROM ibl_plr
-- UNION ALL
-- SELECT 'ibl_team_info', COUNT(*), COUNT(uuid) FROM ibl_team_info
-- UNION ALL
-- SELECT 'ibl_schedule', COUNT(*), COUNT(uuid) FROM ibl_schedule
-- UNION ALL
-- SELECT 'ibl_draft', COUNT(*), COUNT(uuid) FROM ibl_draft
-- UNION ALL
-- SELECT 'ibl_box_scores', COUNT(*), COUNT(uuid) FROM ibl_box_scores;

-- Verify views were created
-- SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Test view queries
-- SELECT COUNT(*) FROM vw_player_current;
-- SELECT COUNT(*) FROM vw_team_standings;
-- SELECT COUNT(*) FROM vw_schedule_upcoming;
-- SELECT COUNT(*) FROM vw_player_career_stats;
-- SELECT COUNT(*) FROM vw_free_agency_offers;

-- ============================================================================
-- ROLLBACK INSTRUCTIONS
-- ============================================================================
-- If you need to rollback this migration, run these commands:
--
-- -- Drop views
-- DROP VIEW IF EXISTS vw_free_agency_offers;
-- DROP VIEW IF EXISTS vw_player_career_stats;
-- DROP VIEW IF EXISTS vw_schedule_upcoming;
-- DROP VIEW IF EXISTS vw_team_standings;
-- DROP VIEW IF EXISTS vw_player_current;
--
-- -- Remove UUID columns
-- ALTER TABLE ibl_box_scores DROP COLUMN uuid;
-- ALTER TABLE ibl_draft DROP COLUMN uuid;
-- ALTER TABLE ibl_schedule DROP COLUMN uuid;
-- ALTER TABLE ibl_team_info DROP COLUMN uuid;
-- ALTER TABLE ibl_plr DROP COLUMN uuid;
--
-- -- Remove timestamps (select tables to rollback)
-- ALTER TABLE ibl_playoff_career_avgs DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_season_career_avgs DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_trade_info DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_demands DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_fa_offers DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_draft_picks DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_draft DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_power DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_standings DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_box_scores_teams DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_box_scores DROP COLUMN created_at, DROP COLUMN updated_at;
-- ALTER TABLE ibl_hist DROP COLUMN created_at, DROP COLUMN updated_at;

-- ============================================================================
-- POST-MIGRATION TASKS
-- ============================================================================
-- 1. Test API endpoints using the new views
-- 2. Update API documentation with UUID usage
-- 3. Implement ETag caching using updated_at timestamps
-- 4. Monitor view query performance
-- 5. Update application code to use UUIDs for public API
-- 6. Run ANALYZE TABLE on modified tables for query optimization

-- ============================================================================
-- NOTES
-- ============================================================================
-- - UUID generation uses MySQL's UUID() function (version 1 UUIDs)
-- - For UUID version 4, consider using UUID_TO_BIN(UUID(), 1) with BINARY(16)
-- - Views are not materialized - consider caching layer for high-traffic endpoints
-- - Timestamps use MySQL's CURRENT_TIMESTAMP with automatic updates
-- - All views include timestamps for ETags and Last-Modified headers
