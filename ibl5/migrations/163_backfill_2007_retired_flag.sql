-- Migration: 163_backfill_2007_retired_flag.sql
-- Purpose: Set ibl_plr.retired = 1 for the 8 players who retired in 2007 and
--          were present in the 2008 mid-season .plr import, which zeroed their
--          flag (bug fixed in PR #1926). The career-leaderboard asterisk is driven
--          solely by this column (CareerLeaderboardsService.php:27-28).
-- Scope:   Exactly the 8 named pids (327 Arvydas Sabonis, 1250 Jamal Murray,
--          2000 Gary Payton, 2750 Donta Smith, 3301 Frank Johnson II,
--          3307 Anthony Tolliver, 5318 Troy Bell, 5645 Danny Granger).
--          Hardcoded pids, not a join -- a join on ibl_jsb_retired_players would
--          clear 721 currently-correct retired=1 flags (721 players carry retired=1
--          while absent from that table). Additive-only: no row is set to retired=0.
-- Idempotent: AND retired = 0 guard -- a second run touches 0 rows.
-- Date:    2026-08-19

UPDATE ibl_plr
   SET retired = 1
 WHERE pid IN (327, 1250, 2000, 2750, 3301, 3307, 5318, 5645)
   AND retired = 0;
