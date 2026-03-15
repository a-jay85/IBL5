-- Merge the engine column (active, renamed to dc_canPlayInGame by 058) and UI column (dc_active)
-- into a single dc_canPlayInGame column. The UI column is authoritative (GM-set values).

-- ibl_plr: drop engine column (058 renamed active → dc_canPlayInGame), rename UI column
ALTER TABLE ibl_plr DROP COLUMN IF EXISTS `dc_canPlayInGame`;
ALTER TABLE ibl_plr
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

-- Recreate indexes from migration 058 (they pointed at the dropped engine column)
DROP INDEX IF EXISTS `idx_dc_canPlayInGame` ON `ibl_plr`;
DROP INDEX IF EXISTS `idx_tid_dc_canPlayInGame` ON `ibl_plr`;
DROP INDEX IF EXISTS `idx_tid_pos_dc_canPlayInGame` ON `ibl_plr`;

ALTER TABLE `ibl_plr`
    ADD INDEX IF NOT EXISTS `idx_dc_canPlayInGame` (`dc_canPlayInGame`),
    ADD INDEX IF NOT EXISTS `idx_tid_dc_canPlayInGame` (`tid`, `dc_canPlayInGame`),
    ADD INDEX IF NOT EXISTS `idx_tid_pos_dc_canPlayInGame` (`tid`, `pos`, `dc_canPlayInGame`);

-- Recreate the view from migration 058 (now points at merged column)
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
    `p`.`dc_canPlayInGame` AS `dc_canPlayInGame`,
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

-- ibl_plr_chunk: dropped by migration 035, skip

-- ibl_olympics_plr: drop engine column (still named `active`, 058 didn't touch it), rename UI column
ALTER TABLE ibl_olympics_plr DROP COLUMN IF EXISTS `active`;
ALTER TABLE ibl_olympics_plr
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(3) UNSIGNED DEFAULT 1 COMMENT 'Can play in game (1=yes)';

-- Saved depth chart tables: only have the UI column, just rename
ALTER TABLE ibl_saved_depth_chart_players
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

ALTER TABLE ibl_olympics_saved_depth_chart_players
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';
