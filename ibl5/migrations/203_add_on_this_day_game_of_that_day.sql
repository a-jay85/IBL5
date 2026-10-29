-- On This Day: store the league-wide game ordinal so moments can link to their box score.
-- Derived table, truncate-and-rebuild nightly; the next rebuild fills the column.
-- 0 means no ordinal known: the formatter renders no box-score link for that moment.
-- Idempotent: ADD COLUMN IF NOT EXISTS, safe on DBs that already ran the create migration.
-- Rollback (never run here): ALTER TABLE ibl_on_this_day_moments DROP COLUMN game_of_that_day;
ALTER TABLE ibl_on_this_day_moments
    ADD COLUMN IF NOT EXISTS game_of_that_day TINYINT UNSIGNED NOT NULL DEFAULT 0
    AFTER opponent_name;
