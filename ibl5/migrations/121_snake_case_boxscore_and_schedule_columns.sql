-- Migration 121: Tier 6 — snake_case rename of box-score and schedule columns (ADR-0010).
-- Final cosmetic case-consistency PR. Renames PascalCase / camelCase columns on
-- the four box-score tables and the two schedule tables.
--
-- Strategy: each box-score table has 5 STORED generated columns and a CHECK
-- constraint that reference the columns being renamed. RENAME COLUMN's
-- "auto-update generated columns" behaviour is not reliable across MariaDB
-- patch levels, so we drop the generated columns + CHECK constraint first
-- (which also auto-drops the 11 indexes that reference them), then rename the
-- source columns with CHANGE COLUMN, then recreate the generated columns,
-- CHECK constraint, and indexes with the new column names.
-- Schedule FKs on Home/Visitor are dropped + re-added because the FK columns
-- are also being renamed.

-- ============================================================
-- ibl_box_scores
-- ============================================================

ALTER TABLE `ibl_box_scores` DROP CONSTRAINT `chk_box_minutes`;

-- Drop the indexes that reference renamed source columns (the index keeps
-- working after CHANGE COLUMN — MariaDB updates the column-list pointer in
-- place — but the index ENTRY would block re-adding the same name later in
-- this migration). Drop everything we plan to recreate.
ALTER TABLE `ibl_box_scores`
  DROP INDEX `idx_gt_points`,
  DROP INDEX `idx_gt_rebounds`,
  DROP INDEX `idx_gt_fg_made`,
  DROP INDEX `idx_gt_ast`,
  DROP INDEX `idx_gt_stl`,
  DROP INDEX `idx_gt_blk`,
  DROP INDEX `idx_gt_tov`,
  DROP INDEX `idx_gt_ftm`,
  DROP INDEX `idx_gt_3gm`,
  DROP INDEX `idx_gt_pid`,
  DROP INDEX `idx_gt_pid_season`;

ALTER TABLE `ibl_box_scores`
  DROP COLUMN `game_type`,
  DROP COLUMN `season_year`,
  DROP COLUMN `calc_points`,
  DROP COLUMN `calc_rebounds`,
  DROP COLUMN `calc_fg_made`;

ALTER TABLE `ibl_box_scores`
  CHANGE COLUMN `Date`           `game_date`         date                 NOT NULL                COMMENT 'Game date',
  CHANGE COLUMN `gameMIN`        `game_min`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Minutes played',
  CHANGE COLUMN `game2GM`        `game_2gm`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Two-point field goals made',
  CHANGE COLUMN `game2GA`        `game_2ga`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Two-point field goals attempted',
  CHANGE COLUMN `gameFTM`        `game_ftm`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Free throws made',
  CHANGE COLUMN `gameFTA`        `game_fta`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Free throws attempted',
  CHANGE COLUMN `game3GM`        `game_3gm`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Three pointers made',
  CHANGE COLUMN `game3GA`        `game_3ga`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Three pointers attempted',
  CHANGE COLUMN `gameORB`        `game_orb`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Offensive rebounds',
  CHANGE COLUMN `gameDRB`        `game_drb`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Defensive rebounds',
  CHANGE COLUMN `gameAST`        `game_ast`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Assists',
  CHANGE COLUMN `gameSTL`        `game_stl`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Steals',
  CHANGE COLUMN `gameTOV`        `game_tov`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Turnovers',
  CHANGE COLUMN `gameBLK`        `game_blk`          tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Blocks',
  CHANGE COLUMN `gamePF`         `game_pf`           tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Personal fouls',
  CHANGE COLUMN `gameOfThatDay`  `game_of_that_day`  tinyint(3) unsigned  DEFAULT NULL            COMMENT 'Game number for that date (1st, 2nd game)',
  CHANGE COLUMN `visitorWins`    `visitor_wins`      smallint(5) unsigned DEFAULT NULL            COMMENT 'Visitor team wins before this game',
  CHANGE COLUMN `visitorLosses`  `visitor_losses`    smallint(5) unsigned DEFAULT NULL            COMMENT 'Visitor team losses before this game',
  CHANGE COLUMN `homeWins`       `home_wins`         smallint(5) unsigned DEFAULT NULL            COMMENT 'Home team wins before this game',
  CHANGE COLUMN `homeLosses`     `home_losses`       smallint(5) unsigned DEFAULT NULL            COMMENT 'Home team losses before this game';

