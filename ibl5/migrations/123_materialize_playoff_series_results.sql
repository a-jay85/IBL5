-- Migration 123: Materialize vw_playoff_series_results into a real table.
--
-- Replaces the multi-CTE TEMPTABLE-style view with a materialized InnoDB
-- table refreshed by RefreshPlayoffSeriesResultsStep on every IBL pipeline
-- run. The view name is preserved as a thin pass-through so existing
-- consumers (vw_team_awards, vw_franchise_summary, 7+ PHP repos) need no
-- changes. See ADR-0015.

-- Drop the existing view; this also invalidates the dependent views
-- (vw_team_awards, vw_franchise_summary), which are recreated below.
DROP VIEW IF EXISTS `vw_playoff_series_results`;

CREATE TABLE IF NOT EXISTS `ibl_playoff_series_results` (
    `year` SMALLINT NOT NULL,
    `round` TINYINT NOT NULL,
    `winner_tid` INT NOT NULL,
    `loser_tid` INT NOT NULL,
    `winner` VARCHAR(35) NOT NULL,
    `loser` VARCHAR(35) NOT NULL,
    `winner_games` SMALLINT NOT NULL,
    `loser_games` SMALLINT NOT NULL,
    `total_games` SMALLINT NOT NULL,
    PRIMARY KEY (`year`, `round`, `winner_tid`, `loser_tid`),
    INDEX `idx_winner` (`winner`),
    INDEX `idx_loser` (`loser`),
    INDEX `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Populate from the same CTE that previously defined vw_playoff_series_results
-- (migration 121, line 397). Snake-case columns from migration 121.
INSERT INTO `ibl_playoff_series_results`
    (`year`, `round`, `winner_tid`, `loser_tid`, `winner`, `loser`,
     `winner_games`, `loser_games`, `total_games`)
WITH playoff_games AS (
    SELECT
        game_date,
        YEAR(game_date) AS `year`,
        visitor_teamid,
        home_teamid,
        game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS v_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS h_total
    FROM ibl_box_scores_teams
    WHERE game_type = 2
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
game_results AS (
    SELECT *,
        CASE WHEN v_total > h_total THEN visitor_teamid ELSE home_teamid END AS winner_tid,
        CASE WHEN v_total > h_total THEN home_teamid ELSE visitor_teamid END AS loser_tid
    FROM playoff_games
),
team_wins AS (
    SELECT
        `year`,
        LEAST(visitor_teamid, home_teamid) AS team_a,
        GREATEST(visitor_teamid, home_teamid) AS team_b,
        winner_tid,
        COUNT(*) AS wins,
        ROW_NUMBER() OVER (
            PARTITION BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid)
            ORDER BY COUNT(*) DESC
        ) AS rn
    FROM game_results
    GROUP BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid), winner_tid
),
series_meta AS (
    SELECT
        `year`,
        LEAST(visitor_teamid, home_teamid) AS team_a,
        GREATEST(visitor_teamid, home_teamid) AS team_b,
        COUNT(*) AS total_games,
        MIN(game_date) AS series_start,
        DENSE_RANK() OVER (PARTITION BY `year` ORDER BY MIN(game_date)) AS `round`
    FROM game_results
    GROUP BY `year`, LEAST(visitor_teamid, home_teamid), GREATEST(visitor_teamid, home_teamid)
)
SELECT
    sm.`year`,
    sm.`round`,
    tw.winner_tid,
    CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END AS loser_tid,
    w.team_name AS winner,
    l.team_name AS loser,
    tw.wins AS winner_games,
    sm.total_games - tw.wins AS loser_games,
    sm.total_games
FROM series_meta sm
JOIN team_wins tw
    ON tw.`year` = sm.`year` AND tw.team_a = sm.team_a AND tw.team_b = sm.team_b AND tw.rn = 1
JOIN ibl_team_info w ON w.teamid = tw.winner_tid
JOIN ibl_team_info l ON l.teamid = CASE WHEN tw.winner_tid = sm.team_a THEN sm.team_b ELSE sm.team_a END;

-- Recreate the view as a thin pass-through over the materialized table.
-- Existing consumers (PHP and dependent views) bind to this name.
CREATE OR REPLACE VIEW `vw_playoff_series_results` AS
SELECT
    `year`,
    `round`,
    `winner_tid`,
    `loser_tid`,
    `winner`,
    `loser`,
    `winner_games`,
    `loser_games`,
    `total_games`
FROM `ibl_playoff_series_results`
ORDER BY `year` DESC, `round` ASC;

-- Recreate dependent views invalidated by the DROP above.
-- Definitions copied verbatim from migration 121 (vw_team_awards, line 405)
-- and migration 120 (vw_franchise_summary, lines 145-193).

CREATE OR REPLACE VIEW `vw_team_awards` AS
SELECT `ibl_team_awards`.`year` AS `year`,
       `ibl_team_awards`.`name` AS `name`,
       `ibl_team_awards`.`award` AS `award`,
       `ibl_team_awards`.`id` AS `id`
FROM `ibl_team_awards`
UNION ALL
SELECT `ranked`.`year` AS `year`,
       `ranked`.`name` AS `name`,
       'IBL Champions' AS `award`,
       0 AS `id`
FROM (
    SELECT `psr`.`year` AS `year`,
           `psr`.`winner` AS `name`,
           `psr`.`round` AS `round`,
           MAX(`psr`.`round`) OVER (PARTITION BY `psr`.`year`) AS `max_round`,
           COUNT(0) OVER (PARTITION BY `psr`.`year`, `psr`.`round`) AS `series_in_round`
    FROM `vw_playoff_series_results` `psr`
) `ranked`
WHERE `ranked`.`round` = `ranked`.`max_round`
  AND `ranked`.`series_in_round` = 1
UNION ALL
SELECT `hc`.`year` AS `year`,
       `ti`.`team_name` AS `name`,
       'IBL HEAT Champions' AS `award`,
       0 AS `id`
FROM (
    SELECT YEAR(`bst`.`game_date`) AS `year`,
           CASE WHEN `bst`.`home_q1_points` + `bst`.`home_q2_points` + `bst`.`home_q3_points` + `bst`.`home_q4_points`
                  + COALESCE(`bst`.`home_ot_points`, 0)
                > `bst`.`visitor_q1_points` + `bst`.`visitor_q2_points` + `bst`.`visitor_q3_points` + `bst`.`visitor_q4_points`
                  + COALESCE(`bst`.`visitor_ot_points`, 0)
                THEN `bst`.`home_teamid`
                ELSE `bst`.`visitor_teamid`
           END AS `winner_tid`,
           ROW_NUMBER() OVER (
               PARTITION BY YEAR(`bst`.`game_date`)
               ORDER BY `bst`.`game_date` DESC, `bst`.`game_of_that_day`
           ) AS `rn`
    FROM `ibl_box_scores_teams` `bst`
    WHERE `bst`.`game_type` = 3
) `hc`
JOIN `ibl_team_info` `ti` ON `ti`.`teamid` = `hc`.`winner_tid`
WHERE `hc`.`rn` = 1;

CREATE OR REPLACE SQL SECURITY INVOKER VIEW `vw_franchise_summary` AS
SELECT
    `ti`.`teamid` AS `teamid`,
    COALESCE(`wl`.`totwins`, 0)  AS `totwins`,
    COALESCE(`wl`.`totloss`, 0)  AS `totloss`,
    CASE
        WHEN COALESCE(`wl`.`totwins`, 0) + COALESCE(`wl`.`totloss`, 0) = 0 THEN 0.000
        ELSE ROUND(COALESCE(`wl`.`totwins`, 0) / (COALESCE(`wl`.`totwins`, 0) + COALESCE(`wl`.`totloss`, 0)), 3)
    END AS `winpct`,
    COALESCE(`po`.`playoffs`, 0)    AS `playoffs`,
    COALESCE(`tc`.`div_titles`, 0)  AS `div_titles`,
    COALESCE(`tc`.`conf_titles`, 0) AS `conf_titles`,
    COALESCE(`tc`.`ibl_titles`, 0)  AS `ibl_titles`,
    COALESCE(`tc`.`heat_titles`, 0) AS `heat_titles`
FROM `ibl_team_info` `ti`
LEFT JOIN (
    SELECT
        `ibl_team_win_loss`.`currentname` AS `currentname`,
        SUM(`ibl_team_win_loss`.`wins`)   AS `totwins`,
        SUM(`ibl_team_win_loss`.`losses`) AS `totloss`
    FROM `ibl_team_win_loss`
    GROUP BY `ibl_team_win_loss`.`currentname`
) `wl` ON `wl`.`currentname` = `ti`.`team_name`
LEFT JOIN (
    SELECT
        `po_inner`.`team_name` AS `team_name`,
        COUNT(DISTINCT `po_inner`.`year`) AS `playoffs`
    FROM (
        SELECT `vw_playoff_series_results`.`winner` AS `team_name`, `vw_playoff_series_results`.`year` AS `year`
        FROM `vw_playoff_series_results`
        WHERE `vw_playoff_series_results`.`round` = 1
        UNION
        SELECT `vw_playoff_series_results`.`loser` AS `team_name`, `vw_playoff_series_results`.`year` AS `year`
        FROM `vw_playoff_series_results`
        WHERE `vw_playoff_series_results`.`round` = 1
    ) `po_inner`
    GROUP BY `po_inner`.`team_name`
) `po` ON `po`.`team_name` = `ti`.`team_name`
LEFT JOIN (
    SELECT
        `vw_team_awards`.`name` AS `name`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%Division%'      THEN 1 ELSE 0 END) AS `div_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%Conference%'    THEN 1 ELSE 0 END) AS `conf_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%IBL Champions%' THEN 1 ELSE 0 END) AS `ibl_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%HEAT%'          THEN 1 ELSE 0 END) AS `heat_titles`
    FROM `vw_team_awards`
    GROUP BY `vw_team_awards`.`name`
) `tc` ON `tc`.`name` = `ti`.`team_name`
WHERE `ti`.`teamid` BETWEEN 1 AND 30;
