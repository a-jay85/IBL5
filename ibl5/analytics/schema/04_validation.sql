-- 04_validation.sql: Data quality assertions
-- Prints pass/fail for each check. Any FAIL should be investigated.

.mode column
.headers on

SELECT '=== DATA VALIDATION ===' AS status;

-- Row count checks: ensure non-empty tables
SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dim_player has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_player);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dim_team has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_team);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dim_season has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_season);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'fact_player_season has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_player_season);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'fact_team_season has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_team_season);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'agg_player_career has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM agg_player_career);

-- Sanity bounds: PPG should be < 50
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'no PPG > 50' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_player_season WHERE ppg > 50);

-- Sanity bounds: estimated age should be 18-45
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'ages in range 18-45' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_player_season
      WHERE estimated_age IS NOT NULL AND (estimated_age < 18 OR estimated_age > 45));

-- Sanity bounds: win_pct should be 0-1
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'win_pct in 0-1 range' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_team_season
      WHERE win_pct IS NOT NULL AND (win_pct < 0 OR win_pct > 1));

-- TSI sum should be 3-15
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'TSI sum in 3-15 range' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_player
      WHERE tsi_sum IS NOT NULL AND tsi_sum > 0 AND (tsi_sum < 3 OR tsi_sum > 15));

-- Orphan check: fact_player_season PIDs should exist in dim_player
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'no orphan PIDs in fact_player_season' AS assertion,
    cnt AS value
FROM (SELECT COUNT(DISTINCT f.pid) AS cnt
      FROM fact_player_season f
      LEFT JOIN dim_player p ON f.pid = p.pid
      WHERE p.pid IS NULL);

-- Orphan check: fact_team_season teamids should exist in dim_team
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'no orphan teamids in fact_team_season' AS assertion,
    cnt AS value
FROM (SELECT COUNT(DISTINCT f.teamid) AS cnt
      FROM fact_team_season f
      LEFT JOIN dim_team t ON f.teamid = t.teamid
      WHERE f.teamid IS NOT NULL AND t.teamid IS NULL);

-- Career seasons should be >= 1
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'career_seasons >= 1' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM agg_player_career WHERE career_seasons < 1);

-- Progression table should have rows if hist has consecutive seasons
SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'agg_tsi_progression has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM agg_tsi_progression);

-- Shooting percentages should be 0-100
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'FG% in 0-100 range' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_player_season
      WHERE fg_pct IS NOT NULL AND (fg_pct < 0 OR fg_pct > 100));

-- New table row count checks
SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'fact_plb_snapshots has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_plb_snapshots);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'fact_plr_snapshots has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_plr_snapshots);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dim_sim_dates has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_sim_dates);

SELECT
    CASE WHEN cnt > 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'fact_player_sim has rows' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM fact_player_sim);

-- Structural: sim_dates should have exactly 698 rows
SELECT
    CASE WHEN cnt = 698 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dim_sim_dates has 698 sims' AS assertion,
    cnt AS value
FROM (SELECT COUNT(*) AS cnt FROM dim_sim_dates);

-- No orphan PLB season_years
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'no orphan PLB season_years' AS assertion,
    cnt AS value
FROM (
    SELECT COUNT(DISTINCT p.season_year) AS cnt
    FROM fact_plb_snapshots p
    LEFT JOIN dim_season s ON p.season_year = s.season_year
    WHERE s.season_year IS NULL
);

-- PLR snapshot phase coverage: each season should have at least one HEAT-phase snapshot
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'WARN' END AS status,
    'All seasons have HEAT-phase PLR snapshots' AS assertion,
    cnt AS value
FROM (
    SELECT COUNT(*) AS cnt
    FROM dim_season ds
    LEFT JOIN (
        SELECT DISTINCT season_year
        FROM fact_plr_snapshots
        WHERE snapshot_phase IN ('heat-end', 'heat-finals', 'post-heat', 'heat-wb', 'heat-lb')
    ) h ON ds.season_year = h.season_year
    WHERE h.season_year IS NULL
);

-- DC settings sanity: dc_minutes should be 0-40
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dc_minutes in 0-40 range' AS assertion,
    cnt AS value
FROM (
    SELECT COUNT(*) AS cnt FROM fact_plb_snapshots
    WHERE dc_minutes < 0 OR dc_minutes > 40
);

-- DC settings sanity: dc_bh/oi/di should be -2 to 2
SELECT
    CASE WHEN cnt = 0 THEN 'PASS' ELSE 'FAIL' END AS status,
    'dc_bh/oi/di in -2 to 2 range' AS assertion,
    cnt AS value
FROM (
    SELECT COUNT(*) AS cnt FROM fact_plb_snapshots
    WHERE dc_bh NOT BETWEEN -2 AND 2
       OR dc_oi NOT BETWEEN -2 AND 2
       OR dc_di NOT BETWEEN -2 AND 2
);

-- Spot check: 2007 sim_number 7 should map to 2006-11-09
SELECT
    CASE WHEN sd.sim_start_date = DATE '2006-11-09' THEN 'PASS' ELSE 'FAIL' END AS status,
    '2007 sim 7 maps to 2006-11-09' AS assertion,
    sd.sim_start_date AS value
FROM dim_sim_dates sd
WHERE sd.global_sim = 664 + 7 - 1;

SELECT '=== VALIDATION COMPLETE ===' AS status;
