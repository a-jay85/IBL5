-- CI E2E seed data: minimal rows for Playwright smoke tests.
-- Imported AFTER migrations into the CI MariaDB service container.
--
-- RULES:
-- 1. Use INSERT ... ON DUPLICATE KEY UPDATE for any rows that may already
--    exist from migrations (e.g., ibl_settings). Plain INSERT fails with
--    ERROR 1062 Duplicate entry.
-- 2. Seed values must meet PHP WHERE-clause thresholds. For example, EOY MVP
--    requires stats_gm >= 41 AND stats_min / stats_gm >= 30. Always check
--    the PHP query before setting seed data values.

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
  CensorMode, CensorReplace,
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
  0, '',
  '', '5.11', 0, 0, 0
);

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
  ('PlayerDatabase',      'PlayerDatabase',      1, 0),
  ('NextSim',             'NextSim',             1, 0),
  ('ProjectedDraftOrder', 'ProjectedDraftOrder', 1, 0),
  ('Schedule',            'Schedule',            1, 0),
  ('Waivers',             'Waivers',             1, 0),
  ('Voting',              'Voting',              1, 0),
  ('GMContactList',       'GMContactList',       1, 0),
  ('Injuries',            'Injuries',            1, 0),
  ('ActivityTracker',     'ActivityTracker',     1, 0),
  ('LeagueStarters',      'LeagueStarters',      1, 0),
  ('SeriesRecords',       'SeriesRecords',       1, 0),
  ('SeasonHighs',         'SeasonHighs',         1, 0),
  ('PlayerMovement',      'PlayerMovement',      1, 0),
  ('ContractList',        'ContractList',        1, 0),
  ('DraftPickLocator',    'DraftPickLocator',    1, 0),
  ('FreeAgencyPreview',   'FreeAgencyPreview',   1, 0),
  ('Draft',               'Draft',               1, 0),
  ('TeamOffDefStats',     'TeamOffDefStats',     1, 0),
  ('Transaction',         'Transaction',         1, 0),
  ('Topics',              'Topics',              1, 0),
  ('VotingResults',       'VotingResults',       1, 0),
  ('AllStarAppearances',  'AllStarAppearances',  1, 0),
  ('SeasonArchive',       'SeasonArchive',       1, 0),
  ('OneOnOneGame',        'OneOnOneGame',        1, 0),
  ('SiteStatistics',      'SiteStatistics',      1, 0);

-- ============================================================
-- IBL season bootstrap
-- ============================================================

INSERT INTO ibl_settings (name, value) VALUES
  ('Current Season Phase',        'Free Agency'),
  ('Current Season Ending Year',  '2026'),
  ('Allow Trades',                'No'),
  ('Allow Waiver Moves',          'No'),
  ('Show Draft Link',             'Off'),
  ('Free Agency Notifications',   'Off'),
  ('Trivia Mode',                 'Off'),
  ('ASG Voting',                  'No'),
  ('EOY Voting',                  'No'),
  ('Sim Length in Days',            '7')
ON DUPLICATE KEY UPDATE value = VALUES(value);

INSERT INTO ibl_sim_dates (`Sim`, `Start Date`, `End Date`) VALUES
  (689, '2026-03-01', '2026-03-07');

-- ============================================================
-- Teams (28 real franchises + Free Agents)
-- Only columns required by the app; others use table defaults.
-- ============================================================

INSERT INTO ibl_team_info (teamid, team_city, team_name, color1, color2, uuid) VALUES
  ( 0, '',             'Free Agents',  '888888', 'cccccc', 'b0000000-0000-0000-0000-000000000000'),
  ( 1, 'New York',     'Metros',       '003DA5', 'FF5733', 'b0000000-0000-0000-0000-000000000001'),
  ( 2, 'Los Angeles',  'Stars',        '552583', 'FDB927', 'b0000000-0000-0000-0000-000000000002'),
  ( 3, 'Chicago',      'Cougars',      'CE1141', '000000', 'b0000000-0000-0000-0000-000000000003'),
  ( 4, 'Detroit',      'Diesels',      '006BB6', 'ED174C', 'b0000000-0000-0000-0000-000000000004'),
  ( 5, 'Boston',       'Minutemen',    '007A33', 'BA9653', 'b0000000-0000-0000-0000-000000000005'),
  ( 6, 'Philadelphia', 'Rage',         'ED174C', '003DA5', 'b0000000-0000-0000-0000-000000000006'),
  ( 7, 'Orlando',      'Tropics',      '0077C0', '000000', 'b0000000-0000-0000-0000-000000000007'),
  ( 8, 'Miami',        'Monarchs',     '98002E', 'F9A01B', 'b0000000-0000-0000-0000-000000000008'),
  ( 9, 'Phoenix',      'Flames',       'E56020', '1D1160', 'b0000000-0000-0000-0000-000000000009'),
  (10, 'San Antonio',  'Spurs',        'C4CED4', '000000', 'b0000000-0000-0000-0000-000000000010'),
  (11, 'Portland',     'Pioneers',     'E03A3E', '000000', 'b0000000-0000-0000-0000-000000000011'),
  (12, 'Charlotte',    'Royals',       '1D428A', '00788C', 'b0000000-0000-0000-0000-000000000012'),
  (13, 'Houston',      'Apollos',      'CE1141', 'C4CED4', 'b0000000-0000-0000-0000-000000000013'),
  (14, 'Atlanta',      'Phoenixes',    'E03A3E', 'C1D32F', 'b0000000-0000-0000-0000-000000000014'),
  (15, 'Memphis',      'Blues',        '5D76A9', '12173F', 'b0000000-0000-0000-0000-000000000015'),
  (16, 'Minnesota',    'Blizzard',     '0C2340', '236192', 'b0000000-0000-0000-0000-000000000016'),
  (17, 'Toronto',      'Huskies',      'CE1141', '000000', 'b0000000-0000-0000-0000-000000000017'),
  (18, 'Milwaukee',    'Bucks',        '00471B', 'EEE1C6', 'b0000000-0000-0000-0000-000000000018'),
  (19, 'Denver',       'Nuggets',      '0E2240', 'FEC524', 'b0000000-0000-0000-0000-000000000019'),
  (20, 'Sacramento',   'Pilots',       '5A2D81', '63727A', 'b0000000-0000-0000-0000-000000000020'),
  (21, 'Dallas',       'Mavericks',    '00538C', '002B5E', 'b0000000-0000-0000-0000-000000000021'),
  (22, 'Cleveland',    'Cavaliers',    '6F263D', '041E42', 'b0000000-0000-0000-0000-000000000022'),
  (23, 'Seattle',      'Supersonics',  '006633', 'FFC200', 'b0000000-0000-0000-0000-000000000023'),
  (24, 'New Jersey',   'Nets',         '002A60', 'CD1041', 'b0000000-0000-0000-0000-000000000024'),
  (25, 'Washington',   'Generals',     '002B5C', 'E31837', 'b0000000-0000-0000-0000-000000000025'),
  (26, 'Indiana',      'Pacers',       '002D62', 'FDBB30', 'b0000000-0000-0000-0000-000000000026'),
  (27, 'Utah',         'Jazz',         '002B5C', '00471B', 'b0000000-0000-0000-0000-000000000027'),
  (28, 'Oklahoma City','Thunder',      '007AC1', 'EF6100', 'b0000000-0000-0000-0000-000000000028');

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

