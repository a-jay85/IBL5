-- Migration 156 — structured per-game sim recap storage.
--
-- Migration 155 created ibl_sim_summaries as a single recap_text blob. The recap
-- generator now emits a structured document: league-wide intro prose, one recap
-- per game, then league-wide outro prose. 155 is already merged and recorded as
-- applied on long-lived databases, so the runner (which tracks by filename) would
-- never re-run an amended 155 — this ships the change forward as its own migration.
--
-- Forward-only, purely additive: two nullable columns plus one new child table.
-- No destructive DDL.

ALTER TABLE `ibl_sim_summaries`
    ADD COLUMN IF NOT EXISTS `intro_text` MEDIUMTEXT NULL COMMENT 'League-wide intro prose bracketing the per-game recaps.' AFTER `recap_text`,
    ADD COLUMN IF NOT EXISTS `outro_text` MEDIUMTEXT NULL COMMENT 'League-wide outro prose bracketing the per-game recaps.' AFTER `intro_text`;

CREATE TABLE IF NOT EXISTS `ibl_sim_game_recaps` (
    `id`               INT UNSIGNED      NOT NULL AUTO_INCREMENT COMMENT 'Surrogate PK.',
    `sim`              INT UNSIGNED      NOT NULL COMMENT 'FK to ibl_sim_summaries.sim (the envelope).',
    `season_year`      SMALLINT UNSIGNED NOT NULL COMMENT 'Season year — first component of the natural game key.',
    `game_date`        DATE              NOT NULL COMMENT 'Game date — natural-key component.',
    `visitor_teamid`   INT               NOT NULL COMMENT 'Visitor team id — natural-key component.',
    `home_teamid`      INT               NOT NULL COMMENT 'Home team id — natural-key component.',
    `game_of_that_day` INT               NOT NULL DEFAULT 0 COMMENT 'Nth game of that matchup that day; NULL->0 normalised (matches ibl_box_scores_teams).',
    `box_id`           INT               NULL COMMENT 'Convenience pointer to the box score when known; NULL when unresolved.',
    `sort_order`       SMALLINT UNSIGNED NOT NULL COMMENT 'Presentation order within the sim.',
    `recap_text`       MEDIUMTEXT        NOT NULL COMMENT 'The per-game recap prose.',
    `created_at`       DATETIME          NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT 'Row creation timestamp.',
    PRIMARY KEY (`id`),
    UNIQUE KEY `uniq_game` (`season_year`, `game_date`, `visitor_teamid`, `home_teamid`, `game_of_that_day`) COMMENT 'One recap per game — the natural game key.',
    KEY `idx_sim` (`sim`, `sort_order`) COMMENT 'Ordered read-back of a sim''s game recaps.',
    KEY `idx_game` (`game_date`, `visitor_teamid`, `home_teamid`) COMMENT 'Join to ibl_box_scores_teams by natural key.',
    CONSTRAINT `fk_sgr_sim` FOREIGN KEY (`sim`) REFERENCES `ibl_sim_summaries` (`sim`) ON DELETE CASCADE,
    CONSTRAINT `fk_sgr_visitor` FOREIGN KEY (`visitor_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE,
    CONSTRAINT `fk_sgr_home` FOREIGN KEY (`home_teamid`) REFERENCES `ibl_team_info` (`teamid`) ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
