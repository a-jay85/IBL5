-- Migration 055: Fix vw_team_awards awarding IBL Champions during incomplete playoffs
--
-- Bug: The "IBL Champions" union selects winners from the max round each year.
-- When playoffs are in progress (e.g., only round 1 complete), all first-round
-- winners are incorrectly listed as IBL Champions.
--
-- Fix: Add a condition requiring the max round to have exactly 1 series,
-- which only happens when the Finals (round 4) have been played.

CREATE OR REPLACE VIEW vw_team_awards AS

-- Division Champions, Conference Champions, Draft Lottery Winners (pass through)
SELECT year, name, Award, ID
FROM ibl_team_awards

UNION ALL

-- IBL Champions: winner of the final round each playoff year
-- Only when the max round has exactly 1 series (= the Finals are complete)
SELECT
    psr.year,
    psr.winner AS name,
    'IBL Champions' AS Award,
    0 AS ID
FROM vw_playoff_series_results psr
WHERE psr.round = (
    SELECT MAX(psr2.round)
    FROM vw_playoff_series_results psr2
    WHERE psr2.year = psr.year
)
AND (
    SELECT COUNT(*)
    FROM vw_playoff_series_results psr3
    WHERE psr3.year = psr.year
      AND psr3.round = (
          SELECT MAX(psr4.round)
          FROM vw_playoff_series_results psr4
          WHERE psr4.year = psr.year
      )
) = 1

UNION ALL

-- HEAT Champions: winner of the championship game (min gameOfThatDay on last date per year)
SELECT
    hc.year,
    ti.team_name AS name,
    'IBL HEAT Champions' AS Award,
    0 AS ID
FROM (
    SELECT
        YEAR(bst.Date) AS year,
        CASE
            WHEN (bst.homeQ1points + bst.homeQ2points + bst.homeQ3points + bst.homeQ4points + COALESCE(bst.homeOTpoints, 0))
               > (bst.visitorQ1points + bst.visitorQ2points + bst.visitorQ3points + bst.visitorQ4points + COALESCE(bst.visitorOTpoints, 0))
            THEN bst.homeTeamID
            ELSE bst.visitorTeamID
        END AS winner_tid
    FROM ibl_box_scores_teams bst
    JOIN (
        SELECT YEAR(Date) AS yr, MAX(Date) AS last_date
        FROM ibl_box_scores_teams
        WHERE game_type = 3
        GROUP BY YEAR(Date)
    ) ld ON bst.Date = ld.last_date AND YEAR(bst.Date) = ld.yr
    WHERE bst.game_type = 3
      AND bst.gameOfThatDay = (
          SELECT MIN(bst2.gameOfThatDay)
          FROM ibl_box_scores_teams bst2
          WHERE bst2.Date = ld.last_date
            AND bst2.game_type = 3
      )
    GROUP BY YEAR(bst.Date)
) hc
JOIN ibl_team_info ti ON ti.teamid = hc.winner_tid;