-- Clinch indicators for standings E2E tests
-- Y = clinched playoff berth, X = clinched division, W = clinched conference
UPDATE ibl_standings SET clinchedPlayoffs = 1 WHERE tid = 1;
UPDATE ibl_standings SET clinchedDivision = 1 WHERE tid = 2;
UPDATE ibl_standings SET clinchedConference = 1 WHERE tid = 3;

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
-- Players
--   pid=1,2: active on Metros (tid=1) for Compare Players + trading
--   pid=3:   retired for retirees toggle
--   pid=4,5: active on Stars (tid=2) for additional trading partner
--   pid=6,7: active on Phoenixes (tid=14, Atlanta) — first partner alphabetically by city
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
   1, 3, 800, 880,
   0, 5,
   6, 4, 200, 'Test University',
   1, 5, 2021, 'Metros', 'Metros',
   41, 1260, 200, 450, 100, 120,
   60, 150, 40, 120, 180, 50,
   80, 20, 90,
   'a0000000-0000-0000-0000-000000000001'),
  (2, 'Test Player Two', 26, 27, 1, 'PF', 2,
   78, 72, 68, 63, 58, 70, 66, 68, 63,
   1, 2, 600, 660,
   0, 3,
   6, 8, 220, 'Test State',
   1, 12, 2023, 'Metros', 'Metros',
   41, 1260, 180, 400, 90, 110,
   40, 120, 50, 130, 150, 45,
   70, 25, 85,
   'a0000000-0000-0000-0000-000000000002'),
  (3, 'Retired Legend', 38, 30, 0, 'C', 0,
   60, 80, 75, 70, 65, 75, 70, 72, 67,
   0, 0, 0, 0,
   1, 15,
   7, 0, 250, 'Legend College',
   1, 1, 2011, 'Stars', 'Stars',
   500, 16000, 3000, 6500, 1500, 1800,
   200, 600, 800, 2200, 1500, 400,
   900, 300, 1200,
   'a0000000-0000-0000-0000-000000000003'),
  (4, 'Stars Guard', 25, 27, 2, 'PG', 1,
   82, 78, 72, 66, 62, 74, 70, 72, 67,
   1, 3, 1800, 1900,
   0, 4,
   6, 2, 190, 'Stars Academy',
   1, 8, 2022, 'Stars', 'Stars',
   41, 1300, 210, 460, 110, 130,
   65, 160, 35, 110, 200, 55,
   75, 15, 80,
   'a0000000-0000-0000-0000-000000000004'),
  (5, 'Stars Forward', 27, 28, 2, 'SF', 2,
   79, 74, 69, 64, 59, 71, 67, 69, 64,
   1, 2, 1400, 1500,
   0, 6,
   6, 7, 215, 'Stars College',
   2, 3, 2020, 'Stars', 'Stars',
   41, 1260, 190, 420, 95, 115,
   45, 130, 45, 140, 160, 48,
   65, 22, 88,
   'a0000000-0000-0000-0000-000000000005'),
  (6, 'Phoenixes Guard', 26, 28, 14, 'PG', 1,
   81, 76, 71, 65, 61, 73, 69, 71, 66,
   1, 3, 1700, 1800,
   0, 5,
   6, 1, 185, 'Phoenixes Academy',
   1, 10, 2021, 'Phoenixes', 'Phoenixes',
   41, 1260, 200, 440, 105, 125,
   55, 145, 38, 115, 190, 52,
   72, 18, 82,
   'a0000000-0000-0000-0000-000000000006'),
  (7, 'Phoenixes Center', 29, 29, 14, 'C', 2,
   83, 80, 74, 68, 64, 76, 72, 74, 69,
   1, 2, 1300, 1400,
   0, 7,
   6, 11, 240, 'Phoenixes College',
   1, 6, 2019, 'Phoenixes', 'Phoenixes',
   41, 1300, 220, 480, 115, 135,
   20, 60, 60, 160, 100, 40,
   60, 35, 95,
   'a0000000-0000-0000-0000-000000000007');

-- ============================================================
-- Free agent players (pid=10,11,12) for Free Agency E2E tests
-- Formula: draftYear + exp + cyt - cy = 2026 (season ending year)
-- With cy=0, cyt=0: draftYear + exp = 2026
-- ============================================================

INSERT INTO ibl_plr (
  pid, name, age, peak, tid, pos, ordinal,
  sta, oo, od, `do`, dd, po, pd, `to`, td,
  cy, cyt, cy1, cy2,
  retired, exp, bird,
  htft, htin, wt, college,
  draftround, draftpickno, draftyear, draftedby, draftedbycurrentname,
  stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta,
  stats_3gm, stats_3ga, stats_orb, stats_drb, stats_ast, stats_stl,
  stats_to, stats_blk, stats_pf,
  uuid
) VALUES
  (10, 'FA Guard', 26, 28, 1, 'SG', 3,
   80, 75, 70, 65, 60, 72, 68, 70, 65,
   0, 0, 0, 0,
   0, 5, 4,
   6, 3, 195, 'Guard Academy',
   1, 15, 2021, 'Metros', 'Metros',
   41, 1260, 200, 450, 100, 120,
   60, 150, 40, 120, 180, 50,
   80, 20, 90,
   'a0000000-0000-0000-0000-000000000010'),
  (11, 'FA Center', 30, 29, 0, 'C', 0,
   78, 72, 68, 63, 58, 70, 66, 68, 63,
   0, 0, 0, 0,
   0, 8, 0,
   7, 0, 250, 'Center College',
   1, 20, 2018, 'Stars', 'Stars',
   41, 1260, 180, 400, 90, 110,
   40, 120, 50, 130, 150, 45,
   70, 25, 85,
   'a0000000-0000-0000-0000-000000000011'),
  (12, 'FA Forward', 25, 27, 2, 'SF', 3,
   79, 74, 69, 64, 59, 71, 67, 69, 64,
   0, 0, 0, 0,
   0, 3, 2,
   6, 6, 210, 'Forward University',
   2, 5, 2023, 'Stars', 'Stars',
   41, 1260, 190, 420, 95, 115,
   45, 130, 45, 140, 160, 48,
   65, 22, 88,
   'a0000000-0000-0000-0000-000000000012');

-- Free agent demands
INSERT INTO ibl_demands (name, pid, dem1, dem2, dem3, dem4, dem5, dem6) VALUES
  ('FA Guard',   10, 800, 880, 960, 1040, 0, 0),
  ('FA Center',  11, 500, 550, 600, 0, 0, 0),
  ('FA Forward', 12, 400, 440, 480, 520, 560, 600);

-- Free agent player history (needed for SeasonLeaderboards)
INSERT INTO ibl_hist (
  pid, name, year, team, teamid,
  games, minutes, fgm, fga, ftm, fta, tgm, tga,
  orb, reb, ast, stl, blk, tvr, pf, pts, salary
) VALUES
  (10, 'FA Guard', 2026, 'Metros', 1,
   41, 1260, 200, 450, 100, 120, 60, 150,
   40, 160, 180, 50, 20, 80, 90, 620, 0),
  (11, 'FA Center', 2026, 'Stars', 2,
   41, 1260, 180, 400, 90, 110, 40, 120,
   50, 180, 150, 45, 25, 70, 85, 530, 0),
  (12, 'FA Forward', 2026, 'Stars', 2,
   41, 1260, 190, 420, 95, 115, 45, 130,
   45, 185, 160, 48, 22, 65, 88, 565, 0);

-- Team MLE/LLE flags: Metros have both exceptions available
UPDATE ibl_team_info SET HasMLE = 1, HasLLE = 1 WHERE teamid = 1;

