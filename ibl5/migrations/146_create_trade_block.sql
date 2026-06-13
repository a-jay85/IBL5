-- Migration 146: Trade Block / availability board.
-- gm_trade_block: per-player availability (pid PK), team derived at read time via JOIN to ibl_plr.
-- gm_trade_seeking: per-team free-text "seeking" note (teamid PK, 0..1 per team).
-- Signedness matches FK targets: ibl_plr.pid int(11) signed, ibl_team_info.teamid int(11) signed.
-- note is NOT NULL DEFAULT '' (matches setOnBlock(int, string) — always a string, no nullable-bind footgun).
-- adr-check: plain CREATE TABLE, not destructive.

CREATE TABLE IF NOT EXISTS `gm_trade_block` (
  `pid` int(11) NOT NULL,
  `note` varchar(255) NOT NULL DEFAULT '',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`pid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `gm_trade_block` DROP FOREIGN KEY IF EXISTS `fk_trade_block_player`;
ALTER TABLE `gm_trade_block`
  ADD CONSTRAINT `fk_trade_block_player` FOREIGN KEY (`pid`)
    REFERENCES `ibl_plr` (`pid`) ON DELETE CASCADE ON UPDATE CASCADE;

CREATE TABLE IF NOT EXISTS `gm_trade_seeking` (
  `teamid` int(11) NOT NULL,
  `seeking_note` varchar(255) NOT NULL DEFAULT '',
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`teamid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `gm_trade_seeking` DROP FOREIGN KEY IF EXISTS `fk_trade_seeking_team`;
ALTER TABLE `gm_trade_seeking`
  ADD CONSTRAINT `fk_trade_seeking_team` FOREIGN KEY (`teamid`)
    REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE ON UPDATE CASCADE;
