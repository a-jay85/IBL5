-- Migration 134: Create the JSB native-engine SHADOW box-score tables.
--
-- PR8 of the native-engine program runs the compiled Go sim in SHADOW mode
-- (never a cutover): the canonical `.sco` -> ibl_box_scores / ibl_box_scores_teams
-- path stays authoritative, and the engine's output is written here for in-DB
-- engine-vs-JSB comparison across a season. These tables are intentionally
-- droppable and trimmed.
--
-- They mirror only the RAW columns the engine emits plus the identity keys used
-- to JOIN back to canonical -- NO generated columns (calc_*, game_type,
-- season_year), no name/uuid/attendance/capacity, and no FK constraints
-- (canonical itself indexes pid/teamid as MUL, not FK). Stats are plain nullable
-- integers; sim_seed / sim_game_type are carried for replay + diagnostics.
--
-- Identity convention matches canonical exactly: visitor_teamid / home_teamid are
-- the ACTUAL schedule visitor/home (NOT a lower-id swap -- ibl_schedule can carry
-- visitor_teamid > home_teamid, e.g. the 2026-03-10 seed row 3@1), and the team
-- table stores two rows per game (visitor inserted first, then home), each row
-- carrying its own shooting stats plus both teams' quarter points. Unlike
-- canonical -- which disambiguates its two team rows only by insert order and the
-- unreliable `name` -- the shadow team table adds an explicit `teamid` column so
-- the diff is self-describing.

CREATE TABLE IF NOT EXISTS `ibl_box_scores_engine_shadow` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_date` DATE NOT NULL,
    `visitor_teamid` INT NOT NULL,
    `home_teamid` INT NOT NULL,
    `game_of_that_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `pid` INT NOT NULL,
    `teamid` INT NULL,
    `pos` VARCHAR(5) NULL,
    `game_min` SMALLINT UNSIGNED NULL,
    `game_2gm` SMALLINT UNSIGNED NULL,
    `game_2ga` SMALLINT UNSIGNED NULL,
    `game_ftm` SMALLINT UNSIGNED NULL,
    `game_fta` SMALLINT UNSIGNED NULL,
    `game_3gm` SMALLINT UNSIGNED NULL,
    `game_3ga` SMALLINT UNSIGNED NULL,
    `game_orb` SMALLINT UNSIGNED NULL,
    `game_drb` SMALLINT UNSIGNED NULL,
    `game_ast` SMALLINT UNSIGNED NULL,
    `game_stl` SMALLINT UNSIGNED NULL,
    `game_tov` SMALLINT UNSIGNED NULL,
    `game_blk` SMALLINT UNSIGNED NULL,
    `game_pf` SMALLINT UNSIGNED NULL,
    `sim_seed` BIGINT UNSIGNED NULL,
    `sim_game_type` TINYINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_shadow_game` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`),
    INDEX `idx_shadow_pid` (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `ibl_box_scores_engine_shadow_teams` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `game_date` DATE NOT NULL,
    `visitor_teamid` INT NOT NULL,
    `home_teamid` INT NOT NULL,
    `game_of_that_day` TINYINT UNSIGNED NOT NULL DEFAULT 1,
    `teamid` INT NOT NULL,
    `game_2gm` SMALLINT UNSIGNED NULL,
    `game_2ga` SMALLINT UNSIGNED NULL,
    `game_ftm` SMALLINT UNSIGNED NULL,
    `game_fta` SMALLINT UNSIGNED NULL,
    `game_3gm` SMALLINT UNSIGNED NULL,
    `game_3ga` SMALLINT UNSIGNED NULL,
    `game_orb` SMALLINT UNSIGNED NULL,
    `game_drb` SMALLINT UNSIGNED NULL,
    `game_ast` SMALLINT UNSIGNED NULL,
    `game_stl` SMALLINT UNSIGNED NULL,
    `game_tov` SMALLINT UNSIGNED NULL,
    `game_blk` SMALLINT UNSIGNED NULL,
    `game_pf` SMALLINT UNSIGNED NULL,
    `visitor_q1_points` SMALLINT UNSIGNED NULL,
    `visitor_q2_points` SMALLINT UNSIGNED NULL,
    `visitor_q3_points` SMALLINT UNSIGNED NULL,
    `visitor_q4_points` SMALLINT UNSIGNED NULL,
    `visitor_ot_points` SMALLINT UNSIGNED NULL,
    `home_q1_points` SMALLINT UNSIGNED NULL,
    `home_q2_points` SMALLINT UNSIGNED NULL,
    `home_q3_points` SMALLINT UNSIGNED NULL,
    `home_q4_points` SMALLINT UNSIGNED NULL,
    `home_ot_points` SMALLINT UNSIGNED NULL,
    `sim_seed` BIGINT UNSIGNED NULL,
    `sim_game_type` TINYINT UNSIGNED NULL,
    PRIMARY KEY (`id`),
    INDEX `idx_shadow_teams_game` (`game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