-- ============================================================
-- Additional players for depth chart starters
-- Metros need PG, SF, C starters (pid=1 is SG, pid=2 is PF)
-- Cougars (tid=3) need players for NextSim opposing starters
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
  (20, 'Metros PG', 27, 28, 1, 'PG', 3,
   80, 76, 70, 66, 60, 72, 68, 70, 65,
   1, 2, 300, 330,
   0, 4,
   6, 1, 185, 'PG University',
   1, 7, 2022, 'Metros', 'Metros',
   41, 1260, 200, 450, 100, 120,
   60, 150, 30, 100, 200, 55,
   75, 10, 80,
   'a0000000-0000-0000-0000-000000000020'),
  (21, 'Metros SF', 26, 27, 1, 'SF', 4,
   78, 74, 68, 64, 58, 70, 66, 68, 63,
   1, 2, 200, 220,
   0, 3,
   6, 6, 205, 'SF University',
   1, 15, 2023, 'Metros', 'Metros',
   41, 1260, 180, 400, 90, 110,
   40, 120, 35, 120, 140, 45,
   65, 20, 85,
   'a0000000-0000-0000-0000-000000000021'),
  (22, 'Metros Center', 29, 29, 1, 'C', 5,
   82, 78, 72, 68, 64, 76, 72, 74, 69,
   1, 2, 300, 330,
   0, 6,
   7, 0, 245, 'Center University',
   1, 3, 2020, 'Metros', 'Metros',
   41, 1300, 220, 480, 115, 135,
   20, 60, 60, 160, 100, 40,
   60, 35, 95,
   'a0000000-0000-0000-0000-000000000022'),
  (23, 'Cougars Guard', 26, 27, 3, 'PG', 1,
   79, 74, 69, 64, 59, 71, 67, 69, 64,
   1, 2, 1300, 1400,
   0, 4,
   6, 2, 190, 'Cougars Academy',
   1, 11, 2022, 'Cougars', 'Cougars',
   41, 1260, 195, 440, 100, 120,
   55, 140, 35, 110, 185, 50,
   70, 15, 80,
   'a0000000-0000-0000-0000-000000000023'),
  (24, 'Cougars Forward', 28, 28, 3, 'SF', 2,
   81, 76, 71, 66, 62, 73, 69, 71, 66,
   1, 3, 1500, 1600,
   0, 5,
   6, 7, 215, 'Cougars College',
   1, 9, 2021, 'Cougars', 'Cougars',
   41, 1300, 210, 460, 105, 125,
   45, 130, 45, 140, 160, 48,
   65, 22, 88,
   'a0000000-0000-0000-0000-000000000024');

-- ============================================================
-- Placeholder player for LeagueStarters module
-- LeagueStartersService::getOrLoadPlaceholder() loads pid=4040404
-- as a stand-in for teams with no starter at a given position.
-- Without this row, Player::withPlayerID queries a nonexistent row
-- 24 times per position (for 24 teams without starters), slowing
-- the page under CI's PHP built-in server.
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
  (4040404, 'No Starter', 0, 0, 0, 'C', 0,
   0, 0, 0, 0, 0, 0, 0, 0, 0,
   0, 0, 0, 0,
   0, 0,
   0, 0, 0, '',
   0, 0, 0, '', '',
   0, 0, 0, 0, 0, 0,
   0, 0, 0, 0, 0, 0,
   0, 0, 0,
   'a0000000-0000-ffff-0000-000004040404');

-- ============================================================
-- Depth chart starters (NextSim needs dc_*Depth=1 and *Depth=1)
-- ============================================================

-- Metros (tid=1): 5 distinct starters
UPDATE ibl_plr SET dc_PGDepth = 1, PGDepth = 1 WHERE pid = 20;
UPDATE ibl_plr SET dc_SGDepth = 1, SGDepth = 1 WHERE pid = 1;
UPDATE ibl_plr SET dc_SFDepth = 1, SFDepth = 1 WHERE pid = 21;
UPDATE ibl_plr SET dc_PFDepth = 1, PFDepth = 1 WHERE pid = 2;
UPDATE ibl_plr SET dc_CDepth  = 1, CDepth  = 1 WHERE pid = 22;

-- Stars (tid=2): pid=4 covers PG/SG/PF, pid=5 covers SF/C
UPDATE ibl_plr SET dc_PGDepth = 1, PGDepth = 1, dc_SGDepth = 1, SGDepth = 1, dc_PFDepth = 1, PFDepth = 1 WHERE pid = 4;
UPDATE ibl_plr SET dc_SFDepth = 1, SFDepth = 1, dc_CDepth = 1, CDepth = 1 WHERE pid = 5;

-- Cougars (tid=3): pid=23 covers PG/SG/PF, pid=24 covers SF/C
UPDATE ibl_plr SET dc_PGDepth = 1, PGDepth = 1, dc_SGDepth = 1, SGDepth = 1, dc_PFDepth = 1, PFDepth = 1 WHERE pid = 23;
UPDATE ibl_plr SET dc_SFDepth = 1, SFDepth = 1, dc_CDepth = 1, CDepth = 1 WHERE pid = 24;

-- Phoenixes (tid=14): pid=6 covers PG/SG/SF, pid=7 covers PF/C
UPDATE ibl_plr SET dc_PGDepth = 1, PGDepth = 1, dc_SGDepth = 1, SGDepth = 1, dc_SFDepth = 1, SFDepth = 1 WHERE pid = 6;
UPDATE ibl_plr SET dc_PFDepth = 1, PFDepth = 1, dc_CDepth = 1, CDepth = 1 WHERE pid = 7;

-- ============================================================
-- Saved depth chart configs (for depth-chart-changes test)
-- Need at least 1 saved config so #saved-dc-select has >= 2 options
-- ============================================================

INSERT INTO ibl_saved_depth_charts (id, tid, username, name, phase, season_year, sim_start_date, sim_number_start, is_active) VALUES
  (1, 1, 'A-Jay', 'Offensive Config', 'Free Agency', 2026, '2026-03-01', 689, 0),
  (2, 1, 'A-Jay', 'Defensive Config', 'Free Agency', 2026, '2026-03-01', 689, 0);

INSERT INTO ibl_saved_depth_chart_players (depth_chart_id, pid, player_name, ordinal, dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth, dc_canPlayInGame, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh) VALUES
  (1, 1, 'Test Player', 1, 0, 1, 0, 0, 0, 1, 0, 0, 0, 0, 0, 0),
  (1, 2, 'Test Player Two', 2, 0, 0, 0, 1, 0, 1, 0, 0, 0, 0, 0, 0),
  (2, 1, 'Test Player', 1, 0, 1, 0, 0, 0, 1, 0, 1, 0, 0, 0, 0),
  (2, 2, 'Test Player Two', 2, 0, 0, 0, 1, 0, 1, 0, 0, 1, 0, 0, 0);

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
   41, 1260, 200, 450, 100, 120, 60, 150,
   40, 160, 180, 50, 20, 80, 90, 620, 800),
  (2, 'Test Player Two', 2026, 'Metros', 1,
   41, 1260, 180, 400, 90, 110, 40, 120,
   50, 180, 150, 45, 25, 70, 85, 530, 600),
  (3, 'Retired Legend', 2025, 'Stars', 2,
   41, 1300, 250, 500, 150, 180, 30, 100,
   80, 220, 100, 40, 50, 60, 100, 710, 2000),
  (4, 'Stars Guard', 2026, 'Stars', 2,
   41, 1300, 210, 460, 110, 130, 65, 160,
   35, 145, 200, 55, 15, 75, 80, 660, 1800),
  (5, 'Stars Forward', 2026, 'Stars', 2,
   41, 1260, 190, 420, 95, 115, 45, 130,
   45, 185, 160, 48, 22, 65, 88, 565, 1400),
  (6, 'Phoenixes Guard', 2026, 'Phoenixes', 14,
   41, 1260, 200, 440, 105, 125, 55, 145,
   38, 153, 190, 52, 18, 72, 82, 615, 1700),
  (7, 'Phoenixes Center', 2026, 'Phoenixes', 14,
   41, 1300, 220, 480, 115, 135, 20, 60,
   60, 220, 100, 40, 35, 60, 95, 610, 1300),
  -- RecordHolders: full-season row (games >= 50 threshold for season averages)
  (3, 'Retired Legend', 2024, 'Stars', 2,
   55, 1800, 320, 650, 200, 240, 40, 130,
   100, 280, 130, 50, 60, 70, 120, 920, 2200),
  -- PlayerMovement: pid=4 played for Metros (tid=1) in 2025, now on Stars (tid=2)
  (4, 'Stars Guard', 2025, 'Metros', 1,
   41, 1280, 200, 440, 100, 120, 60, 150,
   30, 140, 190, 50, 12, 70, 78, 620, 1600);

