-- 03_aggregates.sql: Pre-materialized analytical tables
-- Run after 02_facts.sql

-- fact_player_sim: Denormalized simulation validation table
-- Pre-joins PLB coaching decisions + sim date windows + game box scores + player ratings.
-- Grain: one row per (box_score_id, pid, sim_number) — each game gets the DC settings active.
-- Join path: PLB -> sim_dates (via season offset) -> box_scores (date range) -> PLR (canonical HEAT phase)
CREATE OR REPLACE TABLE fact_player_sim AS
WITH
season_first_sim AS (
    SELECT
        CASE WHEN MONTH(sim_start_date) >= 10
             THEN YEAR(sim_start_date) + 1
             ELSE YEAR(sim_start_date)
        END AS season_year,
        MIN(global_sim) AS first_sim
    FROM dim_sim_dates
    GROUP BY 1
),
plb_deduped AS (
    SELECT *
    FROM fact_plb_snapshots
    WHERE pid IS NOT NULL
    QUALIFY ROW_NUMBER() OVER (
        PARTITION BY pid, season_year, sim_number ORDER BY slot_index
    ) = 1
),
plb_with_dates AS (
    SELECT
        plb.pid, plb.season_year, plb.sim_number,
        plb.tid AS team_id,
        sfs.first_sim + plb.sim_number - 1 AS global_sim,
        sd.sim_start_date, sd.sim_end_date,
        plb.dc_minutes, plb.dc_of, plb.dc_df,
        plb.dc_oi, plb.dc_di, plb.dc_bh
    FROM plb_deduped plb
    JOIN season_first_sim sfs ON plb.season_year = sfs.season_year
    JOIN dim_sim_dates sd ON sd.global_sim = sfs.first_sim + plb.sim_number - 1
)
SELECT
    bs.id                AS box_score_id,
    p.pid,
    p.season_year,
    p.sim_number,
    p.global_sim,
    p.team_id,
    p.sim_start_date,
    p.sim_end_date,
    bs.game_date,
    bs.game_type,
    bs.game_type_label,
    -- DC coaching settings
    p.dc_minutes, p.dc_of, p.dc_df, p.dc_oi, p.dc_di, p.dc_bh,
    -- Player identity
    bs.name              AS player_name,
    r.pos,
    r.age,
    r.peak,
    r.age - r.peak       AS age_relative_to_peak,
    -- Game stats
    bs.minutes,
    bs.fg2_made, bs.fg2_att,
    bs.ft_made,  bs.ft_att,
    bs.fg3_made, bs.fg3_att,
    bs.orb, bs.drb,
    bs.ast, bs.stl, bs.tov, bs.blk, bs.pf,
    bs.points, bs.rebounds,
    -- PLR positional ratings (1-9)
    r.oo, r.od, r."do", r.dd, r.po, r.pd, r."to", r.td,
    -- PLR stat ratings (0-99)
    r.r_fga, r.r_fgp, r.r_fta, r.r_ftp,
    r.r_tga, r.r_tgp, r.r_orb, r.r_drb,
    r.r_ast, r.r_stl, r.r_to, r.r_blk, r.r_foul,
    -- Quality attributes
    r.tsi_sum, r.clutch, r.consistency
FROM plb_with_dates p
JOIN fact_player_game bs
    ON bs.pid = p.pid
    AND bs.team_id = p.team_id
    AND bs.game_date BETWEEN p.sim_start_date AND p.sim_end_date
    AND bs.game_type IN (1, 2)
LEFT JOIN (
    SELECT *
    FROM fact_plr_snapshots
    WHERE snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
    QUALIFY ROW_NUMBER() OVER (
        PARTITION BY pid, season_year
        ORDER BY CASE snapshot_phase
            WHEN 'heat-end' THEN 1 WHEN 'heat-finals' THEN 2
            WHEN 'post-heat' THEN 3 WHEN 'heat-wb' THEN 4
            WHEN 'heat-lb' THEN 5 END
    ) = 1
) r ON r.pid = p.pid AND r.season_year = p.season_year;

