-- Drop DuckDB analytics write-back tables (ADR-0016).
-- Neither table is referenced by any PHP class, view, test, or other table.

DROP TABLE IF EXISTS `ibl_analytics_tsi_bands`;
DROP TABLE IF EXISTS `ibl_analytics_player_peaks`;