-- ============================================================
-- Draft picks (DraftHistory page)
-- ============================================================

INSERT INTO ibl_draft_picks (ownerofpick, owner_tid, teampick, teampick_tid, year, round) VALUES
  ('Metros',    1,  'Metros',    1,  2026, 1),
  ('Stars',     2,  'Stars',     2,  2026, 1),
  ('Phoenixes', 14, 'Phoenixes', 14, 2026, 1);

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
  (1, 'USA',    'Eagles',  '002868', 'BF0A30', 'd0000000-0000-0000-0000-000000000001'),
  (2, 'Canada', 'Maple',   'FF0000', 'FFFFFF', 'd0000000-0000-0000-0000-000000000002'),
  (3, 'Spain',  'Bulls',   'AA151B', 'F1BF00', 'd0000000-0000-0000-0000-000000000003'),
  (4, 'France', 'Coqs',    '002395', 'ED2939', 'd0000000-0000-0000-0000-000000000004');

INSERT INTO ibl_olympics_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, division) VALUES
  (1, 'Eagles', 0.750, '3-1', 3, 1, 'Group A', ''),
  (2, 'Maple',  0.500, '2-2', 2, 2, 'Group A', ''),
  (3, 'Bulls',  0.500, '2-2', 2, 2, 'Group B', ''),
  (4, 'Coqs',   0.250, '1-3', 1, 3, 'Group B', '');

INSERT INTO ibl_olympics_schedule (SchedID, Date, Year, Visitor, VScore, Home, HScore, BoxID, uuid) VALUES
  (1, '2026-07-01', 2026, 1, 95, 2, 88, 1, 'e0000000-0000-0000-0000-000000000001'),
  (2, '2026-07-01', 2026, 3, 82, 4, 79, 2, 'e0000000-0000-0000-0000-000000000002');

INSERT INTO ibl_olympics_league_config (season_ending_year, team_slot, team_name, conference, division, team_count) VALUES
  (2026, 1, 'Eagles', 'Group A', '', 4),
  (2026, 2, 'Maple',  'Group A', '', 4),
  (2026, 3, 'Bulls',  'Group B', '', 4),
  (2026, 4, 'Coqs',   'Group B', '', 4);

-- ============================================================
-- Trade offers (for Trading review E2E tests)
-- Test user is on Metros (tid=1). Offers must involve Metros.
-- ============================================================

INSERT INTO ibl_trade_offers (id) VALUES (1), (2), (3), (4), (5), (6);

INSERT INTO ibl_trade_info (tradeofferid, itemid, itemtype, trade_from, trade_to, approval) VALUES
  -- Offer 1: Stars Guard (pid=4) from Stars to Metros, player (pid=2) from Metros to Stars
  (1, 4, '1', 'Stars', 'Metros', 'Metros'),
  (1, 2, '1', 'Metros', 'Stars', 'Metros'),
  -- Offer 2: Phoenixes Guard (pid=6) from Phoenixes to Metros, draft pick from Metros to Phoenixes
  (2, 6, '1', 'Phoenixes', 'Metros', 'Metros'),
  (2, 1, '0', 'Metros', 'Phoenixes', 'Metros'),
  -- Offers 3-6: extra offers so review card tests survive parallel consumption by submission tests
  -- Offer 3: Cougars Guard (pid=23) from Cougars to Metros
  (3, 23, '1', 'Cougars', 'Metros', 'Metros'),
  (3, 21, '1', 'Metros', 'Cougars', 'Metros'),
  -- Offer 4: Stars Forward (pid=5) from Stars to Metros
  (4, 5, '1', 'Stars', 'Metros', 'Metros'),
  (4, 20, '1', 'Metros', 'Stars', 'Metros'),
  -- Offer 5: Phoenixes Center (pid=7) from Phoenixes to Metros
  (5, 7, '1', 'Phoenixes', 'Metros', 'Metros'),
  (5, 22, '1', 'Metros', 'Phoenixes', 'Metros'),
  -- Offer 6: Cougars Forward (pid=24) from Cougars to Metros
  (6, 24, '1', 'Cougars', 'Metros', 'Metros'),
  (6, 10, '1', 'Metros', 'Cougars', 'Metros');

-- ============================================================
-- Stories for search pagination (need >10 results for "the")
-- ============================================================

INSERT INTO nuke_stories (catid, aid, title, time, hometext, bodytext, topic) VALUES
  (1, 'admin', 'The Cougars waive backup center',          '2026-02-20 10:00:00', 'The team needed the roster spot', '', 1),
  (1, 'admin', 'The Diesels claim forward off waivers',    '2026-02-19 10:00:00', 'The pickup bolsters the bench',   '', 1),
  (2, 'admin', 'The Minutemen complete trade with Tropics', '2026-02-18 10:00:00', 'The deal sends three players',    '', 1),
  (2, 'admin', 'The Monarchs acquire the number one pick', '2026-02-17 10:00:00', 'The draft pick was the centerpiece', '', 1),
  (3, 'admin', 'The Flames extend their star player',      '2026-02-16 10:00:00', 'The extension is for three years', '', 1),
  (8, 'admin', 'The Spurs sign free agent guard',          '2026-02-15 10:00:00', 'The signing fills the starting role', '', 1),
  (1, 'admin', 'The Pioneers waive the veteran forward',   '2026-02-14 10:00:00', 'The move clears cap space',       '', 1),
  (2, 'admin', 'The Royals trade for the young center',    '2026-02-13 10:00:00', 'The rebuild continues',           '', 1),
  (3, 'admin', 'The Apollos extend the franchise player',  '2026-02-12 10:00:00', 'The max deal locks them in',      '', 1),
  (8, 'admin', 'The Blues sign the top free agent',        '2026-02-11 10:00:00', 'The biggest signing of the period', '', 1),
  (1, 'admin', 'The Blizzard waive the backup guard',      '2026-02-10 10:00:00', 'The roster move was expected',     '', 1),
  (2, 'admin', 'The Huskies pull off the trade deadline deal', '2026-02-09 10:00:00', 'The swingman fits the system',   '', 1),
  (1, 'admin', 'The Bucks waive the reserve center',      '2026-02-08 10:00:00', 'The move opens the roster spot',  '', 1),
  (8, 'admin', 'The Nuggets sign the veteran shooter',    '2026-02-07 10:00:00', 'The addition fills the gap',      '', 1),
  (2, 'admin', 'The Pilots trade the young prospect',     '2026-02-06 10:00:00', 'The rebuild enters the next phase', '', 1),
  (3, 'admin', 'The Mavericks extend the all-star guard', '2026-02-05 10:00:00', 'The deal is the largest in IBL history', '', 1);

