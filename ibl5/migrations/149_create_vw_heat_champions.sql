-- Migration 149: Extract the HEAT-champion CTE from RecordHoldersRepository into a view
-- (maintenance-47, chunk C9 deferred from maintenance-38/PR #1040 finding 1.2).
--
-- Body is the verbatim branch-3 subquery of buildMostTitlesByTypeQuery(): the winner of
-- each year's HEAT championship game (game_type = 3, latest game_date, smallest
-- game_of_that_day), joined to ibl_team_info. Columns: year, name, award.
-- Snake-case columns per migrations 116/121 (matches the live repo CTE, NOT stale
-- camelCase migration 110). CREATE OR REPLACE = idempotent; no DROP VIEW (no adr-check /
-- destructive-migration trigger fires).

CREATE OR REPLACE VIEW `vw_heat_champions` AS
SELECT
    hc.year,
    ti.team_name AS name,
    'IBL HEAT Champions' AS award
FROM (
    SELECT
        YEAR(bst.game_date) AS year,
        CASE
            WHEN (bst.home_q1_points + bst.home_q2_points + bst.home_q3_points + bst.home_q4_points
                  + COALESCE(bst.home_ot_points, 0))
               > (bst.visitor_q1_points + bst.visitor_q2_points + bst.visitor_q3_points + bst.visitor_q4_points
                  + COALESCE(bst.visitor_ot_points, 0))
            THEN bst.home_teamid
            ELSE bst.visitor_teamid
        END AS winner_tid,
        ROW_NUMBER() OVER (
            PARTITION BY YEAR(bst.game_date)
            ORDER BY bst.game_date DESC, bst.game_of_that_day ASC
        ) AS rn
    FROM `ibl_box_scores_teams` bst
    WHERE bst.game_type = 3
) hc
JOIN `ibl_team_info` ti ON ti.teamid = hc.winner_tid
WHERE hc.rn = 1;
