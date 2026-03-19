-- Minimal seed data for database integration tests.
-- Inserted after migrations. Only reference/lookup data that tests read but never mutate.
-- All INSERTs use ON DUPLICATE KEY UPDATE for idempotency.

-- Teams: Free Agents (0), Metros (1), Sharks (2)
INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, arena, owner_name, owner_email, gm_username, uuid)
VALUES (0, '', 'Free Agents', '000000', 'ffffff', '', '', '', NULL, '00000000-0000-0000-0000-000000000000')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, arena, owner_name, owner_email, gm_username, uuid)
VALUES (1, 'New York', 'Metros', '1a2e5a', 'ffffff', 'Metro Arena', 'Test GM', 'test@example.com', 'testgm', '11111111-1111-1111-1111-111111111111')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, arena, owner_name, owner_email, gm_username, uuid)
VALUES (2, 'San Diego', 'Sharks', '0077cc', '000000', 'Shark Tank', 'Shark GM', 'shark@example.com', 'sharkgm', '22222222-2222-2222-2222-222222222222')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

-- Players: PID 1 (rostered on Metros), PID 2 (free agent)
INSERT INTO ibl_plr (pid, name, age, tid, pos, sta, exp, bird, cy, cyt, cy1, cy2, retired, ordinal, droptime, uuid)
VALUES (1, 'Test Player One', 27, 1, 'PG', 80, 5, 3, 1, 3, 1500, 1600, 0, 1, 0, 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa')
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO ibl_plr (pid, name, age, tid, pos, sta, exp, bird, cy, cyt, cy1, cy2, retired, ordinal, droptime, uuid)
VALUES (2, 'Test Player Two', 22, 0, 'SF', 75, 1, 0, 0, 0, 0, 0, 0, 1000, 0, 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Users: one GM mapped to Metros
INSERT INTO nuke_users (user_id, username, user_email, user_ibl_team, name, user_password, date_started, user_avatar, bio, ublock)
VALUES (1, 'testgm', 'test@example.com', 'Metros', 'Test GM', 'hashed', '2020', '', '', '')
ON DUPLICATE KEY UPDATE username = VALUES(username);

-- Settings: commonly read by services
INSERT INTO ibl_settings (name, value)
VALUES ('Allow Trades', 'Yes')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Phase', 'Regular Season')
ON DUPLICATE KEY UPDATE value = VALUES(value);
