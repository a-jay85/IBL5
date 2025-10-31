-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 1: Critical Infrastructure
-- ============================================================================
-- This migration implements the highest priority improvements:
-- 1. Convert MyISAM to InnoDB for ACID compliance and better concurrency
-- 2. Add critical missing indexes for query performance
-- 3. Add timestamps for audit trails
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 30-60 minutes depending on data size
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- ============================================================================
-- PART 1: CONVERT MyISAM TABLES TO InnoDB
-- ============================================================================
-- InnoDB provides ACID transactions, foreign key support, and row-level locking
-- Essential for API operations and data integrity

-- Core Player Tables
ALTER TABLE ibl_plr ENGINE=InnoDB;
ALTER TABLE ibl_plr_chunk ENGINE=InnoDB;

-- Historical Stats Tables
ALTER TABLE ibl_hist ENGINE=InnoDB;
ALTER TABLE ibl_season_career_avgs ENGINE=InnoDB;
ALTER TABLE ibl_playoff_career_avgs ENGINE=InnoDB;
ALTER TABLE ibl_playoff_career_totals ENGINE=InnoDB;
ALTER TABLE ibl_playoff_stats ENGINE=InnoDB;
ALTER TABLE ibl_playoff_results ENGINE=InnoDB;
ALTER TABLE ibl_heat_career_avgs ENGINE=InnoDB;
ALTER TABLE ibl_heat_career_totals ENGINE=InnoDB;
ALTER TABLE ibl_heat_stats ENGINE=InnoDB;
ALTER TABLE ibl_heat_win_loss ENGINE=InnoDB;
ALTER TABLE ibl_olympics_career_avgs ENGINE=InnoDB;
ALTER TABLE ibl_olympics_career_totals ENGINE=InnoDB;
ALTER TABLE ibl_olympics_stats ENGINE=InnoDB;

-- Team Tables
ALTER TABLE ibl_team_info ENGINE=InnoDB;
ALTER TABLE ibl_team_history ENGINE=InnoDB;
ALTER TABLE ibl_team_win_loss ENGINE=InnoDB;
ALTER TABLE ibl_team_offense_stats ENGINE=InnoDB;
ALTER TABLE ibl_team_defense_stats ENGINE=InnoDB;
ALTER TABLE ibl_team_awards ENGINE=InnoDB;

-- Standings and Rankings
ALTER TABLE ibl_standings ENGINE=InnoDB;
ALTER TABLE ibl_power ENGINE=InnoDB;

-- Schedule and Games
ALTER TABLE ibl_schedule ENGINE=InnoDB;
ALTER TABLE ibl_box_scores ENGINE=InnoDB;
ALTER TABLE ibl_box_scores_teams ENGINE=InnoDB;

-- Draft System
ALTER TABLE ibl_draft ENGINE=InnoDB;
ALTER TABLE ibl_draft_class ENGINE=InnoDB;
ALTER TABLE ibl_draft_picks ENGINE=InnoDB;

-- Free Agency and Contracts
ALTER TABLE ibl_fa_offers ENGINE=InnoDB;
ALTER TABLE ibl_demands ENGINE=InnoDB;

-- Trade System
ALTER TABLE ibl_trade_info ENGINE=InnoDB;
ALTER TABLE ibl_trade_autocounter ENGINE=InnoDB;

-- Awards and Settings
ALTER TABLE ibl_awards ENGINE=InnoDB;
ALTER TABLE ibl_banners ENGINE=InnoDB;
ALTER TABLE ibl_settings ENGINE=InnoDB;
ALTER TABLE ibl_sim_dates ENGINE=InnoDB;
ALTER TABLE ibl_gm_history ENGINE=InnoDB;

-- Voting Tables
ALTER TABLE ibl_votes_ASG ENGINE=InnoDB;
ALTER TABLE ibl_votes_EOY ENGINE=InnoDB;

-- One on One
ALTER TABLE ibl_one_on_one ENGINE=InnoDB;

-- ============================================================================
-- PART 2: ADD CRITICAL MISSING INDEXES
-- ============================================================================
-- These indexes dramatically improve query performance for common operations

-- ---------------------------------------------------------------------------
-- Player Table (ibl_plr) Indexes
-- ---------------------------------------------------------------------------
-- Team-based queries
ALTER TABLE ibl_plr ADD INDEX idx_tid (tid);

-- Active/retired player queries
ALTER TABLE ibl_plr ADD INDEX idx_active (active);
ALTER TABLE ibl_plr ADD INDEX idx_retired (retired);

-- Combined filters (team + active status)
ALTER TABLE ibl_plr ADD INDEX idx_tid_active (tid, active);

