-- On This Day: precomputed moments, rebuilt nightly by RebuildOnThisDayMomentsStep.
-- Read by the homepage card and PostOnThisDayStep with one indexed query per radius.
-- Derived data only. Truncate-and-rebuild is always safe; the source of truth is
-- ibl_box_scores, ibl_box_scores_teams, ibl_hist/trade rows and ibl_plr.
-- Rollback (never run here): DROP TABLE ibl_on_this_day_moments;
CREATE TABLE IF NOT EXISTS ibl_on_this_day_moments (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    moment_key VARCHAR(96) NOT NULL,
    kind VARCHAR(32) NOT NULL,
    game_date DATE NOT NULL,
    month_day CHAR(5) NOT NULL,
    season_year SMALLINT UNSIGNED NOT NULL,
    pid INT NOT NULL DEFAULT 0,
    player_name VARCHAR(64) NOT NULL DEFAULT '',
    teamid INT NOT NULL DEFAULT 0,
    team_name VARCHAR(64) NOT NULL DEFAULT '',
    opponent_teamid INT NOT NULL DEFAULT 0,
    opponent_name VARCHAR(64) NOT NULL DEFAULT '',
    facts_json TEXT NOT NULL,
    stat_label VARCHAR(32) NULL DEFAULT NULL,
    built_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_otd_moment_key (moment_key),
    KEY idx_otd_month_day_season (month_day, season_year)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
