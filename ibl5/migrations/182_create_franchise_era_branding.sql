-- 182: slim branding table for retired franchise identities (ADR-0136).
-- One row per (franchise_id, team_city, team_name) era whose identity no
-- longer matches ibl_team_info. Current identities are NOT stored here.
CREATE TABLE IF NOT EXISTS `ibl_franchise_era_branding` (
  `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `franchise_id` INT NOT NULL,
  `team_city` VARCHAR(50) NOT NULL,
  `team_name` VARCHAR(50) NOT NULL,
  `color1` VARCHAR(6) NOT NULL DEFAULT '',
  `color2` VARCHAR(6) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_era` (`franchise_id`, `team_city`, `team_name`),
  CONSTRAINT `fk_era_franchise` FOREIGN KEY (`franchise_id`)
    REFERENCES `ibl_team_info` (`teamid`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO `ibl_franchise_era_branding`
  (`franchise_id`, `team_city`, `team_name`, `color1`, `color2`) VALUES
  (4,  'Brooklyn',      'Nets',        '000000', 'FFFFFF'),
  (10, 'Charlotte',     'Hornets',     '00788C', '1D1160'),
  (16, 'Oklahoma City', 'Thunder',     '007AC1', 'EF6F31'),
  (16, 'Las Vegas',     'Thunder',     '1C1C1C', 'F5C518'),
  (17, 'San Antonio',   'Spurs',       'C4CED4', '000000'),
  (22, 'Seattle',       'Supersonics', '00653A', 'FFC200');
