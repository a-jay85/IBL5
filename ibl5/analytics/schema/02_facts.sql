-- 02_facts.sql: Fact tables with pre-joined dimensions and computed stats
-- Run after 01_dimensions.sql

-- fact_player_season: Season-level player stats with computed per-game averages
-- Stat formulas match SeasonLeaderboardsRepository: PPG = (2*fgm + ftm + tgm) / games
CREATE OR REPLACE TABLE fact_player_season AS
WITH hist_raw AS (
    SELECT
        CAST(pid AS INTEGER) AS pid,
        name,
        CAST(year AS INTEGER) AS season_year,
        team,
        CAST(teamid AS INTEGER) AS teamid,
        CAST(games AS INTEGER) AS games,
        CAST(minutes AS INTEGER) AS minutes,
        CAST(fgm AS INTEGER) AS fgm,
        CAST(fga AS INTEGER) AS fga,
        CAST(ftm AS INTEGER) AS ftm,
        CAST(fta AS INTEGER) AS fta,
        CAST(tgm AS INTEGER) AS tgm,
        CAST(tga AS INTEGER) AS tga,
        CAST(orb AS INTEGER) AS orb,
        CAST(reb AS INTEGER) AS reb,
        CAST(ast AS INTEGER) AS ast,
        CAST(stl AS INTEGER) AS stl,
        CAST(blk AS INTEGER) AS blk,
        CAST(tvr AS INTEGER) AS tvr,
        CAST(pf AS INTEGER) AS pf,
        CAST(pts AS INTEGER) AS pts,
        CAST(r_2ga AS INTEGER) AS r_2ga,
        CAST(r_2gp AS INTEGER) AS r_2gp,
        CAST(r_fta AS INTEGER) AS r_fta,
        CAST(r_ftp AS INTEGER) AS r_ftp,
        CAST(r_3ga AS INTEGER) AS r_3ga,
        CAST(r_3gp AS INTEGER) AS r_3gp,
        CAST(r_orb AS INTEGER) AS r_orb,
        CAST(r_drb AS INTEGER) AS r_drb,
        CAST(r_ast AS INTEGER) AS r_ast,
        CAST(r_stl AS INTEGER) AS r_stl,
        CAST(r_blk AS INTEGER) AS r_blk,
        CAST(r_tvr AS INTEGER) AS r_tvr,
        CAST(r_oo AS INTEGER)  AS r_oo,
        CAST(r_do AS INTEGER)  AS r_do,
        CAST(r_po AS INTEGER)  AS r_po,
        CAST(r_to AS INTEGER)  AS r_to,
        CAST(r_od AS INTEGER)  AS r_od,
        CAST(r_dd AS INTEGER)  AS r_dd,
        CAST(r_pd AS INTEGER)  AS r_pd,
        CAST(r_td AS INTEGER)  AS r_td,
        CAST(salary AS INTEGER) AS salary
    FROM read_csv('data/ibl_hist.csv', delim='\t', header=true, all_varchar=true,
        null_padding=true, ignore_errors=true)
)
SELECT
    h.*,
    -- Per-game averages (guard against division by zero)
    CASE WHEN h.games > 0 THEN ROUND((2.0 * h.fgm + h.ftm + h.tgm) / h.games, 1) END AS ppg,
    CASE WHEN h.games > 0 THEN ROUND(h.reb * 1.0 / h.games, 1) END AS rpg,
    CASE WHEN h.games > 0 THEN ROUND(h.orb * 1.0 / h.games, 1) END AS orpg,
    CASE WHEN h.games > 0 THEN ROUND(h.ast * 1.0 / h.games, 1) END AS apg,
    CASE WHEN h.games > 0 THEN ROUND(h.stl * 1.0 / h.games, 1) END AS spg,
    CASE WHEN h.games > 0 THEN ROUND(h.blk * 1.0 / h.games, 1) END AS bpg,
    CASE WHEN h.games > 0 THEN ROUND(h.tvr * 1.0 / h.games, 1) END AS topg,
    CASE WHEN h.games > 0 THEN ROUND(h.minutes * 1.0 / h.games, 1) END AS mpg,
    -- Shooting percentages
    CASE WHEN h.fga > 0 THEN ROUND(h.fgm * 100.0 / h.fga, 1) END AS fg_pct,
    CASE WHEN h.fta > 0 THEN ROUND(h.ftm * 100.0 / h.fta, 1) END AS ft_pct,
    CASE WHEN h.tga > 0 THEN ROUND(h.tgm * 100.0 / h.tga, 1) END AS three_pct,
    -- Quality Adjusted per game
    CASE WHEN h.games > 0 THEN ROUND(
        ((2.0*h.fgm + h.ftm + h.tgm + h.reb + 2.0*h.ast + 2.0*h.stl + 2.0*h.blk)
         - ((h.fga - h.fgm) + (h.fta - h.ftm) + h.tvr + h.pf)) / h.games, 1
    ) END AS qa,
    -- TSI from snapshot if available, else from dim_player (current proxy)
    COALESCE(s.tsi_sum, p.tsi_sum) AS tsi_sum,
    COALESCE(s.tsi_band, p.tsi_band) AS tsi_band,
    COALESCE(s.talent, p.talent) AS talent,
    COALESCE(s.skill, p.skill) AS skill,
    COALESCE(s.intangibles, p.intangibles) AS intangibles,
    -- Age estimation
    COALESCE(s.age, p.age - ((SELECT MAX(season_year) FROM dim_season) - h.season_year)) AS estimated_age,
    COALESCE(s.peak, p.peak) AS peak,
    COALESCE(s.age, p.age - ((SELECT MAX(season_year) FROM dim_season) - h.season_year))
        - COALESCE(s.peak, p.peak) AS age_relative_to_peak
