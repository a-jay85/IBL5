--- Migration 055: Fix vw_team_awards awarding IBL Champions prematurely
--- The IBL Champions UNION selected the winner of MAX(round) per year,
--- so during incomplete playoffs (e.g. only round 1 finished), all
--- first-round winners were listed as IBL Champions.
--- Fix: require round = 4 (the Finals) instead of MAX(round).

CREATE OR REPLACE VIEW vw_team_awards AS

-- Division Champions, Conference Champions, Draft Lottery Winners (pass through)
SELECT year, name, Award, ID
FROM ibl_team_awards

UNION ALL

-- IBL Champions: winner of the Finals (round 4) each playoff year
SELECT
    psr.year,
    psr.winner AS name,
    'IBL Champions' AS Award,
    0 AS ID
FROM vw_playoff_series_results psr
WHERE psr.round = 4

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
