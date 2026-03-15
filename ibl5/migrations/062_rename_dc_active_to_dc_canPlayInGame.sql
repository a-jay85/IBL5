-- Rename dc_active → dc_canPlayInGame on all tables that carry UI depth chart columns.
-- This aligns with migration 058 which renamed the engine-side `active` → `dc_canPlayInGame`.

ALTER TABLE ibl_plr
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

-- ibl_plr_chunk: dropped by migration 035, skip

ALTER TABLE ibl_saved_depth_chart_players
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

ALTER TABLE ibl_olympics_saved_depth_chart_players
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

ALTER TABLE ibl_olympics_plr
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(3) UNSIGNED DEFAULT 1 COMMENT 'Can play in game (1=yes)';
