-- On This Day: shown-moment history. One row per moment key per sim that showed it.
-- Written by RecordOnThisDayShownStep on every updater run; read by OnThisDayService
-- to keep a moment from repeating in a later sim. Not FK'd to ibl_on_this_day_moments,
-- which is wiped and rebuilt nightly; moment_key is the stable identity string.
-- Rollback (never run here): remove the ibl_on_this_day_shown table. Old code never reads it.
CREATE TABLE IF NOT EXISTS ibl_on_this_day_shown (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    moment_key VARCHAR(96) NOT NULL,
    sim INT UNSIGNED NOT NULL,
    season_year SMALLINT UNSIGNED NOT NULL,
    shown_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    UNIQUE KEY uq_otd_shown_key_sim (moment_key, sim),
    KEY idx_otd_shown_sim (sim)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
