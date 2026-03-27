ALTER TABLE ibl_jsb_retired_players
    ADD COLUMN retirement_year SMALLINT NOT NULL DEFAULT 0 AFTER player_name;

ALTER TABLE ibl_jsb_retired_players
    DROP INDEX uk_jsb_pid,
    ADD UNIQUE KEY uk_jsb_pid_year (jsb_pid, retirement_year);
