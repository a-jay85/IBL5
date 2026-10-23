-- Migration 199: NULL-quarter games count toward W/L via a side-aware calc_points fallback.
--
-- The restored 2004 Suns@Heat game (ibl_box_scores_teams ids 104577 / 104578) carries NULL
-- quarter and OT columns by design, so every quarter-sum expression evaluated to NULL and the
-- game credited neither team. calc_points is a per-row generated column (that row's own
-- team), so the fallback must read the visitor row (lowest id of the pair) for the visitor
-- total and the home row (highest id) for the home total. A single COALESCE on the collapsed
-- GROUP BY row would read one team's points for both sides.
--
-- ibl_team_win_loss: canonical_games and the outer select copied from migration 172;
-- unique_games now picks the two row ids and a new scored_games CTE joins back by PK.
-- vw_heat_champions: body copied from migration 149; only the CASE changes.
-- vw_team_awards: body copied verbatim from migration 174; only the HEAT branch CASE changes.
-- Same expressions live in RefreshTeamSeasonRecordsStep, FranchiseHistoryRepository,
-- HeadToHeadRecordsRepository, TeamRepository and SeasonArchiveRepository.

CREATE OR REPLACE VIEW `ibl_team_win_loss` AS
with canonical_games as (
  select `ibl_box_scores_teams`.`game_date` AS `game_date`,
         `ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,
         `ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,
         min(`ibl_box_scores_teams`.`game_of_that_day`) AS `game_of_that_day`
  from `ibl_box_scores_teams`
  where `ibl_box_scores_teams`.`game_type` = 1
  group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`
),
unique_games as (
  select `b`.`game_date` AS `game_date`,
         `b`.`visitor_teamid` AS `visitor_teamid`,
         `b`.`home_teamid` AS `home_teamid`,
         min(`b`.`id`) AS `visitor_row_id`,
         max(`b`.`id`) AS `home_row_id`
  from `ibl_box_scores_teams` `b`
  join `canonical_games` `c`
    on `c`.`game_date` = `b`.`game_date`
   and `c`.`visitor_teamid` = `b`.`visitor_teamid`
   and `c`.`home_teamid` = `b`.`home_teamid`
   and `c`.`game_of_that_day` <=> `b`.`game_of_that_day`
  where `b`.`game_type` = 1
  group by `b`.`game_date`,`b`.`visitor_teamid`,`b`.`home_teamid`
),
scored_games as (
  select `u`.`game_date` AS `game_date`,
         `u`.`visitor_teamid` AS `visitor_teamid`,
         `u`.`home_teamid` AS `home_teamid`,
         coalesce(`v`.`visitor_q1_points` + `v`.`visitor_q2_points` + `v`.`visitor_q3_points` + `v`.`visitor_q4_points` + coalesce(`v`.`visitor_ot_points`,0), `v`.`calc_points`) AS `visitor_total`,
         coalesce(`v`.`home_q1_points` + `v`.`home_q2_points` + `v`.`home_q3_points` + `v`.`home_q4_points` + coalesce(`v`.`home_ot_points`,0), `h`.`calc_points`) AS `home_total`
  from `unique_games` `u`
  join `ibl_box_scores_teams` `v` on `v`.`id` = `u`.`visitor_row_id`
  join `ibl_box_scores_teams` `h` on `h`.`id` = `u`.`home_row_id`
),
team_games as (select `scored_games`.`visitor_teamid` AS `teamid`,`scored_games`.`game_date` AS `game_date`,if(`scored_games`.`visitor_total` > `scored_games`.`home_total`,1,0) AS `win`,if(`scored_games`.`visitor_total` < `scored_games`.`home_total`,1,0) AS `loss` from `scored_games` union all select `scored_games`.`home_teamid` AS `teamid`,`scored_games`.`game_date` AS `game_date`,if(`scored_games`.`home_total` > `scored_games`.`visitor_total`,1,0) AS `win`,if(`scored_games`.`home_total` < `scored_games`.`visitor_total`,1,0) AS `loss` from `scored_games`)
select case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end)) group by `tg`.`teamid`,case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end,`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);

CREATE OR REPLACE VIEW `vw_heat_champions` AS
SELECT
    hc.year,
    ti.team_name AS name,
    'IBL HEAT Champions' AS award