ALTER TABLE `ibl_box_scores`
  ADD COLUMN `game_type`     tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED,
  ADD COLUMN `season_year`   smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 10 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED,
  ADD COLUMN `calc_points`   smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` * 2 + `game_ftm` + `game_3gm` * 3) STORED,
  ADD COLUMN `calc_rebounds` tinyint(3) unsigned  GENERATED ALWAYS AS (`game_orb` + `game_drb`) STORED,
  ADD COLUMN `calc_fg_made`  tinyint(3) unsigned  GENERATED ALWAYS AS (`game_2gm` + `game_3gm`) STORED;

ALTER TABLE `ibl_box_scores`
  ADD KEY `idx_gt_points`     (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds`   (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`    (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`        (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`        (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`        (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`        (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`        (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`        (`game_type`, `game_3gm`),
  ADD KEY `idx_gt_pid`        (`game_type`, `pid`),
  ADD KEY `idx_gt_pid_season` (`game_type`, `pid`, `season_year`);

ALTER TABLE `ibl_box_scores`
  ADD CONSTRAINT `chk_box_minutes` CHECK (`game_min` IS NULL OR `game_min` >= 0 AND `game_min` <= 70);

-- ============================================================
-- ibl_olympics_box_scores
-- ============================================================

ALTER TABLE `ibl_olympics_box_scores` DROP CONSTRAINT `chk_olympics_box_minutes`;

ALTER TABLE `ibl_olympics_box_scores`
  DROP INDEX `idx_gt_points`,
  DROP INDEX `idx_gt_rebounds`,
  DROP INDEX `idx_gt_fg_made`,
  DROP INDEX `idx_gt_ast`,
  DROP INDEX `idx_gt_stl`,
  DROP INDEX `idx_gt_blk`,
  DROP INDEX `idx_gt_tov`,
  DROP INDEX `idx_gt_ftm`,
  DROP INDEX `idx_gt_3gm`,
  DROP INDEX `idx_gt_pid`;

ALTER TABLE `ibl_olympics_box_scores`
  DROP COLUMN `game_type`,
  DROP COLUMN `season_year`,
  DROP COLUMN `calc_points`,
  DROP COLUMN `calc_rebounds`,
  DROP COLUMN `calc_fg_made`;

ALTER TABLE `ibl_olympics_box_scores`
  CHANGE COLUMN `Date`           `game_date`         date                NOT NULL                COMMENT 'Game date',
  CHANGE COLUMN `gameMIN`        `game_min`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Minutes played',
  CHANGE COLUMN `game2GM`        `game_2gm`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Field goals made',
  CHANGE COLUMN `game2GA`        `game_2ga`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Field goals attempted',
  CHANGE COLUMN `gameFTM`        `game_ftm`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Free throws made',
  CHANGE COLUMN `gameFTA`        `game_fta`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Free throws attempted',
  CHANGE COLUMN `game3GM`        `game_3gm`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Three pointers made',
  CHANGE COLUMN `game3GA`        `game_3ga`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Three pointers attempted',
  CHANGE COLUMN `gameORB`        `game_orb`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Offensive rebounds',
  CHANGE COLUMN `gameDRB`        `game_drb`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Defensive rebounds',
  CHANGE COLUMN `gameAST`        `game_ast`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Assists',
  CHANGE COLUMN `gameSTL`        `game_stl`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Steals',
  CHANGE COLUMN `gameTOV`        `game_tov`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Turnovers',
  CHANGE COLUMN `gameBLK`        `game_blk`          tinyint(3) unsigned DEFAULT NULL            COMMENT 'Blocks',
  CHANGE COLUMN `gamePF`         `game_pf`           tinyint(3) unsigned DEFAULT NULL            COMMENT 'Personal fouls',
  CHANGE COLUMN `gameOfThatDay`  `game_of_that_day`  int(11)             DEFAULT NULL            COMMENT 'Game number for that date',
  CHANGE COLUMN `visitorWins`    `visitor_wins`      int(11)             DEFAULT NULL            COMMENT 'Visitor team wins before game',
  CHANGE COLUMN `visitorLosses`  `visitor_losses`    int(11)             DEFAULT NULL            COMMENT 'Visitor team losses before game',
  CHANGE COLUMN `homeWins`       `home_wins`         int(11)             DEFAULT NULL            COMMENT 'Home team wins before game',
  CHANGE COLUMN `homeLosses`     `home_losses`       int(11)             DEFAULT NULL            COMMENT 'Home team losses before game';

ALTER TABLE `ibl_olympics_box_scores`
  ADD COLUMN `game_type`     tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED,
  ADD COLUMN `season_year`   smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 10 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED,
  ADD COLUMN `calc_points`   smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` * 2 + `game_ftm` + `game_3gm` * 3) STORED,
  ADD COLUMN `calc_rebounds` tinyint(3) unsigned  GENERATED ALWAYS AS (`game_orb` + `game_drb`) STORED,
  ADD COLUMN `calc_fg_made`  tinyint(3) unsigned  GENERATED ALWAYS AS (`game_2gm` + `game_3gm`) STORED;

ALTER TABLE `ibl_olympics_box_scores`
  ADD KEY `idx_gt_points`   (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds` (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`  (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`      (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`      (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`      (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`      (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`      (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`      (`game_type`, `game_3gm`),
  ADD KEY `idx_gt_pid`      (`game_type`, `pid`);

ALTER TABLE `ibl_olympics_box_scores`
  ADD CONSTRAINT `chk_olympics_box_minutes` CHECK (`game_min` IS NULL OR `game_min` >= 0 AND `game_min` <= 70);

-- ============================================================
-- ibl_box_scores_teams
-- ============================================================

ALTER TABLE `ibl_box_scores_teams`
  DROP INDEX `idx_gt_points`,
  DROP INDEX `idx_gt_rebounds`,
  DROP INDEX `idx_gt_fg_made`,
  DROP INDEX `idx_gt_ast`,
  DROP INDEX `idx_gt_stl`,
  DROP INDEX `idx_gt_blk`,
  DROP INDEX `idx_gt_tov`,
  DROP INDEX `idx_gt_ftm`,
  DROP INDEX `idx_gt_3gm`,
  DROP INDEX `idx_gt_date_teams`,
  DROP INDEX `idx_gt_name_season`,
  DROP INDEX `idx_date_visitor_home_gotd`;

ALTER TABLE `ibl_box_scores_teams`
  DROP COLUMN `game_type`,
  DROP COLUMN `season_year`,
  DROP COLUMN `calc_points`,
  DROP COLUMN `calc_rebounds`,
  DROP COLUMN `calc_fg_made`;

ALTER TABLE `ibl_box_scores_teams`
  CHANGE COLUMN `Date`            `game_date`          date    NOT NULL     COMMENT 'Game date',
  CHANGE COLUMN `gameOfThatDay`   `game_of_that_day`   int(11) DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd, etc.)',
  CHANGE COLUMN `visitorWins`     `visitor_wins`       int(11) DEFAULT NULL COMMENT 'Visitor record wins before game',
  CHANGE COLUMN `visitorLosses`   `visitor_losses`     int(11) DEFAULT NULL COMMENT 'Visitor record losses before game',
  CHANGE COLUMN `homeWins`        `home_wins`          int(11) DEFAULT NULL COMMENT 'Home record wins before game',
  CHANGE COLUMN `homeLosses`      `home_losses`        int(11) DEFAULT NULL COMMENT 'Home record losses before game',
  CHANGE COLUMN `visitorQ1points` `visitor_q1_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q1 points',
  CHANGE COLUMN `visitorQ2points` `visitor_q2_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q2 points',
  CHANGE COLUMN `visitorQ3points` `visitor_q3_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q3 points',
  CHANGE COLUMN `visitorQ4points` `visitor_q4_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q4 points',
  CHANGE COLUMN `visitorOTpoints` `visitor_ot_points`  int(11) DEFAULT NULL COMMENT 'Visitor overtime points',
  CHANGE COLUMN `homeQ1points`    `home_q1_points`     int(11) DEFAULT NULL COMMENT 'Home Q1 points',
  CHANGE COLUMN `homeQ2points`    `home_q2_points`     int(11) DEFAULT NULL COMMENT 'Home Q2 points',
  CHANGE COLUMN `homeQ3points`    `home_q3_points`     int(11) DEFAULT NULL COMMENT 'Home Q3 points',
  CHANGE COLUMN `homeQ4points`    `home_q4_points`     int(11) DEFAULT NULL COMMENT 'Home Q4 points',
  CHANGE COLUMN `homeOTpoints`    `home_ot_points`     int(11) DEFAULT NULL COMMENT 'Home overtime points',
  CHANGE COLUMN `gameMIN`         `game_min`           int(11) DEFAULT NULL COMMENT 'Total game minutes',
  CHANGE COLUMN `game2GM`         `game_2gm`           int(11) DEFAULT NULL COMMENT 'Two-point field goals made',
  CHANGE COLUMN `game2GA`         `game_2ga`           int(11) DEFAULT NULL COMMENT 'Two-point field goals attempted',
  CHANGE COLUMN `gameFTM`         `game_ftm`           int(11) DEFAULT NULL COMMENT 'Free throws made',
  CHANGE COLUMN `gameFTA`         `game_fta`           int(11) DEFAULT NULL COMMENT 'Free throws attempted',
  CHANGE COLUMN `game3GM`         `game_3gm`           int(11) DEFAULT NULL COMMENT 'Three pointers made',
  CHANGE COLUMN `game3GA`         `game_3ga`           int(11) DEFAULT NULL COMMENT 'Three pointers attempted',
  CHANGE COLUMN `gameORB`         `game_orb`           int(11) DEFAULT NULL COMMENT 'Offensive rebounds',
  CHANGE COLUMN `gameDRB`         `game_drb`           int(11) DEFAULT NULL COMMENT 'Defensive rebounds',
  CHANGE COLUMN `gameAST`         `game_ast`           int(11) DEFAULT NULL COMMENT 'Assists',
  CHANGE COLUMN `gameSTL`         `game_stl`           int(11) DEFAULT NULL COMMENT 'Steals',
  CHANGE COLUMN `gameTOV`         `game_tov`           int(11) DEFAULT NULL COMMENT 'Turnovers',
  CHANGE COLUMN `gameBLK`         `game_blk`           int(11) DEFAULT NULL COMMENT 'Blocks',
  CHANGE COLUMN `gamePF`          `game_pf`            int(11) DEFAULT NULL COMMENT 'Personal fouls';

ALTER TABLE `ibl_box_scores_teams`
  ADD COLUMN `game_type`     tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED,
  ADD COLUMN `season_year`   smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 10 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED,
  ADD COLUMN `calc_points`   smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` * 2 + `game_ftm` + `game_3gm` * 3) STORED,
  ADD COLUMN `calc_rebounds` smallint(5) unsigned GENERATED ALWAYS AS (`game_orb` + `game_drb`) STORED,
  ADD COLUMN `calc_fg_made`  smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` + `game_3gm`) STORED;

ALTER TABLE `ibl_box_scores_teams`
  ADD KEY `idx_gt_points`             (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds`           (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`            (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`                (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`                (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`                (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`                (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`                (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`                (`game_type`, `game_3gm`),
  ADD KEY `idx_gt_date_teams`         (`game_type`, `game_date`, `visitor_teamid`, `home_teamid`),
  ADD KEY `idx_gt_name_season`        (`game_type`, `name`, `season_year`),
  ADD KEY `idx_date_visitor_home_gotd` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`);

-- ============================================================
-- ibl_olympics_box_scores_teams
-- ============================================================

ALTER TABLE `ibl_olympics_box_scores_teams`
  DROP INDEX `idx_gt_points`,
  DROP INDEX `idx_gt_rebounds`,
  DROP INDEX `idx_gt_fg_made`,
  DROP INDEX `idx_gt_ast`,
  DROP INDEX `idx_gt_stl`,
  DROP INDEX `idx_gt_blk`,
  DROP INDEX `idx_gt_tov`,
  DROP INDEX `idx_gt_ftm`,
  DROP INDEX `idx_gt_3gm`;

ALTER TABLE `ibl_olympics_box_scores_teams`
  DROP COLUMN `game_type`,
  DROP COLUMN `season_year`,
  DROP COLUMN `calc_points`,
  DROP COLUMN `calc_rebounds`,
  DROP COLUMN `calc_fg_made`;

ALTER TABLE `ibl_olympics_box_scores_teams`
  CHANGE COLUMN `Date`            `game_date`          date    NOT NULL     COMMENT 'Game date',
  CHANGE COLUMN `gameOfThatDay`   `game_of_that_day`   int(11) DEFAULT NULL COMMENT 'Game number for that date',
  CHANGE COLUMN `visitorWins`     `visitor_wins`       int(11) DEFAULT NULL COMMENT 'Visitor team wins before game',
  CHANGE COLUMN `visitorLosses`   `visitor_losses`     int(11) DEFAULT NULL COMMENT 'Visitor team losses before game',
  CHANGE COLUMN `homeWins`        `home_wins`          int(11) DEFAULT NULL COMMENT 'Home team wins before game',
  CHANGE COLUMN `homeLosses`      `home_losses`        int(11) DEFAULT NULL COMMENT 'Home team losses before game',
  CHANGE COLUMN `visitorQ1points` `visitor_q1_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q1 points',
  CHANGE COLUMN `visitorQ2points` `visitor_q2_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q2 points',
  CHANGE COLUMN `visitorQ3points` `visitor_q3_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q3 points',
  CHANGE COLUMN `visitorQ4points` `visitor_q4_points`  int(11) DEFAULT NULL COMMENT 'Visitor Q4 points',
  CHANGE COLUMN `visitorOTpoints` `visitor_ot_points`  int(11) DEFAULT NULL COMMENT 'Visitor overtime points',
  CHANGE COLUMN `homeQ1points`    `home_q1_points`     int(11) DEFAULT NULL COMMENT 'Home Q1 points',
  CHANGE COLUMN `homeQ2points`    `home_q2_points`     int(11) DEFAULT NULL COMMENT 'Home Q2 points',
  CHANGE COLUMN `homeQ3points`    `home_q3_points`     int(11) DEFAULT NULL COMMENT 'Home Q3 points',
  CHANGE COLUMN `homeQ4points`    `home_q4_points`     int(11) DEFAULT NULL COMMENT 'Home Q4 points',
  CHANGE COLUMN `homeOTpoints`    `home_ot_points`     int(11) DEFAULT NULL COMMENT 'Home overtime points',
  CHANGE COLUMN `gameMIN`         `game_min`           int(11) DEFAULT NULL COMMENT 'Total game minutes',
  CHANGE COLUMN `game2GM`         `game_2gm`           int(11) DEFAULT NULL COMMENT 'Field goals made',
  CHANGE COLUMN `game2GA`         `game_2ga`           int(11) DEFAULT NULL COMMENT 'Field goals attempted',
  CHANGE COLUMN `gameFTM`         `game_ftm`           int(11) DEFAULT NULL COMMENT 'Free throws made',
  CHANGE COLUMN `gameFTA`         `game_fta`           int(11) DEFAULT NULL COMMENT 'Free throws attempted',
  CHANGE COLUMN `game3GM`         `game_3gm`           int(11) DEFAULT NULL COMMENT 'Three pointers made',
  CHANGE COLUMN `game3GA`         `game_3ga`           int(11) DEFAULT NULL COMMENT 'Three pointers attempted',
  CHANGE COLUMN `gameORB`         `game_orb`           int(11) DEFAULT NULL COMMENT 'Offensive rebounds',
  CHANGE COLUMN `gameDRB`         `game_drb`           int(11) DEFAULT NULL COMMENT 'Defensive rebounds',
  CHANGE COLUMN `gameAST`         `game_ast`           int(11) DEFAULT NULL COMMENT 'Assists',
  CHANGE COLUMN `gameSTL`         `game_stl`           int(11) DEFAULT NULL COMMENT 'Steals',
  CHANGE COLUMN `gameTOV`         `game_tov`           int(11) DEFAULT NULL COMMENT 'Turnovers',
  CHANGE COLUMN `gameBLK`         `game_blk`           int(11) DEFAULT NULL COMMENT 'Blocks',
  CHANGE COLUMN `gamePF`          `game_pf`            int(11) DEFAULT NULL COMMENT 'Personal fouls';

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD COLUMN `game_type`     tinyint(3) unsigned  GENERATED ALWAYS AS (CASE WHEN MONTH(`game_date`) = 6 THEN 2 WHEN MONTH(`game_date`) = 10 THEN 3 WHEN MONTH(`game_date`) = 0 THEN 0 ELSE 1 END) STORED,
  ADD COLUMN `season_year`   smallint(5) unsigned GENERATED ALWAYS AS (CASE WHEN YEAR(`game_date`) = 0 THEN 0 WHEN MONTH(`game_date`) >= 10 THEN YEAR(`game_date`) + 1 ELSE YEAR(`game_date`) END) STORED,
  ADD COLUMN `calc_points`   smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` * 2 + `game_ftm` + `game_3gm` * 3) STORED,
  ADD COLUMN `calc_rebounds` smallint(5) unsigned GENERATED ALWAYS AS (`game_orb` + `game_drb`) STORED,
  ADD COLUMN `calc_fg_made`  smallint(5) unsigned GENERATED ALWAYS AS (`game_2gm` + `game_3gm`) STORED;

ALTER TABLE `ibl_olympics_box_scores_teams`
  ADD KEY `idx_gt_points`   (`game_type`, `calc_points`),
  ADD KEY `idx_gt_rebounds` (`game_type`, `calc_rebounds`),
  ADD KEY `idx_gt_fg_made`  (`game_type`, `calc_fg_made`),
  ADD KEY `idx_gt_ast`      (`game_type`, `game_ast`),
  ADD KEY `idx_gt_stl`      (`game_type`, `game_stl`),
  ADD KEY `idx_gt_blk`      (`game_type`, `game_blk`),
  ADD KEY `idx_gt_tov`      (`game_type`, `game_tov`),
  ADD KEY `idx_gt_ftm`      (`game_type`, `game_ftm`),
  ADD KEY `idx_gt_3gm`      (`game_type`, `game_3gm`);

-- ============================================================
-- ibl_schedule (FK churn on Home/Visitor)
-- ============================================================

ALTER TABLE `ibl_schedule`
  DROP FOREIGN KEY `fk_schedule_home`,
  DROP FOREIGN KEY `fk_schedule_visitor`;

ALTER TABLE `ibl_schedule`
  CHANGE COLUMN `SchedID` `id`             int(11)              NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  CHANGE COLUMN `Year`    `season_year`    smallint(5) unsigned NOT NULL                COMMENT 'Season year',
  CHANGE COLUMN `BoxID`   `box_id`         int(11)              NOT NULL DEFAULT 0      COMMENT 'Link to box score data',
  CHANGE COLUMN `Date`    `game_date`      date                 NOT NULL                COMMENT 'Game date',
  CHANGE COLUMN `Visitor` `visitor_teamid` int(11)              NOT NULL DEFAULT 0      COMMENT 'Visiting team ID (FK to ibl_team_info)',
  CHANGE COLUMN `VScore`  `visitor_score`  tinyint(3) unsigned  NOT NULL DEFAULT 0      COMMENT 'Visitor score',
  CHANGE COLUMN `Home`    `home_teamid`    int(11)              NOT NULL DEFAULT 0      COMMENT 'Home team ID (FK to ibl_team_info)',
  CHANGE COLUMN `HScore`  `home_score`     tinyint(3) unsigned  NOT NULL DEFAULT 0      COMMENT 'Home score';

ALTER TABLE `ibl_schedule`
  ADD CONSTRAINT `fk_schedule_home`
    FOREIGN KEY (`home_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_schedule_visitor`
    FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE;

-- ============================================================
-- ibl_olympics_schedule (FK churn on Home/Visitor)
-- ============================================================

ALTER TABLE `ibl_olympics_schedule`
  DROP FOREIGN KEY `fk_olympics_schedule_home`,
  DROP FOREIGN KEY `fk_olympics_schedule_visitor`;

ALTER TABLE `ibl_olympics_schedule`
  CHANGE COLUMN `SchedID` `id`             int(11)              NOT NULL AUTO_INCREMENT COMMENT 'Primary key',
  CHANGE COLUMN `Year`    `season_year`    smallint(5) unsigned NOT NULL                COMMENT 'Tournament year',
  CHANGE COLUMN `BoxID`   `box_id`         int(11)              NOT NULL DEFAULT 0      COMMENT 'Box score identifier',
  CHANGE COLUMN `Date`    `game_date`      date                 NOT NULL                COMMENT 'Game date',
  CHANGE COLUMN `Visitor` `visitor_teamid` int(11)              NOT NULL                COMMENT 'Visiting team ID (FK to ibl_olympics_team_info)',
  CHANGE COLUMN `VScore`  `visitor_score`  tinyint(3) unsigned  NOT NULL DEFAULT 0      COMMENT 'Visitor score',
  CHANGE COLUMN `Home`    `home_teamid`    int(11)              NOT NULL                COMMENT 'Home team ID (FK to ibl_olympics_team_info)',
  CHANGE COLUMN `HScore`  `home_score`     tinyint(3) unsigned  NOT NULL DEFAULT 0      COMMENT 'Home score';

ALTER TABLE `ibl_olympics_schedule`
  ADD CONSTRAINT `fk_olympics_schedule_home`
    FOREIGN KEY (`home_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_olympics_schedule_visitor`
    FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_olympics_team_info` (`teamid`) ON UPDATE CASCADE;

-- ============================================================
-- View regeneration — recreate every view that referenced a renamed column.
-- 21 views total (the 20 listed in the Tier 6 plan plus vw_team_awards which
-- was missed — its third UNION branch directly references `bst.Date`,
-- `bst.homeQ1points`, etc. from `ibl_box_scores_teams`).
-- Output aliases stay unchanged where downstream PHP relies on them.
-- ============================================================

CREATE OR REPLACE VIEW `ibl_allstar_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`game_min`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game_2ga` + `bs`.`game_3ga`),2) AS `fga`,case when sum(`bs`.`game_2ga` + `bs`.`game_3ga`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game_2ga` + `bs`.`game_3ga`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`game_ftm`),2) AS `ftm`,round(avg(`bs`.`game_fta`),2) AS `fta`,case when sum(`bs`.`game_fta`) > 0 then round(sum(`bs`.`game_ftm`) / sum(`bs`.`game_fta`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game_3gm`),2) AS `tgm`,round(avg(`bs`.`game_3ga`),2) AS `tga`,case when sum(`bs`.`game_3ga`) > 0 then round(sum(`bs`.`game_3gm`) / sum(`bs`.`game_3ga`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`game_orb`),2) AS `orb`,round(avg(`bs`.`game_drb`),2) AS `drb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`game_ast`),2) AS `ast`,round(avg(`bs`.`game_stl`),2) AS `stl`,round(avg(`bs`.`game_tov`),2) AS `tvr`,round(avg(`bs`.`game_blk`),2) AS `blk`,round(avg(`bs`.`game_pf`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` in (50,51) group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_allstar_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`game_drb`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` in (50,51) group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_heat_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`game_min`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game_2ga` + `bs`.`game_3ga`),2) AS `fga`,case when sum(`bs`.`game_2ga` + `bs`.`game_3ga`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game_2ga` + `bs`.`game_3ga`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`game_ftm`),2) AS `ftm`,round(avg(`bs`.`game_fta`),2) AS `fta`,case when sum(`bs`.`game_fta`) > 0 then round(sum(`bs`.`game_ftm`) / sum(`bs`.`game_fta`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game_3gm`),2) AS `tgm`,round(avg(`bs`.`game_3ga`),2) AS `tga`,case when sum(`bs`.`game_3ga`) > 0 then round(sum(`bs`.`game_3gm`) / sum(`bs`.`game_3ga`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`game_orb`),2) AS `orb`,round(avg(`bs`.`game_drb`),2) AS `drb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`game_ast`),2) AS `ast`,round(avg(`bs`.`game_stl`),2) AS `stl`,round(avg(`bs`.`game_tov`),2) AS `tvr`,round(avg(`bs`.`game_blk`),2) AS `blk`,round(avg(`bs`.`game_pf`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_heat_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`game_drb`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_heat_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `ibl_franchise_seasons` `fs` on(`bs`.`teamid` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 3 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name`;

CREATE OR REPLACE VIEW `ibl_heat_win_loss` AS with unique_games as (select `ibl_box_scores_teams`.`game_date` AS `game_date`,`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`ibl_box_scores_teams`.`game_of_that_day` AS `game_of_that_day`,`ibl_box_scores_teams`.`visitor_q1_points` + `ibl_box_scores_teams`.`visitor_q2_points` + `ibl_box_scores_teams`.`visitor_q3_points` + `ibl_box_scores_teams`.`visitor_q4_points` + coalesce(`ibl_box_scores_teams`.`visitor_ot_points`,0) AS `visitor_total`,`ibl_box_scores_teams`.`home_q1_points` + `ibl_box_scores_teams`.`home_q2_points` + `ibl_box_scores_teams`.`home_q3_points` + `ibl_box_scores_teams`.`home_q4_points` + coalesce(`ibl_box_scores_teams`.`home_ot_points`,0) AS `home_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 3 and year(`ibl_box_scores_teams`.`game_date`) < 9000 group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`,`ibl_box_scores_teams`.`game_of_that_day`), team_games as (select `unique_games`.`visitor_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`home_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select year(`tg`.`game_date`) AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = year(`tg`.`game_date`) + 1)) group by `tg`.`teamid`,year(`tg`.`game_date`),`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);

CREATE OR REPLACE VIEW `ibl_playoff_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`game_min`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game_2ga` + `bs`.`game_3ga`),2) AS `fga`,case when sum(`bs`.`game_2ga` + `bs`.`game_3ga`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game_2ga` + `bs`.`game_3ga`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`game_ftm`),2) AS `ftm`,round(avg(`bs`.`game_fta`),2) AS `fta`,case when sum(`bs`.`game_fta`) > 0 then round(sum(`bs`.`game_ftm`) / sum(`bs`.`game_fta`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game_3gm`),2) AS `tgm`,round(avg(`bs`.`game_3ga`),2) AS `tga`,case when sum(`bs`.`game_3ga`) > 0 then round(sum(`bs`.`game_3gm`) / sum(`bs`.`game_3ga`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`game_orb`),2) AS `orb`,round(avg(`bs`.`game_drb`),2) AS `drb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`game_ast`),2) AS `ast`,round(avg(`bs`.`game_stl`),2) AS `stl`,round(avg(`bs`.`game_tov`),2) AS `tvr`,round(avg(`bs`.`game_blk`),2) AS `blk`,round(avg(`bs`.`game_pf`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_playoff_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`game_drb`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_playoff_stats` AS select `bs`.`season_year` AS `year`,min(`bs`.`pos`) AS `pos`,`bs`.`pid` AS `pid`,`p`.`name` AS `name`,`fs`.`team_name` AS `team`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts` from ((`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) join `ibl_franchise_seasons` `fs` on(`bs`.`teamid` = `fs`.`franchise_id` and `bs`.`season_year` = `fs`.`season_ending_year`)) where `bs`.`game_type` = 2 group by `bs`.`pid`,`p`.`name`,`bs`.`season_year`,`fs`.`team_name`;