-- ============================================================
-- Saved depth chart configs (for depth-chart-changes.spec.ts)
-- ============================================================

INSERT INTO ibl_saved_depth_charts (id, tid, username, name, phase, season_year, sim_start_date, sim_number_start, is_active) VALUES
  (1, 1, 'A-Jay', 'Offensive Config', 'Free Agency', 2026, '2026-03-01', 689, 0),
  (2, 1, 'A-Jay', 'Defensive Config', 'Free Agency', 2026, '2026-03-01', 689, 0)
ON DUPLICATE KEY UPDATE name = VALUES(name);

INSERT INTO ibl_saved_depth_chart_players (depth_chart_id, pid, player_name, ordinal, dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth, dc_canPlayInGame, dc_minutes, dc_of, dc_df, dc_oi, dc_di, dc_bh) VALUES
  (1, 1, 'Test Player One', 1, 1, 0, 0, 0, 0, 1, 30, 5, 5, 3, 3, 5),
  (1, 2, 'Test Player Two', 2, 0, 1, 0, 0, 0, 1, 28, 4, 6, 2, 4, 3),
  (2, 1, 'Test Player One', 1, 0, 0, 0, 1, 0, 1, 32, 3, 7, 2, 5, 4),
  (2, 2, 'Test Player Two', 2, 1, 0, 0, 0, 0, 1, 26, 6, 4, 4, 2, 6)
ON DUPLICATE KEY UPDATE player_name = VALUES(player_name);

-- ============================================================
-- Schedule games for NextSim (Metros games in sim 689 window)
-- ============================================================

INSERT INTO ibl_schedule (Year, Date, Visitor, Home, VScore, HScore, BoxID, uuid) VALUES
  (2026, '2026-03-08', 1, 2,  0, 0, 0, 'c0000000-0000-0000-0000-000000000001'),
  (2026, '2026-03-10', 3, 1,  0, 0, 0, 'c0000000-0000-0000-0000-000000000002'),
  (2026, '2026-03-12', 1, 14, 0, 0, 0, 'c0000000-0000-0000-0000-000000000003'),
  -- Schedule for teamID=5 (Minutemen) — needed by team-schedule no-SOS-data tests
  (2026, '2026-03-09', 5, 6,  0, 0, 0, 'c0000000-0000-0000-0000-000000000010'),
  (2026, '2026-03-11', 7, 5,  0, 0, 0, 'c0000000-0000-0000-0000-000000000011');

-- ============================================================
-- Topics (nuke_topics) for Topics module E2E tests
-- PK is `id`, topicid is an auto-increment index
-- ============================================================

INSERT INTO nuke_topics (topicid, topicname, topicimage, topictext, counter, id) VALUES
  (1, 'IBL',      'IBL.gif',      'IBL News',      10, 1),
  (2, 'Trades',   'trades.gif',   'Trade News',     5, 2),
  (3, 'Draft',    'draft.gif',    'Draft Coverage',  3, 3);

-- Stories linked to topics (for topic card article lists)
INSERT INTO nuke_stories (catid, aid, title, time, hometext, bodytext, topic) VALUES
  (0, 'admin', 'Metros win season opener',             '2026-03-05 10:00:00', 'Great start to the season', '', 1),
  (0, 'admin', 'Stars acquire top free agent',          '2026-03-04 10:00:00', 'Big move for the Stars',    '', 1),
  (0, 'admin', 'Blockbuster trade shakes up the league', '2026-03-03 10:00:00', 'Three-team deal completed',  '', 2);

-- Categories for search filter dropdown
INSERT INTO nuke_stories_cat (catid, title) VALUES
  (15, 'General')
ON DUPLICATE KEY UPDATE title = VALUES(title);

-- Authors for search filter dropdown (admin already used in stories above)
INSERT INTO nuke_authors (aid, name) VALUES
  ('admin', 'Admin')
ON DUPLICATE KEY UPDATE name = VALUES(name);

-- ============================================================
-- Draft class prospects (for Draft E2E tests)
-- 4 undrafted + 2 already drafted
-- ============================================================

INSERT INTO ibl_draft_class (name, pos, age, team, fga, fgp, fta, ftp, tga, tgp, orb, drb, ast, stl, tvr, blk, oo, `do`, po, `to`, od, dd, pd, td, talent, skill, intangibles, drafted, sta) VALUES
  ('Prospect Guard',    'PG', 19, 'Duke',       60, 55, 50, 70, 40, 45, 30, 40, 65, 50, 45, 20, 60, 55, 40, 50, 55, 50, 40, 45, 70, 65, 60, 0, 75),
  ('Prospect Wing',     'SF', 20, 'Kentucky',   55, 50, 45, 65, 35, 40, 40, 50, 50, 45, 40, 30, 55, 50, 50, 45, 50, 55, 45, 50, 65, 60, 55, 0, 70),
  ('Prospect Big',      'C',  21, 'Gonzaga',    50, 55, 55, 60, 20, 30, 55, 60, 35, 30, 35, 55, 45, 40, 60, 35, 45, 40, 60, 40, 60, 55, 65, 0, 80),
  ('Prospect Shooter',  'SG', 19, 'Villanova',  65, 60, 40, 75, 60, 55, 25, 35, 45, 40, 50, 15, 65, 45, 35, 50, 50, 45, 35, 45, 55, 50, 50, 0, 68),
  ('Already Drafted PG','PG', 20, 'UCLA',       58, 52, 48, 68, 38, 42, 32, 42, 60, 48, 42, 18, 58, 52, 38, 48, 52, 48, 38, 42, 68, 62, 58, 1, 72),
  ('Already Drafted PF','PF', 21, 'Michigan',   52, 48, 50, 62, 25, 35, 48, 55, 40, 35, 38, 40, 50, 45, 55, 40, 48, 45, 55, 42, 62, 58, 60, 1, 76);

-- Update drafted prospects with team names
UPDATE ibl_draft_class SET team = 'Stars' WHERE name = 'Already Drafted PG';
UPDATE ibl_draft_class SET team = 'Cougars' WHERE name = 'Already Drafted PF';

-- ============================================================
-- Draft picks for round 1 (Metros pick 1 = on the clock)
-- Only need a few picks; pick 1 has empty player (on the clock)
-- ============================================================

INSERT INTO ibl_draft (year, team, tid, player, round, pick, uuid) VALUES
  (2026, 'Metros',    1,  '', 1, 1, 'draft-uuid-r1p01'),
  (2026, 'Stars',     2,  '', 1, 2, 'draft-uuid-r1p02'),
  (2026, 'Cougars',   3,  '', 1, 3, 'draft-uuid-r1p03'),
  (2026, 'Diesels',   4,  '', 1, 4, 'draft-uuid-r1p04');

-- ============================================================
-- GM owner names (for EOY Voting GM of Year ballot)
-- ============================================================

