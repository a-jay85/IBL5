-- CI E2E seed data: minimal rows for Playwright smoke tests.
-- Imported AFTER schema.sql into the CI MariaDB service container.

-- ============================================================
-- PHP-Nuke bootstrap tables
-- ============================================================

INSERT INTO nuke_config (
  sitename, nukeurl, site_logo, slogan, startdate, adminmail,
  anonpost, Default_Theme, overwrite_theme,
  foot1, foot2, foot3,
  commentlimit, anonymous, minpass, pollcomm, articlecomm,
  broadcast_msg, my_headlines, top, storyhome, user_news, oldnum,
  ultramode, banners, backend_title, backend_language, language, locale,
  multilingual, useflags, notify, notify_email, notify_subject,
  notify_message, notify_from, moderate, admingraphic,
  httpref, httprefmax, httprefmode, CensorMode, CensorReplace,
  copyright, Version_Num, gfx_chk, nuke_editor, display_errors
) VALUES (
  'IBL5', 'http://localhost:8080/ibl5/', '', 'Internet Basketball League', '2026-01-01',
  'admin@example.com',
  0, 'IBL', 1,
  '', '', '',
  4096, 'Anonymous', 5, 0, 0,
  0, 0, 10, 10, 0, 30,
  0, 0, '', 'en', 'english', 'en_US',
  0, 0, 0, '', '',
  '', '', 0, 1,
  0, 1000, 1, 0, '',
  '', '5.11', 0, 0, 0
);

INSERT INTO nuke_main (main_module) VALUES ('News');

-- Modules referenced by E2E tests (is_active() checks)
INSERT INTO nuke_modules (title, custom_title, active, view) VALUES
  ('Standings',         'Standings',         1, 0),
  ('Player',            'Player',            1, 0),
  ('Team',              'Team',              1, 0),
  ('SeasonLeaderboards','SeasonLeaderboards',1, 0),
  ('CareerLeaderboards','CareerLeaderboards',1, 0),
  ('DraftHistory',      'DraftHistory',      1, 0),
  ('CapSpace',          'CapSpace',          1, 0),
  ('Trading',           'Trading',           1, 0),
  ('DepthChartEntry',   'DepthChartEntry',   1, 0),
  ('ComparePlayers',    'ComparePlayers',    1, 0),
  ('YourAccount',       'Your Account',      1, 0),
  ('News',              'News',              1, 0),
  ('FreeAgency',          'FreeAgency',          1, 0),
  ('AwardHistory',        'AwardHistory',        1, 0),
  ('FranchiseRecordBook', 'FranchiseRecordBook', 1, 0),
  ('RecordHolders',       'RecordHolders',       1, 0),
  ('TransactionHistory',  'TransactionHistory',  1, 0),
  ('Search',              'Search',              1, 0),
  ('FranchiseHistory',    'FranchiseHistory',    1, 0),
  ('TeamStats',           'TeamStats',           1, 0),
  ('PlayerDatabase',      'PlayerDatabase',      1, 0);

-- ============================================================
-- IBL season bootstrap
-- ============================================================

INSERT INTO ibl_settings (name, value) VALUES
  ('Current Season Phase',        'Free Agency'),
  ('Current Season Ending Year',  '2026'),
  ('Allow Trades',                'Off'),
  ('Allow Waiver Moves',          'Off'),
  ('Show Draft Link',             'Off'),
  ('Free Agency Notifications',   'Off'),
  ('League Sim Length',            '7');

INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES
  (689, '2026-03-01', '2026-03-07');

-- ============================================================
-- Teams (28 real franchises + Free Agents)
-- Only columns required by the app; others use table defaults.
-- ============================================================

INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, uuid) VALUES
  ( 0, '',             'Free Agents',  '888888', 'cccccc', 'team-uuid-00'),
  ( 1, 'New York',     'Metros',       '003DA5', 'FF5733', 'team-uuid-01'),
  ( 2, 'Los Angeles',  'Stars',        '552583', 'FDB927', 'team-uuid-02'),
  ( 3, 'Chicago',      'Cougars',      'CE1141', '000000', 'team-uuid-03'),
  ( 4, 'Detroit',      'Diesels',      '006BB6', 'ED174C', 'team-uuid-04'),
  ( 5, 'Boston',       'Minutemen',    '007A33', 'BA9653', 'team-uuid-05'),
  ( 6, 'Philadelphia', 'Rage',         'ED174C', '003DA5', 'team-uuid-06'),
  ( 7, 'Orlando',      'Tropics',      '0077C0', '000000', 'team-uuid-07'),
  ( 8, 'Miami',        'Monarchs',     '98002E', 'F9A01B', 'team-uuid-08'),
  ( 9, 'Phoenix',      'Flames',       'E56020', '1D1160', 'team-uuid-09'),
  (10, 'San Antonio',  'Spurs',        'C4CED4', '000000', 'team-uuid-10'),
  (11, 'Portland',     'Pioneers',     'E03A3E', '000000', 'team-uuid-11'),
  (12, 'Charlotte',    'Royals',       '1D428A', '00788C', 'team-uuid-12'),
  (13, 'Houston',      'Apollos',      'CE1141', 'C4CED4', 'team-uuid-13'),
  (14, 'Atlanta',      'Phoenixes',    'E03A3E', 'C1D32F', 'team-uuid-14'),
  (15, 'Memphis',      'Blues',        '5D76A9', '12173F', 'team-uuid-15'),
  (16, 'Minnesota',    'Blizzard',     '0C2340', '236192', 'team-uuid-16'),
  (17, 'Toronto',      'Huskies',      'CE1141', '000000', 'team-uuid-17'),
  (18, 'Milwaukee',    'Bucks',        '00471B', 'EEE1C6', 'team-uuid-18'),
  (19, 'Denver',       'Nuggets',      '0E2240', 'FEC524', 'team-uuid-19'),
  (20, 'Sacramento',   'Pilots',       '5A2D81', '63727A', 'team-uuid-20'),
  (21, 'Dallas',       'Mavericks',    '00538C', '002B5E', 'team-uuid-21'),
  (22, 'Cleveland',    'Cavaliers',    '6F263D', '041E42', 'team-uuid-22'),
  (23, 'Seattle',      'Supersonics',  '006633', 'FFC200', 'team-uuid-23'),
  (24, 'New Jersey',   'Nets',         '002A60', 'CD1041', 'team-uuid-24'),
  (25, 'Washington',   'Generals',     '002B5C', 'E31837', 'team-uuid-25'),
  (26, 'Indiana',      'Pacers',       '002D62', 'FDBB30', 'team-uuid-26'),
  (27, 'Utah',         'Jazz',         '002B5C', '00471B', 'team-uuid-27'),
  (28, 'Oklahoma City','Thunder',      '007AC1', 'EF6100', 'team-uuid-28');

-- ============================================================
-- Standings (28 teams — FK to ibl_team_info)
-- ============================================================

-- Divisions must match League::DIVISION_NAMES = ['Atlantic', 'Central', 'Midwest', 'Pacific']
-- 7 teams per division: Atlantic & Central (Eastern), Midwest & Pacific (Western)
INSERT INTO ibl_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, division) VALUES
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
  (28, 'Thunder',      0.500, '20-20', 20, 20, 'Western',  'Pacific');

-- ============================================================
-- Franchise seasons (required by trigger FK; 1 row per franchise)
-- ============================================================

INSERT INTO ibl_franchise_seasons (franchise_id, season_year, season_ending_year, team_city, team_name) VALUES
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
  (28, 2025, 2026, 'Oklahoma City', 'Thunder');

-- ============================================================
-- Players (pid=1,2 active for Compare Players; pid=3 retired for retirees toggle)
-- ordinal must be != 0 for players to appear in Compare Players datalist
-- ============================================================

INSERT INTO ibl_plr (
  pid, name, age, peak, tid, pos, ordinal,
  sta, oo, od, `do`, dd, po, pd, `to`, td,
  cy, cyt, cy1, cy2,
  retired, exp,
  htft, htin, wt, college,
  draftround, draftpickno, draftyear, draftedby, draftedbycurrentname,
  stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta,
  stats_3gm, stats_3ga, stats_orb, stats_drb, stats_ast, stats_stl,
  stats_to, stats_blk, stats_pf,
  uuid
) VALUES
  (1, 'Test Player', 28, 28, 1, 'SG', 1,
   80, 75, 70, 65, 60, 72, 68, 70, 65,
   2, 3, 1500, 1600,
   0, 5,
   6, 4, 200, 'Test University',
   1, 5, 2021, 'Metros', 'Metros',
   40, 1200, 200, 450, 100, 120,
   60, 150, 40, 120, 180, 50,
   80, 20, 90,
   'plr-uuid-00000000-0000-000000000001'),
  (2, 'Test Player Two', 26, 27, 1, 'PF', 2,
   78, 72, 68, 63, 58, 70, 66, 68, 63,
   1, 2, 1200, 0,
   0, 3,
   6, 8, 220, 'Test State',
   1, 12, 2023, 'Metros', 'Metros',
   38, 1100, 180, 400, 90, 110,
   40, 120, 50, 130, 150, 45,
   70, 25, 85,
   'plr-uuid-00000000-0000-000000000002'),
  (3, 'Retired Legend', 38, 30, 0, 'C', 0,
   60, 80, 75, 70, 65, 75, 70, 72, 67,
   0, 0, 0, 0,
   1, 15,
   7, 0, 250, 'Legend College',
   1, 1, 2011, 'Stars', 'Stars',
   500, 16000, 3000, 6500, 1500, 1800,
   200, 600, 800, 2200, 1500, 400,
   900, 300, 1200,
   'plr-uuid-00000000-0000-000000000003');

