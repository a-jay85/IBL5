-- Migration: Add game context fields to player box scores
-- Date: 2026-02-10
-- Purpose: Denormalize game context data into player box score records for simpler queries

ALTER TABLE ibl_box_scores
  ADD COLUMN gameOfThatDay TINYINT UNSIGNED DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd game)',
  ADD COLUMN attendance INT DEFAULT NULL COMMENT 'Attendance at the game',
  ADD COLUMN capacity INT DEFAULT NULL COMMENT 'Arena capacity',
  ADD COLUMN visitorWins SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team wins before this game',
  ADD COLUMN visitorLosses SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team losses before this game',
  ADD COLUMN homeWins SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team wins before this game',
  ADD COLUMN homeLosses SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team losses before this game',
  ADD COLUMN teamID INT DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)',
  ADD INDEX idx_team_id (teamID);
