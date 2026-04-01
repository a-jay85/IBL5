-- 02_facts.sql: Fact tables with pre-joined dimensions and computed stats
-- Run after 01_dimensions.sql

-- fact_player_season: Season-level player stats with computed per-game averages
-- Stat formulas match SeasonLeaderboardsRepository: PPG = (2*fgm + ftm + tgm) / games
CREATE OR REPLACE TABLE fact_player_season AS
WITH hist_raw AS (
    SELECT
        TRY_CAST(pid AS INTEGER) AS pid,
        name,
        TRY_CAST(year AS INTEGER) AS season_year,
        team,
        TRY_CAST(teamid AS INTEGER) AS teamid,
        TRY_CAST(games AS INTEGER) AS games,
        TRY_CAST(minutes AS INTEGER) AS minutes,
        TRY_CAST(fgm AS INTEGER) AS fgm,
        TRY_CAST(fga AS INTEGER) AS fga,
        TRY_CAST(ftm AS INTEGER) AS ftm,
        TRY_CAST(fta AS INTEGER) AS fta,
        TRY_CAST(tgm AS INTEGER) AS tgm,
        TRY_CAST(tga AS INTEGER) AS tga,
        TRY_CAST(orb AS INTEGER) AS orb,
        TRY_CAST(reb AS INTEGER) AS reb,
        TRY_CAST(ast AS INTEGER) AS ast,
        TRY_CAST(stl AS INTEGER) AS stl,
        TRY_CAST(blk AS INTEGER) AS blk,
        TRY_CAST(tvr AS INTEGER) AS tvr,
        TRY_CAST(pf AS INTEGER) AS pf,
        TRY_CAST(pts AS INTEGER) AS pts,
        TRY_CAST(r_2ga AS INTEGER) AS r_2ga,
        TRY_CAST(r_2gp AS INTEGER) AS r_2gp,
        TRY_CAST(r_fta AS INTEGER) AS r_fta,
        TRY_CAST(r_ftp AS INTEGER) AS r_ftp,
        TRY_CAST(r_3ga AS INTEGER) AS r_3ga,
        TRY_CAST(r_3gp AS INTEGER) AS r_3gp,
        TRY_CAST(r_orb AS INTEGER) AS r_orb,
        TRY_CAST(r_drb AS INTEGER) AS r_drb,
        TRY_CAST(r_ast AS INTEGER) AS r_ast,
        TRY_CAST(r_stl AS INTEGER) AS r_stl,
        TRY_CAST(r_blk AS INTEGER) AS r_blk,
        TRY_CAST(r_tvr AS INTEGER) AS r_tvr,
        TRY_CAST(r_oo AS INTEGER)  AS r_oo,
        TRY_CAST(r_do AS INTEGER)  AS r_do,
        TRY_CAST(r_po AS INTEGER)  AS r_po,
        TRY_CAST(r_to AS INTEGER)  AS r_to,
        TRY_CAST(r_od AS INTEGER)  AS r_od,
        TRY_CAST(r_dd AS INTEGER)  AS r_dd,
        TRY_CAST(r_pd AS INTEGER)  AS r_pd,
        TRY_CAST(r_td AS INTEGER)  AS r_td,
        TRY_CAST(salary AS INTEGER) AS salary
    FROM read_csv('data/ibl_hist.csv', delim='\t', header=true, all_varchar=true,
        null_padding=true, ignore_errors=true, strict_mode=false, quote='')
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
    TRY_CAST(id AS INTEGER) AS id,
    TRY_CAST(Date AS DATE) AS game_date,
    TRY_CAST(pid AS INTEGER) AS pid,
    name,
    pos,
    TRY_CAST(teamID AS INTEGER) AS team_id,
    TRY_CAST(visitorTID AS INTEGER) AS visitor_tid,
    TRY_CAST(homeTID AS INTEGER) AS home_tid,
    TRY_CAST(gameMIN AS INTEGER) AS minutes,
    TRY_CAST(game2GM AS INTEGER) AS fg2_made,
    TRY_CAST(game2GA AS INTEGER) AS fg2_att,
    TRY_CAST(gameFTM AS INTEGER) AS ft_made,
    TRY_CAST(gameFTA AS INTEGER) AS ft_att,
    TRY_CAST(game3GM AS INTEGER) AS fg3_made,
    TRY_CAST(game3GA AS INTEGER) AS fg3_att,
    TRY_CAST(gameORB AS INTEGER) AS orb,
    TRY_CAST(gameDRB AS INTEGER) AS drb,
    TRY_CAST(gameAST AS INTEGER) AS ast,
    TRY_CAST(gameSTL AS INTEGER) AS stl,
    TRY_CAST(gameTOV AS INTEGER) AS tov,
    TRY_CAST(gameBLK AS INTEGER) AS blk,
    TRY_CAST(gamePF AS INTEGER)  AS pf,
    TRY_CAST(game_type AS INTEGER) AS game_type,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    TRY_CAST(calc_points AS INTEGER) AS points,
    TRY_CAST(calc_rebounds AS INTEGER) AS rebounds,
    TRY_CAST(attendance AS INTEGER) AS attendance,
    -- Game type label
    CASE TRY_CAST(game_type AS INTEGER)
        WHEN 1 THEN 'regular'
        WHEN 2 THEN 'playoffs'
        WHEN 3 THEN 'preseason'
        ELSE 'other'
    END AS game_type_label
FROM read_csv('data/ibl_box_scores.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- fact_team_season: Team season records from JSB history
CREATE OR REPLACE TABLE fact_team_season AS
SELECT
    TRY_CAST(id AS INTEGER) AS id,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    team_name,
    TRY_CAST(teamid AS INTEGER) AS teamid,
    TRY_CAST(wins AS INTEGER) AS wins,
    TRY_CAST(losses AS INTEGER) AS losses,
    CASE WHEN (TRY_CAST(wins AS INTEGER) + TRY_CAST(losses AS INTEGER)) > 0
        THEN ROUND(TRY_CAST(wins AS INTEGER) * 1.0 / (TRY_CAST(wins AS INTEGER) + TRY_CAST(losses AS INTEGER)), 3)
    END AS win_pct,
    TRY_CAST(made_playoffs AS INTEGER) AS made_playoffs,
    playoff_result,
    playoff_round_reached,
    TRY_CAST(won_championship AS INTEGER) AS won_championship
FROM read_csv('data/ibl_jsb_history.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- fact_team_game: Team-level game box scores
CREATE OR REPLACE TABLE fact_team_game AS
SELECT
    TRY_CAST(id AS INTEGER) AS id,
    TRY_CAST(Date AS DATE) AS game_date,
    TRY_CAST(visitorTeamID AS INTEGER) AS visitor_tid,
    TRY_CAST(homeTeamID AS INTEGER) AS home_tid,
    TRY_CAST(game_type AS INTEGER) AS game_type,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    TRY_CAST(attendance AS INTEGER) AS attendance,
    TRY_CAST(capacity AS INTEGER) AS capacity,
    -- Quarter scores
    TRY_CAST(visitorQ1points AS INTEGER) AS visitor_q1,
    TRY_CAST(visitorQ2points AS INTEGER) AS visitor_q2,
    TRY_CAST(visitorQ3points AS INTEGER) AS visitor_q3,
    TRY_CAST(visitorQ4points AS INTEGER) AS visitor_q4,
    TRY_CAST(visitorOTpoints AS INTEGER) AS visitor_ot,
    TRY_CAST(homeQ1points AS INTEGER) AS home_q1,
    TRY_CAST(homeQ2points AS INTEGER) AS home_q2,
    TRY_CAST(homeQ3points AS INTEGER) AS home_q3,
    TRY_CAST(homeQ4points AS INTEGER) AS home_q4,
    TRY_CAST(homeOTpoints AS INTEGER) AS home_ot,
    -- Totals
    TRY_CAST(calc_points AS INTEGER) AS total_points,
    TRY_CAST(calc_rebounds AS INTEGER) AS total_rebounds
FROM read_csv('data/ibl_box_scores_teams.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- Awards tables
CREATE OR REPLACE TABLE fact_player_awards AS
SELECT
    TRY_CAST(year AS INTEGER) AS season_year,
    Award AS award,
    name AS player_name
FROM read_csv('data/ibl_awards.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

CREATE OR REPLACE TABLE fact_team_awards AS
SELECT
    TRY_CAST(year AS INTEGER) AS season_year,
    name AS team_name,
    Award AS award
FROM read_csv('data/ibl_team_awards.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- Transactions
CREATE OR REPLACE TABLE fact_transactions AS
SELECT
    TRY_CAST(id AS INTEGER) AS id,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    TRY_CAST(transaction_month AS INTEGER) AS transaction_month,
    TRY_CAST(transaction_day AS INTEGER) AS transaction_day,
    TRY_CAST(transaction_type AS INTEGER) AS transaction_type,
    CASE TRY_CAST(transaction_type AS INTEGER)
        WHEN 1 THEN 'injury'
        WHEN 2 THEN 'trade'
        WHEN 3 THEN 'waiver_claim'
        WHEN 4 THEN 'waiver_release'
        ELSE 'unknown'
    END AS transaction_label,
    TRY_CAST(pid AS INTEGER) AS pid,
    player_name,
    TRY_CAST(from_teamid AS INTEGER) AS from_teamid,
    TRY_CAST(to_teamid AS INTEGER) AS to_teamid,
    TRY_CAST(injury_games_missed AS INTEGER) AS injury_games_missed,
    injury_description,
    TRY_CAST(trade_group_id AS INTEGER) AS trade_group_id,
    TRY_CAST(is_draft_pick AS INTEGER) AS is_draft_pick,
    TRY_CAST(draft_pick_year AS INTEGER) AS draft_pick_year
FROM read_csv('data/ibl_jsb_transactions.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- All-Star rosters
CREATE OR REPLACE TABLE fact_allstar_rosters AS
SELECT
    TRY_CAST(season_year AS INTEGER) AS season_year,
    event_type,
    TRY_CAST(roster_slot AS INTEGER) AS roster_slot,
    TRY_CAST(pid AS INTEGER) AS pid,
    player_name
FROM read_csv('data/ibl_jsb_allstar_rosters.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- fact_plb_snapshots: Depth chart coaching decisions per player per sim
-- 173K+ rows: 19 seasons x 28 teams x ~30 sims/season x ~11 active players
-- DC settings: minutes target, offensive/defensive formation and instruction, ball handling
CREATE OR REPLACE TABLE fact_plb_snapshots AS
SELECT
    TRY_CAST(id AS INTEGER)          AS id,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    TRY_CAST(sim_number AS INTEGER)  AS sim_number,
    source_archive,
    TRY_CAST(tid AS INTEGER)         AS tid,
    TRY_CAST(slot_index AS INTEGER)  AS slot_index,
    TRY_CAST(pid AS INTEGER)         AS pid,
    player_name,
    TRY_CAST(dc_minutes AS INTEGER)  AS dc_minutes,
    TRY_CAST(dc_of AS INTEGER)       AS dc_of,
    TRY_CAST(dc_df AS INTEGER)       AS dc_df,
    TRY_CAST(dc_oi AS INTEGER)       AS dc_oi,
    TRY_CAST(dc_di AS INTEGER)       AS dc_di,
    TRY_CAST(dc_bh AS INTEGER)       AS dc_bh
FROM read_csv('data/ibl_plb_snapshots.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='')
WHERE TRY_CAST(pid AS INTEGER) IS NOT NULL;

-- fact_plr_snapshots: Player ratings snapshots at every archived point in the season.
-- Includes all phases (preseason, heat, regular season sims, playoffs, finals).
CREATE OR REPLACE TABLE fact_plr_snapshots AS
SELECT
    TRY_CAST(pid AS INTEGER)         AS pid,
    name,
    TRY_CAST(season_year AS INTEGER) AS season_year,
    snapshot_phase,
    TRY_CAST(tid AS INTEGER)         AS tid,
    TRY_CAST(age AS INTEGER)         AS age,
    pos,
    TRY_CAST(peak AS INTEGER)        AS peak,
    -- Physical
    TRY_CAST(htft AS INTEGER) AS htft, TRY_CAST(htin AS INTEGER) AS htin,
    TRY_CAST(wt AS INTEGER) AS wt,
    -- Positional ratings (1-9 scale)
    TRY_CAST(oo AS INTEGER) AS oo, TRY_CAST(od AS INTEGER) AS od,
    TRY_CAST("do" AS INTEGER) AS "do", TRY_CAST(dd AS INTEGER) AS dd,
    TRY_CAST(po AS INTEGER) AS po, TRY_CAST(pd AS INTEGER) AS pd,
    TRY_CAST("to" AS INTEGER) AS "to", TRY_CAST(td AS INTEGER) AS td,
    -- Stat ratings (0-99 scale)
    TRY_CAST(r_fga AS INTEGER) AS r_fga, TRY_CAST(r_fgp AS INTEGER) AS r_fgp,
    TRY_CAST(r_fta AS INTEGER) AS r_fta, TRY_CAST(r_ftp AS INTEGER) AS r_ftp,
    TRY_CAST(r_tga AS INTEGER) AS r_tga, TRY_CAST(r_tgp AS INTEGER) AS r_tgp,
    TRY_CAST(r_orb AS INTEGER) AS r_orb, TRY_CAST(r_drb AS INTEGER) AS r_drb,
    TRY_CAST(r_ast AS INTEGER) AS r_ast, TRY_CAST(r_stl AS INTEGER) AS r_stl,
    TRY_CAST(r_to AS INTEGER)  AS r_to,  TRY_CAST(r_blk AS INTEGER) AS r_blk,
    TRY_CAST(r_foul AS INTEGER) AS r_foul,
    -- Quality attributes
    TRY_CAST(talent AS INTEGER)      AS talent,
    TRY_CAST(skill AS INTEGER)       AS skill,
    TRY_CAST(intangibles AS INTEGER) AS intangibles,
    (TRY_CAST(talent AS INTEGER) + TRY_CAST(skill AS INTEGER) + TRY_CAST(intangibles AS INTEGER)) AS tsi_sum,
    TRY_CAST(clutch AS INTEGER)      AS clutch,
    TRY_CAST(consistency AS INTEGER) AS consistency,
    TRY_CAST(exp AS INTEGER)         AS exp,
    -- Contract
    TRY_CAST(bird AS INTEGER) AS bird,
    TRY_CAST(cy AS INTEGER) AS cy, TRY_CAST(cyt AS INTEGER) AS cyt,
    TRY_CAST(cy1 AS INTEGER) AS cy1, TRY_CAST(cy2 AS INTEGER) AS cy2,
    TRY_CAST(cy3 AS INTEGER) AS cy3, TRY_CAST(cy4 AS INTEGER) AS cy4,
    TRY_CAST(cy5 AS INTEGER) AS cy5, TRY_CAST(cy6 AS INTEGER) AS cy6,
    -- Depth chart positions
    TRY_CAST(PGDepth AS INTEGER) AS PGDepth, TRY_CAST(SGDepth AS INTEGER) AS SGDepth,
    TRY_CAST(SFDepth AS INTEGER) AS SFDepth, TRY_CAST(PFDepth AS INTEGER) AS PFDepth,
    TRY_CAST(CDepth AS INTEGER) AS CDepth,
    -- Season stats
    TRY_CAST(ordinal AS INTEGER) AS ordinal,
    TRY_CAST(stats_gs AS INTEGER) AS stats_gs, TRY_CAST(stats_gm AS INTEGER) AS stats_gm,
    TRY_CAST(stats_min AS INTEGER) AS stats_min,
    TRY_CAST(stats_fgm AS INTEGER) AS stats_fgm, TRY_CAST(stats_fga AS INTEGER) AS stats_fga,
    TRY_CAST(stats_ftm AS INTEGER) AS stats_ftm, TRY_CAST(stats_fta AS INTEGER) AS stats_fta,
    TRY_CAST(stats_3gm AS INTEGER) AS stats_3gm, TRY_CAST(stats_3ga AS INTEGER) AS stats_3ga,
    TRY_CAST(stats_orb AS INTEGER) AS stats_orb, TRY_CAST(stats_drb AS INTEGER) AS stats_drb,
    TRY_CAST(stats_ast AS INTEGER) AS stats_ast, TRY_CAST(stats_stl AS INTEGER) AS stats_stl,
    TRY_CAST(stats_to AS INTEGER) AS stats_to, TRY_CAST(stats_blk AS INTEGER) AS stats_blk,
    TRY_CAST(stats_pf AS INTEGER) AS stats_pf,
    TRY_CAST(stats_reb AS INTEGER) AS stats_reb, TRY_CAST(stats_pts AS INTEGER) AS stats_pts,
    -- Playoff stats
    TRY_CAST(po_stats_gm AS INTEGER) AS po_stats_gm, TRY_CAST(po_stats_min AS INTEGER) AS po_stats_min,
    TRY_CAST(po_stats_2gm AS INTEGER) AS po_stats_2gm, TRY_CAST(po_stats_2ga AS INTEGER) AS po_stats_2ga,
    TRY_CAST(po_stats_ftm AS INTEGER) AS po_stats_ftm, TRY_CAST(po_stats_fta AS INTEGER) AS po_stats_fta,
    TRY_CAST(po_stats_3gm AS INTEGER) AS po_stats_3gm, TRY_CAST(po_stats_3ga AS INTEGER) AS po_stats_3ga,
    TRY_CAST(po_stats_orb AS INTEGER) AS po_stats_orb, TRY_CAST(po_stats_drb AS INTEGER) AS po_stats_drb,
    TRY_CAST(po_stats_ast AS INTEGER) AS po_stats_ast, TRY_CAST(po_stats_stl AS INTEGER) AS po_stats_stl,
    TRY_CAST(po_stats_tvr AS INTEGER) AS po_stats_tvr, TRY_CAST(po_stats_blk AS INTEGER) AS po_stats_blk,
    TRY_CAST(po_stats_pf AS INTEGER) AS po_stats_pf,
    -- Career stats
    TRY_CAST(car_gm AS INTEGER) AS car_gm, TRY_CAST(car_min AS INTEGER) AS car_min,
    TRY_CAST(car_fgm AS INTEGER) AS car_fgm, TRY_CAST(car_fga AS INTEGER) AS car_fga,
    TRY_CAST(car_ftm AS INTEGER) AS car_ftm, TRY_CAST(car_fta AS INTEGER) AS car_fta,
    TRY_CAST(car_tgm AS INTEGER) AS car_tgm, TRY_CAST(car_tga AS INTEGER) AS car_tga,
    TRY_CAST(car_orb AS INTEGER) AS car_orb, TRY_CAST(car_drb AS INTEGER) AS car_drb,
    TRY_CAST(car_reb AS INTEGER) AS car_reb, TRY_CAST(car_ast AS INTEGER) AS car_ast,
    TRY_CAST(car_stl AS INTEGER) AS car_stl, TRY_CAST(car_to AS INTEGER) AS car_to,
    TRY_CAST(car_blk AS INTEGER) AS car_blk, TRY_CAST(car_pf AS INTEGER) AS car_pf,
    TRY_CAST(car_pts AS INTEGER) AS car_pts,
    -- Season highs
    TRY_CAST(sh_pts AS INTEGER) AS sh_pts, TRY_CAST(sh_reb AS INTEGER) AS sh_reb,
    TRY_CAST(sh_ast AS INTEGER) AS sh_ast, TRY_CAST(sh_stl AS INTEGER) AS sh_stl,
    TRY_CAST(sh_blk AS INTEGER) AS sh_blk,
    TRY_CAST(s_dd AS INTEGER) AS s_dd, TRY_CAST(s_td AS INTEGER) AS s_td,
    -- Playoff highs
    TRY_CAST(sp_pts AS INTEGER) AS sp_pts, TRY_CAST(sp_reb AS INTEGER) AS sp_reb,
    TRY_CAST(sp_ast AS INTEGER) AS sp_ast, TRY_CAST(sp_stl AS INTEGER) AS sp_stl,
    TRY_CAST(sp_blk AS INTEGER) AS sp_blk,
    -- Career season highs
    TRY_CAST(ch_pts AS INTEGER) AS ch_pts, TRY_CAST(ch_reb AS INTEGER) AS ch_reb,
    TRY_CAST(ch_ast AS INTEGER) AS ch_ast, TRY_CAST(ch_stl AS INTEGER) AS ch_stl,
    TRY_CAST(ch_blk AS INTEGER) AS ch_blk,
    TRY_CAST(c_dd AS INTEGER) AS c_dd, TRY_CAST(c_td AS INTEGER) AS c_td,
    -- Career playoff highs
    TRY_CAST(cp_pts AS INTEGER) AS cp_pts, TRY_CAST(cp_reb AS INTEGER) AS cp_reb,
    TRY_CAST(cp_ast AS INTEGER) AS cp_ast, TRY_CAST(cp_stl AS INTEGER) AS cp_stl,
    TRY_CAST(cp_blk AS INTEGER) AS cp_blk,
    -- Real-life stats
    TRY_CAST(rl_gp AS INTEGER) AS rl_gp, TRY_CAST(rl_min AS INTEGER) AS rl_min,
    TRY_CAST(rl_fgm AS INTEGER) AS rl_fgm, TRY_CAST(rl_fga AS INTEGER) AS rl_fga,
    TRY_CAST(rl_ftm AS INTEGER) AS rl_ftm, TRY_CAST(rl_fta AS INTEGER) AS rl_fta,
    TRY_CAST(rl_3gm AS INTEGER) AS rl_3gm, TRY_CAST(rl_3ga AS INTEGER) AS rl_3ga,
    TRY_CAST(rl_orb AS INTEGER) AS rl_orb, TRY_CAST(rl_drb AS INTEGER) AS rl_drb,
    TRY_CAST(rl_ast AS INTEGER) AS rl_ast, TRY_CAST(rl_stl AS INTEGER) AS rl_stl,
    TRY_CAST(rl_tvr AS INTEGER) AS rl_tvr, TRY_CAST(rl_blk AS INTEGER) AS rl_blk,
    TRY_CAST(rl_pf AS INTEGER) AS rl_pf,
    -- Preference weights
    TRY_CAST(coach AS INTEGER) AS coach, TRY_CAST(loyalty AS INTEGER) AS loyalty,
    TRY_CAST(playingTime AS INTEGER) AS playingTime, TRY_CAST(winner AS INTEGER) AS winner,
    TRY_CAST(tradition AS INTEGER) AS tradition, TRY_CAST(security AS INTEGER) AS security,
    -- Draft info
    TRY_CAST(draftround AS INTEGER) AS draftround,
    TRY_CAST(draftpickno AS INTEGER) AS draftpickno,
    TRY_CAST(fa_signing_flag AS INTEGER) AS fa_signing_flag,
    -- Other fields
    TRY_CAST(dc_canPlayInGame AS INTEGER) AS dc_canPlayInGame,
    TRY_CAST(injured AS INTEGER) AS injured,
    -- Derived fields
    TRY_CAST(draftyear AS INTEGER) AS draftyear,
    TRY_CAST(salary AS INTEGER) AS salary
FROM read_csv('data/ibl_plr_snapshots.csv', delim='\t', header=true, all_varchar=true,
    null_padding=true, ignore_errors=true, strict_mode=false, quote='');

-- Summary
SELECT 'fact_player_season' AS table_name, COUNT(*) AS row_count FROM fact_player_season
UNION ALL SELECT 'fact_player_game', COUNT(*) FROM fact_player_game
UNION ALL SELECT 'fact_team_season', COUNT(*) FROM fact_team_season
UNION ALL SELECT 'fact_team_game', COUNT(*) FROM fact_team_game
UNION ALL SELECT 'fact_player_awards', COUNT(*) FROM fact_player_awards
UNION ALL SELECT 'fact_team_awards', COUNT(*) FROM fact_team_awards
UNION ALL SELECT 'fact_transactions', COUNT(*) FROM fact_transactions
UNION ALL SELECT 'fact_allstar_rosters', COUNT(*) FROM fact_allstar_rosters
UNION ALL SELECT 'fact_plb_snapshots', COUNT(*) FROM fact_plb_snapshots
UNION ALL SELECT 'fact_plr_snapshots', COUNT(*) FROM fact_plr_snapshots
ORDER BY table_name;