UPDATE ibl_team_info SET owner_name = 'GM TestUser' WHERE teamid = 1;
UPDATE ibl_team_info SET owner_name = 'GM Stars'    WHERE teamid = 2;
UPDATE ibl_team_info SET owner_name = 'GM Cougars'  WHERE teamid = 3;
UPDATE ibl_team_info SET owner_name = 'GM Diesels'  WHERE teamid = 4;
UPDATE ibl_team_info SET owner_name = 'GM Minutemen' WHERE teamid = 5;
UPDATE ibl_team_info SET owner_name = 'GM Rage'     WHERE teamid = 6;
UPDATE ibl_team_info SET owner_name = 'GM Tropics'  WHERE teamid = 7;
UPDATE ibl_team_info SET owner_name = 'GM Monarchs' WHERE teamid = 8;
UPDATE ibl_team_info SET owner_name = 'GM Flames'   WHERE teamid = 9;
UPDATE ibl_team_info SET owner_name = 'GM Spurs'    WHERE teamid = 10;
UPDATE ibl_team_info SET owner_name = 'GM Pioneers' WHERE teamid = 11;
UPDATE ibl_team_info SET owner_name = 'GM Royals'   WHERE teamid = 12;
UPDATE ibl_team_info SET owner_name = 'GM Apollos'  WHERE teamid = 13;
UPDATE ibl_team_info SET owner_name = 'GM Phoenixes' WHERE teamid = 14;
UPDATE ibl_team_info SET owner_name = 'GM Blues'    WHERE teamid = 15;
UPDATE ibl_team_info SET owner_name = 'GM Blizzard' WHERE teamid = 16;
UPDATE ibl_team_info SET owner_name = 'GM Huskies'  WHERE teamid = 17;
UPDATE ibl_team_info SET owner_name = 'GM Bucks'    WHERE teamid = 18;
UPDATE ibl_team_info SET owner_name = 'GM Nuggets'  WHERE teamid = 19;
UPDATE ibl_team_info SET owner_name = 'GM Pilots'   WHERE teamid = 20;
UPDATE ibl_team_info SET owner_name = 'GM Mavericks' WHERE teamid = 21;
UPDATE ibl_team_info SET owner_name = 'GM Cavaliers' WHERE teamid = 22;
UPDATE ibl_team_info SET owner_name = 'GM Supersonics' WHERE teamid = 23;
UPDATE ibl_team_info SET owner_name = 'GM Nets'     WHERE teamid = 24;
UPDATE ibl_team_info SET owner_name = 'GM Generals' WHERE teamid = 25;
UPDATE ibl_team_info SET owner_name = 'GM Pacers'   WHERE teamid = 26;
UPDATE ibl_team_info SET owner_name = 'GM Jazz'     WHERE teamid = 27;
UPDATE ibl_team_info SET owner_name = 'GM Thunder'  WHERE teamid = 28;

-- ============================================================
-- Additional players for voting candidate coverage
-- Need players on non-Metros teams across conferences/positions
-- for ASG ballot categories (ECF, ECB, WCF, WCB)
-- Existing: pid 4,5 (Stars/Western), pid 6,7 (Phoenixes/Eastern),
--           pid 23,24 (Cougars/Eastern)
-- Need more Western backcourt + frontcourt candidates
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
  -- Spurs PG (Western/Midwest backcourt)
  (30, 'Spurs Guard', 25, 27, 10, 'PG', 1,
   80, 75, 70, 65, 60, 72, 68, 70, 65,
   1, 2, 500, 550,
   0, 3,
   6, 2, 190, 'Spurs Academy',
   1, 14, 2023, 'Spurs', 'Spurs',
   41, 1260, 200, 450, 100, 120,
   60, 150, 40, 120, 180, 50,
   80, 20, 90,
   'a0000000-0000-0000-0000-000000000030'),
  -- Flames SF (Western/Pacific frontcourt)
  (31, 'Flames Forward', 27, 28, 9, 'SF', 1,
   78, 74, 68, 64, 58, 70, 66, 68, 63,
   1, 2, 600, 660,
   0, 5,
   6, 7, 215, 'Flames College',
   1, 9, 2021, 'Flames', 'Flames',
   41, 1260, 190, 420, 95, 115,
   45, 130, 45, 140, 160, 48,
   65, 22, 88,
   'a0000000-0000-0000-0000-000000000031'),
  -- Minutemen SG (Eastern/Atlantic backcourt)
  (32, 'Minutemen Guard', 26, 27, 5, 'SG', 1,
   79, 73, 69, 63, 59, 71, 67, 69, 64,
   1, 2, 700, 770,
   0, 4,
   6, 3, 195, 'Minutemen Academy',
   1, 11, 2022, 'Minutemen', 'Minutemen',
   41, 1260, 195, 440, 100, 120,
   55, 140, 35, 110, 185, 50,
   70, 15, 80,
   'a0000000-0000-0000-0000-000000000032'),
  -- Royals PF (Eastern/Central frontcourt)
  (33, 'Royals Forward', 28, 28, 12, 'PF', 1,
   81, 76, 71, 66, 62, 73, 69, 71, 66,
   1, 2, 800, 880,
   0, 6,
   6, 8, 225, 'Royals College',
   1, 7, 2020, 'Royals', 'Royals',
   41, 1300, 210, 460, 105, 125,
   30, 80, 50, 150, 120, 42,
   60, 30, 90,
   'a0000000-0000-0000-0000-000000000033');

-- Starters for new players (needed to appear in voting)
UPDATE ibl_plr SET dc_PGDepth = 1, PGDepth = 1 WHERE pid = 30;
UPDATE ibl_plr SET dc_SFDepth = 1, SFDepth = 1 WHERE pid = 31;
UPDATE ibl_plr SET dc_SGDepth = 1, SGDepth = 1 WHERE pid = 32;
UPDATE ibl_plr SET dc_PFDepth = 1, PFDepth = 1 WHERE pid = 33;

-- Player history for voting candidates (must have current year stats)
INSERT INTO ibl_hist (
  pid, name, year, team, teamid,
  games, minutes, fgm, fga, ftm, fta, tgm, tga,
  orb, reb, ast, stl, blk, tvr, pf, pts, salary
) VALUES
  (30, 'Spurs Guard', 2026, 'Spurs', 10,
   41, 1260, 200, 450, 100, 120, 60, 150,
   40, 160, 180, 50, 20, 80, 90, 620, 500),
  (31, 'Flames Forward', 2026, 'Flames', 9,
   41, 1260, 190, 420, 95, 115, 45, 130,
   45, 185, 160, 48, 22, 65, 88, 565, 600),
  (32, 'Minutemen Guard', 2026, 'Minutemen', 5,
   41, 1260, 195, 440, 100, 120, 55, 140,
   35, 145, 185, 50, 15, 70, 80, 600, 700),
  (33, 'Royals Forward', 2026, 'Royals', 12,
   41, 1300, 210, 460, 105, 125, 30, 80,
   50, 200, 120, 42, 30, 60, 90, 590, 800),
  (20, 'Metros PG', 2026, 'Metros', 1,
   41, 1260, 200, 450, 100, 120, 60, 150,
   30, 130, 200, 55, 10, 75, 80, 620, 300),
  (21, 'Metros SF', 2026, 'Metros', 1,
   41, 1260, 180, 400, 90, 110, 40, 120,
   35, 155, 140, 45, 20, 65, 85, 530, 200),
  (22, 'Metros Center', 2026, 'Metros', 1,
   41, 1300, 220, 480, 115, 135, 20, 60,
   60, 220, 100, 40, 35, 60, 95, 610, 300),
  (23, 'Cougars Guard', 2026, 'Cougars', 3,
   41, 1260, 195, 440, 100, 120, 55, 140,
   35, 145, 185, 50, 15, 70, 80, 600, 1300),
  (24, 'Cougars Forward', 2026, 'Cougars', 3,
   41, 1300, 210, 460, 105, 125, 45, 130,
   45, 185, 160, 48, 22, 65, 88, 565, 1500);

-- ============================================================
-- Played schedule games (covers win/loss, streak, record, score display)
-- ============================================================

