-- Migration 102: Drop nuke_users table
--
-- All code now uses auth_users (delight-im/auth) for authentication.
-- The nuke_users table has zero PHP references, zero triggers, and zero FKs.
-- See PRs #599 and #600 for the migration path.

DROP TABLE IF EXISTS nuke_users;