-- agg_tsi_progression: Year-over-year rating changes per player
-- Self-join fact_player_season on consecutive years to compute deltas.
-- Replicates TSI analysis from tsi-progression-analysis.md.
CREATE OR REPLACE TABLE agg_tsi_progression AS
SELECT
    curr.pid,
    curr.name,
    curr.season_year,
    curr.tsi_sum,
    curr.tsi_band,
    curr.talent,
    curr.skill,
    curr.intangibles,
    curr.estimated_age,
    curr.peak,
    curr.age_relative_to_peak,
    -- Development phase classification
    CASE
        WHEN curr.age_relative_to_peak <= -3 THEN 'far_from_peak'
        WHEN curr.age_relative_to_peak BETWEEN -2 AND 2 THEN 'near_peak'
        ELSE 'post_peak'
    END AS development_phase,
    -- Per-game stat deltas
    curr.ppg - prev.ppg AS delta_ppg,
    curr.rpg - prev.rpg AS delta_rpg,
    curr.apg - prev.apg AS delta_apg,
    curr.spg - prev.spg AS delta_spg,
    curr.bpg - prev.bpg AS delta_bpg,
    -- Shooting percentage deltas
    curr.fg_pct - prev.fg_pct AS delta_fg_pct,
    curr.ft_pct - prev.ft_pct AS delta_ft_pct,
    curr.three_pct - prev.three_pct AS delta_three_pct,
    -- Rating deltas (all 20 rating columns)
    curr.r_2ga - prev.r_2ga AS delta_r_2ga,
    curr.r_2gp - prev.r_2gp AS delta_r_2gp,
    curr.r_fta - prev.r_fta AS delta_r_fta,
    curr.r_ftp - prev.r_ftp AS delta_r_ftp,
    curr.r_3ga - prev.r_3ga AS delta_r_3ga,
    curr.r_3gp - prev.r_3gp AS delta_r_3gp,
    curr.r_orb - prev.r_orb AS delta_r_orb,
    curr.r_drb - prev.r_drb AS delta_r_drb,
    curr.r_ast - prev.r_ast AS delta_r_ast,
    curr.r_stl - prev.r_stl AS delta_r_stl,
    curr.r_blk - prev.r_blk AS delta_r_blk,
    curr.r_tvr - prev.r_tvr AS delta_r_tvr,
    curr.r_oo  - prev.r_oo  AS delta_r_oo,
    curr.r_do  - prev.r_do  AS delta_r_do,
    curr.r_po  - prev.r_po  AS delta_r_po,
    curr.r_to  - prev.r_to  AS delta_r_to,
    curr.r_od  - prev.r_od  AS delta_r_od,
    curr.r_dd  - prev.r_dd  AS delta_r_dd,
    curr.r_pd  - prev.r_pd  AS delta_r_pd,
    curr.r_td  - prev.r_td  AS delta_r_td,
    -- QA delta
    curr.qa - prev.qa AS delta_qa
FROM fact_player_season curr
JOIN fact_player_season prev
    ON curr.pid = prev.pid
    AND curr.season_year = prev.season_year + 1
WHERE curr.games > 0 AND prev.games > 0;

-- agg_player_career: Career totals and averages per player
CREATE OR REPLACE TABLE agg_player_career AS
SELECT
    pid,
    MAX(name) AS name,
    COUNT(*) AS career_seasons,
    SUM(games) AS career_games,
    SUM(pts) AS career_pts,
    SUM(reb) AS career_reb,
    SUM(ast) AS career_ast,
    -- Career per-game averages
    CASE WHEN SUM(games) > 0
        THEN ROUND(SUM(2.0 * fgm + ftm + tgm) / SUM(games), 1)
    END AS career_ppg,
    CASE WHEN SUM(games) > 0
        THEN ROUND(SUM(reb) * 1.0 / SUM(games), 1)
    END AS career_rpg,
    CASE WHEN SUM(games) > 0
        THEN ROUND(SUM(ast) * 1.0 / SUM(games), 1)
    END AS career_apg,
    -- Career shooting
    CASE WHEN SUM(fga) > 0
        THEN ROUND(SUM(fgm) * 100.0 / SUM(fga), 1)
    END AS career_fg_pct,
    CASE WHEN SUM(fta) > 0
        THEN ROUND(SUM(ftm) * 100.0 / SUM(fta), 1)
    END AS career_ft_pct,
    CASE WHEN SUM(tga) > 0
        THEN ROUND(SUM(tgm) * 100.0 / SUM(tga), 1)
    END AS career_three_pct,
    -- Peak season
    MAX(ppg) AS peak_ppg,
    (SELECT f2.season_year FROM fact_player_season f2
     WHERE f2.pid = fact_player_season.pid AND f2.games > 0
     ORDER BY f2.ppg DESC NULLS LAST LIMIT 1) AS peak_season_year,
    -- TSI (latest available)
    (SELECT f3.tsi_sum FROM fact_player_season f3
     WHERE f3.pid = fact_player_season.pid AND f3.tsi_sum IS NOT NULL
     ORDER BY f3.season_year DESC LIMIT 1) AS tsi_sum,
    MIN(season_year) AS first_season,
    MAX(season_year) AS last_season
