-- Migration 101: Re-point ibl_api_keys FK from nuke_users to auth_users
--
-- user_id values are already synchronized between nuke_users and auth_users,
-- so no data migration is needed — just swap the constraint.
-- Column type must match: auth_users.id is INT UNSIGNED, so convert first.

ALTER TABLE ibl_api_keys DROP FOREIGN KEY fk_api_keys_user;

ALTER TABLE ibl_api_keys MODIFY user_id INT UNSIGNED NULL;

ALTER TABLE ibl_api_keys
  ADD CONSTRAINT fk_api_keys_auth_user
    FOREIGN KEY (user_id) REFERENCES auth_users(id)
    ON DELETE SET NULL;