CREATE OR REPLACE VIEW `ibl_rookie_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`game_drb`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` = 40 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_season_career_avgs` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,round(avg(`bs`.`game_min`),2) AS `minutes`,round(avg(`bs`.`calc_fg_made`),2) AS `fgm`,round(avg(`bs`.`game_2ga` + `bs`.`game_3ga`),2) AS `fga`,case when sum(`bs`.`game_2ga` + `bs`.`game_3ga`) > 0 then round(sum(`bs`.`calc_fg_made`) / sum(`bs`.`game_2ga` + `bs`.`game_3ga`),3) else 0.000 end AS `fgpct`,round(avg(`bs`.`game_ftm`),2) AS `ftm`,round(avg(`bs`.`game_fta`),2) AS `fta`,case when sum(`bs`.`game_fta`) > 0 then round(sum(`bs`.`game_ftm`) / sum(`bs`.`game_fta`),3) else 0.000 end AS `ftpct`,round(avg(`bs`.`game_3gm`),2) AS `tgm`,round(avg(`bs`.`game_3ga`),2) AS `tga`,case when sum(`bs`.`game_3ga`) > 0 then round(sum(`bs`.`game_3gm`) / sum(`bs`.`game_3ga`),3) else 0.000 end AS `tpct`,round(avg(`bs`.`game_orb`),2) AS `orb`,round(avg(`bs`.`game_drb`),2) AS `drb`,round(avg(`bs`.`calc_rebounds`),2) AS `reb`,round(avg(`bs`.`game_ast`),2) AS `ast`,round(avg(`bs`.`game_stl`),2) AS `stl`,round(avg(`bs`.`game_tov`),2) AS `tvr`,round(avg(`bs`.`game_blk`),2) AS `blk`,round(avg(`bs`.`game_pf`),2) AS `pf`,round(avg(`bs`.`calc_points`),2) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`game_type` = 1 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_sophomore_career_totals` AS select `bs`.`pid` AS `pid`,`p`.`name` AS `name`,cast(count(0) as signed) AS `games`,cast(sum(`bs`.`game_min`) as signed) AS `minutes`,cast(sum(`bs`.`calc_fg_made`) as signed) AS `fgm`,cast(sum(`bs`.`game_2ga` + `bs`.`game_3ga`) as signed) AS `fga`,cast(sum(`bs`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bs`.`game_fta`) as signed) AS `fta`,cast(sum(`bs`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bs`.`game_3ga`) as signed) AS `tga`,cast(sum(`bs`.`game_orb`) as signed) AS `orb`,cast(sum(`bs`.`game_drb`) as signed) AS `drb`,cast(sum(`bs`.`calc_rebounds`) as signed) AS `reb`,cast(sum(`bs`.`game_ast`) as signed) AS `ast`,cast(sum(`bs`.`game_stl`) as signed) AS `stl`,cast(sum(`bs`.`game_tov`) as signed) AS `tvr`,cast(sum(`bs`.`game_blk`) as signed) AS `blk`,cast(sum(`bs`.`game_pf`) as signed) AS `pf`,cast(sum(`bs`.`calc_points`) as signed) AS `pts`,`p`.`retired` AS `retired` from (`ibl_box_scores` `bs` join `ibl_plr` `p` on(`bs`.`pid` = `p`.`pid`)) where `bs`.`teamid` = 41 group by `bs`.`pid`,`p`.`name`,`p`.`retired`;

