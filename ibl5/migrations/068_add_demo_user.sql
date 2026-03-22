-- Add read-only demo user for hiring manager magic link access
-- CRITICAL: INSERT only — never UPDATE user_ibl_team.
-- The trg_gm_tenure_track trigger fires AFTER UPDATE ON nuke_users
-- and would NULL out the real Warriors GM's ibl_team_info.gm_username.

INSERT INTO nuke_users
  (username, user_email, user_ibl_team, user_password, user_regdate, bio, ublock, user_level, user_active)
VALUES
  ('ibl_demo', 'demo@iblhoops.net', 'Warriors',
   '$2y$12$fjwBgVxheIiKifRWzXPbQ.gqbE.9LNLn7MevVdOjac2VxtPMfYEeG',
   'Mar 22, 2026', '', '', 1, 1)
ON DUPLICATE KEY UPDATE user_active = 1;

INSERT INTO auth_users
  (email, password, username, status, verified, resettable, roles_mask, registered)
VALUES
  ('demo@iblhoops.net', 'delight-auth:0', 'ibl_demo', 0, 1, 0, 0, UNIX_TIMESTAMP())
ON DUPLICATE KEY UPDATE verified = 1;
