-- Migration 035: Migrate existing users from nuke_users to auth_users
--
-- Strategy: Matched IDs — auth_users.id = nuke_users.user_id (1:1 mapping).
-- nuke_users remains the profile table; auth_users owns authentication.
--
-- Password handling:
--   - bcrypt hashes ($2y$...) are copied directly (compatible with password_verify)
--   - MD5 hashes (32-char hex) cannot be used by delight-im/auth — those users
--     will need to use password reset on first login
--
-- Run this AFTER 034_create_auth_tables.sql

-- 1. Copy all users with valid user_id
INSERT INTO auth_users (id, email, password, username, status, verified, resettable, roles_mask, registered, last_login)
SELECT
    nu.user_id,
    nu.user_email,
    nu.user_password,
    nu.username,
    0,  -- status: normal (0 = normal in delight-im/auth)
    1,  -- verified: all existing users are pre-verified
    1,  -- resettable: allow password resets
    0,  -- roles_mask: regular user (no special roles)
    COALESCE(UNIX_TIMESTAMP(STR_TO_DATE(NULLIF(nu.user_regdate, ''), '%M %d, %Y')), 0),  -- convert regdate string to unix timestamp (0 for empty/invalid)
    NULL  -- last_login: unknown
FROM nuke_users nu
WHERE nu.user_id > 0
  AND nu.username <> ''
  AND nu.user_email <> ''
ON DUPLICATE KEY UPDATE id = id;  -- skip if already migrated

-- 2. Set auto_increment past existing user IDs so new registrations get fresh IDs
-- Note: This must be run as a prepared statement or manually since ALTER TABLE
-- doesn't support subqueries directly in all MySQL versions.
-- Run this manually: ALTER TABLE auth_users AUTO_INCREMENT = <MAX_USER_ID + 1>;
SET @max_id = (SELECT COALESCE(MAX(user_id), 0) + 1 FROM nuke_users);
SET @sql = CONCAT('ALTER TABLE auth_users AUTO_INCREMENT = ', @max_id);
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- 3. Assign admin role to users who have corresponding nuke_authors entries
-- Role::ADMIN = 1 in delight-im/auth's Role class
UPDATE auth_users au
INNER JOIN nuke_users nu ON au.id = nu.user_id
INNER JOIN nuke_authors na ON LOWER(nu.username) = LOWER(na.aid)
SET au.roles_mask = au.roles_mask | 1;  -- bit 0 = ADMIN role
