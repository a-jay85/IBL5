-- ============================================================================
-- IBL5 Database Schema Improvements - Phase 2: Foreign Key Relationships
-- ============================================================================
-- This migration adds foreign key constraints for referential integrity
-- 
-- PREREQUISITES:
-- - Phase 1 migration must be completed (InnoDB conversion)
-- - Data must be clean (no orphaned records)
--
-- IMPORTANT: Run this during a maintenance window
-- Estimated time: 10-20 minutes
-- 
-- BACKUP REQUIRED: Always backup database before running!
-- ============================================================================

-- ============================================================================
-- DATA CLEANUP (if needed)
-- ============================================================================
-- Before adding foreign keys, ensure referential integrity
-- Run these checks and fix any orphaned records:

-- Check for players with invalid team IDs
-- SELECT p.pid, p.name, p.tid 
-- FROM ibl_plr p 
-- LEFT JOIN ibl_team_info t ON p.tid = t.teamid 
-- WHERE p.tid != 0 AND t.teamid IS NULL;

-- Check for historical stats with invalid player IDs
-- SELECT h.pid, h.name 
-- FROM ibl_hist h 
-- LEFT JOIN ibl_plr p ON h.pid = p.pid 
-- WHERE p.pid IS NULL;

-- Check for schedules with invalid team IDs
-- SELECT s.SchedID, s.Visitor, s.Home 
-- FROM ibl_schedule s 
-- LEFT JOIN ibl_team_info tv ON s.Visitor = tv.teamid 
-- LEFT JOIN ibl_team_info th ON s.Home = th.teamid 
-- WHERE tv.teamid IS NULL OR th.teamid IS NULL;

-- ============================================================================
-- PART 1: PLAYER-RELATED FOREIGN KEYS
-- ============================================================================