FROM fact_player_season
WHERE games > 0
GROUP BY pid;

-- agg_draft_cohort: Approximate draft class analysis (first season year ~ draft year)
CREATE OR REPLACE TABLE agg_draft_cohort AS
WITH player_first_season AS (
    SELECT
        pid,
        first_season AS first_season_year,
        career_ppg,
        career_seasons,
        tsi_sum
    FROM agg_player_career
)
SELECT
    first_season_year,
    COUNT(*) AS cohort_size,
    ROUND(AVG(career_ppg), 1) AS avg_career_ppg,
    ROUND(AVG(career_seasons), 1) AS avg_career_seasons,
    ROUND(AVG(tsi_sum), 1) AS avg_tsi_sum,
    MAX(career_ppg) AS max_career_ppg,
    MIN(career_ppg) AS min_career_ppg
FROM player_first_season
GROUP BY first_season_year
ORDER BY first_season_year;

-- agg_team_season_roster: Per team-season roster composition metrics
CREATE OR REPLACE TABLE agg_team_season_roster AS
SELECT
    f.teamid,
    f.season_year,
    f.team AS team_name,
    COUNT(*) AS roster_size,
    ROUND(AVG(f.tsi_sum), 1) AS avg_tsi_sum,
    ROUND(AVG(f.estimated_age), 1) AS avg_age,
    SUM(f.salary) AS salary_total,
    COUNT(CASE WHEN f.tsi_sum >= 12 THEN 1 END) AS star_count,
    COUNT(CASE WHEN f.tsi_sum >= 10 THEN 1 END) AS quality_count,
    -- Join with team season record
    t.wins,
    t.losses,
    t.win_pct,
    t.made_playoffs,
    t.won_championship
FROM fact_player_season f
LEFT JOIN fact_team_season t
    ON f.teamid = t.teamid AND f.season_year = t.season_year
WHERE f.games > 0
GROUP BY f.teamid, f.season_year, f.team, t.wins, t.losses, t.win_pct, t.made_playoffs, t.won_championship;

-- agg_playoff_predictor: Team-seasons that made playoffs with regular-season stats
-- For correlation analysis between roster metrics and playoff success.
CREATE OR REPLACE TABLE agg_playoff_predictor AS
SELECT
    r.*,
    CASE
        WHEN r.won_championship = 1 THEN 'champion'
        WHEN t.playoff_round_reached = 'finals' THEN 'finals'
        WHEN t.playoff_round_reached = 'semi-finals' THEN 'semis'
        WHEN t.playoff_round_reached = 'quarter-finals' THEN 'quarters'
        WHEN r.made_playoffs = 1 THEN 'first_round'
        ELSE 'missed'
    END AS playoff_outcome
FROM agg_team_season_roster r
LEFT JOIN fact_team_season t
    ON r.teamid = t.teamid AND r.season_year = t.season_year;

-- Summary
SELECT 'fact_player_sim' AS table_name, COUNT(*) AS row_count FROM fact_player_sim
UNION ALL SELECT 'agg_tsi_progression', COUNT(*) FROM agg_tsi_progression
UNION ALL SELECT 'agg_player_career', COUNT(*) FROM agg_player_career
UNION ALL SELECT 'agg_draft_cohort', COUNT(*) FROM agg_draft_cohort
UNION ALL SELECT 'agg_team_season_roster', COUNT(*) FROM agg_team_season_roster
UNION ALL SELECT 'agg_playoff_predictor', COUNT(*) FROM agg_playoff_predictor
ORDER BY table_name;
