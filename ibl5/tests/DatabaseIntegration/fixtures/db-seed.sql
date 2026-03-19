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

-- Additional settings needed by LeagueControlPanel tests
INSERT INTO ibl_settings (name, value)
VALUES ('Current Season Phase', 'Regular Season')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Sim Length in Days', '3')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Allow Waiver Moves', 'Yes')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Show Draft Link', 'Off')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('ASG Voting', 'No')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('EOY Voting', 'No')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Free Agency Notifications', 'Yes')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Trivia Mode', 'Off')
ON DUPLICATE KEY UPDATE value = VALUES(value);

-- Standings: Metros and Sharks in same division/conference
INSERT INTO ibl_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, confRecord, confGB, division, divRecord, divGB, homeRecord, awayRecord, gamesUnplayed)
VALUES (1, 'Metros', 0.600, '30-20', 30, 20, 'Eastern', '18-12', 0.0, 'Atlantic', '8-4', 0.0, '18-7', '12-13', 32)
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name), wins = VALUES(wins), losses = VALUES(losses);

INSERT INTO ibl_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, confRecord, confGB, division, divRecord, divGB, homeRecord, awayRecord, gamesUnplayed)
VALUES (2, 'Sharks', 0.400, '20-30', 20, 30, 'Western', '10-18', 5.0, 'Pacific', '4-8', 3.0, '12-13', '8-17', 32)
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name), wins = VALUES(wins), losses = VALUES(losses);

-- Power ratings for both teams
INSERT INTO ibl_power (TeamID, ranking, last_win, last_loss, streak_type, streak, sos, remaining_sos)
VALUES (1, 75.5, 7, 3, 'W', 3, 0.510, 0.490)
ON DUPLICATE KEY UPDATE ranking = VALUES(ranking);

INSERT INTO ibl_power (TeamID, ranking, last_win, last_loss, streak_type, streak, sos, remaining_sos)
VALUES (2, 45.2, 4, 6, 'L', 2, 0.480, 0.520)
ON DUPLICATE KEY UPDATE ranking = VALUES(ranking);

-- Championship banner for Metros
INSERT INTO ibl_banners (year, currentname, bannername, bannertype)
VALUES (2024, 'Metros', 'Metros', 1);

-- GM tenure for Metros franchise
INSERT INTO ibl_gm_tenures (franchise_id, gm_username, start_season_year, end_season_year, is_mid_season_start, is_mid_season_end)
VALUES (1, 'testgm', 2020, NULL, 0, 0)
ON DUPLICATE KEY UPDATE gm_username = VALUES(gm_username);

-- Historical player stats (ibl_hist) for franchise history queries
INSERT INTO ibl_hist (pid, name, year, team, teamid, games, minutes, fgm, fga, ftm, fta, tgm, tga, orb, reb, ast, stl, blk, tvr, pf, pts, salary)
VALUES (1, 'Test Player One', 2024, 'Metros', 1, 50, 1600, 300, 600, 100, 120, 50, 130, 40, 200, 150, 50, 20, 80, 100, 750, 1500)
ON DUPLICATE KEY UPDATE games = VALUES(games);

-- Franchise seasons for historical name lookups
INSERT INTO ibl_franchise_seasons (franchise_id, season_year, season_ending_year, team_city, team_name)
VALUES (1, 2023, 2024, 'New York', 'Metros')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

-- nuke_modules: Draft module entry (needed by setSeasonPhase/setShowDraftLink tests)
-- Note: nuke_modules uses MyISAM — not covered by transaction rollback.
-- Tests that modify this table must clean up manually.
INSERT INTO nuke_modules (title, custom_title, active, view, inmenu, mod_group, admins)
VALUES ('Draft', 'Draft', 1, 0, 1, 0, '')
ON DUPLICATE KEY UPDATE active = 1;

-- ASG voting rows for Metros and Sharks
INSERT INTO ibl_votes_ASG (teamid, team_city, team_name, East_F1)
VALUES (1, 'New York', 'Metros', 'Some Player')
ON DUPLICATE KEY UPDATE East_F1 = VALUES(East_F1);

INSERT INTO ibl_votes_ASG (teamid, team_city, team_name, West_F1)
VALUES (2, 'San Diego', 'Sharks', 'Another Player')
ON DUPLICATE KEY UPDATE West_F1 = VALUES(West_F1);

-- EOY voting rows for Metros and Sharks
INSERT INTO ibl_votes_EOY (teamid, team_city, team_name, MVP_1)
VALUES (1, 'New York', 'Metros', 'Some Player')
ON DUPLICATE KEY UPDATE MVP_1 = VALUES(MVP_1);

INSERT INTO ibl_votes_EOY (teamid, team_city, team_name, MVP_1)
VALUES (2, 'San Diego', 'Sharks', 'Another Player')
ON DUPLICATE KEY UPDATE MVP_1 = VALUES(MVP_1);

