-- Migration 037b: Add composite index for roster query optimization

ALTER TABLE ibl_plr ADD INDEX IF NOT EXISTS idx_retired_ordinal (retired, ordinal);
