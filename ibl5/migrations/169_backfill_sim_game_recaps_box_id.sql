-- Migration 169 — backfill ibl_sim_game_recaps.box_id from the natural game key.
--
-- Migration 156 added box_id as a nullable convenience pointer but nothing ever
-- wrote it: every historical row is NULL. Ingest now resolves it (see
-- SimSummaryRepository::resolveBoxId), and the read path prefers the pointer with
-- the natural key as fallback. This carries the already-stored rows forward so the
-- pointer path is the normal case rather than the exception.
--
-- Forward-only and purely a data write: no DDL, no schema change, no data loss.
-- Rows whose natural key matches no archived box score are left NULL on purpose —
-- the read path's natural-key arm and the orphan warning both handle that state.

UPDATE `ibl_sim_game_recaps` gr
   SET gr.`box_id` = (
       SELECT MIN(bst.`id`)
         FROM `ibl_box_scores_teams` bst
        WHERE bst.`game_date`      = gr.`game_date`
          AND bst.`visitor_teamid` = gr.`visitor_teamid`
          AND bst.`home_teamid`    = gr.`home_teamid`
          AND COALESCE(bst.`game_of_that_day`, 0) = gr.`game_of_that_day`
   )
 WHERE gr.`box_id` IS NULL
   AND EXISTS (
       SELECT 1
         FROM `ibl_box_scores_teams` bst
        WHERE bst.`game_date`      = gr.`game_date`
          AND bst.`visitor_teamid` = gr.`visitor_teamid`
          AND bst.`home_teamid`    = gr.`home_teamid`
          AND COALESCE(bst.`game_of_that_day`, 0) = gr.`game_of_that_day`
   );

-- Rollback: this migration is lossless — box_id was NULL for every historical row
-- before it ran, so the inverse is safe. To undo:
-- UPDATE `ibl_sim_game_recaps` SET `box_id` = NULL;
