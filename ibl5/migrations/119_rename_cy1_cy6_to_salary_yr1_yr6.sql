-- Migration 119: Tier 4 — rename cy1-cy6 → salary_yr1-salary_yr6.
-- Column types/defaults preserved exactly from SHOW CREATE TABLE.
-- CHECK constraints must be dropped before CHANGE COLUMN, then recreated.
-- Views recreated at end.

-- Step 1: Drop CHECK constraints on ibl_plr (cy1-cy6 only, not cy/cyt)
ALTER TABLE `ibl_plr`
  DROP CONSTRAINT `chk_plr_cy1`,
  DROP CONSTRAINT `chk_plr_cy2`,
  DROP CONSTRAINT `chk_plr_cy3`,
  DROP CONSTRAINT `chk_plr_cy4`,
  DROP CONSTRAINT `chk_plr_cy5`,
  DROP CONSTRAINT `chk_plr_cy6`;

-- Step 2: Drop CHECK constraints on ibl_olympics_plr (cy1-cy6 only)
ALTER TABLE `ibl_olympics_plr`
  DROP CONSTRAINT `chk_olym_plr_cy1`,
  DROP CONSTRAINT `chk_olym_plr_cy2`,
  DROP CONSTRAINT `chk_olym_plr_cy3`,
  DROP CONSTRAINT `chk_olym_plr_cy4`,
  DROP CONSTRAINT `chk_olym_plr_cy5`,
  DROP CONSTRAINT `chk_olym_plr_cy6`;

-- Step 3: Rename columns

