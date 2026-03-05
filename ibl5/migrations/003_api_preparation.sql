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

-- Historical Stats Tables
ALTER TABLE ibl_hist
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Box Scores and Game Data
ALTER TABLE ibl_box_scores
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_box_scores_teams
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Standings and Rankings
ALTER TABLE ibl_standings
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_power
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Draft System
ALTER TABLE ibl_draft
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_draft_picks
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Free Agency and Contract Management
ALTER TABLE ibl_fa_offers
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

ALTER TABLE ibl_demands
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Trade System
ALTER TABLE ibl_trade_info
  ADD COLUMN IF NOT EXISTS created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ibl_season_career_avgs, ibl_playoff_career_avgs: now views (migration 028), skip

-- ============================================================================
-- PART 2: ADD UUID SUPPORT (Priority 4.1)
-- ============================================================================

-- Step 2.1: Add UUID Columns (initially nullable)
ALTER TABLE ibl_plr ADD COLUMN IF NOT EXISTS uuid CHAR(36) UNIQUE DEFAULT NULL;
ALTER TABLE ibl_team_info ADD COLUMN IF NOT EXISTS uuid CHAR(36) UNIQUE DEFAULT NULL;
ALTER TABLE ibl_schedule ADD COLUMN IF NOT EXISTS uuid CHAR(36) UNIQUE DEFAULT NULL;
ALTER TABLE ibl_draft ADD COLUMN IF NOT EXISTS uuid CHAR(36) UNIQUE DEFAULT NULL;
ALTER TABLE ibl_box_scores ADD COLUMN IF NOT EXISTS uuid CHAR(36) UNIQUE DEFAULT NULL;

-- Step 2.2: Generate UUIDs for Existing Records
UPDATE ibl_plr SET uuid = UUID() WHERE uuid IS NULL;
UPDATE ibl_team_info SET uuid = UUID() WHERE uuid IS NULL;
UPDATE ibl_schedule SET uuid = UUID() WHERE uuid IS NULL;
UPDATE ibl_draft SET uuid = UUID() WHERE uuid IS NULL;
UPDATE ibl_box_scores SET uuid = UUID() WHERE uuid IS NULL;

-- Step 2.3: Make UUID Columns NOT NULL (after population)
ALTER TABLE ibl_plr MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_team_info MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_schedule MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_draft MODIFY uuid CHAR(36) NOT NULL;
ALTER TABLE ibl_box_scores MODIFY uuid CHAR(36) NOT NULL;

-- Step 2.4: Add Indexes on UUID Columns
ALTER TABLE ibl_plr ADD UNIQUE INDEX IF NOT EXISTS idx_uuid (uuid);
ALTER TABLE ibl_team_info ADD UNIQUE INDEX IF NOT EXISTS idx_uuid (uuid);
ALTER TABLE ibl_schedule ADD UNIQUE INDEX IF NOT EXISTS idx_uuid (uuid);
ALTER TABLE ibl_draft ADD UNIQUE INDEX IF NOT EXISTS idx_uuid (uuid);
ALTER TABLE ibl_box_scores ADD UNIQUE INDEX IF NOT EXISTS idx_uuid (uuid);

-- ============================================================================
-- PART 3: CREATE API-FRIENDLY VIEWS (Priority 4.2)
-- ============================================================================

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
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  t.owner_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  p.cy AS current_salary,
  p.cy1 AS year1_salary,
  p.cy2 AS year2_salary,
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
  ROUND(p.stats_fgm / NULLIF(p.stats_fga, 0), 3) AS fg_percentage,
  ROUND(p.stats_ftm / NULLIF(p.stats_fta, 0), 3) AS ft_percentage,
  ROUND(p.stats_3gm / NULLIF(p.stats_3ga, 0), 3) AS three_pt_percentage,
  ROUND((p.stats_fgm * 2 + p.stats_3gm + p.stats_ftm) / NULLIF(p.stats_gm, 0), 1) AS points_per_game,
  p.created_at,
  p.updated_at
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.active = 1 AND p.retired = 0;

