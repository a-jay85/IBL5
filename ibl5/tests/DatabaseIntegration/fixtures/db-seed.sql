-- Production-like seed data for database integration tests.
-- Inserted after migrations. Only reference/lookup data that tests read but never mutate.
-- All INSERTs use ON DUPLICATE KEY UPDATE for idempotency.

-- ============================================================
-- Teams: All 29 rows (Free Agents + 28 real franchises)
-- Copied from E2E seed; ON DUPLICATE KEY preserves gm_username/uuid on dev DBs.
-- ============================================================
INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, arena, owner_name, owner_email, gm_username, uuid)
VALUES
  ( 0, '',             'Free Agents',  '888888', 'cccccc', '', '', '', NULL, 'db-team-uuid-00'),
  ( 1, 'New York',     'Metros',       '003DA5', 'FF5733', 'Metro Arena', 'Test GM', 'test@example.com', 'testgm', 'db-team-uuid-01'),
  ( 2, 'Los Angeles',  'Stars',        '552583', 'FDB927', '', '', '', NULL, 'db-team-uuid-02'),
  ( 3, 'Chicago',      'Cougars',      'CE1141', '000000', '', '', '', NULL, 'db-team-uuid-03'),
  ( 4, 'Detroit',      'Diesels',      '006BB6', 'ED174C', '', '', '', NULL, 'db-team-uuid-04'),
  ( 5, 'Boston',       'Minutemen',    '007A33', 'BA9653', '', '', '', NULL, 'db-team-uuid-05'),
  ( 6, 'Philadelphia', 'Rage',         'ED174C', '003DA5', '', '', '', NULL, 'db-team-uuid-06'),
  ( 7, 'Orlando',      'Tropics',      '0077C0', '000000', '', '', '', NULL, 'db-team-uuid-07'),
  ( 8, 'Miami',        'Monarchs',     '98002E', 'F9A01B', '', '', '', NULL, 'db-team-uuid-08'),
  ( 9, 'Phoenix',      'Flames',       'E56020', '1D1160', '', '', '', NULL, 'db-team-uuid-09'),
  (10, 'San Antonio',  'Spurs',        'C4CED4', '000000', '', '', '', NULL, 'db-team-uuid-10'),
  (11, 'Portland',     'Pioneers',     'E03A3E', '000000', '', '', '', NULL, 'db-team-uuid-11'),
  (12, 'Charlotte',    'Royals',       '1D428A', '00788C', '', '', '', NULL, 'db-team-uuid-12'),
  (13, 'Houston',      'Apollos',      'CE1141', 'C4CED4', '', '', '', NULL, 'db-team-uuid-13'),
  (14, 'Atlanta',      'Phoenixes',    'E03A3E', 'C1D32F', '', '', '', NULL, 'db-team-uuid-14'),
  (15, 'Memphis',      'Blues',        '5D76A9', '12173F', '', '', '', NULL, 'db-team-uuid-15'),
  (16, 'Minnesota',    'Blizzard',     '0C2340', '236192', '', '', '', NULL, 'db-team-uuid-16'),
  (17, 'Toronto',      'Huskies',      'CE1141', '000000', '', '', '', NULL, 'db-team-uuid-17'),
  (18, 'Milwaukee',    'Bucks',        '00471B', 'EEE1C6', '', '', '', NULL, 'db-team-uuid-18'),
  (19, 'Denver',       'Nuggets',      '0E2240', 'FEC524', '', '', '', NULL, 'db-team-uuid-19'),
  (20, 'Sacramento',   'Pilots',       '5A2D81', '63727A', '', '', '', NULL, 'db-team-uuid-20'),
  (21, 'Dallas',       'Mavericks',    '00538C', '002B5E', '', '', '', NULL, 'db-team-uuid-21'),
  (22, 'Cleveland',    'Cavaliers',    '6F263D', '041E42', '', '', '', NULL, 'db-team-uuid-22'),
  (23, 'Seattle',      'Supersonics',  '006633', 'FFC200', '', '', '', NULL, 'db-team-uuid-23'),
  (24, 'New Jersey',   'Nets',         '002A60', 'CD1041', '', '', '', NULL, 'db-team-uuid-24'),
  (25, 'Washington',   'Generals',     '002B5C', 'E31837', '', '', '', NULL, 'db-team-uuid-25'),
  (26, 'Indiana',      'Pacers',       '002D62', 'FDBB30', '', '', '', NULL, 'db-team-uuid-26'),
  (27, 'Utah',         'Jazz',         '002B5C', '00471B', '', '', '', NULL, 'db-team-uuid-27'),
  (28, 'Oklahoma City','Thunder',      '007AC1', 'EF6100', '', '', '', NULL, 'db-team-uuid-28')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name), team_city = VALUES(team_city), color1 = VALUES(color1), color2 = VALUES(color2);

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

