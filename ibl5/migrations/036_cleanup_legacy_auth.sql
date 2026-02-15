-- Migration 036: Cleanup legacy auth tables (run AFTER confirming all admins can log in)
--
-- WARNING: Only run this migration after verifying:
-- 1. All admin users can log in via the new AuthService
-- 2. All regular users can log in, register, and reset passwords
-- 3. The old nuke_authors table data has been backed up
--
-- This migration:
-- - Drops the nuke_authors table (admin auth now via auth_users roles_mask)
-- - Drops the nuke_users_temp table (registration now via auth_users_confirmations)
-- - Marks nuke_users.user_password as deprecated (auth now in auth_users)

-- Back up nuke_authors before dropping (create a copy table)
CREATE TABLE IF NOT EXISTS nuke_authors_backup AS SELECT * FROM nuke_authors;

-- Drop the legacy admin table
-- DROP TABLE IF EXISTS nuke_authors;

-- Back up nuke_users_temp before dropping
CREATE TABLE IF NOT EXISTS nuke_users_temp_backup AS SELECT * FROM nuke_users_temp;

-- Drop the legacy temp registration table
-- DROP TABLE IF EXISTS nuke_users_temp;

-- Note: The DROP statements are commented out for safety.
-- Uncomment them only after confirming the migration is successful.
-- The nuke_users.user_password column is kept for backward compatibility
-- but authentication now happens via auth_users.
