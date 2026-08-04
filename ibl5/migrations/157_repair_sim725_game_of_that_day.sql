-- Migration 157: Repair sim 725's game_of_that_day indices from the box-score archive
--
-- bin/sim-recap-prompt described game_of_that_day as a doubleheader counter. It is
-- actually the 1-based index of a game within its DATE across the whole league. The
-- generation agent for sim 725 (2026-08-02) followed the contract literally and wrote
-- 1 for all 49 games. SimSummaryRepository::findDisplayableGameRecaps() matches recaps
-- to box scores on date + teams + game_of_that_day, so exactly one game per date
-- matched: 7 of 49 recaps were displayable and 42 were unreachable. Every other column
-- on those rows is correct, including the full recap_text.
--
-- ibl_box_scores_teams stores one row per TEAM side, so the derived table collapses
-- each matchup to a single row. HAVING COUNT(DISTINCT game_of_that_day) = 1 is the
-- collision guard: uniq_game spans (season_year, game_date, visitor_teamid,
-- home_teamid, game_of_that_day), so a matchup that legitimately carries two indices
-- on one date would map both recap rows to the same key and the UPDATE would abort
-- mid-batch (MigrationRunner uses multi_query, so an abort halts every statement
-- after it). Excluding ambiguous matchups makes the collision impossible by
-- construction, independent of whether any such matchup exists in sim 725; the
-- post-deploy check in Step 6.4 names any row the guard skipped, for a separate call.
--
-- Idempotent: the `<>` predicate means a second run matches no rows and reports
-- affected_rows = 0, mirroring the sentinel-guarded UPDATEs in migration 097.
--
-- Scoped to sim 725 alone. No other sim's rows are read or written.

UPDATE ibl_sim_game_recaps gr
  JOIN (
        SELECT game_date, visitor_teamid, home_teamid,
               MIN(game_of_that_day) AS game_of_that_day
          FROM ibl_box_scores_teams
         GROUP BY game_date, visitor_teamid, home_teamid
        HAVING COUNT(DISTINCT game_of_that_day) = 1
       ) bst
    ON bst.game_date      = gr.game_date
   AND bst.visitor_teamid = gr.visitor_teamid
   AND bst.home_teamid    = gr.home_teamid
   SET gr.game_of_that_day = COALESCE(bst.game_of_that_day, 0)
 WHERE gr.sim = 725
   AND gr.game_of_that_day <> COALESCE(bst.game_of_that_day, 0);