-- Additional settings needed by LeagueControlPanel and SeasonQuery tests
INSERT INTO ibl_settings (name, value)
VALUES ('Current Season Phase', 'Regular Season')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_settings (name, value)
VALUES ('Current Season Ending Year', '2026')
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

-- ============================================================
-- Standings: All 28 real teams with neutral records
-- Division/conference assignments match League::DIVISION_NAMES
-- ============================================================
INSERT INTO ibl_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, division)
VALUES
  ( 1, 'Metros',       0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 2, 'Stars',        0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  ( 3, 'Cougars',      0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  ( 4, 'Diesels',      0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  ( 5, 'Minutemen',    0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 6, 'Rage',         0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 7, 'Tropics',      0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 8, 'Monarchs',     0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 9, 'Flames',       0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (10, 'Spurs',        0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (11, 'Pioneers',     0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (12, 'Royals',       0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (13, 'Apollos',      0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (14, 'Phoenixes',    0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (15, 'Blues',         0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (16, 'Blizzard',     0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (17, 'Huskies',      0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  (18, 'Bucks',        0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (19, 'Nuggets',      0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (20, 'Pilots',       0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (21, 'Mavericks',    0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (22, 'Cavaliers',    0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (23, 'Supersonics',  0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (24, 'Nets',         0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  (25, 'Generals',     0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (26, 'Pacers',       0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (27, 'Jazz',         0.500, '20-20', 20, 20, 'Western',  'Midwest'),
  (28, 'Thunder',      0.500, '20-20', 20, 20, 'Western',  'Pacific')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name), wins = VALUES(wins), losses = VALUES(losses);

-- ============================================================
-- Power ratings: All 28 real teams with neutral values
-- ============================================================
INSERT INTO ibl_power (TeamID, ranking, last_win, last_loss, streak_type, streak, sos, remaining_sos)
VALUES
  ( 1, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 2, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 3, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 4, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 5, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 6, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 7, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 8, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  ( 9, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (10, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (11, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (12, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (13, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (14, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (15, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (16, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (17, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (18, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (19, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (20, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (21, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (22, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (23, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (24, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (25, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (26, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (27, 50.0, 5, 5, 'W', 1, 0.500, 0.500),
  (28, 50.0, 5, 5, 'W', 1, 0.500, 0.500)
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

-- ============================================================
-- Franchise seasons: historical row + all 28 teams for current season
-- ============================================================
INSERT INTO ibl_franchise_seasons (franchise_id, season_year, season_ending_year, team_city, team_name)
VALUES (1, 2023, 2024, 'New York', 'Metros')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

INSERT INTO ibl_franchise_seasons (franchise_id, season_year, season_ending_year, team_city, team_name)
VALUES
  ( 1, 2025, 2026, 'New York',      'Metros'),
  ( 2, 2025, 2026, 'Los Angeles',   'Stars'),
  ( 3, 2025, 2026, 'Chicago',       'Cougars'),
  ( 4, 2025, 2026, 'Detroit',       'Diesels'),
  ( 5, 2025, 2026, 'Boston',        'Minutemen'),
  ( 6, 2025, 2026, 'Philadelphia',  'Rage'),
  ( 7, 2025, 2026, 'Orlando',       'Tropics'),
  ( 8, 2025, 2026, 'Miami',         'Monarchs'),
  ( 9, 2025, 2026, 'Phoenix',       'Flames'),
  (10, 2025, 2026, 'San Antonio',   'Spurs'),
  (11, 2025, 2026, 'Portland',      'Pioneers'),
  (12, 2025, 2026, 'Charlotte',     'Royals'),
  (13, 2025, 2026, 'Houston',       'Apollos'),
  (14, 2025, 2026, 'Atlanta',       'Phoenixes'),
  (15, 2025, 2026, 'Memphis',       'Blues'),
  (16, 2025, 2026, 'Minnesota',     'Blizzard'),
  (17, 2025, 2026, 'Toronto',       'Huskies'),
  (18, 2025, 2026, 'Milwaukee',     'Bucks'),
  (19, 2025, 2026, 'Denver',        'Nuggets'),
  (20, 2025, 2026, 'Sacramento',    'Pilots'),
  (21, 2025, 2026, 'Dallas',        'Mavericks'),
  (22, 2025, 2026, 'Cleveland',     'Cavaliers'),
  (23, 2025, 2026, 'Seattle',       'Supersonics'),
  (24, 2025, 2026, 'New Jersey',    'Nets'),
  (25, 2025, 2026, 'Washington',    'Generals'),
  (26, 2025, 2026, 'Indiana',       'Pacers'),
  (27, 2025, 2026, 'Utah',          'Jazz'),
  (28, 2025, 2026, 'Oklahoma City', 'Thunder')
ON DUPLICATE KEY UPDATE team_name = VALUES(team_name);

-- ASG voting rows for Metros and Stars
INSERT INTO ibl_votes_ASG (teamid, team_city, team_name, East_F1)
VALUES (1, 'New York', 'Metros', 'Some Player')
ON DUPLICATE KEY UPDATE East_F1 = VALUES(East_F1);

INSERT INTO ibl_votes_ASG (teamid, team_city, team_name, West_F1)
VALUES (2, 'Los Angeles', 'Stars', 'Another Player')
ON DUPLICATE KEY UPDATE West_F1 = VALUES(West_F1);

-- EOY voting rows for Metros and Stars
INSERT INTO ibl_votes_EOY (teamid, team_city, team_name, MVP_1)
VALUES (1, 'New York', 'Metros', 'Some Player')
ON DUPLICATE KEY UPDATE MVP_1 = VALUES(MVP_1);

INSERT INTO ibl_votes_EOY (teamid, team_city, team_name, MVP_1)
VALUES (2, 'Los Angeles', 'Stars', 'Another Player')
ON DUPLICATE KEY UPDATE MVP_1 = VALUES(MVP_1);

-- Transaction history entries (nuke_stories with transaction categories)
INSERT INTO nuke_stories (sid, catid, aid, title, time, hometext, comments, counter, topic, informant, ihome, acomm, haspoll, pollID, score, ratings)
VALUES (1, 1, 'admin', 'Metros sign Test Player One', '2024-03-15 12:00:00', 'Details...', 0, 0, 1, '', 0, 0, 0, 0, 0, 0)
ON DUPLICATE KEY UPDATE title = VALUES(title);

INSERT INTO nuke_stories (sid, catid, aid, title, time, hometext, comments, counter, topic, informant, ihome, acomm, haspoll, pollID, score, ratings)
VALUES (2, 2, 'admin', 'Stars trade for draft pick', '2023-07-10 14:30:00', 'Details...', 0, 0, 1, '', 0, 0, 0, 0, 0, 0)
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
