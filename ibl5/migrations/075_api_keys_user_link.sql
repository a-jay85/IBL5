-- Migration 075: Link API keys to user accounts for self-service key management
-- Adds user_id column to ibl_api_keys with FK to nuke_users
-- Regular index (not UNIQUE) because soft-deleted rows retain user_id;
-- one-active-key-per-user is enforced in ApiKeysService::generateKeyForUser()
-- NULL user_id allows admin-created keys (IBLbot) to remain unlinked

ALTER TABLE ibl_api_keys
  ADD COLUMN user_id INT NULL AFTER id,
  ADD CONSTRAINT fk_api_keys_user FOREIGN KEY (user_id) REFERENCES nuke_users(user_id)
    ON DELETE SET NULL,
  ADD INDEX idx_user_id (user_id);
