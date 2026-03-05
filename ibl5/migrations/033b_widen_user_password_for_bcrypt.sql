-- Migration 033: Widen user_password columns for bcrypt hashes
-- bcrypt hashes are 60 characters; VARCHAR(255) provides headroom for future algorithms
-- Current column: varchar(40) — only fits MD5 (32 hex chars)

ALTER TABLE `nuke_users` MODIFY `user_password` VARCHAR(255) NOT NULL DEFAULT '';
-- nuke_users_temp: dropped by migration 035, skip
