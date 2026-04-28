-- Migration 124: Create ibl_team_season_records materialized table.
--
-- Replaces the per-call CTE in TeamRepository::buildWinLossQuery /
-- buildHeatWinLossQuery with an indexed lookup. Refreshed by
-- RefreshTeamSeasonRecordsStep on every IBL pipeline run. See ADR-0015.

CREATE TABLE IF NOT EXISTS `ibl_team_season_records` (
    `team_id` INT NOT NULL,
    `year` SMALLINT NOT NULL,
    `game_type` TINYINT NOT NULL COMMENT '1=regular, 3=HEAT',
    `currentname` VARCHAR(35) NOT NULL,
    `namethatyear` VARCHAR(35) NOT NULL,
    `wins` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    `losses` SMALLINT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`team_id`, `year`, `game_type`),
    INDEX `idx_game_type_currentname` (`game_type`, `currentname`),
    INDEX `idx_year` (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed game_type=1 (regular season).
-- Year boundary: MONTH(game_date) >= 10 ? YEAR+1 : YEAR (matches the
-- ibl_team_win_loss view from migration 121).
INSERT IGNORE INTO `ibl_team_season_records`
    (`team_id`, `year`, `game_type`, `currentname`, `namethatyear`, `wins`, `losses`)
WITH unique_games AS (
    SELECT
        game_date, visitor_teamid, home_teamid, game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS visitor_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 1
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
)
SELECT
    tg.team_id,
    CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1
         ELSE YEAR(tg.game_date) END AS `year`,
    1 AS game_type,
    ti.team_name AS currentname,
    COALESCE(fs.team_name, ti.team_name) AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED) AS losses
FROM team_games tg
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (
        CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1
             ELSE YEAR(tg.game_date) END
    )
GROUP BY
    tg.team_id,
    CASE WHEN MONTH(tg.game_date) >= 10 THEN YEAR(tg.game_date) + 1 ELSE YEAR(tg.game_date) END,
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name);

-- Seed game_type=3 (HEAT). Year is YEAR(game_date), franchise lookup uses
-- year+1 (matches ibl_heat_win_loss view).
INSERT IGNORE INTO `ibl_team_season_records`
    (`team_id`, `year`, `game_type`, `currentname`, `namethatyear`, `wins`, `losses`)
WITH unique_games AS (
    SELECT
        game_date, visitor_teamid, home_teamid, game_of_that_day,
        (visitor_q1_points + visitor_q2_points + visitor_q3_points + visitor_q4_points
         + COALESCE(visitor_ot_points, 0)) AS visitor_total,
        (home_q1_points + home_q2_points + home_q3_points + home_q4_points
         + COALESCE(home_ot_points, 0)) AS home_total
    FROM ibl_box_scores_teams
    WHERE game_type = 3
      AND YEAR(game_date) < 9000
    GROUP BY game_date, visitor_teamid, home_teamid, game_of_that_day
),
team_games AS (
    SELECT visitor_teamid AS team_id, game_date,
           IF(visitor_total > home_total, 1, 0) AS win,
           IF(visitor_total < home_total, 1, 0) AS loss
    FROM unique_games
    UNION ALL
    SELECT home_teamid AS team_id, game_date,
           IF(home_total > visitor_total, 1, 0) AS win,
           IF(home_total < visitor_total, 1, 0) AS loss
    FROM unique_games
)
SELECT
    tg.team_id,
    YEAR(tg.game_date) AS `year`,
    3 AS game_type,
    ti.team_name AS currentname,
    COALESCE(fs.team_name, ti.team_name) AS namethatyear,
    CAST(SUM(tg.win)  AS UNSIGNED) AS wins,
    CAST(SUM(tg.loss) AS UNSIGNED) AS losses
FROM team_games tg
JOIN ibl_team_info ti ON ti.teamid = tg.team_id
LEFT JOIN ibl_franchise_seasons fs
    ON fs.franchise_id = tg.team_id
    AND fs.season_ending_year = (YEAR(tg.game_date) + 1)
GROUP BY
    tg.team_id,
    YEAR(tg.game_date),
    ti.team_name,
    COALESCE(fs.team_name, ti.team_name);
