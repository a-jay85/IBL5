-- Fix #572: Remove duplicate consecutive seasons from ibl_hist_archive.
--
-- Root cause: bulkJsbImport.php imported the same .car snapshot (same player
-- stats) for two consecutive years (e.g. 2006 and 2007) because a heat-end
-- snapshot was stored under both the N and N+1 season directory.
--
-- The ibl_hist VIEW already derives correct stats from ibl_box_scores for any
-- player-season that has box score data. These duplicate archive rows are both
-- unreachable (shadowed by the VIEW primary branch) and incorrect.
--
-- This delete targets only exact-match duplicates: rows where ALL seven core
-- stat columns match the prior year for the same player. Players who
-- legitimately posted identical stat lines in consecutive seasons are not
-- affected (matching all seven simultaneously by coincidence is negligible).

DELETE h_dup
FROM ibl_hist_archive h_dup
INNER JOIN ibl_hist_archive h_prev
    ON  h_prev.pid     = h_dup.pid
    AND h_prev.year    = h_dup.year - 1
    AND h_prev.games   = h_dup.games
    AND h_prev.pts     = h_dup.pts
    AND h_prev.fgm     = h_dup.fgm
    AND h_prev.fga     = h_dup.fga
    AND h_prev.reb     = h_dup.reb
    AND h_prev.ast     = h_dup.ast
    AND h_prev.minutes = h_dup.minutes;