FROM (
    SELECT
        YEAR(bst.game_date) AS year,
        CASE
            WHEN COALESCE(bst.home_q1_points + bst.home_q2_points + bst.home_q3_points + bst.home_q4_points
                          + COALESCE(bst.home_ot_points, 0),
                          FIRST_VALUE(bst.calc_points) OVER (
                              PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day
                              ORDER BY bst.id DESC))
               > COALESCE(bst.visitor_q1_points + bst.visitor_q2_points + bst.visitor_q3_points + bst.visitor_q4_points
                          + COALESCE(bst.visitor_ot_points, 0),
                          FIRST_VALUE(bst.calc_points) OVER (
                              PARTITION BY bst.game_date, bst.visitor_teamid, bst.home_teamid, bst.game_of_that_day
                              ORDER BY bst.id ASC))
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
-- Derived conference champions: the winner of the single round-3 series in each
-- conference, once that series has reached the season's clinch threshold.
--
-- Three deliberate guards, none of which may be "simplified":
--   * `psr.round = 3` is a literal, not MAX(round). Generalizing it would also match
--     round 4 (the Finals) and fabricate a third conference title every season.
--   * NULLIF(CAST(... AS UNSIGNED), 0) fails CLOSED. MariaDB's CAST returns 0 for
--     unparseable text, so a malformed playoff_round3_format would otherwise make
--     `winner_games >= 0` trivially true and award a title mid-series. NULLIF turns
--     that 0 into NULL, so the comparison is NULL and the row is suppressed.
--   * `series_in_conf = 1` (partitioned by year AND conference) suppresses the award
--     for any season whose round-3 data is malformed or duplicated within a conference.
--
-- The LEFT JOIN to ibl_franchise_seasons resolves the franchise's name AS OF that
-- season, so a renamed franchise still matches ibl_league_config.team_name; COALESCE
-- falls back to the current name when no historical row exists.
SELECT `ranked`.`year` AS `year`,
       `ranked`.`name` AS `name`,
       `ranked`.`award` AS `award`,
       0 AS `id`
FROM (
    SELECT
        `psr`.`year` AS `year`,
        `psr`.`winner` AS `name`,
        CONCAT(`lc`.`conference`, ' Conference Champions') AS `award`,
        COUNT(*) OVER (PARTITION BY `psr`.`year`, `lc`.`conference`) AS `series_in_conf`
    FROM `vw_playoff_series_results` `psr`
    LEFT JOIN `ibl_franchise_seasons` `fs`
        ON `fs`.`franchise_id` = `psr`.`winner_tid` AND `fs`.`season_ending_year` = `psr`.`year`
    JOIN `ibl_league_config` `lc`
        ON `lc`.`season_ending_year` = `psr`.`year`
       AND `lc`.`team_name` = COALESCE(`fs`.`team_name`, `psr`.`winner`)
    WHERE `psr`.`round` = 3
      AND `psr`.`winner_games` >= NULLIF(CAST(SUBSTRING_INDEX(`lc`.`playoff_round3_format`, ' ', 1) AS UNSIGNED), 0)
) `ranked`
WHERE `ranked`.`series_in_conf` = 1
UNION ALL
SELECT `hc`.`year` AS `year`,
       `ti`.`team_name` AS `name`,
       'IBL HEAT Champions' AS `award`,
       0 AS `id`
FROM (
    SELECT YEAR(`bst`.`game_date`) AS `year`,
           CASE WHEN COALESCE(`bst`.`home_q1_points` + `bst`.`home_q2_points` + `bst`.`home_q3_points` + `bst`.`home_q4_points`
                             + COALESCE(`bst`.`home_ot_points`, 0),
                             FIRST_VALUE(`bst`.`calc_points`) OVER (
                                 PARTITION BY `bst`.`game_date`, `bst`.`visitor_teamid`, `bst`.`home_teamid`, `bst`.`game_of_that_day`
                                 ORDER BY `bst`.`id` DESC))
                   > COALESCE(`bst`.`visitor_q1_points` + `bst`.`visitor_q2_points` + `bst`.`visitor_q3_points` + `bst`.`visitor_q4_points`
                             + COALESCE(`bst`.`visitor_ot_points`, 0),
                             FIRST_VALUE(`bst`.`calc_points`) OVER (
                                 PARTITION BY `bst`.`game_date`, `bst`.`visitor_teamid`, `bst`.`home_teamid`, `bst`.`game_of_that_day`
                                 ORDER BY `bst`.`id` ASC))
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