-- ============================================================
-- Box scores (must be after players for FK constraint)
-- ============================================================

-- ibl_box_scores needs rows for Season date queries and career averages view.
-- Generated columns (game_type, season_year, calc_*) are computed automatically.
-- game_type=1 (regular season) when month is not 6 (playoffs), 10, or 0.
INSERT INTO ibl_box_scores (
  `Date`, pid, name, pos, visitorTID, homeTID, teamID,
  gameMIN, game2GM, game2GA, gameFTM, gameFTA, game3GM, game3GA,
  gameORB, gameDRB, gameAST, gameSTL, gameTOV, gameBLK, gamePF,
  `uuid`
) VALUES
  ('2026-03-07', 1, 'Test Player', 'SG', 2, 1, 1,
   32, 5, 10, 3, 4, 2, 5,
   2, 4, 5, 1, 2, 1, 3,
   '00000000-0000-0000-0000-000000000001'),
  ('2026-03-07', 2, 'Test Player Two', 'PF', 2, 1, 1,
   28, 4, 9, 2, 3, 1, 3,
   3, 5, 3, 2, 1, 2, 2,
   '00000000-0000-0000-0000-000000000002');

-- ============================================================
-- Player history (SeasonLeaderboards needs current-year stats)
-- ============================================================

INSERT INTO ibl_hist (
  pid, name, year, team, teamid,
  games, minutes, fgm, fga, ftm, fta, tgm, tga,
  orb, reb, ast, stl, blk, tvr, pf, pts, salary
) VALUES
  (1, 'Test Player', 2026, 'Metros', 1,
   40, 1200, 200, 450, 100, 120, 60, 150,
   40, 160, 180, 50, 20, 80, 90, 620, 1500),
  (2, 'Test Player Two', 2026, 'Metros', 1,
   38, 1100, 180, 400, 90, 110, 40, 120,
   50, 180, 150, 45, 25, 70, 85, 530, 1200),
  (3, 'Retired Legend', 2025, 'Stars', 2,
   40, 1300, 250, 500, 150, 180, 30, 100,
   80, 220, 100, 40, 50, 60, 100, 710, 2000);

-- ============================================================
-- Draft picks (DraftHistory page)
-- ============================================================

INSERT INTO ibl_draft_picks (ownerofpick, owner_tid, teampick, teampick_tid, year, round) VALUES
  ('Metros', 1, 'Metros', 1, 2026, 1),
  ('Stars',  2, 'Stars',  2, 2026, 1);

-- Franchise Record Book data (tables created by migration 037c_create_rcb_tables.sql)

-- League single-season records (scope=league, team_id=0)
INSERT INTO ibl_rcb_alltime_records (scope, team_id, record_type, stat_category, ranking, player_name, pid, stat_value, stat_raw, team_of_record, season_year) VALUES
  ('league', 0, 'single_season', 'ppg', 1, 'Test Player',     1, 15.5000, 155, 1, 2026),
  ('league', 0, 'single_season', 'ppg', 2, 'Test Player Two', 2, 13.9474, 139, 1, 2026),
  ('league', 0, 'single_season', 'rpg', 1, 'Test Player Two', 2, 4.7368,  47,  1, 2026),
  ('league', 0, 'single_season', 'rpg', 2, 'Test Player',     1, 4.0000,  40,  1, 2026),
  ('league', 0, 'single_season', 'apg', 1, 'Test Player',     1, 4.5000,  45,  1, 2026),
  ('league', 0, 'single_season', 'apg', 2, 'Test Player Two', 2, 3.9474,  39,  1, 2026);

