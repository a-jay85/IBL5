-- Migration 141: Index three prod slow-query hot spots
--
-- Evidence: prod slow_query log (14 days) + read-only prod EXPLAIN, run 27093907413.
-- All three statements are idempotent (IF NOT EXISTS / IF EXISTS) and forward-only.
-- Indexes are transparent to query text and results — no application change.

-- Fix 1 — TrainingCamp\TrainingCampRatingsDiffRepository::getLatestEndOfSeasonYear()
--   Query: SELECT MAX(season_year) FROM ibl_plr_snapshots WHERE snapshot_phase = 'end-of-season'
--   Before: type=index, key=uq_pid_year_phase, rows=400952, "Using where; Using index"
--           (full index scan — snapshot_phase is the 3rd column of the only usable index,
--            so it cannot be seeked). Measured 0.358s.
--   After: snapshot_phase becomes the lead column -> equality seek; MAX(season_year)
--          reads the last entry of that range. Single seek, sub-ms.
--   No existing index is made redundant (idx_season=(season_year),
--   idx_tid_season=(teamid,season_year), uq_pid_year_phase leads with pid) — no drop here.
CREATE INDEX IF NOT EXISTS idx_snapshot_phase_year
    ON ibl_plr_snapshots (snapshot_phase, season_year);

-- Fix 2 — Player\Stats\PlayerStatsRepository::getBoxScoresBetweenDates()
--   Query: ... FROM ibl_box_scores bs ... WHERE bs.pid = ? AND bs.game_date BETWEEN ? AND ?
--          ORDER BY bs.game_date ASC
--   Before: type=ref on idx_pid (pid-only), up to 1763 rows, "Using filesort"
--           (idx_pid provides no ordering for ORDER BY game_date).
--   After: composite serves the pid equality AND the game_date range AND the
--          game_date ordering -> no filesort.
--
-- ORDERING IS MANDATORY: ibl_box_scores.pid carries FK fk_boxscore_player -> ibl_plr.pid.
-- An FK requires a backing index leading with the FK column. idx_pid currently backs it.
-- We must CREATE the composite FIRST so its (pid) left-prefix backs the FK, THEN drop
-- idx_pid — otherwise MariaDB refuses the drop ("needed in a foreign key constraint").
CREATE INDEX IF NOT EXISTS idx_pid_date
    ON ibl_box_scores (pid, game_date);

-- Drop the now-redundant single-column idx_pid: the (pid, game_date) composite's (pid)
-- left-prefix supersedes it for every pid-only consumer and backs fk_boxscore_player.
-- Safe only AFTER the composite above exists (see ordering note). Other pid+game_type
-- queries use idx_gt_pid / idx_gt_pid_season and are unaffected.
DROP INDEX IF EXISTS idx_pid ON ibl_box_scores;
