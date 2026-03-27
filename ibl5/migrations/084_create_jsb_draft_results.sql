CREATE TABLE IF NOT EXISTS ibl_jsb_draft_results (
    id          INT UNSIGNED     NOT NULL AUTO_INCREMENT PRIMARY KEY,
    draft_year  SMALLINT         NOT NULL,
    round       TINYINT UNSIGNED NOT NULL,
    pick        TINYINT UNSIGNED NOT NULL,
    team_name   VARCHAR(64)      NOT NULL,
    pos         VARCHAR(2)       NOT NULL,
    player_name VARCHAR(64)      NOT NULL,
    pid         INT              DEFAULT NULL,
    created_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP        NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_draft_year_round_pick (draft_year, round, pick)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
