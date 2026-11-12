-- Stores the down SQL and the up-file checksum for a migration that ships a
-- NNN_name.down.sql partner. Both columns stay NULL for every other migration.
-- The CREATE matches 000_baseline_schema.sql and is a no-op wherever the table exists.
CREATE TABLE IF NOT EXISTS migrations (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  migration varchar(255) NOT NULL,
  batch int(11) NOT NULL,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE migrations
  ADD COLUMN IF NOT EXISTS down_sql MEDIUMTEXT NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS up_sha256 CHAR(64) NULL DEFAULT NULL;