-- Position-based queries
ALTER TABLE ibl_plr ADD INDEX idx_pos (pos);

-- Draft information
ALTER TABLE ibl_plr ADD INDEX idx_draftyear (draftyear);
ALTER TABLE ibl_plr ADD INDEX idx_draftround (draftround);

-- ---------------------------------------------------------------------------
-- Historical Stats (ibl_hist) Indexes
-- ---------------------------------------------------------------------------
-- Player history by year
ALTER TABLE ibl_hist ADD INDEX idx_pid_year (pid, year);

-- Team history by year
ALTER TABLE ibl_hist ADD INDEX idx_team_year (team, year);
ALTER TABLE ibl_hist ADD INDEX idx_teamid_year (teamid, year);

-- Year-based queries
ALTER TABLE ibl_hist ADD INDEX idx_year (year);

-- ---------------------------------------------------------------------------
-- Schedule (ibl_schedule) Indexes
-- ---------------------------------------------------------------------------
-- Year-based queries
ALTER TABLE ibl_schedule ADD INDEX idx_year (Year);

-- Date-based queries
ALTER TABLE ibl_schedule ADD INDEX idx_date (Date);

-- Team schedule queries
ALTER TABLE ibl_schedule ADD INDEX idx_visitor (Visitor);
ALTER TABLE ibl_schedule ADD INDEX idx_home (Home);

-- Combined year and date queries
ALTER TABLE ibl_schedule ADD INDEX idx_year_date (Year, Date);

-- ---------------------------------------------------------------------------
-- Box Scores (ibl_box_scores) Indexes
-- ---------------------------------------------------------------------------
-- Date-based box score queries
ALTER TABLE ibl_box_scores ADD INDEX idx_date (Date);

-- Player statistics
ALTER TABLE ibl_box_scores ADD INDEX idx_pid (pid);

-- Team box scores
ALTER TABLE ibl_box_scores ADD INDEX idx_visitor_tid (visitorTID);
ALTER TABLE ibl_box_scores ADD INDEX idx_home_tid (homeTID);

-- Player performance on specific dates
ALTER TABLE ibl_box_scores ADD INDEX idx_date_pid (Date, pid);

-- ---------------------------------------------------------------------------
-- Team Info (ibl_team_info) Indexes
-- ---------------------------------------------------------------------------
-- Owner lookup
ALTER TABLE ibl_team_info ADD INDEX idx_owner_email (owner_email);

-- Discord integration
ALTER TABLE ibl_team_info ADD INDEX idx_discordID (discordID);

-- ---------------------------------------------------------------------------
-- Standings (ibl_standings) Indexes
-- ---------------------------------------------------------------------------
-- Conference standings
ALTER TABLE ibl_standings ADD INDEX idx_conference (conference);

-- Division standings  
ALTER TABLE ibl_standings ADD INDEX idx_division (division);

-- ---------------------------------------------------------------------------
-- Draft (ibl_draft) Indexes
-- ---------------------------------------------------------------------------
-- Draft year queries
ALTER TABLE ibl_draft ADD INDEX idx_year (year);

-- Team draft history
ALTER TABLE ibl_draft ADD INDEX idx_team (team);

-- Player draft info
ALTER TABLE ibl_draft ADD INDEX idx_player (player);

-- Draft order queries
ALTER TABLE ibl_draft ADD INDEX idx_year_round (year, round);
ALTER TABLE ibl_draft ADD INDEX idx_year_round_pick (year, round, pick);

-- ---------------------------------------------------------------------------
-- Draft Picks (ibl_draft_picks) Indexes
-- ---------------------------------------------------------------------------
-- Pick owner queries
ALTER TABLE ibl_draft_picks ADD INDEX idx_ownerofpick (ownerofpick);

-- Future picks by year
ALTER TABLE ibl_draft_picks ADD INDEX idx_year (year);

-- Pick trading queries
ALTER TABLE ibl_draft_picks ADD INDEX idx_year_round (year, round);

-- ---------------------------------------------------------------------------
-- Draft Class (ibl_draft_class) Indexes
-- ---------------------------------------------------------------------------
-- Draft rankings
ALTER TABLE ibl_draft_class ADD INDEX idx_ranking (ranking);

-- Drafted status
ALTER TABLE ibl_draft_class ADD INDEX idx_drafted (drafted);

-- Position-based queries
ALTER TABLE ibl_draft_class ADD INDEX idx_pos (pos);

-- ---------------------------------------------------------------------------
-- Playoff Stats Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_playoff_stats ADD INDEX idx_year (year);
ALTER TABLE ibl_playoff_stats ADD INDEX idx_team (team);
ALTER TABLE ibl_playoff_stats ADD INDEX idx_name (name);