FROM hist_raw h
LEFT JOIN dim_player p ON h.pid = p.pid
LEFT JOIN dim_player_snapshot s ON h.pid = s.pid AND h.season_year = s.season_year;

-- fact_player_game: Game-level player box scores
CREATE OR REPLACE TABLE fact_player_game AS
SELECT
    CAST(id AS INTEGER) AS id,
    CAST(Date AS DATE) AS game_date,
    CAST(pid AS INTEGER) AS pid,
    name,
    pos,
    CAST(teamID AS INTEGER) AS team_id,
    CAST(visitorTID AS INTEGER) AS visitor_tid,
    CAST(homeTID AS INTEGER) AS home_tid,
    CAST(gameMIN AS INTEGER) AS minutes,
    CAST(game2GM AS INTEGER) AS fg2_made,
    CAST(game2GA AS INTEGER) AS fg2_att,
    CAST(gameFTM AS INTEGER) AS ft_made,
    CAST(gameFTA AS INTEGER) AS ft_att,
    CAST(game3GM AS INTEGER) AS fg3_made,
    CAST(game3GA AS INTEGER) AS fg3_att,
    CAST(gameORB AS INTEGER) AS orb,
    CAST(gameDRB AS INTEGER) AS drb,
    CAST(gameAST AS INTEGER) AS ast,
    CAST(gameSTL AS INTEGER) AS stl,
    CAST(gameTOV AS INTEGER) AS tov,
    CAST(gameBLK AS INTEGER) AS blk,
    CAST(gamePF AS INTEGER)  AS pf,
    CAST(game_type AS INTEGER) AS game_type,
    CAST(season_year AS INTEGER) AS season_year,
    CAST(calc_points AS INTEGER) AS points,
    CAST(calc_rebounds AS INTEGER) AS rebounds,
    CAST(attendance AS INTEGER) AS attendance,
    -- Game type label
    CASE CAST(game_type AS INTEGER)
        WHEN 1 THEN 'regular'
        WHEN 2 THEN 'playoffs'
        WHEN 3 THEN 'preseason'
        ELSE 'other'
    END AS game_type_label
