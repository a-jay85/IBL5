-- Migration 110: Optimize vw_team_awards by eliminating correlated subqueries
--
-- The IBL Champions branch previously used 4 correlated subqueries against
-- vw_playoff_series_results (itself a 4-CTE chain). Each correlated reference
-- re-materialized the full view. Replaced with window functions that materialize
-- vw_playoff_series_results exactly once.
--
-- The HEAT Champions branch previously used a correlated MIN(gameOfThatDay)
-- subquery. Replaced with ROW_NUMBER window function in a single pass.
--
-- vw_franchise_summary depends on this view and benefits automatically.

CREATE OR REPLACE VIEW vw_team_awards AS

-- Branch 1: Division Champions, Conference Champions, Draft Lottery Winners (pass through)
SELECT year, name, Award, ID
FROM ibl_team_awards

UNION ALL

-- Branch 2: IBL Champions — winner of the final round each playoff year
-- Only when the max round has exactly 1 series (= the Finals are complete)
SELECT ranked.year, ranked.name, 'IBL Champions' AS Award, 0 AS ID
FROM (
    SELECT
        psr.year,
        psr.winner AS name,
        psr.round,
        MAX(psr.round) OVER (PARTITION BY psr.year) AS max_round,
        COUNT(*) OVER (PARTITION BY psr.year, psr.round) AS series_in_round
    FROM vw_playoff_series_results psr
) ranked
WHERE ranked.round = ranked.max_round AND ranked.series_in_round = 1

UNION ALL

-- Branch 3: HEAT Champions — winner of the championship game
-- (latest date + smallest gameOfThatDay per year, game_type=3)
SELECT
    hc.year,
    ti.team_name AS name,
    'IBL HEAT Champions' AS Award,
    0 AS ID
FROM (
    SELECT
        YEAR(bst.Date) AS year,
        CASE
            WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points
                  + COALESCE(bst.homeOTpoints, 0))
               > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points
                  + COALESCE(bst.visitorOTpoints, 0))
            THEN bst.homeTeamID
            ELSE bst.visitorTeamID
        END AS winner_tid,
        ROW_NUMBER() OVER (
            PARTITION BY YEAR(bst.Date)
            ORDER BY bst.Date DESC, bst.gameOfThatDay ASC
        ) AS rn
    FROM ibl_box_scores_teams bst
    WHERE bst.game_type = 3
) hc
JOIN ibl_team_info ti ON ti.teamid = hc.winner_tid
WHERE hc.rn = 1;