-- ibl_plr (smallint(6) DEFAULT 0, nullable)
ALTER TABLE `ibl_plr`
  CHANGE COLUMN `cy1` `salary_yr1` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 1 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy2` `salary_yr2` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 2 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy3` `salary_yr3` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 3 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy4` `salary_yr4` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 4 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy5` `salary_yr5` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 5 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy6` `salary_yr6` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 6 (thousands, negative=cash from other team)';

-- ibl_plr_snapshots (smallint(6) DEFAULT 0, nullable)
ALTER TABLE `ibl_plr_snapshots`
  CHANGE COLUMN `cy1` `salary_yr1` smallint(6) DEFAULT 0,
  CHANGE COLUMN `cy2` `salary_yr2` smallint(6) DEFAULT 0,
  CHANGE COLUMN `cy3` `salary_yr3` smallint(6) DEFAULT 0,
  CHANGE COLUMN `cy4` `salary_yr4` smallint(6) DEFAULT 0,
  CHANGE COLUMN `cy5` `salary_yr5` smallint(6) DEFAULT 0,
  CHANGE COLUMN `cy6` `salary_yr6` smallint(6) DEFAULT 0;

-- ibl_olympics_plr (smallint(6) DEFAULT 0, nullable)
ALTER TABLE `ibl_olympics_plr`
  CHANGE COLUMN `cy1` `salary_yr1` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 1 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy2` `salary_yr2` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 2 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy3` `salary_yr3` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 3 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy4` `salary_yr4` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 4 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy5` `salary_yr5` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 5 (thousands, negative=cash from other team)',
  CHANGE COLUMN `cy6` `salary_yr6` smallint(6) DEFAULT 0 COMMENT 'Salary for contract year 6 (thousands, negative=cash from other team)';

-- ibl_cash_considerations (smallint(6) NOT NULL DEFAULT 0)
ALTER TABLE `ibl_cash_considerations`
  CHANGE COLUMN `cy1` `salary_yr1` smallint(6) NOT NULL DEFAULT 0 COMMENT 'Salary year 1 (thousands, negative=incoming)',
  CHANGE COLUMN `cy2` `salary_yr2` smallint(6) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy3` `salary_yr3` smallint(6) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy4` `salary_yr4` smallint(6) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy5` `salary_yr5` smallint(6) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy6` `salary_yr6` smallint(6) NOT NULL DEFAULT 0;

-- ibl_trade_cash (int(11) DEFAULT NULL, nullable)
ALTER TABLE `ibl_trade_cash`
  CHANGE COLUMN `cy1` `salary_yr1` int(11) DEFAULT NULL COMMENT 'Cash amount year 1 (thousands)',
  CHANGE COLUMN `cy2` `salary_yr2` int(11) DEFAULT NULL COMMENT 'Cash amount year 2 (thousands)',
  CHANGE COLUMN `cy3` `salary_yr3` int(11) DEFAULT NULL COMMENT 'Cash amount year 3 (thousands)',
  CHANGE COLUMN `cy4` `salary_yr4` int(11) DEFAULT NULL COMMENT 'Cash amount year 4 (thousands)',
  CHANGE COLUMN `cy5` `salary_yr5` int(11) DEFAULT NULL COMMENT 'Cash amount year 5 (thousands)',
  CHANGE COLUMN `cy6` `salary_yr6` int(11) DEFAULT NULL COMMENT 'Cash amount year 6 (thousands)';

-- ibl_hist (int(11) NOT NULL DEFAULT 0)
ALTER TABLE `ibl_hist`
  CHANGE COLUMN `cy1` `salary_yr1` int(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy2` `salary_yr2` int(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy3` `salary_yr3` int(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy4` `salary_yr4` int(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy5` `salary_yr5` int(11) NOT NULL DEFAULT 0,
  CHANGE COLUMN `cy6` `salary_yr6` int(11) NOT NULL DEFAULT 0;

-- Step 4: Recreate CHECK constraints with new column names
ALTER TABLE `ibl_plr`
  ADD CONSTRAINT `chk_plr_salary_yr1` CHECK (`salary_yr1` >= -7000 AND `salary_yr1` <= 7000),
  ADD CONSTRAINT `chk_plr_salary_yr2` CHECK (`salary_yr2` >= -7000 AND `salary_yr2` <= 7000),
  ADD CONSTRAINT `chk_plr_salary_yr3` CHECK (`salary_yr3` >= -7000 AND `salary_yr3` <= 7000),
  ADD CONSTRAINT `chk_plr_salary_yr4` CHECK (`salary_yr4` >= -7000 AND `salary_yr4` <= 7000),
  ADD CONSTRAINT `chk_plr_salary_yr5` CHECK (`salary_yr5` >= -7000 AND `salary_yr5` <= 7000),
  ADD CONSTRAINT `chk_plr_salary_yr6` CHECK (`salary_yr6` >= -7000 AND `salary_yr6` <= 7000);

ALTER TABLE `ibl_olympics_plr`
  ADD CONSTRAINT `chk_olym_plr_salary_yr1` CHECK (`salary_yr1` >= -7000 AND `salary_yr1` <= 7000),
  ADD CONSTRAINT `chk_olym_plr_salary_yr2` CHECK (`salary_yr2` >= -7000 AND `salary_yr2` <= 7000),
  ADD CONSTRAINT `chk_olym_plr_salary_yr3` CHECK (`salary_yr3` >= -7000 AND `salary_yr3` <= 7000),
  ADD CONSTRAINT `chk_olym_plr_salary_yr4` CHECK (`salary_yr4` >= -7000 AND `salary_yr4` <= 7000),
  ADD CONSTRAINT `chk_olym_plr_salary_yr5` CHECK (`salary_yr5` >= -7000 AND `salary_yr5` <= 7000),
  ADD CONSTRAINT `chk_olym_plr_salary_yr6` CHECK (`salary_yr6` >= -7000 AND `salary_yr6` <= 7000);

-- Step 5: Recreate views

DROP VIEW IF EXISTS `vw_current_salary`;
CREATE VIEW `vw_current_salary` AS
SELECT
    `p`.`pid` AS `pid`,
    `p`.`name` AS `name`,
    `p`.`teamid` AS `teamid`,
    `t`.`team_name` AS `teamname`,
    `p`.`pos` AS `pos`,
    `p`.`cy` AS `cy`,
    `p`.`cyt` AS `cyt`,
    `p`.`salary_yr1` AS `cy1`,
    `p`.`salary_yr2` AS `cy2`,
    `p`.`salary_yr3` AS `cy3`,
    `p`.`salary_yr4` AS `cy4`,
    `p`.`salary_yr5` AS `cy5`,
    `p`.`salary_yr6` AS `cy6`,
    CASE `p`.`cy`
        WHEN 1 THEN `p`.`salary_yr1`
        WHEN 2 THEN `p`.`salary_yr2`
        WHEN 3 THEN `p`.`salary_yr3`
        WHEN 4 THEN `p`.`salary_yr4`
        WHEN 5 THEN `p`.`salary_yr5`
        WHEN 6 THEN `p`.`salary_yr6`
        ELSE 0
    END AS `current_salary`,
    CASE `p`.`cy`
        WHEN 0 THEN `p`.`salary_yr1`
        WHEN 1 THEN `p`.`salary_yr2`
        WHEN 2 THEN `p`.`salary_yr3`
        WHEN 3 THEN `p`.`salary_yr4`
        WHEN 4 THEN `p`.`salary_yr5`
        WHEN 5 THEN `p`.`salary_yr6`
        ELSE 0
    END AS `next_year_salary`
FROM `ibl_plr` `p`
LEFT JOIN `ibl_team_info` `t` ON `p`.`teamid` = `t`.`teamid`
WHERE `p`.`retired` = 0
UNION ALL
SELECT
    -`cc`.`id` AS `pid`,
    `cc`.`label` AS `name`,
    `cc`.`teamid` AS `teamid`,
    `t`.`team_name` AS `teamname`,
    '' AS `pos`,
    `cc`.`cy` AS `cy`,
    `cc`.`cyt` AS `cyt`,
    `cc`.`salary_yr1` AS `cy1`,
    `cc`.`salary_yr2` AS `cy2`,
    `cc`.`salary_yr3` AS `cy3`,
    `cc`.`salary_yr4` AS `cy4`,
    `cc`.`salary_yr5` AS `cy5`,
    `cc`.`salary_yr6` AS `cy6`,
    CASE `cc`.`cy`
        WHEN 1 THEN `cc`.`salary_yr1`
        WHEN 2 THEN `cc`.`salary_yr2`
        WHEN 3 THEN `cc`.`salary_yr3`
        WHEN 4 THEN `cc`.`salary_yr4`
        WHEN 5 THEN `cc`.`salary_yr5`
        WHEN 6 THEN `cc`.`salary_yr6`
        ELSE 0
    END AS `current_salary`,
    CASE `cc`.`cy`
        WHEN 0 THEN `cc`.`salary_yr1`
        WHEN 1 THEN `cc`.`salary_yr2`
        WHEN 2 THEN `cc`.`salary_yr3`
        WHEN 3 THEN `cc`.`salary_yr4`
        WHEN 4 THEN `cc`.`salary_yr5`
        WHEN 5 THEN `cc`.`salary_yr6`
        ELSE 0
    END AS `next_year_salary`
FROM `ibl_cash_considerations` `cc`
LEFT JOIN `ibl_team_info` `t` ON `cc`.`teamid` = `t`.`teamid`;

DROP VIEW IF EXISTS `vw_player_current`;
CREATE VIEW `vw_player_current` AS
SELECT
    `p`.`uuid` AS `player_uuid`,
    `p`.`pid` AS `pid`,
    `p`.`name` AS `name`,
    `p`.`nickname` AS `nickname`,
    `p`.`age` AS `age`,
    `p`.`pos` AS `position`,
    `p`.`htft` AS `htft`,
    `p`.`htin` AS `htin`,
    `p`.`dc_can_play_in_game` AS `dc_can_play_in_game`,
    `p`.`retired` AS `retired`,
    `p`.`exp` AS `experience`,
    `p`.`bird` AS `bird_rights`,
    `t`.`uuid` AS `team_uuid`,
    `t`.`teamid` AS `teamid`,
    `t`.`team_city` AS `team_city`,
    `t`.`team_name` AS `team_name`,
    `t`.`owner_name` AS `owner_name`,
    CONCAT(`t`.`team_city`, ' ', `t`.`team_name`) AS `full_team_name`,
    `p`.`cy` AS `contract_year`,
    CASE `p`.`cy`
        WHEN 1 THEN `p`.`salary_yr1`
        WHEN 2 THEN `p`.`salary_yr2`
        WHEN 3 THEN `p`.`salary_yr3`
        WHEN 4 THEN `p`.`salary_yr4`
        WHEN 5 THEN `p`.`salary_yr5`
        WHEN 6 THEN `p`.`salary_yr6`
        ELSE 0
    END AS `current_salary`,
    `p`.`salary_yr1` AS `year1_salary`,
    `p`.`salary_yr2` AS `year2_salary`,
    `p`.`salary_yr3` AS `year3_salary`,
    `p`.`salary_yr4` AS `year4_salary`,
    `p`.`salary_yr5` AS `year5_salary`,
    `p`.`salary_yr6` AS `year6_salary`,
    `p`.`stats_gm` AS `games_played`,
    `p`.`stats_min` AS `minutes_played`,
    `p`.`stats_fgm` AS `field_goals_made`,
    `p`.`stats_fga` AS `field_goals_attempted`,
    `p`.`stats_ftm` AS `free_throws_made`,
    `p`.`stats_fta` AS `free_throws_attempted`,
    `p`.`stats_3gm` AS `three_pointers_made`,
    `p`.`stats_3ga` AS `three_pointers_attempted`,
    `p`.`stats_orb` AS `offensive_rebounds`,
    `p`.`stats_drb` AS `defensive_rebounds`,
    `p`.`stats_ast` AS `assists`,
    `p`.`stats_stl` AS `steals`,
    `p`.`stats_tvr` AS `turnovers`,
    `p`.`stats_blk` AS `blocks`,
    `p`.`stats_pf` AS `personal_fouls`,
    ROUND(`p`.`stats_fgm` / NULLIF(`p`.`stats_fga`, 0), 3) AS `fg_percentage`,
    ROUND(`p`.`stats_ftm` / NULLIF(`p`.`stats_fta`, 0), 3) AS `ft_percentage`,
    ROUND(`p`.`stats_3gm` / NULLIF(`p`.`stats_3ga`, 0), 3) AS `three_pt_percentage`,
    ROUND((`p`.`stats_fgm` * 2 + `p`.`stats_3gm` + `p`.`stats_ftm`) / NULLIF(`p`.`stats_gm`, 0), 1) AS `points_per_game`,
    `p`.`created_at` AS `created_at`,
    `p`.`updated_at` AS `updated_at`
FROM `ibl_plr` `p`
LEFT JOIN `ibl_team_info` `t` ON `p`.`teamid` = `t`.`teamid`;
