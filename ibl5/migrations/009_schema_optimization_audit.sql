-- =====================================================================
-- Migration 009: Schema Optimization Audit
-- =====================================================================
-- Date: February 2026
-- Risk Level: Low-Medium
-- Estimated Time: 15-30 minutes
--
-- This migration implements the IBL5 Database Optimization Audit:
--   1. Remove duplicate indexes (1.1)
--   2. Add missing primary keys to InnoDB tables (1.2)
--   3. Remove dead columns (1.3)
--   4. Fix ibl_box_scores / ibl_box_scores_teams missing PK (1.4)
--   5. Create optimization views (2.1-2.5)
--   6. Fix column types (3)
--   7. Add missing foreign keys (6.1)
--   8. Clean ibl_team_awards HTML contamination
--
-- Prerequisites:
--   - Migrations 001-008 completed
--   - Database backup taken
-- =====================================================================

-- =====================================================================
-- PART 1: Remove Duplicate Indexes (Section 1.1)
-- =====================================================================

-- ibl_draft: Drop duplicate uuid index
ALTER TABLE `ibl_draft` DROP KEY `idx_uuid`;

-- ibl_plr: Drop duplicate uuid index
ALTER TABLE `ibl_plr` DROP KEY `idx_uuid`;

-- ibl_team_info: Drop duplicate uuid index
ALTER TABLE `ibl_team_info` DROP KEY `idx_uuid`;

-- ibl_heat_stats: Drop redundant UNIQUE KEY on id (already PRIMARY KEY)
ALTER TABLE `ibl_heat_stats` DROP KEY `id`;

-- ibl_olympics_stats: Drop redundant UNIQUE KEY on id (already PRIMARY KEY)
ALTER TABLE `ibl_olympics_stats` DROP KEY `id`;

-- ibl_plr_chunk: Drop duplicate pid index (pid_2 duplicates pid)
ALTER TABLE `ibl_plr_chunk` DROP KEY `pid_2`;

-- ibl_draft: Convert draft_id from UNIQUE KEY to PRIMARY KEY
-- (AUTO_INCREMENT column should be the PK, not just a unique key)
ALTER TABLE `ibl_draft` DROP KEY `draft_id`, ADD PRIMARY KEY (`draft_id`);

-- =====================================================================
-- PART 2: Add Missing Primary Keys (Section 1.2)
-- =====================================================================

-- ibl_banners: Add synthetic PK
ALTER TABLE `ibl_banners`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- ibl_heat_career_avgs: Deduplicate before adding PK
-- Keep the row with more games (more complete career data)
DELETE t1 FROM `ibl_heat_career_avgs` t1
INNER JOIN `ibl_heat_career_avgs` t2
  ON t1.pid = t2.pid
  AND t1.games < t2.games;
ALTER TABLE `ibl_heat_career_avgs` ADD PRIMARY KEY (`pid`);

-- ibl_heat_career_totals: Add PK on pid
ALTER TABLE `ibl_heat_career_totals` ADD PRIMARY KEY (`pid`);

-- ibl_olympics_career_avgs: Add PK on pid
ALTER TABLE `ibl_olympics_career_avgs` ADD PRIMARY KEY (`pid`);

-- ibl_olympics_career_totals: Add PK on pid
ALTER TABLE `ibl_olympics_career_totals` ADD PRIMARY KEY (`pid`);

-- ibl_playoff_career_totals: Add PK on pid
ALTER TABLE `ibl_playoff_career_totals` ADD PRIMARY KEY (`pid`);

-- ibl_season_career_avgs: Add PK on pid
ALTER TABLE `ibl_season_career_avgs` ADD PRIMARY KEY (`pid`);

-- ibl_box_scores_teams: Add synthetic PK
ALTER TABLE `ibl_box_scores_teams`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- ibl_trade_cash: Add synthetic PK
ALTER TABLE `ibl_trade_cash`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- ibl_trade_info: Add synthetic PK
ALTER TABLE `ibl_trade_info`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- ibl_trade_queue: Add synthetic PK
ALTER TABLE `ibl_trade_queue`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- =====================================================================
-- PART 3: Remove Dead Columns (Section 1.3)
-- =====================================================================

-- ibl_team_info: Remove defunct messaging service columns
ALTER TABLE `ibl_team_info`
  DROP COLUMN `skype`,
  DROP COLUMN `aim`,
  DROP COLUMN `msn`;

-- ibl_plr: Remove unused scratch column
ALTER TABLE `ibl_plr` DROP COLUMN `temp`;

-- =====================================================================
-- PART 4: Fix ibl_box_scores Missing PK (Section 1.4)
-- =====================================================================

-- Add integer PK; keep uuid as UNIQUE KEY for API access
ALTER TABLE `ibl_box_scores`
  ADD COLUMN `id` int NOT NULL AUTO_INCREMENT FIRST,
  ADD PRIMARY KEY (`id`);

-- =====================================================================
-- PART 5: Create Optimization Views (Section 2)
-- =====================================================================

-- 5.1: vw_current_salary — Salary Resolution
-- Eliminates CASE cy WHEN... pattern duplicated across 5 repositories
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

-- 5.2: vw_team_total_score — Game Totals from Quarter Scores
-- Eliminates quarter-sum expressions repeated in RecordHoldersRepository
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

-- 5.3: vw_career_totals — Regular Season Career Totals from ibl_hist
-- Eliminates live SUM/GROUP BY on CareerLeaderboardsRepository page loads
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