-- Player to Team relationship
-- Note: tid = 0 means free agent, so we use RESTRICT to prevent accidental team deletions
ALTER TABLE ibl_plr 
  ADD CONSTRAINT fk_plr_team 
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- Historical stats to Player
ALTER TABLE ibl_hist 
  ADD CONSTRAINT fk_hist_player 
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Box scores to Player
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_player
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Box scores to Teams
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_visitor
  FOREIGN KEY (visitorTID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_home
  FOREIGN KEY (homeTID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 2: SCHEDULE AND GAME FOREIGN KEYS
-- ============================================================================

-- Schedule to Teams
ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_visitor
  FOREIGN KEY (Visitor) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_home
  FOREIGN KEY (Home) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- Box Scores Teams to Teams
ALTER TABLE ibl_box_scores_teams
  ADD CONSTRAINT fk_boxscoreteam_visitor
  FOREIGN KEY (visitorTeamID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

ALTER TABLE ibl_box_scores_teams
  ADD CONSTRAINT fk_boxscoreteam_home
  FOREIGN KEY (homeTeamID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 3: STATISTICS FOREIGN KEYS
-- ============================================================================

-- Playoff Stats
ALTER TABLE ibl_playoff_stats
  ADD CONSTRAINT fk_playoff_stats_player
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Heat Stats
ALTER TABLE ibl_heat_stats
  ADD CONSTRAINT fk_heat_stats_name
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Olympics Stats
ALTER TABLE ibl_olympics_stats
  ADD CONSTRAINT fk_olympics_stats_name
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Team Offense Stats
ALTER TABLE ibl_team_offense_stats
  ADD CONSTRAINT fk_team_offense_team
  FOREIGN KEY (teamID) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Team Defense Stats
ALTER TABLE ibl_team_defense_stats
  ADD CONSTRAINT fk_team_defense_team
  FOREIGN KEY (teamID) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 4: DRAFT SYSTEM FOREIGN KEYS
-- ============================================================================

-- Note: Draft picks reference team_name (varchar) not teamid
-- This requires exact match with ibl_team_info.team_name

-- Draft Picks to Team (owner of pick)
ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_owner
  FOREIGN KEY (ownerofpick) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- Draft Picks to Team (original pick)
ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_team
  FOREIGN KEY (teampick) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- Draft to Team
ALTER TABLE ibl_draft
  ADD CONSTRAINT fk_draft_team
  FOREIGN KEY (team) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 5: STANDINGS AND RANKINGS FOREIGN KEYS
-- ============================================================================

-- Standings to Team
ALTER TABLE ibl_standings
  ADD CONSTRAINT fk_standings_team
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Power Rankings to Team
ALTER TABLE ibl_power
  ADD CONSTRAINT fk_power_team
  FOREIGN KEY (Team) REFERENCES ibl_team_info(team_name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 6: FREE AGENCY AND TRADING FOREIGN KEYS
-- ============================================================================

-- FA Offers to Player
ALTER TABLE ibl_fa_offers
  ADD CONSTRAINT fk_faoffer_player
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- FA Offers to Team
ALTER TABLE ibl_fa_offers
  ADD CONSTRAINT fk_faoffer_team
  FOREIGN KEY (team) REFERENCES ibl_team_info(team_name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- Player Demands
ALTER TABLE ibl_demands
  ADD CONSTRAINT fk_demands_player
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 7: VOTING FOREIGN KEYS
-- ============================================================================

-- ASG Votes to Team
ALTER TABLE ibl_votes_ASG
  ADD CONSTRAINT fk_asg_votes_team
  FOREIGN KEY (teamid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- EOY Votes to Team
ALTER TABLE ibl_votes_EOY
  ADD CONSTRAINT fk_eoy_votes_team
  FOREIGN KEY (teamid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE 
  ON UPDATE CASCADE;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================

-- List all foreign keys created
-- SELECT 
--   TABLE_NAME,
--   CONSTRAINT_NAME,
--   COLUMN_NAME,
--   REFERENCED_TABLE_NAME,
--   REFERENCED_COLUMN_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND REFERENCED_TABLE_NAME IS NOT NULL
-- AND TABLE_NAME LIKE 'ibl_%'
-- ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- Count foreign keys per table
-- SELECT 
--   TABLE_NAME,
--   COUNT(*) as fk_count
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE()
-- AND REFERENCED_TABLE_NAME IS NOT NULL
-- AND TABLE_NAME LIKE 'ibl_%'
-- GROUP BY TABLE_NAME
-- ORDER BY fk_count DESC;

-- ============================================================================
-- TROUBLESHOOTING
-- ============================================================================

-- If foreign key creation fails, check for orphaned records:

-- 1. Find players with invalid team IDs
-- SELECT pid, name, tid FROM ibl_plr 
-- WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;

-- 2. Find hist records with invalid player IDs
-- SELECT nuke_iblhist, pid, name FROM ibl_hist 
-- WHERE pid NOT IN (SELECT pid FROM ibl_plr);

-- 3. Find box scores with invalid player IDs
-- SELECT Date, pid, name FROM ibl_box_scores 
-- WHERE pid NOT IN (SELECT pid FROM ibl_plr);

-- Fix orphaned records before retrying foreign key creation
-- Either delete them or update to valid references

-- ============================================================================
-- ROLLBACK SCRIPT
-- ============================================================================

-- To remove all foreign keys created by this migration:

-- ALTER TABLE ibl_plr DROP FOREIGN KEY fk_plr_team;
-- ALTER TABLE ibl_hist DROP FOREIGN KEY fk_hist_player;
-- ALTER TABLE ibl_box_scores DROP FOREIGN KEY fk_boxscore_player;
-- ALTER TABLE ibl_box_scores DROP FOREIGN KEY fk_boxscore_visitor;
-- ALTER TABLE ibl_box_scores DROP FOREIGN KEY fk_boxscore_home;
-- ALTER TABLE ibl_schedule DROP FOREIGN KEY fk_schedule_visitor;
-- ALTER TABLE ibl_schedule DROP FOREIGN KEY fk_schedule_home;
-- ALTER TABLE ibl_box_scores_teams DROP FOREIGN KEY fk_boxscoreteam_visitor;
-- ALTER TABLE ibl_box_scores_teams DROP FOREIGN KEY fk_boxscoreteam_home;
-- ALTER TABLE ibl_playoff_stats DROP FOREIGN KEY fk_playoff_stats_player;
-- ALTER TABLE ibl_heat_stats DROP FOREIGN KEY fk_heat_stats_name;
-- ALTER TABLE ibl_olympics_stats DROP FOREIGN KEY fk_olympics_stats_name;
-- ALTER TABLE ibl_team_offense_stats DROP FOREIGN KEY fk_team_offense_team;
-- ALTER TABLE ibl_team_defense_stats DROP FOREIGN KEY fk_team_defense_team;
-- ALTER TABLE ibl_draft_picks DROP FOREIGN KEY fk_draftpick_owner;
-- ALTER TABLE ibl_draft_picks DROP FOREIGN KEY fk_draftpick_team;
-- ALTER TABLE ibl_draft DROP FOREIGN KEY fk_draft_team;
-- ALTER TABLE ibl_standings DROP FOREIGN KEY fk_standings_team;
-- ALTER TABLE ibl_power DROP FOREIGN KEY fk_power_team;
-- ALTER TABLE ibl_fa_offers DROP FOREIGN KEY fk_faoffer_player;
-- ALTER TABLE ibl_fa_offers DROP FOREIGN KEY fk_faoffer_team;
-- ALTER TABLE ibl_demands DROP FOREIGN KEY fk_demands_player;
-- ALTER TABLE ibl_votes_ASG DROP FOREIGN KEY fk_asg_votes_team;
-- ALTER TABLE ibl_votes_EOY DROP FOREIGN KEY fk_eoy_votes_team;

-- ============================================================================
-- NOTES
-- ============================================================================

-- 1. Some foreign keys use 'name' (VARCHAR) instead of numeric IDs
--    This is not ideal but maintains compatibility with existing structure
--    Consider migrating to numeric IDs in future phases

-- 2. Free agent players have tid = 0, which is not a valid teamid
--    This is handled by the ON DELETE RESTRICT clause

-- 3. Some relationships couldn't be added due to data inconsistencies
--    These should be reviewed and addressed in future migrations

-- 4. CASCADE deletes are used for dependent data (stats, history)
--    RESTRICT is used for core entities (teams) to prevent accidental deletion

-- ============================================================================
