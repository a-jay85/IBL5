-- Migration 120: Tier 5 — miscellaneous snake_case cleanup (ADR-0010).
-- Sweeps remaining non-snake_case columns across small, low-traffic tables:
-- trade cash, awards, FA offers, voting, sim dates, and legacy nuke_* tables.
-- Box-scores + schedule are deferred to Tier 6.
-- Column types/defaults preserved exactly from SHOW CREATE TABLE.

-- Group A: ibl_trade_cash — drop FK, rename, recreate FK.
ALTER TABLE `ibl_trade_cash` DROP FOREIGN KEY `fk_trade_cash_offer`;
ALTER TABLE `ibl_trade_cash`
  CHANGE COLUMN `tradeOfferID`  `trade_offer_id` int(11)     NOT NULL                COMMENT 'FK to ibl_trade_offers.id',
  CHANGE COLUMN `sendingTeam`   `sending_team`   varchar(16) NOT NULL DEFAULT ''     COMMENT 'Team sending cash',
  CHANGE COLUMN `receivingTeam` `receiving_team` varchar(16) NOT NULL DEFAULT ''     COMMENT 'Team receiving cash';
ALTER TABLE `ibl_trade_cash`
  ADD CONSTRAINT `fk_trade_cash_offer`
    FOREIGN KEY (`trade_offer_id`) REFERENCES `ibl_trade_offers` (`id`)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Group B: Awards tables (ibl_awards, ibl_gm_awards, ibl_gm_history,
-- ibl_team_awards, ibl_olympics_win_loss). MariaDB auto-updates index column
-- references; index names (e.g., idx_award, uk_year_award) are left unchanged.
ALTER TABLE `ibl_awards`
  CHANGE COLUMN `Award`    `award`    varchar(128) NOT NULL DEFAULT '' COMMENT 'Award name (e.g., MVP, DPOY)',
  CHANGE COLUMN `table_ID` `table_id` int(11)      NOT NULL AUTO_INCREMENT;

ALTER TABLE `ibl_gm_awards`
  CHANGE COLUMN `Award`    `award`    varchar(128) NOT NULL DEFAULT '' COMMENT 'Award name',
  CHANGE COLUMN `table_ID` `table_id` int(11)      NOT NULL AUTO_INCREMENT;

ALTER TABLE `ibl_gm_history`
  CHANGE COLUMN `Award` `award` varchar(350) NOT NULL COMMENT 'Award name/description';

ALTER TABLE `ibl_team_awards`
  CHANGE COLUMN `Award` `award` varchar(350) NOT NULL                COMMENT 'Award description',
  CHANGE COLUMN `ID`    `id`    int(11)      NOT NULL AUTO_INCREMENT;

ALTER TABLE `ibl_olympics_win_loss`
  CHANGE COLUMN `table_ID` `table_id` int(11) NOT NULL AUTO_INCREMENT;

-- Group C: ibl_fa_offers — MLE/LLE → mle/lle. Stored as int(11) NOT NULL.
ALTER TABLE `ibl_fa_offers`
  CHANGE COLUMN `MLE` `mle` int(11) NOT NULL DEFAULT 0 COMMENT '1=offer uses Mid-Level Exception',
  CHANGE COLUMN `LLE` `lle` int(11) NOT NULL DEFAULT 0 COMMENT '1=offer uses Lower-Level Exception';

