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
  ('YourAccount',       'Your Account',      1, 0),
  ('News',              'News',              1, 0);

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

-- ibl_box_scores needs at least one row for Season date queries.
-- Generated columns (game_type, season_year, calc_*) are computed automatically.
INSERT INTO ibl_box_scores (`Date`, `uuid`) VALUES
  ('2026-03-07', '00000000-0000-0000-0000-000000000001');

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

INSERT INTO ibl_standings (tid, team_name, pct, leagueRecord, wins, losses, conference, division) VALUES
  ( 1, 'Metros',       0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 2, 'Stars',        0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  ( 3, 'Cougars',      0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  ( 4, 'Diesels',      0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  ( 5, 'Minutemen',    0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 6, 'Rage',         0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  ( 7, 'Tropics',      0.500, '20-20', 20, 20, 'Eastern',  'Southeast'),
  ( 8, 'Monarchs',     0.500, '20-20', 20, 20, 'Eastern',  'Southeast'),
  ( 9, 'Flames',       0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (10, 'Spurs',        0.500, '20-20', 20, 20, 'Western',  'Southwest'),
  (11, 'Pioneers',     0.500, '20-20', 20, 20, 'Western',  'Northwest'),
  (12, 'Royals',       0.500, '20-20', 20, 20, 'Eastern',  'Southeast'),
  (13, 'Apollos',      0.500, '20-20', 20, 20, 'Western',  'Southwest'),
  (14, 'Phoenixes',    0.500, '20-20', 20, 20, 'Eastern',  'Southeast'),
  (15, 'Blues',         0.500, '20-20', 20, 20, 'Western',  'Southwest'),
  (16, 'Blizzard',     0.500, '20-20', 20, 20, 'Western',  'Northwest'),
  (17, 'Huskies',      0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  (18, 'Bucks',        0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (19, 'Nuggets',      0.500, '20-20', 20, 20, 'Western',  'Northwest'),
  (20, 'Pilots',       0.500, '20-20', 20, 20, 'Western',  'Pacific'),
  (21, 'Mavericks',    0.500, '20-20', 20, 20, 'Western',  'Southwest'),
  (22, 'Cavaliers',    0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (23, 'Supersonics',  0.500, '20-20', 20, 20, 'Western',  'Northwest'),
  (24, 'Nets',         0.500, '20-20', 20, 20, 'Eastern',  'Atlantic'),
  (25, 'Generals',     0.500, '20-20', 20, 20, 'Eastern',  'Southeast'),
  (26, 'Pacers',       0.500, '20-20', 20, 20, 'Eastern',  'Central'),
  (27, 'Jazz',         0.500, '20-20', 20, 20, 'Western',  'Northwest'),
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
-- Players (pid=1 for Player page smoke test)
-- ============================================================

INSERT INTO ibl_plr (
  pid, name, age, peak, tid, pos,
  sta, oo, od, `do`, dd, po, pd, `to`, td,
  cy, cyt, cy1, cy2,
  retired, exp,
  htft, htin, wt, college,
  draftround, draftpickno, draftyear, draftedby, draftedbycurrentname,
  stats_gm, stats_min, stats_fgm, stats_fga, stats_ftm, stats_fta,
  stats_3gm, stats_3ga, stats_orb, stats_drb, stats_ast, stats_stl,
  stats_to, stats_blk, stats_pf,
  uuid
) VALUES (
  1, 'Test Player', 28, 28, 1, 'SG',
  80, 75, 70, 65, 60, 72, 68, 70, 65,
  2, 3, 1500, 1600,
  0, 5,
  6, 4, 200, 'Test University',
  1, 5, 2021, 'Metros', 'Metros',
  40, 1200, 200, 450, 100, 120,
  60, 150, 40, 120, 180, 50,
  80, 20, 90,
  'plr-uuid-00000000-0000-000000000001'
);

-- ============================================================
-- Player history (SeasonLeaderboards needs current-year stats)
-- ============================================================

INSERT INTO ibl_hist (
  pid, name, year, team, teamid,
  games, minutes, fgm, fga, ftm, fta, tgm, tga,
  orb, reb, ast, stl, blk, tvr, pf, pts, salary
) VALUES (
  1, 'Test Player', 2026, 'Metros', 1,
  40, 1200, 200, 450, 100, 120, 60, 150,
  40, 160, 180, 50, 20, 80, 90, 620, 1500
);

-- ============================================================
-- Draft picks (DraftHistory page)
-- ============================================================

INSERT INTO ibl_draft_picks (ownerofpick, owner_tid, teampick, teampick_tid, year, round) VALUES
  ('Metros', 1, 'Metros', 1, 2026, 1),
  ('Stars',  2, 'Stars',  2, 2026, 1);

-- ============================================================
-- NOTE: Test user (nuke_users + auth_users) is created by the
-- workflow via PHP bcrypt hash at runtime — not seeded here.
-- ============================================================