-- Transaction history entries (nuke_stories with transaction categories)
-- Note: nuke_stories uses MyISAM — not covered by transaction rollback.
INSERT INTO nuke_stories (sid, catid, aid, title, time, hometext, comments, counter, topic, informant, ihome, acomm, haspoll, pollID, score, ratings)
VALUES (1, 1, 'admin', 'Metros sign Test Player One', '2024-03-15 12:00:00', 'Details...', 0, 0, 1, '', 0, 0, 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO nuke_stories (sid, catid, aid, title, time, hometext, comments, counter, topic, informant, ihome, acomm, haspoll, pollID, score, ratings)
VALUES (2, 2, 'admin', 'Sharks trade for draft pick', '2023-07-10 14:30:00', 'Details...', 0, 0, 1, '', 0, 0, 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Awards: needed by SeasonArchive and RecordHolders
-- Use a distinct name to avoid conflicting with PlayerRepositoryTest (which expects no awards for 'Test Player One')
INSERT INTO ibl_awards (year, Award, name, table_ID)
VALUES (2024, 'Eastern Conference All-Star', 'Seed Awards Player', 10001)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO ibl_awards (year, Award, name, table_ID)
VALUES (2024, 'MVP', 'Seed Awards Player', 10002)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO ibl_awards (year, Award, name, table_ID)
VALUES (2023, 'Eastern Conference All-Star', 'Seed Awards Player', 10003)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- GM Awards: needed by SeasonArchive getAllGmAwardsWithTeams()
INSERT INTO ibl_gm_awards (year, Award, name, table_ID)
VALUES (2024, 'GM of the Year', 'testgm', 10001)
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Schedule: needed by RecordHolders JOIN to schedule, SplitStats wins/losses
INSERT INTO ibl_schedule (`Year`, BoxID, `Date`, Visitor, VScore, Home, HScore, uuid)
VALUES (2025, 1, '2025-01-15', 2, 85, 1, 104, 'sched-0001-0001-0001-000000000001')
ON DUPLICATE KEY UPDATE VScore = VALUES(VScore);

-- RCB alltime records: needed by FranchiseRecordBook (stat_category is ENUM)
INSERT INTO ibl_rcb_alltime_records (scope, team_id, record_type, stat_category, ranking, player_name, stat_value, stat_raw, season_year)
VALUES ('team', 1, 'single_season', 'ppg', 1, 'Test Player One', 25.5000, 255, 2024)
ON DUPLICATE KEY UPDATE player_name = VALUES(player_name);

INSERT INTO ibl_rcb_alltime_records (scope, team_id, record_type, stat_category, ranking, player_name, stat_value, stat_raw, season_year)
VALUES ('league', 0, 'career', 'ppg', 1, 'Test Player One', 22.3000, 223, 0)
ON DUPLICATE KEY UPDATE player_name = VALUES(player_name);

INSERT INTO ibl_rcb_alltime_records (scope, team_id, record_type, stat_category, ranking, player_name, stat_value, stat_raw, season_year)
VALUES ('league', 0, 'single_season', 'rpg', 1, 'Test Player One', 12.1000, 121, 2024)
ON DUPLICATE KEY UPDATE player_name = VALUES(player_name);

-- RCB season records: needed by SeasonHighs getRcbSeasonHighs() (stat_category is ENUM)
INSERT INTO ibl_rcb_season_records (season_year, scope, team_id, context, stat_category, ranking, player_name, player_position, stat_value, record_season_year)
VALUES (2025, 'league', 0, 'home', 'pts', 1, 'Test Player One', 'PG', 45, 2025)
ON DUPLICATE KEY UPDATE player_name = VALUES(player_name);

-- Draft picks: needed by TeamQuery getDraftPicks()
INSERT INTO ibl_draft_picks (ownerofpick, owner_tid, teampick, teampick_tid, year, round, notes)
VALUES ('Metros', 1, 'Metros', 1, 2025, 1, 'Own pick')
ON DUPLICATE KEY UPDATE notes = VALUES(notes);

-- Cache: needed by RecordHolders getLastAnnouncedDate()
INSERT INTO `cache` (`key`, `value`, `expiration`)
VALUES ('test_seed_key', 'test_value', 0)
ON DUPLICATE KEY UPDATE `value` = VALUES(`value`);

-- Sim dates: needed by RecordHolders getUnannouncedGameDates()
INSERT INTO ibl_sim_dates (Sim, `Start Date`, `End Date`)
VALUES (1, '2025-01-10', '2025-01-20')
ON DUPLICATE KEY UPDATE `Start Date` = VALUES(`Start Date`);

-- Team awards: needed by vw_team_awards and SeasonArchive
INSERT INTO ibl_team_awards (year, name, Award)
VALUES (2024, 'Metros', 'Best Record')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- Draft Order Finalized: needed by ProjectedDraftOrderRepository
INSERT INTO ibl_settings (name, value)
VALUES ('Draft Order Finalized', 'No')
ON DUPLICATE KEY UPDATE value = VALUES(value);
