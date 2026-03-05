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
-- PART 1: PLAYER-RELATED FOREIGN KEYS
-- ============================================================================

-- Player to Team relationship
ALTER TABLE ibl_plr DROP FOREIGN KEY IF EXISTS fk_plr_team;
ALTER TABLE ibl_plr
  ADD CONSTRAINT fk_plr_team
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- Historical stats to Player
ALTER TABLE ibl_hist DROP FOREIGN KEY IF EXISTS fk_hist_player;
ALTER TABLE ibl_hist
  ADD CONSTRAINT fk_hist_player
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- Box scores to Player
ALTER TABLE ibl_box_scores DROP FOREIGN KEY IF EXISTS fk_boxscore_player;
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_player
  FOREIGN KEY (pid) REFERENCES ibl_plr(pid)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- Box scores to Teams
ALTER TABLE ibl_box_scores DROP FOREIGN KEY IF EXISTS fk_boxscore_visitor;
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_visitor
  FOREIGN KEY (visitorTID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

ALTER TABLE ibl_box_scores DROP FOREIGN KEY IF EXISTS fk_boxscore_home;
ALTER TABLE ibl_box_scores
  ADD CONSTRAINT fk_boxscore_home
  FOREIGN KEY (homeTID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 2: SCHEDULE AND GAME FOREIGN KEYS
-- ============================================================================

-- Schedule to Teams
ALTER TABLE ibl_schedule DROP FOREIGN KEY IF EXISTS fk_schedule_visitor;
ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_visitor
  FOREIGN KEY (Visitor) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

ALTER TABLE ibl_schedule DROP FOREIGN KEY IF EXISTS fk_schedule_home;
ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_home
  FOREIGN KEY (Home) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- Box Scores Teams to Teams
ALTER TABLE ibl_box_scores_teams DROP FOREIGN KEY IF EXISTS fk_boxscoreteam_visitor;
ALTER TABLE ibl_box_scores_teams
  ADD CONSTRAINT fk_boxscoreteam_visitor
  FOREIGN KEY (visitorTeamID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

ALTER TABLE ibl_box_scores_teams DROP FOREIGN KEY IF EXISTS fk_boxscoreteam_home;
ALTER TABLE ibl_box_scores_teams
  ADD CONSTRAINT fk_boxscoreteam_home
  FOREIGN KEY (homeTeamID) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 3: STATISTICS FOREIGN KEYS
-- ============================================================================

-- ibl_playoff_stats, ibl_heat_stats: now views (migration 028), skip FKs

-- Olympics Stats
ALTER TABLE ibl_olympics_stats DROP FOREIGN KEY IF EXISTS fk_olympics_stats_name;
ALTER TABLE ibl_olympics_stats
  ADD CONSTRAINT fk_olympics_stats_name
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- ibl_team_offense_stats, ibl_team_defense_stats: now views (migration 028), skip FKs

-- ============================================================================
-- PART 4: DRAFT SYSTEM FOREIGN KEYS
-- ============================================================================

-- Draft Picks to Team (owner of pick)
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_owner;
ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_owner
  FOREIGN KEY (ownerofpick) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- Draft Picks to Team (original pick)
ALTER TABLE ibl_draft_picks DROP FOREIGN KEY IF EXISTS fk_draftpick_team;
ALTER TABLE ibl_draft_picks
  ADD CONSTRAINT fk_draftpick_team
  FOREIGN KEY (teampick) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- Draft to Team
ALTER TABLE ibl_draft DROP FOREIGN KEY IF EXISTS fk_draft_team;
ALTER TABLE ibl_draft
  ADD CONSTRAINT fk_draft_team
  FOREIGN KEY (team) REFERENCES ibl_team_info(team_name)
  ON DELETE RESTRICT
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 5: STANDINGS AND RANKINGS FOREIGN KEYS
-- ============================================================================

-- Standings to Team
ALTER TABLE ibl_standings DROP FOREIGN KEY IF EXISTS fk_standings_team;
ALTER TABLE ibl_standings
  ADD CONSTRAINT fk_standings_team
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- ibl_power.Team column dropped by migration 049 (re-keyed to TeamID), skip FK

-- ============================================================================
-- PART 6: FREE AGENCY AND TRADING FOREIGN KEYS
-- ============================================================================

-- FA Offers to Player
ALTER TABLE ibl_fa_offers DROP FOREIGN KEY IF EXISTS fk_faoffer_player;
ALTER TABLE ibl_fa_offers
  ADD CONSTRAINT fk_faoffer_player
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- FA Offers to Team
ALTER TABLE ibl_fa_offers DROP FOREIGN KEY IF EXISTS fk_faoffer_team;
ALTER TABLE ibl_fa_offers
  ADD CONSTRAINT fk_faoffer_team
  FOREIGN KEY (team) REFERENCES ibl_team_info(team_name)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- Player Demands
ALTER TABLE ibl_demands DROP FOREIGN KEY IF EXISTS fk_demands_player;
ALTER TABLE ibl_demands
  ADD CONSTRAINT fk_demands_player
  FOREIGN KEY (name) REFERENCES ibl_plr(name)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- ============================================================================
-- PART 7: VOTING FOREIGN KEYS
-- ============================================================================

-- ASG Votes to Team
ALTER TABLE ibl_votes_ASG DROP FOREIGN KEY IF EXISTS fk_asg_votes_team;
ALTER TABLE ibl_votes_ASG
  ADD CONSTRAINT fk_asg_votes_team
  FOREIGN KEY (teamid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE
  ON UPDATE CASCADE;

-- EOY Votes to Team
ALTER TABLE ibl_votes_EOY DROP FOREIGN KEY IF EXISTS fk_eoy_votes_team;
ALTER TABLE ibl_votes_EOY
  ADD CONSTRAINT fk_eoy_votes_team
  FOREIGN KEY (teamid) REFERENCES ibl_team_info(teamid)
  ON DELETE CASCADE
  ON UPDATE CASCADE;
