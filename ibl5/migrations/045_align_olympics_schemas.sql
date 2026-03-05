-- Migration 045: Align Olympics table schemas with current IBL tables
-- The Olympics tables created in migration 043 are missing columns that were
-- added to the IBL tables since then. This migration adds the missing columns.

-- ============================================================================
-- ibl_olympics_standings: add wins/losses columns
-- ============================================================================
ALTER TABLE `ibl_olympics_standings`
    ADD COLUMN `wins` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total wins' AFTER `team_name`,
    ADD COLUMN `losses` TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Total losses' AFTER `wins`;

-- ============================================================================
-- ibl_olympics_box_scores: add id, generated columns, game metadata, and teamID
-- ============================================================================
ALTER TABLE `ibl_olympics_box_scores`
    ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (`id`),
    ADD COLUMN `game_type` TINYINT UNSIGNED
        GENERATED ALWAYS AS (CASE
            WHEN MONTH(`Date`) = 6 THEN 2
            WHEN MONTH(`Date`) = 10 THEN 3
            WHEN MONTH(`Date`) = 0 THEN 0
            ELSE 1
        END) STORED AFTER `id`,
    ADD COLUMN `season_year` SMALLINT UNSIGNED
        GENERATED ALWAYS AS (CASE
            WHEN YEAR(`Date`) = 0 THEN 0
            WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
            ELSE YEAR(`Date`)
        END) STORED AFTER `game_type`,
    ADD COLUMN `calc_points` SMALLINT UNSIGNED
        GENERATED ALWAYS AS ((`game2GM` * 2) + `gameFTM` + (`game3GM` * 3)) STORED AFTER `season_year`,
    ADD COLUMN `calc_rebounds` TINYINT UNSIGNED
        GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,
    ADD COLUMN `calc_fg_made` TINYINT UNSIGNED
        GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`,
    ADD COLUMN `gameOfThatDay` TINYINT UNSIGNED DEFAULT NULL COMMENT 'Game number for that date (1st, 2nd game)',
    ADD COLUMN `attendance` INT DEFAULT NULL COMMENT 'Attendance at the game',
    ADD COLUMN `capacity` INT DEFAULT NULL COMMENT 'Arena capacity',
    ADD COLUMN `visitorWins` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team wins before this game',
    ADD COLUMN `visitorLosses` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Visitor team losses before this game',
    ADD COLUMN `homeWins` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team wins before this game',
    ADD COLUMN `homeLosses` SMALLINT UNSIGNED DEFAULT NULL COMMENT 'Home team losses before this game',
    ADD COLUMN `teamID` INT DEFAULT NULL COMMENT 'Player''s team ID (visitor or home)',
    ADD KEY `idx_gt_points` (`game_type`, `calc_points`),
    ADD KEY `idx_gt_rebounds` (`game_type`, `calc_rebounds`),
    ADD KEY `idx_gt_fg_made` (`game_type`, `calc_fg_made`),
    ADD KEY `idx_gt_ast` (`game_type`, `gameAST`),
    ADD KEY `idx_gt_stl` (`game_type`, `gameSTL`),
    ADD KEY `idx_gt_blk` (`game_type`, `gameBLK`),
    ADD KEY `idx_gt_tov` (`game_type`, `gameTOV`),
    ADD KEY `idx_gt_ftm` (`game_type`, `gameFTM`),
    ADD KEY `idx_gt_3gm` (`game_type`, `game3GM`),
    ADD KEY `idx_team_id` (`teamID`),
    ADD KEY `idx_gt_pid` (`game_type`, `pid`);

-- ============================================================================
-- ibl_olympics_box_scores_teams: add id and generated columns
-- ============================================================================
ALTER TABLE `ibl_olympics_box_scores_teams`
    ADD COLUMN `id` INT NOT NULL AUTO_INCREMENT FIRST,
    ADD PRIMARY KEY (`id`),
    ADD COLUMN `game_type` TINYINT UNSIGNED
        GENERATED ALWAYS AS (CASE
            WHEN MONTH(`Date`) = 6 THEN 2
            WHEN MONTH(`Date`) = 10 THEN 3
            WHEN MONTH(`Date`) = 0 THEN 0
            ELSE 1
        END) STORED AFTER `id`,
    ADD COLUMN `season_year` SMALLINT UNSIGNED
        GENERATED ALWAYS AS (CASE
            WHEN YEAR(`Date`) = 0 THEN 0
            WHEN MONTH(`Date`) >= 10 THEN YEAR(`Date`) + 1
            ELSE YEAR(`Date`)
        END) STORED AFTER `game_type`,
    ADD COLUMN `calc_points` SMALLINT UNSIGNED
        GENERATED ALWAYS AS ((`game2GM` * 2) + `gameFTM` + (`game3GM` * 3)) STORED AFTER `season_year`,
    ADD COLUMN `calc_rebounds` SMALLINT UNSIGNED
        GENERATED ALWAYS AS (`gameORB` + `gameDRB`) STORED AFTER `calc_points`,
    ADD COLUMN `calc_fg_made` TINYINT UNSIGNED
        GENERATED ALWAYS AS (`game2GM` + `game3GM`) STORED AFTER `calc_rebounds`,
    ADD KEY `idx_gt_points` (`game_type`, `calc_points`),
    ADD KEY `idx_gt_rebounds` (`game_type`, `calc_rebounds`),
    ADD KEY `idx_gt_fg_made` (`game_type`, `calc_fg_made`),
    ADD KEY `idx_gt_ast` (`game_type`, `gameAST`),
    ADD KEY `idx_gt_stl` (`game_type`, `gameSTL`),
    ADD KEY `idx_gt_blk` (`game_type`, `gameBLK`),
    ADD KEY `idx_gt_tov` (`game_type`, `gameTOV`),
    ADD KEY `idx_gt_ftm` (`game_type`, `gameFTM`),
    ADD KEY `idx_gt_3gm` (`game_type`, `game3GM`);

-- ============================================================================
-- ibl_olympics_power: add SOS columns
-- ============================================================================
ALTER TABLE `ibl_olympics_power`
    ADD COLUMN `sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 AFTER `streak`,
    ADD COLUMN `remaining_sos` DECIMAL(4,3) NOT NULL DEFAULT 0.000 AFTER `sos`,
    ADD COLUMN `sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `remaining_sos`,
    ADD COLUMN `remaining_sos_rank` TINYINT UNSIGNED NOT NULL DEFAULT 0 AFTER `sos_rank`;

-- ============================================================================
-- ibl_olympics_plr: add UUID trigger (CREATE TABLE LIKE doesn't copy triggers)
-- ============================================================================
DELIMITER //
CREATE TRIGGER `ibl_olympics_plr_before_insert_uuid` BEFORE INSERT ON `ibl_olympics_plr`
FOR EACH ROW
BEGIN
    IF NEW.uuid IS NULL OR NEW.uuid = '' THEN
        SET NEW.uuid = UUID();
    END IF;
END//
DELIMITER ;
