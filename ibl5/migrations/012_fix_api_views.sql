-- Migration 012: Fix API database views
--
-- The 5 API views created in migration 003 have a definer mismatch
-- (created with production user iblhoops_chibul@71.145.211.164).
-- This recreates them with SQL SECURITY INVOKER so they work on
-- both local dev and production environments.

-- ---------------------------------------------------------------------------
-- View 1: Current Active Players with Team Details
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_player_current;
CREATE SQL SECURITY INVOKER VIEW vw_player_current AS
SELECT
  p.uuid AS player_uuid,
  p.pid,
  p.name,
  p.nickname,
  p.age,
  p.pos AS position,
  p.htft,
  p.htin,
  p.active,
  p.retired,
  p.exp AS experience,
  p.bird AS bird_rights,
  -- Team Information
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  t.owner_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  -- Current Contract
  p.cy AS contract_year,
  CASE p.cy
    WHEN 1 THEN p.cy1
    WHEN 2 THEN p.cy2
    WHEN 3 THEN p.cy3
    WHEN 4 THEN p.cy4
    WHEN 5 THEN p.cy5
    WHEN 6 THEN p.cy6
    ELSE 0
  END AS current_salary,
  p.cy1 AS year1_salary,
  p.cy2 AS year2_salary,
  -- Current Season Stats
  p.stats_gm AS games_played,
  p.stats_min AS minutes_played,
  p.stats_fgm AS field_goals_made,
  p.stats_fga AS field_goals_attempted,
  p.stats_ftm AS free_throws_made,
  p.stats_fta AS free_throws_attempted,
  p.stats_3gm AS three_pointers_made,
  p.stats_3ga AS three_pointers_attempted,
  p.stats_orb AS offensive_rebounds,
  p.stats_drb AS defensive_rebounds,
  p.stats_ast AS assists,
  p.stats_stl AS steals,
  p.stats_to AS turnovers,
  p.stats_blk AS blocks,
  p.stats_pf AS personal_fouls,
  -- Calculated Percentages (avoid division by zero)
  ROUND(p.stats_fgm / NULLIF(p.stats_fga, 0), 3) AS fg_percentage,
  ROUND(p.stats_ftm / NULLIF(p.stats_fta, 0), 3) AS ft_percentage,
  ROUND(p.stats_3gm / NULLIF(p.stats_3ga, 0), 3) AS three_pt_percentage,
  -- Points Per Game
  ROUND((p.stats_fgm * 2 + p.stats_3gm + p.stats_ftm) / NULLIF(p.stats_gm, 0), 1) AS points_per_game,
  -- Timestamps
  p.created_at,
  p.updated_at
FROM ibl_plr p
LEFT JOIN ibl_team_info t ON p.tid = t.teamid
WHERE p.active = 1 AND p.retired = 0;

-- ---------------------------------------------------------------------------
-- View 2: Team Standings with Calculated Fields
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_team_standings;
CREATE SQL SECURITY INVOKER VIEW vw_team_standings AS
SELECT
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  t.owner_name,
  -- Standings Data
  s.leagueRecord AS league_record,
  s.pct AS win_percentage,
  s.conference,
  s.confRecord AS conference_record,
  s.confGB AS conference_games_back,
  s.division,
  s.divRecord AS division_record,
  s.divGB AS division_games_back,
  -- Home/Away Records
  s.homeWins AS home_wins,
  s.homeLosses AS home_losses,
  s.awayWins AS away_wins,
  s.awayLosses AS away_losses,
  CONCAT(s.homeWins, '-', s.homeLosses) AS home_record,
  CONCAT(s.awayWins, '-', s.awayLosses) AS away_record,
  -- Additional Stats
  s.gamesUnplayed AS games_remaining,
  s.confWins AS conference_wins,
  s.confLosses AS conference_losses,
  s.divWins AS division_wins,
  s.divLosses AS division_losses,
  -- Playoff Status
  s.clinchedConference AS clinched_conference,
  s.clinchedDivision AS clinched_division,
  s.clinchedPlayoffs AS clinched_playoffs,
  s.confMagicNumber AS conference_magic_number,
  s.divMagicNumber AS division_magic_number,
  -- Timestamps
  s.created_at,
  s.updated_at
FROM ibl_team_info t
INNER JOIN ibl_standings s ON t.teamid = s.tid;