-- Metros win as visitor (no box score link — BoxID=0, no ibl_box_scores_teams row)
INSERT INTO ibl_schedule (Year, Date, Visitor, Home, VScore, HScore, BoxID, uuid) VALUES
  (2026, '2026-02-20', 1, 2, 105, 98, 0, 'sched-played-01')
ON DUPLICATE KEY UPDATE VScore=VALUES(VScore);

-- Metros loss at home (no box score link)
INSERT INTO ibl_schedule (Year, Date, Visitor, Home, VScore, HScore, BoxID, uuid) VALUES
  (2026, '2026-02-22', 3, 1, 110, 99, 0, 'sched-played-02')
ON DUPLICATE KEY UPDATE VScore=VALUES(VScore);

-- Metros win as visitor, legacy BoxID=42
INSERT INTO ibl_schedule (Year, Date, Visitor, Home, VScore, HScore, BoxID, uuid) VALUES
  (2026, '2026-02-24', 1, 4, 102, 95, 42, 'sched-played-03')
ON DUPLICATE KEY UPDATE VScore=VALUES(VScore);

-- ============================================================
-- Box score row for IBL6 URL path (gameOfThatDay > 0)
-- ============================================================

INSERT INTO ibl_box_scores_teams (Date, visitorTeamID, homeTeamID, gameOfThatDay, name) VALUES
  ('2026-02-20', 1, 2, 1, 'Metros')
ON DUPLICATE KEY UPDATE gameOfThatDay=VALUES(gameOfThatDay);

-- ============================================================
-- Power rankings (covers SOS tier dots, SOS summary)
-- ============================================================

INSERT INTO ibl_power (TeamID, ranking, last_win, last_loss, streak_type, streak, sos, remaining_sos, sos_rank, remaining_sos_rank) VALUES
  (1, 72.0, 7, 3, 'W', 3, 0.510, 0.523, 5, 4),
  (2, 58.0, 6, 4, 'W', 1, 0.490, 0.480, 12, 14),
  (3, 47.0, 5, 5, 'L', 2, 0.505, 0.498, 8, 9),
  (4, 35.0, 4, 6, 'L', 3, 0.470, 0.455, 18, 17)
ON DUPLICATE KEY UPDATE ranking=VALUES(ranking), last_win=VALUES(last_win), last_loss=VALUES(last_loss),
  streak_type=VALUES(streak_type), streak=VALUES(streak), sos=VALUES(sos),
  remaining_sos=VALUES(remaining_sos), sos_rank=VALUES(sos_rank), remaining_sos_rank=VALUES(remaining_sos_rank);

-- ============================================================
-- June game (covers playoff month relabeling + reorder)
-- ============================================================

INSERT INTO ibl_schedule (Year, Date, Visitor, Home, VScore, HScore, BoxID, uuid) VALUES
  (2026, '2026-06-05', 1, 2, 0, 0, 0, 'sched-playoff-june-01')
ON DUPLICATE KEY UPDATE VScore=VALUES(VScore);

-- ============================================================
-- Injury data (Injuries module E2E tests)
-- Set 'injured' column > 0 on a player to appear on Injuries page
-- ============================================================

UPDATE ibl_plr SET injured = 5 WHERE pid = 5;
UPDATE ibl_plr SET injured = 3 WHERE pid = 7;

-- ============================================================
-- All-Star appearance data (AllStarAppearances module)
-- Award must match '%Conference All-Star' pattern
-- ============================================================

INSERT INTO ibl_awards (year, Award, name) VALUES
  (2026, 'Eastern Conference All-Star', 'Test Player'),
  (2025, 'Eastern Conference All-Star', 'Test Player'),
  (2024, 'Eastern Conference All-Star', 'Test Player'),
  (2026, 'Western Conference All-Star', 'Stars Guard'),
  (2025, 'Western Conference All-Star', 'Stars Guard');

-- ============================================================
-- News article for News module tests (needs ihome=0 or catid=0
-- to show on News index)
-- ============================================================

INSERT INTO nuke_stories (catid, aid, title, time, hometext, bodytext, topic, ihome, comments, counter) VALUES
  (0, 'admin', 'Welcome to the new IBL season', '2026-03-10 10:00:00',
   'The new season is here with exciting changes and new rosters.',
   'Full article body text with details about the upcoming season.',
   1, 0, 2, 10);

-- ============================================================
-- Rookie players for EOY ROY ballot (exp=1, stats_gm >= 41)
-- Needed so duplicate EOY test doesn't fail on missing ROY
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
  (40, 'Rookie Guard', 20, 25, 4, 'PG', 1,
   75, 70, 65, 60, 55, 68, 64, 66, 61,
   1, 3, 300, 330,
   0, 1,
   6, 2, 185, 'Rookie University',
   1, 1, 2025, 'Diesels', 'Diesels',
   41, 1260, 180, 420, 80, 100,
   50, 130, 30, 100, 170, 45,
   70, 12, 75,
   'a0000000-0000-0000-0000-000000000040'),
  (41, 'Rookie Wing', 21, 26, 11, 'SF', 1,
   73, 68, 63, 58, 53, 66, 62, 64, 59,
   1, 3, 250, 275,
   0, 1,
   6, 6, 205, 'Rookie College',
   1, 3, 2025, 'Pioneers', 'Pioneers',
   41, 1260, 170, 400, 75, 95,
   40, 110, 35, 110, 140, 40,
   60, 18, 80,
   'a0000000-0000-0000-0000-000000000041'),
  (42, 'Rookie Big', 22, 27, 15, 'C', 1,
   77, 72, 67, 62, 57, 70, 66, 68, 63,
   1, 3, 200, 220,
   0, 1,
   7, 0, 240, 'Rookie State',
   1, 5, 2025, 'Blues', 'Blues',
   41, 1300, 200, 440, 90, 110,
   15, 40, 55, 150, 80, 35,
   55, 30, 90,
   'a0000000-0000-0000-0000-000000000042');

-- Rookie player history (for stats to show on ballot)
INSERT INTO ibl_hist (
  pid, name, year, team, teamid,
  games, minutes, fgm, fga, ftm, fta, tgm, tga,
  orb, reb, ast, stl, blk, tvr, pf, pts, salary
) VALUES
  (40, 'Rookie Guard', 2026, 'Diesels', 4,
   41, 1260, 180, 420, 80, 100, 50, 130,
   30, 130, 170, 45, 12, 70, 75, 540, 300),
  (41, 'Rookie Wing', 2026, 'Pioneers', 11,
   41, 1260, 170, 400, 75, 95, 40, 110,
   35, 145, 140, 40, 18, 60, 80, 495, 250),
  (42, 'Rookie Big', 2026, 'Blues', 15,
   41, 1300, 200, 440, 90, 110, 15, 40,
   55, 205, 80, 35, 30, 55, 90, 540, 200);

-- ============================================================
-- API Key for E2E tests
-- Raw key: "e2e-test-key-do-not-use-in-production"
-- SHA-256 hash: computed below
-- ============================================================
INSERT INTO ibl_api_keys (key_hash, key_prefix, owner_name, permission_level, rate_limit_tier, is_active)
VALUES (
  SHA2('e2e-test-key-do-not-use-in-production', 256),
  'e2e-test',
  'E2E Test Suite',
  'public',
  'unlimited',
  1
) ON DUPLICATE KEY UPDATE is_active = 1;

-- ============================================================
-- SiteStatistics seed data (nuke_counter + nuke_stats_* tables)
-- Minimal data so SiteStatistics module pages don't error on empty tables
-- ============================================================
INSERT IGNORE INTO nuke_counter (type, var, count) VALUES
  ('total', 'hits', 12345),
  ('browser', 'Chrome', 8000),
  ('browser', 'Firefox', 3000),
  ('browser', 'Safari', 1345),
  ('os', 'Windows', 6000),
  ('os', 'Linux', 4000),
  ('os', 'macOS', 2345);

