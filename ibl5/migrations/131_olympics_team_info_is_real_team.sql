ALTER TABLE `ibl_olympics_team_info`
  ADD COLUMN IF NOT EXISTS `is_real_team` TINYINT(1) NOT NULL DEFAULT 0
  AFTER `chart`;

UPDATE `ibl_olympics_team_info`
SET `is_real_team` = 1
WHERE `teamid` BETWEEN 1 AND 8;
