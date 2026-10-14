-- Speed up vw_franchise_summary (FranchiseHistory page, about 1 s in prod).
--
-- The wl arm used to re-aggregate every regular-season box score through the
-- ibl_team_win_loss view (97 percent of the query cost). It now sums the
-- materialized ibl_team_season_records table (ADR-0015), refreshed each sim by
-- RefreshTeamSeasonRecordsStep. Staleness window: one sim cycle, same as ADR-0015.
--
-- wl joins on team_id = ti.teamid, never on the stored currentname. currentname is
-- stamped at refresh time, so a name join would zero a renamed team until the next sim.
--
-- Everything except the wl arm is copied verbatim from migration 174 section C.
-- Rollback (lossless, view only): re-run section C of
-- ibl5/migrations/174_derive_conference_champions_from_playoffs.sql.

-- Statement 1: fail-closed guard (ERROR 1242 idiom from migration 151).
-- Aborts when regular-season box scores exist but the materialized table holds no
-- regular-season rows, because the new view would then show 0-0 for every team.
-- Returns 0 on an empty DB (CI applies migrations before any seed) and on a refreshed DB.
-- GUARD-BEGIN
SELECT IF(
  NOT EXISTS (SELECT 1 FROM `ibl_team_season_records` WHERE `game_type` = 1)
  AND EXISTS (SELECT 1 FROM `ibl_box_scores_teams` WHERE `game_type` = 1),
  (SELECT 1 UNION SELECT 2),
  0
) AS `franchise_summary_guard`;
-- GUARD-END

-- Statement 2: the view. Only the wl arm differs from migration 174.
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
        `tsr`.`team_id`     AS `team_id`,
        SUM(`tsr`.`wins`)   AS `totwins`,
        SUM(`tsr`.`losses`) AS `totloss`
    FROM `ibl_team_season_records` `tsr`
    WHERE `tsr`.`game_type` = 1
    GROUP BY `tsr`.`team_id`
) `wl` ON `wl`.`team_id` = `ti`.`teamid`
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
        SUM(CASE WHEN `vw_team_awards`.`award` IN ('Eastern Conference Champions', 'Western Conference Champions') THEN 1 ELSE 0 END) AS `conf_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%IBL Champions%' THEN 1 ELSE 0 END) AS `ibl_titles`,
        SUM(CASE WHEN `vw_team_awards`.`award` LIKE '%HEAT%'          THEN 1 ELSE 0 END) AS `heat_titles`
    FROM `vw_team_awards`
    GROUP BY `vw_team_awards`.`name`
) `tc` ON `tc`.`name` = `ti`.`team_name`
WHERE `ti`.`teamid` BETWEEN 1 AND 30;