CREATE OR REPLACE VIEW vw_team_standings AS
SELECT
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  t.owner_name,
  s.leagueRecord AS league_record,
  s.pct AS win_percentage,
  s.conference,
  s.confRecord AS conference_record,
  s.confGB AS conference_games_back,
  s.division,
  s.divRecord AS division_record,
  s.divGB AS division_games_back,
  s.homeWins AS home_wins,
  s.homeLosses AS home_losses,
  s.awayWins AS away_wins,
  s.awayLosses AS away_losses,
  CONCAT(s.homeWins, '-', s.homeLosses) AS home_record,
  CONCAT(s.awayWins, '-', s.awayLosses) AS away_record,
  s.gamesUnplayed AS games_remaining,
  s.confWins AS conference_wins,
  s.confLosses AS conference_losses,
  s.divWins AS division_wins,
  s.divLosses AS division_losses,
  s.clinchedConference AS clinched_conference,
  s.clinchedDivision AS clinched_division,
  s.clinchedPlayoffs AS clinched_playoffs,
  s.confMagicNumber AS conference_magic_number,
  s.divMagicNumber AS division_magic_number,
  s.created_at,
  s.updated_at
FROM ibl_team_info t
INNER JOIN ibl_standings s ON t.teamid = s.tid;

CREATE OR REPLACE VIEW vw_schedule_upcoming AS
SELECT
  sch.uuid AS game_uuid,
  sch.SchedID AS schedule_id,
  sch.Year AS season_year,
  sch.Date AS game_date,
  sch.BoxID AS box_score_id,
  tv.uuid AS visitor_uuid,
  tv.teamid AS visitor_team_id,
  tv.team_city AS visitor_city,
  tv.team_name AS visitor_name,
  CONCAT(tv.team_city, ' ', tv.team_name) AS visitor_full_name,
  sch.VScore AS visitor_score,
  th.uuid AS home_uuid,
  th.teamid AS home_team_id,
  th.team_city AS home_city,
  th.team_name AS home_name,
  CONCAT(th.team_city, ' ', th.team_name) AS home_full_name,
  sch.HScore AS home_score,
  CASE
    WHEN sch.VScore = 0 AND sch.HScore = 0 THEN 'scheduled'
    ELSE 'completed'
  END AS game_status,
  sch.created_at,
  sch.updated_at
FROM ibl_schedule sch
INNER JOIN ibl_team_info tv ON sch.Visitor = tv.teamid
INNER JOIN ibl_team_info th ON sch.Home = th.teamid;

CREATE OR REPLACE VIEW vw_player_career_stats AS
SELECT
  p.uuid AS player_uuid,
  p.pid,
  p.name,
  p.car_gm AS career_games,
  p.car_min AS career_minutes,
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm), 0) AS career_points,
  p.car_orb + p.car_drb AS career_rebounds,
  p.car_ast AS career_assists,
  p.car_stl AS career_steals,
  p.car_blk AS career_blocks,
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm) / NULLIF(p.car_gm, 0), 1) AS ppg_career,
  ROUND((p.car_orb + p.car_drb) / NULLIF(p.car_gm, 0), 1) AS rpg_career,
  ROUND(p.car_ast / NULLIF(p.car_gm, 0), 1) AS apg_career,
  ROUND(p.car_fgm / NULLIF(p.car_fga, 0), 3) AS fg_pct_career,
  ROUND(p.car_ftm / NULLIF(p.car_fta, 0), 3) AS ft_pct_career,
  ROUND(p.car_tgm / NULLIF(p.car_tga, 0), 3) AS three_pt_pct_career,
  p.car_playoff_min AS playoff_minutes,
  p.draftyear AS draft_year,
  p.draftround AS draft_round,
  p.draftpickno AS draft_pick,
  p.draftedby AS drafted_by_team,
  p.created_at,
  p.updated_at
FROM ibl_plr p;

CREATE OR REPLACE VIEW vw_free_agency_offers AS
SELECT
  fa.primary_key AS offer_id,
  p.uuid AS player_uuid,
  p.pid,
  p.name AS player_name,
  p.pos AS position,
  p.age,
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  fa.offer1 AS year1_amount,
  fa.offer2 AS year2_amount,
  fa.offer3 AS year3_amount,
  fa.offer4 AS year4_amount,
  fa.offer5 AS year5_amount,
  fa.offer6 AS year6_amount,
  (fa.offer1 + fa.offer2 + fa.offer3 + fa.offer4 + fa.offer5 + fa.offer6) AS total_contract_value,
  fa.modifier,
  fa.random,
  fa.perceivedvalue AS perceived_value,
  fa.MLE AS is_mle,
  fa.LLE AS is_lle,
  fa.created_at,
  fa.updated_at
FROM ibl_fa_offers fa
INNER JOIN ibl_plr p ON fa.name = p.name
INNER JOIN ibl_team_info t ON fa.team = t.team_name;
