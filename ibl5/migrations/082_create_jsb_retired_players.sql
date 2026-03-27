CREATE TABLE IF NOT EXISTS ibl_jsb_retired_players (
    id          INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    jsb_pid     INT          NOT NULL,
    player_name VARCHAR(64)  NOT NULL,
    pid         INT          DEFAULT NULL,
    created_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at  TIMESTAMP    NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uk_jsb_pid (jsb_pid)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