-- Group D: ibl_votes_ASG (16 ballot columns).
ALTER TABLE `ibl_votes_ASG`
  CHANGE COLUMN `East_F1` `east_f1` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 1st pick',
  CHANGE COLUMN `East_F2` `east_f2` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 2nd pick',
  CHANGE COLUMN `East_F3` `east_f3` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 3rd pick',
  CHANGE COLUMN `East_F4` `east_f4` varchar(255) DEFAULT NULL COMMENT 'Eastern frontcourt 4th pick',
  CHANGE COLUMN `East_B1` `east_b1` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 1st pick',
  CHANGE COLUMN `East_B2` `east_b2` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 2nd pick',
  CHANGE COLUMN `East_B3` `east_b3` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 3rd pick',
  CHANGE COLUMN `East_B4` `east_b4` varchar(255) DEFAULT NULL COMMENT 'Eastern backcourt 4th pick',
  CHANGE COLUMN `West_F1` `west_f1` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 1st pick',
  CHANGE COLUMN `West_F2` `west_f2` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 2nd pick',
  CHANGE COLUMN `West_F3` `west_f3` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 3rd pick',
  CHANGE COLUMN `West_F4` `west_f4` varchar(255) DEFAULT NULL COMMENT 'Western frontcourt 4th pick',
  CHANGE COLUMN `West_B1` `west_b1` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 1st pick',
  CHANGE COLUMN `West_B2` `west_b2` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 2nd pick',
  CHANGE COLUMN `West_B3` `west_b3` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 3rd pick',
  CHANGE COLUMN `West_B4` `west_b4` varchar(255) DEFAULT NULL COMMENT 'Western backcourt 4th pick';

-- Group E: ibl_votes_EOY (12 ballot columns).
ALTER TABLE `ibl_votes_EOY`
  CHANGE COLUMN `MVP_1` `mvp_1` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 1st place',
  CHANGE COLUMN `MVP_2` `mvp_2` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 2nd place',
  CHANGE COLUMN `MVP_3` `mvp_3` varchar(255) DEFAULT NULL COMMENT 'MVP ballot 3rd place',
  CHANGE COLUMN `Six_1` `six_1` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 1st place',
  CHANGE COLUMN `Six_2` `six_2` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 2nd place',
  CHANGE COLUMN `Six_3` `six_3` varchar(255) DEFAULT NULL COMMENT 'Sixth Man ballot 3rd place',
  CHANGE COLUMN `ROY_1` `roy_1` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 1st place',
  CHANGE COLUMN `ROY_2` `roy_2` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 2nd place',
  CHANGE COLUMN `ROY_3` `roy_3` varchar(255) DEFAULT NULL COMMENT 'Rookie of Year ballot 3rd place',
  CHANGE COLUMN `GM_1`  `gm_1`  varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 1st place',
  CHANGE COLUMN `GM_2`  `gm_2`  varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 2nd place',
  CHANGE COLUMN `GM_3`  `gm_3`  varchar(255) DEFAULT NULL COMMENT 'GM of Year ballot 3rd place';

-- Group F: ibl_sim_dates — Sim is the AUTO_INCREMENT primary key.
ALTER TABLE `ibl_sim_dates`
  CHANGE COLUMN `Sim` `sim` int(10) unsigned NOT NULL AUTO_INCREMENT COMMENT 'Sim sequence number';

-- Group G: nuke_* tables.
ALTER TABLE `nuke_config`
  CHANGE COLUMN `CensorMode`    `censor_mode`    tinyint(1)   NOT NULL DEFAULT 3,
  CHANGE COLUMN `CensorReplace` `censor_replace` varchar(10)  NOT NULL DEFAULT '',
  CHANGE COLUMN `Default_Theme` `default_theme`  varchar(255) NOT NULL DEFAULT '',
  CHANGE COLUMN `Version_Num`   `version_num`    varchar(10)  NOT NULL DEFAULT '';

ALTER TABLE `nuke_stories`
  CHANGE COLUMN `pollID` `poll_id` int(11) NOT NULL DEFAULT 0;

-- Recreate views referencing renamed columns.

DROP VIEW IF EXISTS `vw_team_awards`;
CREATE VIEW `vw_team_awards` AS
SELECT
    `ibl_team_awards`.`year`  AS `year`,
    `ibl_team_awards`.`name`  AS `name`,
    `ibl_team_awards`.`award` AS `award`,
    `ibl_team_awards`.`id`    AS `id`
FROM `ibl_team_awards`
UNION ALL
SELECT
    `ranked`.`year`        AS `year`,
    `ranked`.`name`        AS `name`,
    'IBL Champions'        AS `award`,
    0                      AS `id`
