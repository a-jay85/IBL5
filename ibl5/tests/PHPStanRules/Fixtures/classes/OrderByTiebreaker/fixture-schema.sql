-- Fixture schema for OrderByRequiresUniqueTiebreakerRuleTest (mysqldump shape).
CREATE TABLE `fx_awards` (
  `table_id` int(11) NOT NULL AUTO_INCREMENT,
  `year` smallint(6) NOT NULL,
  `award` varchar(64) NOT NULL,
  `name` varchar(32) NOT NULL,
  `note` varchar(32) DEFAULT NULL,
  PRIMARY KEY (`table_id`),
  UNIQUE KEY `uk_year_award_name` (`year`,`award`,`name`),
  UNIQUE KEY `uk_note` (`note`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fx_comments` (
  `cid` int(11) NOT NULL,
  `sid` int(11) NOT NULL,
  `posted` datetime NOT NULL,
  PRIMARY KEY (`cid`),
  KEY `sid` (`sid`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fx_offers` (
  `primary_key` int(11) NOT NULL,
  `amount` int(11) NOT NULL,
  PRIMARY KEY (`primary_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
CREATE TABLE `fx_players` (
  `name` varchar(32) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
