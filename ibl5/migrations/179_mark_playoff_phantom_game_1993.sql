-- Mark playoff phantom game in ibl_plr_snapshots for season_year 1993.
--
-- On 1993-06-04 the Warriors (tid 24) at Sonics (tid 22) round-1 playoff game
-- was double-entered in the schedule and simmed twice. The copy at
-- game_of_that_day 1 (SEA 119-115) is a phantom; the copy at
-- game_of_that_day 5 (GSW 120-105) is the real game 4. A separate migration
-- deletes the phantom box-score rows.
--
-- The .plr had already accumulated the phantom game into each player's playoff
-- game count, so po_stats_gm for season_year 1993 is inflated by 1 for the
-- 24 players who appeared in the phantom game.
--
-- This column is the playoff twin of phantom_games (migration 105).
-- ibl_hist has no playoff columns, so no consumer subtracts it yet.
-- Added to ibl_olympics_plr_snapshots too for schema parity (migration 130).

-- Step 1: Add the column to both tables
ALTER TABLE ibl_plr_snapshots
  ADD COLUMN IF NOT EXISTS po_phantom_games TINYINT UNSIGNED NOT NULL DEFAULT 0
  AFTER po_stats_gm;

ALTER TABLE ibl_olympics_plr_snapshots
  ADD COLUMN IF NOT EXISTS po_phantom_games TINYINT UNSIGNED NOT NULL DEFAULT 0
  AFTER po_stats_gm;

-- Step 2: Mark the 24 affected players for season_year 1993
-- Sonics (tid 22): 12 players
UPDATE ibl_plr_snapshots SET po_phantom_games = 1
WHERE season_year = 1993 AND pid IN (
  46,   -- Patrick Ewing
  213,  -- Michael Anderson
  623,  -- Mike Ngo
  651,  -- Sergei Bazarevic
  655,  -- Zeljko Rebraca
  1238, -- Pascal Siakam
  1254, -- Juan Hernangomez
  1259, -- Dragan Kicanovic
  1265, -- Damian Jones
  1501, -- Michelle Snow
  1503, -- Freeman Williams
  1519  -- Anthony Parker
);

-- Warriors (tid 24): 12 players
UPDATE ibl_plr_snapshots SET po_phantom_games = 1
WHERE season_year = 1993 AND pid IN (
  32,   -- Billy Thompson
  147,  -- Rik Smits
  201,  -- Sleepy Floyd
  230,  -- Jerome Kersey
  306,  -- Chris Mullin
  633,  -- Aaron McKie
  930,  -- Arvydas Macijauskas
  1239, -- Earl Manigault
  1494, -- Danny Fortson
  1504, -- Tariq Abdul-Wahad
  1513, -- Kevin Nash
  1520  -- Darius Songaila
);
