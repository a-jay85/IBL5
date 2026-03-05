-- =====================================================================
-- Migration 009: Schema Optimization Audit
-- =====================================================================

-- =====================================================================
-- PART 1: Remove Duplicate Indexes (Section 1.1)
-- =====================================================================

-- ibl_draft: Drop duplicate uuid index
ALTER TABLE `ibl_draft` DROP KEY IF EXISTS `idx_uuid`;

-- ibl_plr: Drop duplicate uuid index
ALTER TABLE `ibl_plr` DROP KEY IF EXISTS `idx_uuid`;

-- ibl_team_info: Drop duplicate uuid index
ALTER TABLE `ibl_team_info` DROP KEY IF EXISTS `idx_uuid`;

-- ibl_heat_stats: Drop redundant UNIQUE KEY on id (already PRIMARY KEY)
ALTER TABLE `ibl_heat_stats` DROP KEY IF EXISTS `id`;

-- ibl_olympics_stats: Drop redundant UNIQUE KEY on id (already PRIMARY KEY)
ALTER TABLE `ibl_olympics_stats` DROP KEY IF EXISTS `id`;

-- ibl_plr_chunk: dropped by migration 035, skip

-- ibl_draft: Convert draft_id from UNIQUE KEY to PRIMARY KEY
-- (AUTO_INCREMENT column should be the PK, not just a unique key)
ALTER TABLE `ibl_draft` DROP KEY IF EXISTS `draft_id`;
-- Only add PK if not already present (MODIFY keeps existing PK)
-- If PK already exists on draft_id this is a no-op via the MODIFY
ALTER TABLE `ibl_draft` MODIFY COLUMN `draft_id` INT NOT NULL AUTO_INCREMENT PRIMARY KEY;

-- =====================================================================
-- PART 2: Add Missing Primary Keys (Section 1.2)
-- =====================================================================

-- ibl_banners: Add synthetic PK
ALTER TABLE `ibl_banners`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- ibl_heat_career_avgs: Deduplicate before adding PK
DELETE t1 FROM `ibl_heat_career_avgs` t1
INNER JOIN `ibl_heat_career_avgs` t2
  ON t1.pid = t2.pid
  AND t1.games < t2.games;
ALTER TABLE `ibl_heat_career_avgs` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_heat_career_totals: Add PK on pid
ALTER TABLE `ibl_heat_career_totals` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_olympics_career_avgs: Add PK on pid
ALTER TABLE `ibl_olympics_career_avgs` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_olympics_career_totals: Add PK on pid
ALTER TABLE `ibl_olympics_career_totals` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_playoff_career_totals: Add PK on pid
ALTER TABLE `ibl_playoff_career_totals` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_season_career_avgs: Add PK on pid
ALTER TABLE `ibl_season_career_avgs` ADD PRIMARY KEY IF NOT EXISTS (`pid`);

-- ibl_box_scores_teams: Add synthetic PK
ALTER TABLE `ibl_box_scores_teams`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- ibl_trade_cash: Add synthetic PK
ALTER TABLE `ibl_trade_cash`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- ibl_trade_info: Add synthetic PK
ALTER TABLE `ibl_trade_info`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- ibl_trade_queue: Add synthetic PK
ALTER TABLE `ibl_trade_queue`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- =====================================================================
-- PART 3: Remove Dead Columns (Section 1.3)
-- =====================================================================

-- ibl_team_info: Remove defunct messaging service columns
ALTER TABLE `ibl_team_info`
  DROP COLUMN IF EXISTS `skype`,
  DROP COLUMN IF EXISTS `aim`,
  DROP COLUMN IF EXISTS `msn`;

-- ibl_plr: Remove unused scratch column
ALTER TABLE `ibl_plr` DROP COLUMN IF EXISTS `temp`;

-- =====================================================================
-- PART 4: Fix ibl_box_scores Missing PK (Section 1.4)
-- =====================================================================