FROM read_csv('data/ibl_box_scores.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- fact_team_season: Team season records from JSB history
CREATE OR REPLACE TABLE fact_team_season AS
SELECT
    CAST(id AS INTEGER) AS id,
    CAST(season_year AS INTEGER) AS season_year,
    team_name,
    CAST(teamid AS INTEGER) AS teamid,
    CAST(wins AS INTEGER) AS wins,
    CAST(losses AS INTEGER) AS losses,
    CASE WHEN (CAST(wins AS INTEGER) + CAST(losses AS INTEGER)) > 0
        THEN ROUND(CAST(wins AS INTEGER) * 1.0 / (CAST(wins AS INTEGER) + CAST(losses AS INTEGER)), 3)
    END AS win_pct,
    CAST(made_playoffs AS INTEGER) AS made_playoffs,
    playoff_result,
    playoff_round_reached,
    CAST(won_championship AS INTEGER) AS won_championship
FROM read_csv('data/ibl_jsb_history.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- fact_team_game: Team-level game box scores
CREATE OR REPLACE TABLE fact_team_game AS
SELECT
    CAST(id AS INTEGER) AS id,
    CAST(Date AS DATE) AS game_date,
    CAST(visitorTeamID AS INTEGER) AS visitor_tid,
    CAST(homeTeamID AS INTEGER) AS home_tid,
    CAST(game_type AS INTEGER) AS game_type,
    CAST(season_year AS INTEGER) AS season_year,
    CAST(attendance AS INTEGER) AS attendance,
    CAST(capacity AS INTEGER) AS capacity,
    -- Quarter scores
    CAST(visitorQ1points AS INTEGER) AS visitor_q1,
    CAST(visitorQ2points AS INTEGER) AS visitor_q2,
    CAST(visitorQ3points AS INTEGER) AS visitor_q3,
    CAST(visitorQ4points AS INTEGER) AS visitor_q4,
    CAST(visitorOTpoints AS INTEGER) AS visitor_ot,
    CAST(homeQ1points AS INTEGER) AS home_q1,
    CAST(homeQ2points AS INTEGER) AS home_q2,
    CAST(homeQ3points AS INTEGER) AS home_q3,
    CAST(homeQ4points AS INTEGER) AS home_q4,
    CAST(homeOTpoints AS INTEGER) AS home_ot,
    -- Totals
    CAST(calc_points AS INTEGER) AS total_points,
    CAST(calc_rebounds AS INTEGER) AS total_rebounds
FROM read_csv('data/ibl_box_scores_teams.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- Awards tables
CREATE OR REPLACE TABLE fact_player_awards AS
SELECT
    CAST(year AS INTEGER) AS season_year,
    Award AS award,
    name AS player_name
FROM read_csv('data/ibl_awards.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

CREATE OR REPLACE TABLE fact_team_awards AS
SELECT
    CAST(year AS INTEGER) AS season_year,
    name AS team_name,
    Award AS award
FROM read_csv('data/ibl_team_awards.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- Transactions
CREATE OR REPLACE TABLE fact_transactions AS
SELECT
    CAST(id AS INTEGER) AS id,
    CAST(season_year AS INTEGER) AS season_year,
    CAST(transaction_month AS INTEGER) AS transaction_month,
    CAST(transaction_day AS INTEGER) AS transaction_day,
    CAST(transaction_type AS INTEGER) AS transaction_type,
    CASE CAST(transaction_type AS INTEGER)
        WHEN 1 THEN 'injury'
        WHEN 2 THEN 'trade'
        WHEN 3 THEN 'waiver_claim'
        WHEN 4 THEN 'waiver_release'
        ELSE 'unknown'
    END AS transaction_label,
    CAST(pid AS INTEGER) AS pid,
    player_name,
    CAST(from_teamid AS INTEGER) AS from_teamid,
    CAST(to_teamid AS INTEGER) AS to_teamid,
    CAST(injury_games_missed AS INTEGER) AS injury_games_missed,
    injury_description,
    CAST(trade_group_id AS INTEGER) AS trade_group_id,
    CAST(is_draft_pick AS INTEGER) AS is_draft_pick,
    CAST(draft_pick_year AS INTEGER) AS draft_pick_year
FROM read_csv('data/ibl_jsb_transactions.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- All-Star rosters
CREATE OR REPLACE TABLE fact_allstar_rosters AS
SELECT
    CAST(season_year AS INTEGER) AS season_year,
    event_type,
    CAST(roster_slot AS INTEGER) AS roster_slot,
    CAST(pid AS INTEGER) AS pid,
    player_name
FROM read_csv('data/ibl_jsb_allstar_rosters.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true);

-- Summary
SELECT 'fact_player_season' AS table_name, COUNT(*) AS row_count FROM fact_player_season
UNION ALL SELECT 'fact_player_game', COUNT(*) FROM fact_player_game
UNION ALL SELECT 'fact_team_season', COUNT(*) FROM fact_team_season
UNION ALL SELECT 'fact_team_game', COUNT(*) FROM fact_team_game
UNION ALL SELECT 'fact_player_awards', COUNT(*) FROM fact_player_awards
UNION ALL SELECT 'fact_team_awards', COUNT(*) FROM fact_team_awards
UNION ALL SELECT 'fact_transactions', COUNT(*) FROM fact_transactions
UNION ALL SELECT 'fact_allstar_rosters', COUNT(*) FROM fact_allstar_rosters
ORDER BY table_name;
