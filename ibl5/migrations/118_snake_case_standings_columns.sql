-- Migration 118: Tier 3c — snake_case standings columns.
-- Renames 22 camelCase columns on ibl_standings and ibl_olympics_standings.
-- CHECK constraints auto-updated by CHANGE COLUMN (verified in MariaDB 10.11).
-- vw_team_standings recreated at end with updated source column names.

ALTER TABLE `ibl_standings`
  CHANGE COLUMN `leagueRecord`       `league_record`       varchar(5) DEFAULT '',
  CHANGE COLUMN `confRecord`         `conf_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `confGB`             `conf_gb`             decimal(3,1) DEFAULT NULL,
  CHANGE COLUMN `divRecord`          `div_record`          varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `divGB`              `div_gb`              decimal(3,1) DEFAULT NULL,
  CHANGE COLUMN `homeRecord`         `home_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `awayRecord`         `away_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `gamesUnplayed`      `games_unplayed`      tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confWins`           `conf_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confLosses`         `conf_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `divWins`            `div_wins`            tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `divLosses`          `div_losses`          tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `homeWins`           `home_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `homeLosses`         `home_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `awayWins`           `away_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `awayLosses`         `away_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confMagicNumber`    `conf_magic_number`   tinyint(4) DEFAULT NULL,
  CHANGE COLUMN `divMagicNumber`     `div_magic_number`    tinyint(4) DEFAULT NULL,
  CHANGE COLUMN `clinchedConference` `clinched_conference` tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedDivision`   `clinched_division`   tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedPlayoffs`   `clinched_playoffs`   tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedLeague`     `clinched_league`     tinyint(1) DEFAULT NULL;

ALTER TABLE `ibl_olympics_standings`
  CHANGE COLUMN `leagueRecord`       `league_record`       varchar(5) DEFAULT '',
  CHANGE COLUMN `confRecord`         `conf_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `confGB`             `conf_gb`             decimal(3,1) DEFAULT NULL,
  CHANGE COLUMN `divRecord`          `div_record`          varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `divGB`              `div_gb`              decimal(3,1) DEFAULT NULL,
  CHANGE COLUMN `homeRecord`         `home_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `awayRecord`         `away_record`         varchar(5) NOT NULL DEFAULT '',
  CHANGE COLUMN `gamesUnplayed`      `games_unplayed`      tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confWins`           `conf_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confLosses`         `conf_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `divWins`            `div_wins`            tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `divLosses`          `div_losses`          tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `homeWins`           `home_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `homeLosses`         `home_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `awayWins`           `away_wins`           tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `awayLosses`         `away_losses`         tinyint(3) unsigned DEFAULT NULL,
  CHANGE COLUMN `confMagicNumber`    `conf_magic_number`   tinyint(4) DEFAULT NULL,
  CHANGE COLUMN `divMagicNumber`     `div_magic_number`    tinyint(4) DEFAULT NULL,
  CHANGE COLUMN `clinchedConference` `clinched_conference` tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedDivision`   `clinched_division`   tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedPlayoffs`   `clinched_playoffs`   tinyint(1) DEFAULT NULL,
  CHANGE COLUMN `clinchedLeague`     `clinched_league`     tinyint(1) DEFAULT NULL;

-- Recreate vw_team_standings with updated source column names.
-- Output aliases unchanged — view consumers see the same shape.
CREATE OR REPLACE VIEW `vw_team_standings` AS
SELECT
  `t`.`uuid`                AS `team_uuid`,
  `t`.`teamid`              AS `teamid`,
  `t`.`team_city`           AS `team_city`,
  `t`.`team_name`           AS `team_name`,
  CONCAT(`t`.`team_city`, ' ', `t`.`team_name`) AS `full_team_name`,
  `t`.`owner_name`          AS `owner_name`,
  `s`.`league_record`       AS `league_record`,
  `s`.`pct`                 AS `win_percentage`,
  `s`.`conference`          AS `conference`,
  `s`.`conf_record`         AS `conference_record`,
  `s`.`conf_gb`             AS `conference_games_back`,
  `s`.`division`            AS `division`,
  `s`.`div_record`          AS `division_record`,
  `s`.`div_gb`              AS `division_games_back`,
  `s`.`home_wins`           AS `home_wins`,
  `s`.`home_losses`         AS `home_losses`,
  `s`.`away_wins`           AS `away_wins`,
  `s`.`away_losses`         AS `away_losses`,
  CONCAT(`s`.`home_wins`, '-', `s`.`home_losses`) AS `home_record`,
  CONCAT(`s`.`away_wins`, '-', `s`.`away_losses`) AS `away_record`,
  `s`.`games_unplayed`      AS `games_remaining`,
  `s`.`conf_wins`           AS `conference_wins`,
  `s`.`conf_losses`         AS `conference_losses`,
  `s`.`div_wins`            AS `division_wins`,
  `s`.`div_losses`          AS `division_losses`,
  `s`.`clinched_conference` AS `clinched_conference`,
  `s`.`clinched_division`   AS `clinched_division`,
  `s`.`clinched_playoffs`   AS `clinched_playoffs`,
  `s`.`clinched_league`     AS `clinched_league`,
  `s`.`conf_magic_number`   AS `conference_magic_number`,
  `s`.`div_magic_number`    AS `division_magic_number`,
  `s`.`created_at`          AS `created_at`,
  `s`.`updated_at`          AS `updated_at`
FROM `ibl_team_info` `t`
JOIN `ibl_standings` `s` ON `t`.`teamid` = `s`.`teamid`;
