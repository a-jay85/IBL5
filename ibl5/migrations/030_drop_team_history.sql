-- Migration 030: Drop ibl_team_history table
-- Replaces the denormalized cache table with:
--   1. Operational columns (depth, sim_depth, asg_vote, eoy_vote) moved to ibl_team_info
--   2. vw_team_awards view (UNIONs all team awards from canonical sources)
--   3. vw_franchise_summary view (computes all-time stats per team)
-- Continues the pattern from migrations 026-028 of replacing denormalized tables with views.

-- ============================================================
-- Step 1: Add operational columns to ibl_team_info
-- ============================================================
ALTER TABLE ibl_team_info
  ADD COLUMN depth VARCHAR(100) NOT NULL DEFAULT '' AFTER chart,
  ADD COLUMN sim_depth VARCHAR(100) NOT NULL DEFAULT 'No Depth Chart' AFTER depth,
  ADD COLUMN asg_vote VARCHAR(100) NOT NULL DEFAULT 'No Vote' AFTER sim_depth,
  ADD COLUMN eoy_vote VARCHAR(100) NOT NULL DEFAULT 'No Vote' AFTER asg_vote;

-- ============================================================
-- Step 2: Copy current operational data from ibl_team_history
-- ============================================================
UPDATE ibl_team_info ti
JOIN ibl_team_history th ON ti.teamid = th.teamid
SET ti.depth = th.depth,
    ti.sim_depth = th.sim_depth,
    ti.asg_vote = th.asg_vote,
    ti.eoy_vote = th.eoy_vote;

-- ============================================================
-- Step 3: Create vw_team_awards view
-- UNIONs all team awards from their canonical sources:
--   - Division Champions, Conference Champions, Draft Lottery Winners: from ibl_team_awards
--   - IBL Champions: derived from vw_playoff_series_results (max round per year)
--   - HEAT Champions: derived from ibl_box_scores_teams (championship game)
-- ============================================================
CREATE OR REPLACE VIEW vw_team_awards AS

-- Division Champions, Conference Champions, Draft Lottery Winners (pass through)
SELECT year, name, Award, ID
FROM ibl_team_awards

UNION ALL

-- IBL Champions: winner of the final round each playoff year
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
        -- Find the last date of each HEAT tournament year
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

-- ============================================================
-- Step 4: Create vw_franchise_summary view
-- Computes all-time stats per team from existing views/tables:
--   - totwins/totloss/winpct from ibl_team_win_loss
--   - playoffs from vw_playoff_series_results (distinct years in round 1)
--   - Title counts from vw_team_awards
-- ============================================================
CREATE OR REPLACE VIEW vw_franchise_summary AS
SELECT
    ti.teamid,
    COALESCE(wl.totwins, 0) AS totwins,
    COALESCE(wl.totloss, 0) AS totloss,
    CASE
        WHEN COALESCE(wl.totwins, 0) + COALESCE(wl.totloss, 0) = 0 THEN 0.000
        ELSE ROUND(COALESCE(wl.totwins, 0) / (COALESCE(wl.totwins, 0) + COALESCE(wl.totloss, 0)), 3)
    END AS winpct,
    COALESCE(po.playoffs, 0) AS playoffs,
    COALESCE(tc.div_titles, 0) AS div_titles,
    COALESCE(tc.conf_titles, 0) AS conf_titles,
    COALESCE(tc.ibl_titles, 0) AS ibl_titles,
    COALESCE(tc.heat_titles, 0) AS heat_titles
FROM ibl_team_info ti
LEFT JOIN (
    SELECT currentname, SUM(wins) AS totwins, SUM(losses) AS totloss
    FROM ibl_team_win_loss
    GROUP BY currentname
) wl ON wl.currentname = ti.team_name
LEFT JOIN (
    SELECT team_name, COUNT(DISTINCT year) AS playoffs
    FROM (
        SELECT winner AS team_name, year FROM vw_playoff_series_results WHERE round = 1
        UNION
        SELECT loser AS team_name, year FROM vw_playoff_series_results WHERE round = 1
    ) po_inner
    GROUP BY team_name
) po ON po.team_name = ti.team_name
LEFT JOIN (
    SELECT
        name,
        SUM(CASE WHEN Award LIKE '%Division%' THEN 1 ELSE 0 END) AS div_titles,
        SUM(CASE WHEN Award LIKE '%Conference%' THEN 1 ELSE 0 END) AS conf_titles,
        SUM(CASE WHEN Award LIKE '%IBL Champions%' THEN 1 ELSE 0 END) AS ibl_titles,
        SUM(CASE WHEN Award LIKE '%HEAT%' THEN 1 ELSE 0 END) AS heat_titles
    FROM vw_team_awards
    GROUP BY name
) tc ON tc.name = ti.team_name
WHERE ti.teamid BETWEEN 1 AND 30;

-- ============================================================
-- Step 5: Remove derived entries from ibl_team_awards
-- IBL Champions and HEAT Champions are now derived from game data
-- ============================================================
DELETE FROM ibl_team_awards WHERE Award IN (
    'IBL Champions',
    'IBL HEAT Champions'
);

-- ============================================================
-- Step 6: Drop the denormalized table
-- ============================================================
DROP TABLE ibl_team_history;