-- ---------------------------------------------------------------------------
-- View 3: Schedule with Team Names
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_schedule_upcoming;
CREATE SQL SECURITY INVOKER VIEW vw_schedule_upcoming AS
SELECT
  sch.uuid AS game_uuid,
  sch.SchedID AS schedule_id,
  sch.Year AS season_year,
  sch.Date AS game_date,
  sch.BoxID AS box_score_id,
  -- Visitor Team
  tv.uuid AS visitor_uuid,
  tv.teamid AS visitor_team_id,
  tv.team_city AS visitor_city,
  tv.team_name AS visitor_name,
  CONCAT(tv.team_city, ' ', tv.team_name) AS visitor_full_name,
  sch.VScore AS visitor_score,
  -- Home Team
  th.uuid AS home_uuid,
  th.teamid AS home_team_id,
  th.team_city AS home_city,
  th.team_name AS home_name,
  CONCAT(th.team_city, ' ', th.team_name) AS home_full_name,
  sch.HScore AS home_score,
  -- Game Status
  CASE
    WHEN sch.VScore = 0 AND sch.HScore = 0 THEN 'scheduled'
    ELSE 'completed'
  END AS game_status,
  -- Timestamps
  sch.created_at,
  sch.updated_at
FROM ibl_schedule sch
INNER JOIN ibl_team_info tv ON sch.Visitor = tv.teamid
INNER JOIN ibl_team_info th ON sch.Home = th.teamid;

-- ---------------------------------------------------------------------------
-- View 4: Player Career Statistics Summary
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_player_career_stats;
CREATE SQL SECURITY INVOKER VIEW vw_player_career_stats AS
SELECT
  p.uuid AS player_uuid,
  p.pid,
  p.name,
  -- Career Totals
  p.car_gm AS career_games,
  p.car_min AS career_minutes,
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm), 0) AS career_points,
  p.car_orb + p.car_drb AS career_rebounds,
  p.car_ast AS career_assists,
  p.car_stl AS career_steals,
  p.car_blk AS career_blocks,
  -- Career Averages
  ROUND((p.car_fgm * 2 + p.car_tgm + p.car_ftm) / NULLIF(p.car_gm, 0), 1) AS ppg_career,
  ROUND((p.car_orb + p.car_drb) / NULLIF(p.car_gm, 0), 1) AS rpg_career,
  ROUND(p.car_ast / NULLIF(p.car_gm, 0), 1) AS apg_career,
  -- Career Percentages
  ROUND(p.car_fgm / NULLIF(p.car_fga, 0), 3) AS fg_pct_career,
  ROUND(p.car_ftm / NULLIF(p.car_fta, 0), 3) AS ft_pct_career,
  ROUND(p.car_tgm / NULLIF(p.car_tga, 0), 3) AS three_pt_pct_career,
  -- Playoff Career Stats
  p.car_playoff_min AS playoff_minutes,
  -- Draft Information
  p.draftyear AS draft_year,
  p.draftround AS draft_round,
  p.draftpickno AS draft_pick,
  p.draftedby AS drafted_by_team,
  -- Timestamps
  p.created_at,
  p.updated_at
FROM ibl_plr p;

-- ---------------------------------------------------------------------------
-- View 5: Free Agency Market Overview
-- ---------------------------------------------------------------------------

DROP VIEW IF EXISTS vw_free_agency_offers;
CREATE SQL SECURITY INVOKER VIEW vw_free_agency_offers AS
SELECT
  fa.primary_key AS offer_id,
  -- Player Information
  p.uuid AS player_uuid,
  p.pid,
  p.name AS player_name,
  p.pos AS position,
  p.age,
  -- Team Making Offer
  t.uuid AS team_uuid,
  t.teamid,
  t.team_city,
  t.team_name,
  CONCAT(t.team_city, ' ', t.team_name) AS full_team_name,
  -- Offer Details
  fa.offer1 AS year1_amount,
  fa.offer2 AS year2_amount,
  fa.offer3 AS year3_amount,
  fa.offer4 AS year4_amount,
  fa.offer5 AS year5_amount,
  fa.offer6 AS year6_amount,
  (fa.offer1 + fa.offer2 + fa.offer3 + fa.offer4 + fa.offer5 + fa.offer6) AS total_contract_value,
  -- Offer Modifiers
  fa.modifier,
  fa.random,
  fa.perceivedvalue AS perceived_value,
  fa.MLE AS is_mle,
  fa.LLE AS is_lle,
  -- Timestamps
  fa.created_at,
  fa.updated_at
FROM ibl_fa_offers fa
INNER JOIN ibl_plr p ON fa.name = p.name
INNER JOIN ibl_team_info t ON fa.team = t.team_name;