INSERT IGNORE INTO nuke_stats_year (year, hits) VALUES
  (2025, 50000),
  (2026, 12345);

INSERT IGNORE INTO nuke_stats_month (year, month, hits) VALUES
  (2025, 1, 4000),
  (2025, 6, 5000),
  (2026, 1, 6000),
  (2026, 2, 6345);

INSERT IGNORE INTO nuke_stats_date (year, month, date, hits) VALUES
  (2025, 1, 15, 200),
  (2026, 1, 10, 300);

INSERT IGNORE INTO nuke_stats_hour (year, month, date, hour, hits) VALUES
  (2025, 1, 15, 12, 50),
  (2026, 1, 10, 14, 75);

-- ============================================================
-- Voting Results test data (ASG and EOY ballots)
-- Vote names use "Player Name, Team" format matching extractPlayerName().
-- Player names match ibl_plr.name so PIDs resolve and player links render.
-- ============================================================
-- ASG table has no PK/UNIQUE — delete first for idempotency on re-runs
DELETE FROM ibl_votes_ASG WHERE teamid IN (1, 2);
INSERT INTO ibl_votes_ASG (teamid, team_city, team_name, East_F1, East_F2, East_B1, East_B2, West_F1, West_F2, West_B1, West_B2) VALUES
  (1, 'Test', 'Metros', 'Test Player, Metros', 'Test Player Two, Metros', 'Stars Guard, Stars', 'FA Guard, Metros',
   'FA Forward, Stars', 'FA Center, Stars', 'Test Player, Metros', 'Stars Guard, Stars'),
  (2, 'Test', 'Stars', 'Test Player, Metros', 'Stars Guard, Stars', 'Test Player Two, Metros', 'FA Guard, Metros',
   'FA Forward, Stars', 'Test Player, Metros', 'FA Center, Stars', 'Stars Guard, Stars');

INSERT INTO ibl_votes_EOY (teamid, team_city, team_name, MVP_1, MVP_2, MVP_3, Six_1, Six_2, Six_3, ROY_1, ROY_2, ROY_3, GM_1, GM_2, GM_3) VALUES
  (1, 'Test', 'Metros', 'Test Player, Metros', 'Stars Guard, Stars', 'Test Player Two, Metros',
   'FA Guard, Metros', 'FA Forward, Stars', 'Test Player Two, Metros',
   'Rookie Guard, Diesels', 'Rookie Wing, Pioneers', 'Rookie Big, Blues',
   'TestUser', 'Stars GM', 'Blues GM'),
  (2, 'Test', 'Stars', 'Stars Guard, Stars', 'Test Player, Metros', 'FA Forward, Stars',
   'FA Guard, Metros', 'Stars Guard, Stars', 'Test Player Two, Metros',
   'Rookie Guard, Diesels', 'Rookie Big, Blues', 'Rookie Wing, Pioneers',
   'Stars GM', 'TestUser', 'Blues GM')
ON DUPLICATE KEY UPDATE
  team_city = VALUES(team_city),
  team_name = VALUES(team_name),
  MVP_1 = VALUES(MVP_1),
  MVP_2 = VALUES(MVP_2),
  MVP_3 = VALUES(MVP_3),
  Six_1 = VALUES(Six_1),
  Six_2 = VALUES(Six_2),
  Six_3 = VALUES(Six_3),
  ROY_1 = VALUES(ROY_1),
  ROY_2 = VALUES(ROY_2),
  ROY_3 = VALUES(ROY_3),
  GM_1 = VALUES(GM_1),
  GM_2 = VALUES(GM_2),
  GM_3 = VALUES(GM_3);

-- ============================================================
-- Round 2 draft picks (ProjectedDraftOrder round separator tests)
-- ============================================================

INSERT INTO ibl_draft_picks (ownerofpick, owner_tid, teampick, teampick_tid, year, round) VALUES
  ('Metros',    1,  'Metros',    1,  2026, 2),
  ('Stars',     2,  'Stars',     2,  2026, 2),
  ('Phoenixes', 14, 'Phoenixes', 14, 2026, 2)
ON DUPLICATE KEY UPDATE ownerofpick = VALUES(ownerofpick);

-- ============================================================
-- Additional news articles (homepage flow tests)
-- ============================================================

INSERT INTO nuke_stories (catid, aid, title, time, hometext, bodytext, topic, ihome, comments, counter) VALUES
  (0, 'admin', 'Trade deadline approaches', '2026-03-08 14:00:00',
   'Teams are making final moves before the deadline.',
   'Full details about trade deadline activity.', 1, 0, 5, 25),
  (0, 'admin', 'All-Star Game recap', '2026-03-05 18:00:00',
   'The Eastern Conference won the All-Star Game.',
   'Complete recap of the All-Star festivities.', 1, 0, 3, 15);

-- ============================================================
-- Drafted rookie with draftyear=2026 (DraftHistory year detail tests)
-- DraftHistory module queries ibl_plr.draftyear, not ibl_draft.
-- ============================================================

INSERT INTO ibl_plr (
  pid, name, age, peak, tid, pos, ordinal,
  sta, oo, od, `do`, dd, po, pd, `to`, td,
  cy, cyt, cy1,
  retired, exp,
  htft, htin, wt, college,
  draftround, draftpickno, draftyear, draftedby, draftedbycurrentname,
  stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta,
  stats_3gm, stats_3ga, stats_orb, stats_drb, stats_ast, stats_stl,
  stats_to, stats_blk, stats_pf,
  uuid
) VALUES
  (31, 'Draft Rookie 2026', 20, 25, 2, 'PG', 5,
   70, 65, 60, 55, 50, 62, 58, 60, 55,
   1, 3, 500,
   0, 0,
   6, 2, 185, 'Draft College',
   1, 1, 2026, 'Stars', 'Stars',
   10, 300, 30, 80, 20, 30,
   10, 30, 5, 15, 25, 8,
   12, 3, 15,
   'plr-uuid-00000000-0000-000000000031')
ON DUPLICATE KEY UPDATE name = VALUES(name), draftyear = VALUES(draftyear);

-- ============================================================
-- Extension-eligible player on Metros (contract extension flow tests)
-- cy = cyt = 2 → final contract year → eligible for extension
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
  (30, 'Extension Vet', 30, 30, 1, 'SG', 6,
   85, 80, 75, 70, 65, 78, 74, 76, 71,
   2, 2, 1500, 1650,
   0, 8,
   6, 3, 200, 'Veteran College',
   1, 5, 2018, 'Metros', 'Metros',
   41, 1260, 200, 440, 100, 120,
   50, 130, 35, 120, 150, 50,
   70, 15, 80,
   'a0000000-0000-0000-0000-000000000030')
ON DUPLICATE KEY UPDATE name = VALUES(name), cy = VALUES(cy), cyt = VALUES(cyt);

-- ============================================================
-- NOTE: Test user (nuke_users + auth_users) is created by the
-- workflow via PHP bcrypt hash at runtime — not seeded here.
-- ============================================================

-- ============================================================
-- Non-admin user for phase-gating E2E tests (Batch C prep)
-- Username: E2E-Regular — no team, no admin privileges.
-- Password hash is set at CI runtime (same pattern as primary test user).
-- To activate: add IBL_TEST_USER_REGULAR / IBL_TEST_PASS_REGULAR
-- GitHub secrets and a user-creation step in e2e-tests.yml.
-- ============================================================
