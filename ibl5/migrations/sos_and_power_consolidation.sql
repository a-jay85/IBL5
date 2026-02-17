-- Strength of Schedule & ibl_power Consolidation Migration
--
-- 1. Adds wins/losses INT columns to ibl_standings
-- 2. Adds SOS columns to ibl_power
-- 3. Drops 13 redundant columns from ibl_power (records that duplicate ibl_standings)
-- 4. Re-keys ibl_power from Team (VARCHAR) PK to TeamID (INT) PK
--
-- IMPORTANT: Run after StandingsUpdater has been updated to populate wins/losses.
-- Run PowerRankingsUpdater after this migration to populate SOS columns.

-- Step 1: Add explicit wins/losses INT columns to ibl_standings
ALTER TABLE ibl_standings
  ADD COLUMN wins TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER leagueRecord,
  ADD COLUMN losses TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER wins;

-- Step 2: Add SOS columns to ibl_power
ALTER TABLE ibl_power
  ADD COLUMN sos DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  ADD COLUMN remaining_sos DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  ADD COLUMN sos_rank TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN remaining_sos_rank TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Step 3: Drop redundant columns from ibl_power
-- These all duplicate data that lives authoritatively in ibl_standings.
ALTER TABLE ibl_power
  DROP COLUMN Division,
  DROP COLUMN Conference,
  DROP COLUMN win,
  DROP COLUMN loss,
  DROP COLUMN gb,
  DROP COLUMN conf_win,
  DROP COLUMN conf_loss,
  DROP COLUMN div_win,
  DROP COLUMN div_loss,
  DROP COLUMN home_win,
  DROP COLUMN home_loss,
  DROP COLUMN road_win,
  DROP COLUMN road_loss;

-- Step 4: Re-key ibl_power from Team (VARCHAR) to TeamID (INT) as PK
-- Drop the existing FK constraint on Team first
ALTER TABLE ibl_power
  DROP FOREIGN KEY fk_power_team;

ALTER TABLE ibl_power
  DROP PRIMARY KEY,
  DROP COLUMN Team,
  ADD PRIMARY KEY (TeamID);
