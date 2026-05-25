-- Missing table
CREATE TABLE IF NOT EXISTS `ibl_olympics_plr_snapshots` LIKE `ibl_plr_snapshots`;

-- Missing columns on ibl_olympics_hist
ALTER TABLE `ibl_olympics_hist`
  ADD COLUMN IF NOT EXISTS `talent` int(11) NOT NULL DEFAULT 0 AFTER `salary`,
  ADD COLUMN IF NOT EXISTS `skill` int(11) NOT NULL DEFAULT 0 AFTER `talent`,
  ADD COLUMN IF NOT EXISTS `intangibles` int(11) NOT NULL DEFAULT 0 AFTER `skill`,
  ADD COLUMN IF NOT EXISTS `tsi_sum` int(11) NOT NULL DEFAULT 0 AFTER `intangibles`,
  ADD COLUMN IF NOT EXISTS `clutch` int(11) NOT NULL DEFAULT 0 AFTER `tsi_sum`,
  ADD COLUMN IF NOT EXISTS `consistency` int(11) NOT NULL DEFAULT 0 AFTER `clutch`,
  ADD COLUMN IF NOT EXISTS `age` int(11) NOT NULL DEFAULT 0 AFTER `consistency`,
  ADD COLUMN IF NOT EXISTS `peak` int(11) NOT NULL DEFAULT 0 AFTER `age`,
  ADD COLUMN IF NOT EXISTS `salary_yr1` int(11) NOT NULL DEFAULT 0 AFTER `peak`,
  ADD COLUMN IF NOT EXISTS `salary_yr2` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr1`,
  ADD COLUMN IF NOT EXISTS `salary_yr3` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr2`,
  ADD COLUMN IF NOT EXISTS `salary_yr4` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr3`,
  ADD COLUMN IF NOT EXISTS `salary_yr5` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr4`,
  ADD COLUMN IF NOT EXISTS `salary_yr6` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr5`,
  ADD COLUMN IF NOT EXISTS `phantom_games` int(11) NOT NULL DEFAULT 0 AFTER `salary_yr6`;

-- Column rename on Olympics RCB tables (team_id → teamid)
ALTER TABLE `ibl_olympics_rcb_alltime_records`
  RENAME COLUMN IF EXISTS `team_id` TO `teamid`;
ALTER TABLE `ibl_olympics_rcb_season_records`
  RENAME COLUMN IF EXISTS `team_id` TO `teamid`;

-- Missing indexes
ALTER TABLE `ibl_olympics_box_scores`
  ADD INDEX IF NOT EXISTS `idx_gt_pid_season` (`game_type`, `pid`, `season_year`),
  ADD UNIQUE INDEX IF NOT EXISTS `uq_game_player` (`game_date`, `pid`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `teamid`);

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD INDEX IF NOT EXISTS `idx_name` (`name`),
  ADD INDEX IF NOT EXISTS `idx_date_visitor_home_gotd` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`),
  ADD INDEX IF NOT EXISTS `idx_gt_date_teams` (`game_type`, `game_date`, `visitor_teamid`, `home_teamid`),
  ADD INDEX IF NOT EXISTS `idx_gt_name_season` (`game_type`, `name`, `season_year`),
  ADD UNIQUE INDEX IF NOT EXISTS `uq_game_team` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`, `name`);

ALTER TABLE `ibl_olympics_schedule`
  ADD INDEX IF NOT EXISTS `idx_date_visitor_home` (`game_date`, `visitor_teamid`, `home_teamid`),
  ADD UNIQUE INDEX IF NOT EXISTS `idx_uuid` (`uuid`);

ALTER TABLE `ibl_olympics_plr`
  ADD INDEX IF NOT EXISTS `idx_dc_canPlayInGame` (`dc_can_play_in_game`),
  ADD INDEX IF NOT EXISTS `idx_retired_ordinal` (`retired`, `ordinal`),
  ADD INDEX IF NOT EXISTS `idx_tid_dc_canPlayInGame` (`teamid`, `dc_can_play_in_game`),
  ADD INDEX IF NOT EXISTS `idx_tid_pos_dc_canPlayInGame` (`teamid`, `pos`, `dc_can_play_in_game`);

ALTER TABLE `ibl_olympics_hist`
  ADD INDEX IF NOT EXISTS `idx_name` (`name`);