ALTER TABLE `ibl_box_scores`
  ADD COLUMN IF NOT EXISTS `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY IF NOT EXISTS (`id`);

-- =====================================================================
-- PART 5: Create Optimization Views (Section 2)
-- =====================================================================

-- 5.1: vw_current_salary
CREATE OR REPLACE VIEW `vw_current_salary` AS
SELECT
  pid, name, tid, teamname, pos, cy, cyt,
  cy1, cy2, cy3, cy4, cy5, cy6,
  CASE cy
    WHEN 1 THEN cy1
    WHEN 2 THEN cy2
    WHEN 3 THEN cy3
    WHEN 4 THEN cy4
    WHEN 5 THEN cy5
    WHEN 6 THEN cy6
    ELSE 0
  END AS current_salary,
  CASE cy
    WHEN 0 THEN cy1
    WHEN 1 THEN cy2
    WHEN 2 THEN cy3
    WHEN 3 THEN cy4
    WHEN 4 THEN cy5
    WHEN 5 THEN cy6
    ELSE 0
  END AS next_year_salary
FROM ibl_plr
WHERE retired = 0;

-- 5.2: vw_team_total_score
CREATE OR REPLACE VIEW `vw_team_total_score` AS
SELECT
  Date,
  visitorTeamID,
  homeTeamID,
  game_type,
  (visitorQ1points + visitorQ2points + visitorQ3points + visitorQ4points
   + COALESCE(visitorOTpoints, 0)) AS visitorScore,
  (homeQ1points + homeQ2points + homeQ3points + homeQ4points
   + COALESCE(homeOTpoints, 0)) AS homeScore
FROM ibl_box_scores_teams;

-- 5.3: vw_career_totals
CREATE OR REPLACE VIEW `vw_career_totals` AS
SELECT
  pid,
  name,
  COUNT(*) AS seasons,
  SUM(games) AS games,
  SUM(minutes) AS minutes,
  SUM(fgm) AS fgm,
  SUM(fga) AS fga,
  SUM(ftm) AS ftm,
  SUM(fta) AS fta,
  SUM(tgm) AS tgm,
  SUM(tga) AS tga,
  SUM(orb) AS orb,
  SUM(reb) AS reb,
  SUM(ast) AS ast,
  SUM(stl) AS stl,
  SUM(blk) AS blk,
  SUM(tvr) AS tvr,
  SUM(pf) AS pf,
  SUM(pts) AS pts
FROM ibl_hist
GROUP BY pid, name;

-- 5.4: vw_series_records
CREATE OR REPLACE VIEW `vw_series_records` AS
SELECT self, opponent, SUM(wins) AS wins, SUM(losses) AS losses
FROM (
    SELECT Home AS self, Visitor AS opponent, COUNT(*) AS wins, 0 AS losses
    FROM ibl_schedule
    WHERE HScore > VScore
    GROUP BY self, opponent

    UNION ALL

    SELECT Visitor AS self, Home AS opponent, COUNT(*) AS wins, 0 AS losses
    FROM ibl_schedule
    WHERE VScore > HScore
    GROUP BY self, opponent

    UNION ALL

    SELECT Home AS self, Visitor AS opponent, 0 AS wins, COUNT(*) AS losses
    FROM ibl_schedule
    WHERE HScore < VScore
    GROUP BY self, opponent

    UNION ALL

    SELECT Visitor AS self, Home AS opponent, 0 AS wins, COUNT(*) AS losses
    FROM ibl_schedule
    WHERE VScore < HScore
    GROUP BY self, opponent
) t
GROUP BY self, opponent;

-- =====================================================================
-- PART 6: Fix Column Types (Section 3)
-- =====================================================================

ALTER TABLE `ibl_team_win_loss`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `wins` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `losses` smallint unsigned NOT NULL DEFAULT 0;

ALTER TABLE `ibl_draft_picks`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `round` tinyint unsigned NOT NULL DEFAULT 0;

UPDATE `ibl_plr` SET `htft` = '0' WHERE `htft` = '';
UPDATE `ibl_plr` SET `htin` = '0' WHERE `htin` = '';
UPDATE `ibl_plr` SET `wt` = '0' WHERE `wt` = '';

ALTER TABLE `ibl_plr`
  MODIFY COLUMN `htft` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Height feet',
  MODIFY COLUMN `htin` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Height inches',
  MODIFY COLUMN `wt` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Weight in pounds';

UPDATE `ibl_team_awards`
SET `year` = REPLACE(REPLACE(REPLACE(REPLACE(`year`, '<B>', ''), '</B>', ''), '<b>', ''), '</b>', '');

ALTER TABLE `ibl_team_awards`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0;

-- =====================================================================
-- PART 7: Add Missing Foreign Keys (Section 6.1)
-- =====================================================================

-- Drop CHECK constraints that may or may not exist
ALTER TABLE `ibl_plr` DROP CONSTRAINT IF EXISTS `chk_plr_tid`;
ALTER TABLE `ibl_plr` DROP FOREIGN KEY IF EXISTS `fk_plr_team`;
ALTER TABLE `ibl_plr`
  ADD CONSTRAINT `fk_plr_team` FOREIGN KEY (`tid`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

ALTER TABLE `ibl_hist` DROP FOREIGN KEY IF EXISTS `fk_hist_team`;
ALTER TABLE `ibl_hist`
  ADD CONSTRAINT `fk_hist_team` FOREIGN KEY (`teamid`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

ALTER TABLE `ibl_schedule`
  DROP CONSTRAINT IF EXISTS `chk_schedule_visitor_id`,
  DROP CONSTRAINT IF EXISTS `chk_schedule_home_id`;
ALTER TABLE `ibl_schedule`
  MODIFY COLUMN `Visitor` int NOT NULL DEFAULT 0,
  MODIFY COLUMN `Home` int NOT NULL DEFAULT 0;

ALTER TABLE `ibl_schedule` DROP FOREIGN KEY IF EXISTS `fk_schedule_visitor`;
ALTER TABLE `ibl_schedule`
  ADD CONSTRAINT `fk_schedule_visitor` FOREIGN KEY (`Visitor`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

ALTER TABLE `ibl_schedule` DROP FOREIGN KEY IF EXISTS `fk_schedule_home`;
ALTER TABLE `ibl_schedule`
  ADD CONSTRAINT `fk_schedule_home` FOREIGN KEY (`Home`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_plr_chunk: dropped by migration 035, skip FK
