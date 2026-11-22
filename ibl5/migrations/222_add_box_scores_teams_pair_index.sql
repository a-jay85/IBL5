-- Migration 222: Add composite pair index to ibl_box_scores_teams (and the Olympics twin)
--
-- The Rivalries pair-games dedupe scan (unique_games CTE in
-- RivalriesRepository::gamesCte) filters on a (visitor_teamid, home_teamid)
-- equality pair, then on game_type and a season_year span. No existing index
-- leads with that pair, so the planner scans a single-column index and
-- filters ~98% of the rows away. This index leads with the pair and carries
-- game_type and season_year, so those filters run inside the index.
--
-- Index idx_visitor_home_gt_season, columns in order: visitor_teamid,home_teamid,game_type,season_year
-- (information_schema.STATISTICS lists them in that order on both tables).
--
-- ADDITIVE ONLY — no DROP, no rewrite. Old code ignores the index, so
-- auto-rollback is safe (ibl5/migrations/README.md § Auto-Rollback Compatibility).
--
-- Write cost: each insert into ibl_box_scores_teams updates 19 B-trees today
-- (PRIMARY + 18 secondary). This adds a 20th, about 5% more index maintenance
-- per row. A season adds ~2,750 rows, so the index grows ~80 KB per season.
-- Building it on 54,414 rows is an online InnoDB ADD INDEX.
--
-- Measured locally (54,414 rows, ANALYZE FORMAT=JSON on the dedupe scan):
--   GM pair (Mike Kent vs Peter Kim): before key=idx_visitor_team (FORCE INDEX), rows=5942
--     r_rows=5942, median 7.2 ms, handler_reads 6995. After key=idx_visitor_home_gt_season,
--     rows=121 r_rows=116, median 4.0 ms, handler_reads 1178.
--   Franchise pair (5 vs 18): before index_merge, rows=136 r_rows=96, median 2.6 ms,
--     handler_reads 8641. After key=idx_visitor_home_gt_season, rows=98 r_rows=96,
--     median 1.7 ms, handler_reads 947.
--   New index size: 1,552 KB (idx_gt_date_teams is 2,576 KB). Results byte-identical.

-- Idempotent add (copy of migration 152's information_schema.STATISTICS guard).
SET @exists = (SELECT COUNT(1) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_box_scores_teams'
                 AND INDEX_NAME = 'idx_visitor_home_gt_season');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_box_scores_teams ADD INDEX idx_visitor_home_gt_season (visitor_teamid, home_teamid, game_type, season_year)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;

-- Same index on the Olympics twin, which carries the same index set. Keeps the
-- two index sets identical so a FORCE INDEX naming the new index never raises
-- error 1176 if the same SQL is pointed at the Olympics table.
SET @exists = (SELECT COUNT(1) FROM information_schema.STATISTICS
               WHERE TABLE_SCHEMA = DATABASE()
                 AND TABLE_NAME = 'ibl_olympics_box_scores_teams'
                 AND INDEX_NAME = 'idx_visitor_home_gt_season');
SET @sql = IF(@exists = 0,
    'ALTER TABLE ibl_olympics_box_scores_teams ADD INDEX idx_visitor_home_gt_season (visitor_teamid, home_teamid, game_type, season_year)',
    'SELECT 1');
PREPARE stmt FROM @sql;
EXECUTE stmt;
DEALLOCATE PREPARE stmt;
