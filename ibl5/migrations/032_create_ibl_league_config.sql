-- Migration 032: Create ibl_league_config table
-- Stores parsed Jump Shot Basketball .lge file data per season

CREATE TABLE ibl_league_config (
  id INT NOT NULL AUTO_INCREMENT,
  season_ending_year SMALLINT UNSIGNED NOT NULL,
  team_slot TINYINT UNSIGNED NOT NULL,
  team_name VARCHAR(32) NOT NULL,
  conference VARCHAR(16) NOT NULL,
  division VARCHAR(16) NOT NULL,
  playoff_qualifiers_per_conf TINYINT UNSIGNED NOT NULL,
  playoff_round1_format VARCHAR(8) NOT NULL,
  playoff_round2_format VARCHAR(8) NOT NULL,
  playoff_round3_format VARCHAR(8) NOT NULL,
  playoff_round4_format VARCHAR(8) NOT NULL,
  team_count TINYINT UNSIGNED NOT NULL,
  created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  UNIQUE KEY uq_season_team (season_ending_year, team_slot),
  KEY idx_season_year (season_ending_year)
) ENGINE=InnoDB;
