-- Migration 147: Create gm_player_watchlist — per-GM private watchlist with optional note.
-- Keyed by (teamid, pid). Signedness: teamid int(11)/int(11) → ibl_team_info(teamid);
-- pid int(11)/int(11) → ibl_plr(pid). Both CASCADE (a deleted team/player drops its rows).
-- Composite PK enforces one watch row per (GM, player) — idempotent re-watch.
-- Plain CREATE TABLE IF NOT EXISTS is non-destructive (no DROP) → bin/adr-check does not fire.
CREATE TABLE IF NOT EXISTS `gm_player_watchlist` (
    `teamid` INT NOT NULL,
    `pid` INT NOT NULL,
    `note` TEXT NULL,
    `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`teamid`, `pid`),
    INDEX `idx_watchlist_pid` (`pid`),
    CONSTRAINT `fk_watchlist_team` FOREIGN KEY (`teamid`)
        REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT `fk_watchlist_player` FOREIGN KEY (`pid`)
        REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
