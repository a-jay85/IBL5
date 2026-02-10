-- Migration 014: Remove active/retired filter from vw_player_current
--
-- The view previously filtered WHERE active = 1 AND retired = 0, which
-- excluded players like Bill Robinzine (active = 0, not on a depth chart)
-- from API search results. The "active" flag only means the player is on
-- a depth chart, not that they exist in the league. Retired players also
-- need to be searchable for commands like /history and /career.
--
-- Remove both filters so all players are included.

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
LEFT JOIN ibl_team_info t ON p.tid = t.teamid;