-- Team single-season records for team_id=1 (Metros)
INSERT INTO ibl_rcb_alltime_records (scope, team_id, record_type, stat_category, ranking, player_name, pid, stat_value, stat_raw, team_of_record, season_year) VALUES
  ('team', 1, 'single_season', 'ppg', 1, 'Test Player',     1, 15.5000, 155, 1, 2026),
  ('team', 1, 'single_season', 'ppg', 2, 'Test Player Two', 2, 13.9474, 139, 1, 2026),
  ('team', 1, 'single_season', 'rpg', 1, 'Test Player Two', 2, 4.7368,  47,  1, 2026),
  ('team', 1, 'single_season', 'rpg', 2, 'Test Player',     1, 4.0000,  40,  1, 2026),
  ('team', 1, 'single_season', 'apg', 1, 'Test Player',     1, 4.5000,  45,  1, 2026),
  ('team', 1, 'single_season', 'apg', 2, 'Test Player Two', 2, 3.9474,  39,  1, 2026);

-- ============================================================
-- Award History (ibl_awards — searched by AwardHistory module)
-- Award names must contain 'MVP' for test search, and player
-- names must match ibl_plr.name for pid JOIN.
-- ============================================================

INSERT INTO ibl_awards (year, Award, name) VALUES
  (2026, 'Regular Season MVP',           'Test Player'),
  (2026, 'Defensive Player of the Year', 'Test Player Two'),
  (2025, 'Regular Season MVP',           'Test Player'),
  (2025, 'Rookie of the Year',           'Test Player Two'),
  (2026, 'Most Improved Player',         'Test Player');

-- ============================================================
-- Transaction History (nuke_stories — filtered by catid)
-- Category IDs: 1=Waiver Pool, 2=Trades, 3=Extensions,
--               8=Free Agency, 10=Rookie Extension, 14=Position Changes
-- ============================================================

INSERT INTO nuke_stories (catid, aid, title, time, hometext, bodytext, topic) VALUES
  (1,  'admin', 'Metros waive Test Bench Player',                       '2026-03-01 12:00:00', 'Waiver transaction details', '', 1),
  (2,  'admin', 'Metros trade Test Player Two to Stars for draft pick', '2026-02-15 10:00:00', 'Trade details',              '', 1),
  (2,  'admin', 'Stars trade Draft Pick to Cougars for Cash',           '2026-02-10 09:00:00', 'Trade details',              '', 1),
  (3,  'admin', 'Test Player extends with Metros for 3 years',         '2026-01-20 14:00:00', 'Extension details',          '', 1),
  (8,  'admin', 'Metros sign Free Agent Guard',                        '2025-12-01 08:00:00', 'Free agency signing',        '', 1),
  (14, 'admin', 'Test Player Two changes position from SF to PF',      '2026-03-02 11:00:00', 'Position change details',    '', 1);

-- ============================================================
-- Olympics seed data (minimal for E2E smoke tests)
-- ============================================================

INSERT INTO ibl_olympics_team_info (teamid, team_city, team_name, color1, color2, uuid) VALUES
  (1, 'USA',    'Eagles',  '002868', 'BF0A30', 'oly-team-uuid-01'),
  (2, 'Canada', 'Maple',   'FF0000', 'FFFFFF', 'oly-team-uuid-02'),
  (3, 'Spain',  'Bulls',   'AA151B', 'F1BF00', 'oly-team-uuid-03'),
  (4, 'France', 'Coqs',    '002395', 'ED2939', 'oly-team-uuid-04');

INSERT INTO ibl_olympics_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, division) VALUES
  (1, 'Eagles', 0.750, '3-1', 3, 1, 'Group A', ''),
  (2, 'Maple',  0.500, '2-2', 2, 2, 'Group A', ''),
  (3, 'Bulls',  0.500, '2-2', 2, 2, 'Group B', ''),
  (4, 'Coqs',   0.250, '1-3', 1, 3, 'Group B', '');

INSERT INTO ibl_olympics_schedule (SchedID, Date, Year, Visitor, VScore, Home, HScore, BoxID, uuid) VALUES
  (1, '2026-07-01', 2026, 1, 95, 2, 88, 1, 'oly-sched-uuid-01'),
  (2, '2026-07-01', 2026, 3, 82, 4, 79, 2, 'oly-sched-uuid-02');

INSERT INTO ibl_olympics_league_config (season_ending_year, team_slot, team_name, conference, division, team_count) VALUES
  (2026, 1, 'Eagles', 'Group A', '', 4),
  (2026, 2, 'Maple',  'Group A', '', 4),
  (2026, 3, 'Bulls',  'Group B', '', 4),
  (2026, 4, 'Coqs',   'Group B', '', 4);

-- ============================================================
-- NOTE: Test user (nuke_users + auth_users) is created by the
-- workflow via PHP bcrypt hash at runtime — not seeded here.
-- ============================================================