CREATE OR REPLACE VIEW `ibl_team_defense_stats` AS select `fs`.`franchise_id` AS `teamid`,`fs`.`team_name` AS `name`,`my`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`opp`.`game_min`) as signed) AS `minutes`,cast(sum(`opp`.`game_2gm` + `opp`.`game_3gm`) as signed) AS `fgm`,cast(sum(`opp`.`game_2ga` + `opp`.`game_3ga`) as signed) AS `fga`,cast(sum(`opp`.`game_ftm`) as signed) AS `ftm`,cast(sum(`opp`.`game_fta`) as signed) AS `fta`,cast(sum(`opp`.`game_3gm`) as signed) AS `tgm`,cast(sum(`opp`.`game_3ga`) as signed) AS `tga`,cast(sum(`opp`.`game_orb`) as signed) AS `orb`,cast(sum(`opp`.`game_orb` + `opp`.`game_drb`) as signed) AS `reb`,cast(sum(`opp`.`game_ast`) as signed) AS `ast`,cast(sum(`opp`.`game_stl`) as signed) AS `stl`,cast(sum(`opp`.`game_tov`) as signed) AS `tvr`,cast(sum(`opp`.`game_blk`) as signed) AS `blk`,cast(sum(`opp`.`game_pf`) as signed) AS `pf` from ((`ibl_box_scores_teams` `my` join `ibl_box_scores_teams` `opp` on(`my`.`game_date` = `opp`.`game_date` and `my`.`visitor_teamid` = `opp`.`visitor_teamid` and `my`.`home_teamid` = `opp`.`home_teamid` and `my`.`game_of_that_day` = `opp`.`game_of_that_day` and `my`.`name` <> `opp`.`name`)) join `ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `my`.`name` and `fs`.`season_ending_year` = `my`.`season_year`)) where `my`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`my`.`season_year`;

CREATE OR REPLACE VIEW `ibl_team_offense_stats` AS select `fs`.`franchise_id` AS `teamid`,`fs`.`team_name` AS `name`,`bst`.`season_year` AS `season_year`,cast(count(0) as signed) AS `games`,cast(sum(`bst`.`game_min`) as signed) AS `minutes`,cast(sum(`bst`.`game_2gm` + `bst`.`game_3gm`) as signed) AS `fgm`,cast(sum(`bst`.`game_2ga` + `bst`.`game_3ga`) as signed) AS `fga`,cast(sum(`bst`.`game_ftm`) as signed) AS `ftm`,cast(sum(`bst`.`game_fta`) as signed) AS `fta`,cast(sum(`bst`.`game_3gm`) as signed) AS `tgm`,cast(sum(`bst`.`game_3ga`) as signed) AS `tga`,cast(sum(`bst`.`game_orb`) as signed) AS `orb`,cast(sum(`bst`.`game_orb` + `bst`.`game_drb`) as signed) AS `reb`,cast(sum(`bst`.`game_ast`) as signed) AS `ast`,cast(sum(`bst`.`game_stl`) as signed) AS `stl`,cast(sum(`bst`.`game_tov`) as signed) AS `tvr`,cast(sum(`bst`.`game_blk`) as signed) AS `blk`,cast(sum(`bst`.`game_pf`) as signed) AS `pf` from (`ibl_box_scores_teams` `bst` join `ibl_franchise_seasons` `fs` on(`fs`.`team_name` = `bst`.`name` and `fs`.`season_ending_year` = `bst`.`season_year`)) where `bst`.`game_type` = 1 group by `fs`.`franchise_id`,`fs`.`team_name`,`bst`.`season_year`;

CREATE OR REPLACE VIEW `ibl_team_win_loss` AS with unique_games as (select `ibl_box_scores_teams`.`game_date` AS `game_date`,`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`ibl_box_scores_teams`.`game_of_that_day` AS `game_of_that_day`,`ibl_box_scores_teams`.`visitor_q1_points` + `ibl_box_scores_teams`.`visitor_q2_points` + `ibl_box_scores_teams`.`visitor_q3_points` + `ibl_box_scores_teams`.`visitor_q4_points` + coalesce(`ibl_box_scores_teams`.`visitor_ot_points`,0) AS `visitor_total`,`ibl_box_scores_teams`.`home_q1_points` + `ibl_box_scores_teams`.`home_q2_points` + `ibl_box_scores_teams`.`home_q3_points` + `ibl_box_scores_teams`.`home_q4_points` + coalesce(`ibl_box_scores_teams`.`home_ot_points`,0) AS `home_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 1 group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`,`ibl_box_scores_teams`.`game_of_that_day`), team_games as (select `unique_games`.`visitor_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`visitor_total` > `unique_games`.`home_total`,1,0) AS `win`,if(`unique_games`.`visitor_total` < `unique_games`.`home_total`,1,0) AS `loss` from `unique_games` union all select `unique_games`.`home_teamid` AS `teamid`,`unique_games`.`game_date` AS `game_date`,if(`unique_games`.`home_total` > `unique_games`.`visitor_total`,1,0) AS `win`,if(`unique_games`.`home_total` < `unique_games`.`visitor_total`,1,0) AS `loss` from `unique_games`)select case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end AS `year`,`ti`.`team_name` AS `currentname`,coalesce(`fs`.`team_name`,`ti`.`team_name`) AS `namethatyear`,cast(sum(`tg`.`win`) as unsigned) AS `wins`,cast(sum(`tg`.`loss`) as unsigned) AS `losses` from ((`team_games` `tg` join `ibl_team_info` `ti` on(`ti`.`teamid` = `tg`.`teamid`)) left join `ibl_franchise_seasons` `fs` on(`fs`.`franchise_id` = `tg`.`teamid` and `fs`.`season_ending_year` = case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end)) group by `tg`.`teamid`,case when month(`tg`.`game_date`) >= 10 then year(`tg`.`game_date`) + 1 else year(`tg`.`game_date`) end,`ti`.`team_name`,coalesce(`fs`.`team_name`,`ti`.`team_name`);

CREATE OR REPLACE VIEW `vw_player_career_stats` AS select `p`.`uuid` AS `player_uuid`,`p`.`pid` AS `pid`,`p`.`name` AS `name`,`p`.`car_gm` AS `career_games`,`p`.`car_min` AS `career_minutes`,round(`p`.`car_fgm` * 2 + `p`.`car_tgm` + `p`.`car_ftm`,0) AS `career_points`,`p`.`car_orb` + `p`.`car_drb` AS `career_rebounds`,`p`.`car_ast` AS `career_assists`,`p`.`car_stl` AS `career_steals`,`p`.`car_blk` AS `career_blocks`,round((`p`.`car_fgm` * 2 + `p`.`car_tgm` + `p`.`car_ftm`) / nullif(`p`.`car_gm`,0),1) AS `ppg_career`,round((`p`.`car_orb` + `p`.`car_drb`) / nullif(`p`.`car_gm`,0),1) AS `rpg_career`,round(`p`.`car_ast` / nullif(`p`.`car_gm`,0),1) AS `apg_career`,round(`p`.`car_fgm` / nullif(`p`.`car_fga`,0),3) AS `fg_pct_career`,round(`p`.`car_ftm` / nullif(`p`.`car_fta`,0),3) AS `ft_pct_career`,round(`p`.`car_tgm` / nullif(`p`.`car_tga`,0),3) AS `three_pt_pct_career`,`p`.`car_playoff_min` AS `playoff_minutes`,`p`.`draftyear` AS `draft_year`,`p`.`draftround` AS `draft_round`,`p`.`draftpickno` AS `draft_pick`,`p`.`draftedby` AS `drafted_by_team`,`p`.`created_at` AS `created_at`,`p`.`updated_at` AS `updated_at` from `ibl_plr` `p`;

CREATE OR REPLACE VIEW `vw_playoff_series_results` AS with playoff_games as (select `ibl_box_scores_teams`.`game_date` AS `game_date`,year(`ibl_box_scores_teams`.`game_date`) AS `year`,`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`ibl_box_scores_teams`.`game_of_that_day` AS `game_of_that_day`,`ibl_box_scores_teams`.`visitor_q1_points` + `ibl_box_scores_teams`.`visitor_q2_points` + `ibl_box_scores_teams`.`visitor_q3_points` + `ibl_box_scores_teams`.`visitor_q4_points` + coalesce(`ibl_box_scores_teams`.`visitor_ot_points`,0) AS `v_total`,`ibl_box_scores_teams`.`home_q1_points` + `ibl_box_scores_teams`.`home_q2_points` + `ibl_box_scores_teams`.`home_q3_points` + `ibl_box_scores_teams`.`home_q4_points` + coalesce(`ibl_box_scores_teams`.`home_ot_points`,0) AS `h_total` from `ibl_box_scores_teams` where `ibl_box_scores_teams`.`game_type` = 2 group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`,`ibl_box_scores_teams`.`game_of_that_day`), game_results as (select `playoff_games`.`game_date` AS `game_date`,`playoff_games`.`year` AS `year`,`playoff_games`.`visitor_teamid` AS `visitor_teamid`,`playoff_games`.`home_teamid` AS `home_teamid`,`playoff_games`.`game_of_that_day` AS `game_of_that_day`,`playoff_games`.`v_total` AS `v_total`,`playoff_games`.`h_total` AS `h_total`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`visitor_teamid` else `playoff_games`.`home_teamid` end AS `winner_tid`,case when `playoff_games`.`v_total` > `playoff_games`.`h_total` then `playoff_games`.`home_teamid` else `playoff_games`.`visitor_teamid` end AS `loser_tid` from `playoff_games`), team_wins as (select `game_results`.`year` AS `year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_a`,greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_b`,`game_results`.`winner_tid` AS `winner_tid`,count(0) AS `wins`,row_number() over ( partition by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) order by count(0) desc) AS `rn` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),`game_results`.`winner_tid`), series_meta as (select `game_results`.`year` AS `year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_a`,greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`) AS `team_b`,count(0) AS `total_games`,min(`game_results`.`game_date`) AS `series_start`,dense_rank() over ( partition by `game_results`.`year` order by min(`game_results`.`game_date`)) AS `round` from `game_results` group by `game_results`.`year`,least(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`),greatest(`game_results`.`visitor_teamid`,`game_results`.`home_teamid`))select `sm`.`year` AS `year`,`sm`.`round` AS `round`,`tw`.`winner_tid` AS `winner_tid`,case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end AS `loser_tid`,`w`.`team_name` AS `winner`,`l`.`team_name` AS `loser`,`tw`.`wins` AS `winner_games`,`sm`.`total_games` - `tw`.`wins` AS `loser_games`,`sm`.`total_games` AS `total_games` from (((`series_meta` `sm` join `team_wins` `tw` on(`tw`.`year` = `sm`.`year` and `tw`.`team_a` = `sm`.`team_a` and `tw`.`team_b` = `sm`.`team_b` and `tw`.`rn` = 1)) join `ibl_team_info` `w` on(`w`.`teamid` = `tw`.`winner_tid`)) join `ibl_team_info` `l` on(`l`.`teamid` = case when `tw`.`winner_tid` = `sm`.`team_a` then `sm`.`team_b` else `sm`.`team_a` end)) order by `sm`.`year` desc,`sm`.`round`;

