-- Migration 052: Drop legacy PHP-Nuke referer tracking table
-- and remove unused config columns.
-- Associated dead code removed in this same changeset.
DROP TABLE IF EXISTS nuke_referer;
ALTER TABLE nuke_config
  DROP COLUMN IF EXISTS httpref,
  DROP COLUMN IF EXISTS httprefmax,
  DROP COLUMN IF EXISTS httprefmode;
