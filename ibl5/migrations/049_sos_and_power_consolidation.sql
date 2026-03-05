-- Strength of Schedule & ibl_power Consolidation Migration

-- Step 1: Add explicit wins/losses INT columns to ibl_standings
ALTER TABLE ibl_standings
  ADD COLUMN IF NOT EXISTS wins TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER leagueRecord,
  ADD COLUMN IF NOT EXISTS losses TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER wins;

-- Step 2: Add SOS columns to ibl_power
ALTER TABLE ibl_power
  ADD COLUMN IF NOT EXISTS sos DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  ADD COLUMN IF NOT EXISTS remaining_sos DECIMAL(4,3) NOT NULL DEFAULT 0.000,
  ADD COLUMN IF NOT EXISTS sos_rank TINYINT UNSIGNED NOT NULL DEFAULT 0,
  ADD COLUMN IF NOT EXISTS remaining_sos_rank TINYINT UNSIGNED NOT NULL DEFAULT 0;

-- Step 3: Drop redundant columns from ibl_power
ALTER TABLE ibl_power
  DROP COLUMN IF EXISTS Division,
  DROP COLUMN IF EXISTS Conference,
  DROP COLUMN IF EXISTS win,
  DROP COLUMN IF EXISTS loss,
  DROP COLUMN IF EXISTS gb,
  DROP COLUMN IF EXISTS conf_win,
  DROP COLUMN IF EXISTS conf_loss,
  DROP COLUMN IF EXISTS div_win,
  DROP COLUMN IF EXISTS div_loss,
  DROP COLUMN IF EXISTS home_win,
  DROP COLUMN IF EXISTS home_loss,
  DROP COLUMN IF EXISTS road_win,
  DROP COLUMN IF EXISTS road_loss;

-- Step 4: Re-key ibl_power from Team (VARCHAR) to TeamID (INT) as PK
ALTER TABLE ibl_power
  DROP FOREIGN KEY IF EXISTS fk_power_team;

ALTER TABLE ibl_power
  DROP COLUMN IF EXISTS Team;

-- TeamID becomes the PK (if not already)
ALTER TABLE ibl_power
  MODIFY COLUMN TeamID INT NOT NULL DEFAULT 0;

ALTER TABLE ibl_power
  ADD PRIMARY KEY IF NOT EXISTS (TeamID);
