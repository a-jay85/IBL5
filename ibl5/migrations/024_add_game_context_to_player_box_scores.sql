-- Migration: Add game context fields to player box scores
-- Date: 2026-02-10
-- Purpose: Denormalize game context data into player box score records for simpler queries

ALTER TABLE ibl_box_scores
  ADD COLUMN IF NOT EXISTS gameOfThatDay TINYINT UNSIGNED DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd game)',
  ADD COLUMN IF NOT EXISTS attendance INT DEFAULT NULL COMMENT 'Attendance at the game',
  ADD COLUMN IF NOT EXISTS capacity INT DEFAULT NULL COMMENT 'Arena capacity',
  ADD COLUMN IF NOT EXISTS visitorWins SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team wins before this game',
  ADD COLUMN IF NOT EXISTS visitorLosses SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team losses before this game',
  ADD COLUMN IF NOT EXISTS homeWins SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team wins before this game',
  ADD COLUMN IF NOT EXISTS homeLosses SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team losses before this game',
  ADD COLUMN IF NOT EXISTS teamID INT DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)',
  ADD INDEX IF NOT EXISTS idx_team_id (teamID);