FROM (
    SELECT
        `psr`.`year`   AS `year`,
        `psr`.`winner` AS `name`,
        `psr`.`round`  AS `round`,
        MAX(`psr`.`round`) OVER (PARTITION BY `psr`.`year`) AS `max_round`,
        COUNT(0) OVER (PARTITION BY `psr`.`year`, `psr`.`round`) AS `series_in_round`
    FROM `vw_playoff_series_results` `psr`
) `ranked`
WHERE `ranked`.`round` = `ranked`.`max_round`
  AND `ranked`.`series_in_round` = 1
UNION ALL
SELECT
    `hc`.`year`       AS `year`,
    `ti`.`team_name`  AS `name`,
    'IBL HEAT Champions' AS `award`,
    0                 AS `id`
FROM (
    SELECT
        YEAR(`bst`.`Date`) AS `year`,
        CASE
            WHEN `bst`.`homeQ1points` + `bst`.`homeQ2points` + `bst`.`homeQ3points` + `bst`.`homeQ4points` + COALESCE(`bst`.`homeOTpoints`, 0)
                 > `bst`.`visitorQ1points` + `bst`.`visitorQ2points` + `bst`.`visitorQ3points` + `bst`.`visitorQ4points` + COALESCE(`bst`.`visitorOTpoints`, 0)
            THEN `bst`.`home_teamid`
            ELSE `bst`.`visitor_teamid`
        END AS `winner_tid`,
        ROW_NUMBER() OVER (
            PARTITION BY YEAR(`bst`.`Date`)
            ORDER BY `bst`.`Date` DESC, `bst`.`gameOfThatDay`
        ) AS `rn`
    FROM `ibl_box_scores_teams` `bst`
    WHERE `bst`.`game_type` = 3
) `hc`
JOIN `ibl_team_info` `ti` ON `ti`.`teamid` = `hc`.`winner_tid`
WHERE `hc`.`rn` = 1;

DROP VIEW IF EXISTS `vw_franchise_summary`;
CREATE SQL SECURITY INVOKER VIEW `vw_franchise_summary` AS
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

DROP VIEW IF EXISTS `vw_free_agency_offers`;
CREATE SQL SECURITY INVOKER VIEW `vw_free_agency_offers` AS
SELECT
    `fa`.`primary_key` AS `offer_id`,
    `p`.`uuid`         AS `player_uuid`,
    `p`.`pid`          AS `pid`,
    `p`.`name`         AS `player_name`,
    `p`.`pos`          AS `position`,
    `p`.`age`          AS `age`,
    `t`.`uuid`         AS `team_uuid`,
    `t`.`teamid`       AS `teamid`,
    `t`.`team_city`    AS `team_city`,
    `t`.`team_name`    AS `team_name`,
    CONCAT(`t`.`team_city`, ' ', `t`.`team_name`) AS `full_team_name`,
    `fa`.`offer1` AS `year1_amount`,
    `fa`.`offer2` AS `year2_amount`,
    `fa`.`offer3` AS `year3_amount`,
    `fa`.`offer4` AS `year4_amount`,
    `fa`.`offer5` AS `year5_amount`,
    `fa`.`offer6` AS `year6_amount`,
    `fa`.`offer1` + `fa`.`offer2` + `fa`.`offer3` + `fa`.`offer4` + `fa`.`offer5` + `fa`.`offer6` AS `total_contract_value`,
    `fa`.`modifier`       AS `modifier`,
    `fa`.`random`         AS `random`,
    `fa`.`perceivedvalue` AS `perceived_value`,
    `fa`.`mle`            AS `is_mle`,
    `fa`.`lle`            AS `is_lle`,
    `fa`.`created_at`     AS `created_at`,
    `fa`.`updated_at`     AS `updated_at`
FROM `ibl_fa_offers` `fa`
JOIN `ibl_plr` `p` ON `fa`.`pid` = `p`.`pid`
JOIN `ibl_team_info` `t` ON `fa`.`teamid` = `t`.`teamid`;