CREATE OR REPLACE VIEW `vw_schedule_upcoming` AS select `sch`.`uuid` AS `game_uuid`,`sch`.`id` AS `schedule_id`,`sch`.`season_year` AS `season_year`,`sch`.`game_date` AS `game_date`,`sch`.`box_id` AS `box_score_id`,coalesce(`bst`.`game_of_that_day`,0) AS `game_of_that_day`,`tv`.`uuid` AS `visitor_uuid`,`tv`.`teamid` AS `visitor_team_id`,`tv`.`team_city` AS `visitor_city`,`tv`.`team_name` AS `visitor_name`,concat(`tv`.`team_city`,' ',`tv`.`team_name`) AS `visitor_full_name`,`sch`.`visitor_score` AS `visitor_score`,`th`.`uuid` AS `home_uuid`,`th`.`teamid` AS `home_team_id`,`th`.`team_city` AS `home_city`,`th`.`team_name` AS `home_name`,concat(`th`.`team_city`,' ',`th`.`team_name`) AS `home_full_name`,`sch`.`home_score` AS `home_score`,case when `sch`.`visitor_score` = 0 and `sch`.`home_score` = 0 then 'scheduled' else 'completed' end AS `game_status`,`sch`.`created_at` AS `created_at`,`sch`.`updated_at` AS `updated_at` from (((`ibl_schedule` `sch` join `ibl_team_info` `tv` on(`sch`.`visitor_teamid` = `tv`.`teamid`)) join `ibl_team_info` `th` on(`sch`.`home_teamid` = `th`.`teamid`)) left join (select `ibl_box_scores_teams`.`game_date` AS `game_date`,`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,min(`ibl_box_scores_teams`.`game_of_that_day`) AS `game_of_that_day` from `ibl_box_scores_teams` group by `ibl_box_scores_teams`.`game_date`,`ibl_box_scores_teams`.`visitor_teamid`,`ibl_box_scores_teams`.`home_teamid`) `bst` on(`bst`.`game_date` = `sch`.`game_date` and `bst`.`visitor_teamid` = `sch`.`visitor_teamid` and `bst`.`home_teamid` = `sch`.`home_teamid`));

CREATE OR REPLACE VIEW `vw_series_records` AS select `t`.`self` AS `self`,`t`.`opponent` AS `opponent`,sum(`t`.`wins`) AS `wins`,sum(`t`.`losses`) AS `losses` from (select `ibl_schedule`.`home_teamid` AS `self`,`ibl_schedule`.`visitor_teamid` AS `opponent`,count(0) AS `wins`,0 AS `losses` from `ibl_schedule` where `ibl_schedule`.`home_score` > `ibl_schedule`.`visitor_score` group by `ibl_schedule`.`home_teamid`,`ibl_schedule`.`visitor_teamid` union all select `ibl_schedule`.`visitor_teamid` AS `self`,`ibl_schedule`.`home_teamid` AS `opponent`,count(0) AS `wins`,0 AS `losses` from `ibl_schedule` where `ibl_schedule`.`visitor_score` > `ibl_schedule`.`home_score` group by `ibl_schedule`.`visitor_teamid`,`ibl_schedule`.`home_teamid` union all select `ibl_schedule`.`home_teamid` AS `self`,`ibl_schedule`.`visitor_teamid` AS `opponent`,0 AS `wins`,count(0) AS `losses` from `ibl_schedule` where `ibl_schedule`.`home_score` < `ibl_schedule`.`visitor_score` group by `ibl_schedule`.`home_teamid`,`ibl_schedule`.`visitor_teamid` union all select `ibl_schedule`.`visitor_teamid` AS `self`,`ibl_schedule`.`home_teamid` AS `opponent`,0 AS `wins`,count(0) AS `losses` from `ibl_schedule` where `ibl_schedule`.`visitor_score` < `ibl_schedule`.`home_score` group by `ibl_schedule`.`visitor_teamid`,`ibl_schedule`.`home_teamid`) `t` group by `t`.`self`,`t`.`opponent`;

CREATE OR REPLACE VIEW `vw_team_total_score` AS select `ibl_box_scores_teams`.`game_date` AS `game_date`,`ibl_box_scores_teams`.`visitor_teamid` AS `visitor_teamid`,`ibl_box_scores_teams`.`home_teamid` AS `home_teamid`,`ibl_box_scores_teams`.`game_type` AS `game_type`,`ibl_box_scores_teams`.`visitor_q1_points` + `ibl_box_scores_teams`.`visitor_q2_points` + `ibl_box_scores_teams`.`visitor_q3_points` + `ibl_box_scores_teams`.`visitor_q4_points` + coalesce(`ibl_box_scores_teams`.`visitor_ot_points`,0) AS `visitorScore`,`ibl_box_scores_teams`.`home_q1_points` + `ibl_box_scores_teams`.`home_q2_points` + `ibl_box_scores_teams`.`home_q3_points` + `ibl_box_scores_teams`.`home_q4_points` + coalesce(`ibl_box_scores_teams`.`home_ot_points`,0) AS `homeScore` from `ibl_box_scores_teams`;

CREATE OR REPLACE VIEW `vw_team_awards` AS select `ibl_team_awards`.`year` AS `year`,`ibl_team_awards`.`name` AS `name`,`ibl_team_awards`.`award` AS `award`,`ibl_team_awards`.`id` AS `id` from `ibl_team_awards` union all select `ranked`.`year` AS `year`,`ranked`.`name` AS `name`,'IBL Champions' AS `award`,0 AS `id` from (select `psr`.`year` AS `year`,`psr`.`winner` AS `name`,`psr`.`round` AS `round`,max(`psr`.`round`) over ( partition by `psr`.`year`) AS `max_round`,count(0) over ( partition by `psr`.`year`,`psr`.`round`) AS `series_in_round` from `vw_playoff_series_results` `psr`) `ranked` where `ranked`.`round` = `ranked`.`max_round` and `ranked`.`series_in_round` = 1 union all select `hc`.`year` AS `year`,`ti`.`team_name` AS `name`,'IBL HEAT Champions' AS `award`,0 AS `id` from ((select year(`bst`.`game_date`) AS `year`,case when `bst`.`home_q1_points` + `bst`.`home_q2_points` + `bst`.`home_q3_points` + `bst`.`home_q4_points` + coalesce(`bst`.`home_ot_points`,0) > `bst`.`visitor_q1_points` + `bst`.`visitor_q2_points` + `bst`.`visitor_q3_points` + `bst`.`visitor_q4_points` + coalesce(`bst`.`visitor_ot_points`,0) then `bst`.`home_teamid` else `bst`.`visitor_teamid` end AS `winner_tid`,row_number() over ( partition by year(`bst`.`game_date`) order by `bst`.`game_date` desc,`bst`.`game_of_that_day`) AS `rn` from `ibl_box_scores_teams` `bst` where `bst`.`game_type` = 3) `hc` join `ibl_team_info` `ti` on(`ti`.`teamid` = `hc`.`winner_tid`)) where `hc`.`rn` = 1;
