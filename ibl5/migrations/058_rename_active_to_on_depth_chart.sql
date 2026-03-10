-- Migration 058: Rename `active` column to `on_depth_chart` on ibl_plr
-- The `active` column means "on a depth chart" (1=yes), NOT "active/retired status".
-- Renaming eliminates this confusion.

-- Rename the column
ALTER TABLE `ibl_plr` CHANGE COLUMN IF EXISTS `active` `on_depth_chart` tinyint(1) DEFAULT NULL COMMENT 'On depth chart (1=yes)';

-- Rename indexes to match (drop old, add new)
DROP INDEX IF EXISTS `idx_active` ON `ibl_plr`;
DROP INDEX IF EXISTS `idx_tid_active` ON `ibl_plr`;
DROP INDEX IF EXISTS `idx_tid_pos_active` ON `ibl_plr`;

ALTER TABLE `ibl_plr`
    ADD INDEX IF NOT EXISTS `idx_on_depth_chart` (`on_depth_chart`),
    ADD INDEX IF NOT EXISTS `idx_tid_on_depth_chart` (`tid`, `on_depth_chart`),
    ADD INDEX IF NOT EXISTS `idx_tid_pos_on_depth_chart` (`tid`, `pos`, `on_depth_chart`);

-- Recreate the view with the renamed column
CREATE OR REPLACE VIEW `vw_player_current` AS
SELECT
    `p`.`uuid` AS `player_uuid`,
    `p`.`pid` AS `pid`,
    `p`.`name` AS `name`,
    `p`.`nickname` AS `nickname`,
    `p`.`age` AS `age`,
    `p`.`pos` AS `position`,
    `p`.`htft` AS `htft`,
    `p`.`htin` AS `htin`,
    `p`.`on_depth_chart` AS `on_depth_chart`,
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
        WHEN 1 THEN `p`.`cy1`
        WHEN 2 THEN `p`.`cy2`
        WHEN 3 THEN `p`.`cy3`
        WHEN 4 THEN `p`.`cy4`
        WHEN 5 THEN `p`.`cy5`
        WHEN 6 THEN `p`.`cy6`
        ELSE 0
    END AS `current_salary`,
    `p`.`cy1` AS `year1_salary`,
    `p`.`cy2` AS `year2_salary`,
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
    `p`.`stats_to` AS `turnovers`,
    `p`.`stats_blk` AS `blocks`,
    `p`.`stats_pf` AS `personal_fouls`,
    ROUND(`p`.`stats_fgm` / NULLIF(`p`.`stats_fga`, 0), 3) AS `fg_percentage`,
    ROUND(`p`.`stats_ftm` / NULLIF(`p`.`stats_fta`, 0), 3) AS `ft_percentage`,
    ROUND(`p`.`stats_3gm` / NULLIF(`p`.`stats_3ga`, 0), 3) AS `three_pt_percentage`,
    ROUND((`p`.`stats_fgm` * 2 + `p`.`stats_3gm` + `p`.`stats_ftm`) / NULLIF(`p`.`stats_gm`, 0), 1) AS `points_per_game`,
    `p`.`created_at` AS `created_at`,
    `p`.`updated_at` AS `updated_at`
FROM `ibl_plr` `p`
LEFT JOIN `ibl_team_info` `t` ON `p`.`tid` = `t`.`teamid`;