ALTER TABLE ibl_playoff_results ADD INDEX idx_year (year);
ALTER TABLE ibl_playoff_results ADD INDEX idx_round (round);

-- ---------------------------------------------------------------------------
-- Team Stats Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_team_offense_stats ADD INDEX idx_teamID (teamID);
ALTER TABLE ibl_team_defense_stats ADD INDEX idx_teamID (teamID);

-- ---------------------------------------------------------------------------
-- Awards Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_awards ADD INDEX idx_year (year);
ALTER TABLE ibl_awards ADD INDEX idx_name (name);

-- ---------------------------------------------------------------------------
-- Box Scores Teams Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_box_scores_teams ADD INDEX idx_date (Date);
ALTER TABLE ibl_box_scores_teams ADD INDEX idx_visitor_team (visitorTeamID);
ALTER TABLE ibl_box_scores_teams ADD INDEX idx_home_team (homeTeamID);

-- ---------------------------------------------------------------------------
-- Free Agency Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_fa_offers ADD INDEX idx_name (name);
ALTER TABLE ibl_fa_offers ADD INDEX idx_team (team);

-- ---------------------------------------------------------------------------
-- Trade Indexes
-- ---------------------------------------------------------------------------
ALTER TABLE ibl_trade_info ADD INDEX idx_tradeofferid (tradeofferid);
ALTER TABLE ibl_trade_info ADD INDEX idx_from (`from`);
ALTER TABLE ibl_trade_info ADD INDEX idx_to (`to`);

-- ============================================================================
-- PART 3: ADD TIMESTAMP COLUMNS FOR AUDIT TRAILS
-- ============================================================================
-- Add created_at and updated_at to key tables for change tracking

-- Player table timestamps
ALTER TABLE ibl_plr 
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Team table timestamps
ALTER TABLE ibl_team_info 
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Draft table timestamps (already has date column, add updated_at)
ALTER TABLE ibl_draft 
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Schedule table timestamps
ALTER TABLE ibl_schedule
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- Trade table timestamps
ALTER TABLE ibl_trade_info
  ADD COLUMN created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP;

-- ============================================================================
-- PART 4: OPTIMIZE COLUMN DATA TYPES
-- ============================================================================
-- Use more appropriate data types for better performance and storage

-- Player table optimizations
ALTER TABLE ibl_plr
  MODIFY age TINYINT UNSIGNED,
  MODIFY peak TINYINT UNSIGNED,
  MODIFY active TINYINT(1),
  MODIFY retired TINYINT(1),
  MODIFY injured TINYINT(1),
  MODIFY bird TINYINT(1);

-- Boolean fields in team tables
ALTER TABLE ibl_standings
  MODIFY clinchedConference BOOLEAN,
  MODIFY clinchedDivision BOOLEAN,
  MODIFY clinchedPlayoffs BOOLEAN;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries after migration to verify success

-- Check InnoDB conversion
-- SELECT TABLE_NAME, ENGINE 
-- FROM information_schema.TABLES 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME LIKE 'ibl_%';

-- Check index creation
-- SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME 
-- FROM information_schema.STATISTICS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND TABLE_NAME LIKE 'ibl_%'
-- ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;

-- Check timestamp columns
-- SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE 
-- FROM information_schema.COLUMNS 
-- WHERE TABLE_SCHEMA = DATABASE() 
-- AND COLUMN_NAME IN ('created_at', 'updated_at')
-- AND TABLE_NAME LIKE 'ibl_%';

-- ============================================================================
-- ROLLBACK SCRIPT (if needed)
-- ============================================================================
-- Save current state before running migration:
-- mysqldump -u username -p database_name > backup_before_migration.sql
--
-- To rollback, restore from backup:
-- mysql -u username -p database_name < backup_before_migration.sql
-- ============================================================================

-- ============================================================================
-- PERFORMANCE TESTING
-- ============================================================================
-- After migration, test key queries to measure improvement:
--
-- 1. Active players by team:
-- EXPLAIN SELECT * FROM ibl_plr WHERE tid = 1 AND active = 1;
--
-- 2. Player history:
-- EXPLAIN SELECT * FROM ibl_hist WHERE pid = 123 AND year = 2024;
--
-- 3. Team schedule:
-- EXPLAIN SELECT * FROM ibl_schedule WHERE Home = 1 AND Year = 2024;
--
-- 4. Box scores by date:
-- EXPLAIN SELECT * FROM ibl_box_scores WHERE Date = '2024-01-15';
--
-- Look for "type: ref" or "type: range" instead of "type: ALL" (full scan)
-- ============================================================================
