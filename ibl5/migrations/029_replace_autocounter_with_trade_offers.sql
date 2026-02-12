-- Create ibl_trade_offers parent table (AUTO_INCREMENT continues from autocounter's 12018)
CREATE TABLE IF NOT EXISTS `ibl_trade_offers` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=12018 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Seed any in-flight trade offer IDs from child tables
INSERT IGNORE INTO `ibl_trade_offers` (`id`)
SELECT DISTINCT `tradeofferid` FROM `ibl_trade_info` WHERE `tradeofferid` > 0;

INSERT IGNORE INTO `ibl_trade_offers` (`id`)
SELECT DISTINCT `tradeOfferID` FROM `ibl_trade_cash` WHERE `tradeOfferID` > 0;

-- Drop the old autocounter
DROP TABLE IF EXISTS `ibl_trade_autocounter`;