-- 5.4: vw_series_records — Head-to-Head Records
-- Replaces 4-way UNION ALL in SeriesRecordsRepository::getSeriesRecords()
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

-- 6.1: ibl_team_win_loss — Convert varchar to proper numeric types
ALTER TABLE `ibl_team_win_loss`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `wins` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `losses` smallint unsigned NOT NULL DEFAULT 0;

-- 6.2: ibl_draft_picks — Convert varchar year/round to numeric
ALTER TABLE `ibl_draft_picks`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0,
  MODIFY COLUMN `round` tinyint unsigned NOT NULL DEFAULT 0;

-- 6.3: ibl_plr — Convert height/weight from varchar to numeric
-- Step 1: Fix empty strings that can't convert to integer
UPDATE `ibl_plr` SET `htft` = '0' WHERE `htft` = '';
UPDATE `ibl_plr` SET `htin` = '0' WHERE `htin` = '';
UPDATE `ibl_plr` SET `wt` = '0' WHERE `wt` = '';

-- Step 2: Convert to numeric types
ALTER TABLE `ibl_plr`
  MODIFY COLUMN `htft` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Height feet',
  MODIFY COLUMN `htin` tinyint unsigned NOT NULL DEFAULT 0 COMMENT 'Height inches',
  MODIFY COLUMN `wt` smallint unsigned NOT NULL DEFAULT 0 COMMENT 'Weight in pounds';

-- 6.4: ibl_team_awards — Clean HTML from year column, then convert
-- Step 1: Strip <B> and </B> tags from year values
UPDATE `ibl_team_awards`
SET `year` = REPLACE(REPLACE(REPLACE(REPLACE(`year`, '<B>', ''), '</B>', ''), '<b>', ''), '</b>', '');

-- Step 2: Convert to smallint
ALTER TABLE `ibl_team_awards`
  MODIFY COLUMN `year` smallint unsigned NOT NULL DEFAULT 0;

-- =====================================================================
-- PART 7: Add Missing Foreign Keys (Section 6.1)
-- =====================================================================

-- ibl_plr.tid → ibl_team_info.teamid (most critical FK)
-- Drop CHECK constraint that conflicts with FK referential action
ALTER TABLE `ibl_plr` DROP CONSTRAINT `chk_plr_tid`;
ALTER TABLE `ibl_plr`
  ADD CONSTRAINT `fk_plr_team` FOREIGN KEY (`tid`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_hist.teamid → ibl_team_info.teamid
ALTER TABLE `ibl_hist`
  ADD CONSTRAINT `fk_hist_team` FOREIGN KEY (`teamid`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_schedule: Match column types for FK compatibility (smallint→int)
-- Drop CHECK constraints that conflict with FK referential action
ALTER TABLE `ibl_schedule`
  DROP CONSTRAINT `chk_schedule_visitor_id`,
  DROP CONSTRAINT `chk_schedule_home_id`;
ALTER TABLE `ibl_schedule`
  MODIFY COLUMN `Visitor` int NOT NULL DEFAULT 0,
  MODIFY COLUMN `Home` int NOT NULL DEFAULT 0;

-- ibl_schedule.Visitor → ibl_team_info.teamid
ALTER TABLE `ibl_schedule`
  ADD CONSTRAINT `fk_schedule_visitor` FOREIGN KEY (`Visitor`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_schedule.Home → ibl_team_info.teamid
ALTER TABLE `ibl_schedule`
  ADD CONSTRAINT `fk_schedule_home` FOREIGN KEY (`Home`)
  REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ibl_plr_chunk.pid → ibl_plr.pid
ALTER TABLE `ibl_plr_chunk`
  ADD CONSTRAINT `fk_plr_chunk_player` FOREIGN KEY (`pid`)
  REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE;

-- =====================================================================
-- VERIFICATION QUERIES
-- =====================================================================

-- Verify duplicate indexes removed
-- SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
-- FROM information_schema.STATISTICS
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND TABLE_NAME IN ('ibl_draft', 'ibl_plr', 'ibl_team_info', 'ibl_heat_stats', 'ibl_olympics_stats', 'ibl_plr_chunk')
-- ORDER BY TABLE_NAME, INDEX_NAME;

-- Verify all tables have primary keys
-- SELECT t.TABLE_NAME
-- FROM information_schema.TABLES t
-- LEFT JOIN information_schema.TABLE_CONSTRAINTS c
--   ON t.TABLE_SCHEMA = c.TABLE_SCHEMA
--   AND t.TABLE_NAME = c.TABLE_NAME
--   AND c.CONSTRAINT_TYPE = 'PRIMARY KEY'
-- WHERE t.TABLE_SCHEMA = DATABASE()
--   AND t.TABLE_NAME LIKE 'ibl_%'
--   AND t.ENGINE = 'InnoDB'
--   AND c.CONSTRAINT_NAME IS NULL;

-- Verify views created
-- SHOW FULL TABLES WHERE Table_type = 'VIEW';

-- Test salary view
-- SELECT teamname, SUM(current_salary) AS total FROM vw_current_salary GROUP BY teamname ORDER BY total DESC;

-- Verify foreign keys
-- SELECT TABLE_NAME, CONSTRAINT_NAME, COLUMN_NAME, REFERENCED_TABLE_NAME
-- FROM information_schema.KEY_COLUMN_USAGE
-- WHERE TABLE_SCHEMA = DATABASE()
--   AND REFERENCED_TABLE_NAME IS NOT NULL
--   AND TABLE_NAME LIKE 'ibl_%'
-- ORDER BY TABLE_NAME;
