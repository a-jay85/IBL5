-- Add covering index for the NOT EXISTS anti-join in ibl_hist VIEW.
--
-- The VIEW's UNION ALL fallback uses:
--   WHERE NOT EXISTS (SELECT 1 FROM ibl_box_scores bs
--                     WHERE bs.pid = ha.pid AND bs.season_year = ha.year
--                     AND bs.game_type = 1)
--
-- Without this index, MariaDB materializes ALL game_type=1 rows (~400K)
-- into a temp hash table instead of doing a direct (pid, season_year) lookup.
-- This caused 30s+ query times on production after migration 090 aligned
-- the archive years, making the NOT EXISTS non-trivial for the first time.

CREATE INDEX idx_gt_pid_season ON ibl_box_scores (game_type, pid, season_year);
