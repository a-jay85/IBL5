-- Migration 037: Add composite index for roster query optimization
--
-- ibl_plr: composite index for WHERE retired = 0 ORDER BY ordinal ASC pattern
-- Covers TeamRepository::getEntireLeagueRoster() and TeamRepository::getFutureStars()

ALTER TABLE ibl_plr ADD INDEX idx_retired_ordinal (retired, ordinal);
