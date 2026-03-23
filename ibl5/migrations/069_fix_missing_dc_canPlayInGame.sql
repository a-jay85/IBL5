-- Fix: ensure dc_canPlayInGame exists on ibl_olympics_saved_depth_chart_players.
--
-- Migration 062 used CHANGE COLUMN IF EXISTS to rename dc_active → dc_canPlayInGame,
-- but the IF EXISTS guard silently no-oped when dc_active was already gone.
-- The post-migration schema validator (PR #449) catches this missing column.

-- Attempt the rename in case dc_active still exists on some installations
ALTER TABLE `ibl_olympics_saved_depth_chart_players`
    CHANGE COLUMN IF EXISTS `dc_active` `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)';

-- If neither column exists, add dc_canPlayInGame directly
ALTER TABLE `ibl_olympics_saved_depth_chart_players`
    ADD COLUMN IF NOT EXISTS `dc_canPlayInGame` TINYINT(4) NOT NULL DEFAULT 1 COMMENT 'Can play in game (1=yes)' AFTER `dc_CDepth`;
