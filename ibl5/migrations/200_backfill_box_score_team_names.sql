-- Backfill stale ibl_box_scores_teams.name values to the franchise name for that season.
--
-- Some phase-opener .sco files stored the wrong team name in a box-score team row
-- (a name that matches neither franchise in the game). The offense/defense stat views
-- join the two team rows of a game by name, so those games drop out of the views and
-- their `games` counts disagree with ibl_jsb_history.
--
-- Selection rule. A pair is a (game_date, visitor_teamid, home_teamid) triple with
-- exactly 2 rows, both team ids in 1..32 (all-star ids excluded), and an
-- ibl_franchise_seasons row for each team in that season_year. A pair is affected
-- when at least one row's name matches neither franchise name. Inside an affected
-- pair the lower id is the visitor row (visitor franchise name) and the higher id is
-- the home row (home franchise name). Only rows whose name differs from that target
-- are written. A pair whose two valid names are swapped, with no bad row, is NOT
-- touched; it is counted at the end (swapped_valid_pairs) for a human to look at.
--
-- Collision safety. uq_game_team covers name, and a sibling row can currently hold
-- the name its partner needs (e.g. visitor row stale, home row holding the visitor
-- name). The write therefore goes through a per-row placeholder ('~' + id) first,
-- then the target. The work table is TEMPORARY: bin/db-migrate pipes this file into
-- one mariadb session, so it lives exactly as long as the run.
--
-- Expected on the 2026-10-21 snapshot (plan-time census; not used by any WHERE):
--   bad rows: 138 (1989=45, 1993=4, 1995=4, 2000=52, 2002=1, 2003=2, 2005=4, 2007=26)
--   offense-view games vs ibl_jsb_history mismatches: 21 team-seasons
--   colliding pairs: at least 4 (the sibling already holds the target name)
--   franchise names longer than 16 chars: 0
--
-- Guards. Each guard runs SELECT ... INTO over a query that yields two rows when the
-- invariant is violated, which raises ERROR 1172 under every sql_mode and stops the
-- session before any write.
--
-- Rollback (never run here). In one transaction:
--   UPDATE ibl_box_scores_teams t, ibl_box_scores_teams_franchise_names_backup b
--      SET t.name = CONCAT('~', t.id) WHERE t.id = b.id;
--   UPDATE ibl_box_scores_teams t, ibl_box_scores_teams_franchise_names_backup b
--      SET t.name = b.name WHERE t.id = b.id;
-- Lossless: every changed row's full pre-state is in the backup, keyed by id.

CREATE TABLE IF NOT EXISTS ibl_box_scores_teams_franchise_names_backup LIKE ibl_box_scores_teams;

-- The generated columns are copied by LIKE with their AS (...) STORED clause, which an
-- INSERT cannot write. Store them as plain NULL-able columns (same shape as migration 170).
-- destructive-migration[narrow-type]: same types as the LIKE source; only the STORED GENERATED clause is dropped on a new empty backup table
ALTER TABLE ibl_box_scores_teams_franchise_names_backup
    MODIFY game_type     tinyint(3) unsigned NULL,
    MODIFY season_year   smallint(5) unsigned NULL,
    MODIFY calc_points   smallint(5) unsigned NULL,
    MODIFY calc_rebounds smallint(5) unsigned NULL,
    MODIFY calc_fg_made  smallint(5) unsigned NULL;

CREATE TEMPORARY TABLE tmp_bst_targets (
    id INT NOT NULL PRIMARY KEY,
    target_name VARCHAR(16) NOT NULL
);

INSERT INTO tmp_bst_targets (id, target_name)
SELECT r.id,
       CASE WHEN r.id = p.visitor_row_id THEN v.team_name ELSE h.team_name END
FROM (
    SELECT t.game_date, t.visitor_teamid, t.home_teamid, t.season_year,
           MIN(t.id) AS visitor_row_id
    FROM ibl_box_scores_teams t
    JOIN ibl_franchise_seasons v
      ON v.franchise_id = t.visitor_teamid AND v.season_ending_year = t.season_year
    JOIN ibl_franchise_seasons h
      ON h.franchise_id = t.home_teamid AND h.season_ending_year = t.season_year
    WHERE t.visitor_teamid BETWEEN 1 AND 32
      AND t.home_teamid BETWEEN 1 AND 32
    GROUP BY t.game_date, t.visitor_teamid, t.home_teamid, t.season_year
    HAVING COUNT(*) = 2
       AND SUM(t.name <> v.team_name AND t.name <> h.team_name) >= 1
) p
JOIN ibl_box_scores_teams r
  ON r.game_date = p.game_date
 AND r.visitor_teamid = p.visitor_teamid
 AND r.home_teamid = p.home_teamid
JOIN ibl_franchise_seasons v
  ON v.franchise_id = p.visitor_teamid AND v.season_ending_year = p.season_year
JOIN ibl_franchise_seasons h
  ON h.franchise_id = p.home_teamid AND h.season_ending_year = p.season_year
WHERE r.name <> CASE WHEN r.id = p.visitor_row_id THEN v.team_name ELSE h.team_name END;

-- G1: a target longer than the column (would truncate or error on write)
SELECT COUNT(*) INTO @too_long FROM tmp_bst_targets WHERE CHAR_LENGTH(target_name) > 16;
SELECT x INTO @abort_too_long FROM (SELECT 1 AS x FROM DUAL WHERE @too_long > 0 UNION ALL SELECT 2 FROM DUAL WHERE @too_long > 0) z;

-- G2: a pair whose two targets are the same string (two franchises, one name)
SELECT COUNT(*) INTO @same_name
FROM tmp_bst_targets a JOIN tmp_bst_targets b ON a.id < b.id
JOIN ibl_box_scores_teams ra ON ra.id = a.id
JOIN ibl_box_scores_teams rb ON rb.id = b.id
 AND rb.game_date = ra.game_date AND rb.visitor_teamid = ra.visitor_teamid AND rb.home_teamid = ra.home_teamid
WHERE a.target_name = b.target_name;
SELECT x INTO @abort_same_name FROM (SELECT 1 AS x FROM DUAL WHERE @same_name > 0 UNION ALL SELECT 2 FROM DUAL WHERE @same_name > 0) z;

-- G3: a leftover placeholder from an interrupted earlier run (never expected; see rollback)
SELECT COUNT(*) INTO @stale_ph FROM ibl_box_scores_teams WHERE name LIKE '~%' AND id NOT IN (SELECT id FROM tmp_bst_targets);
SELECT x INTO @abort_stale_ph FROM (SELECT 1 AS x FROM DUAL WHERE @stale_ph > 0 UNION ALL SELECT 2 FROM DUAL WHERE @stale_ph > 0) z;

START TRANSACTION;

INSERT IGNORE INTO ibl_box_scores_teams_franchise_names_backup
SELECT t.* FROM ibl_box_scores_teams t JOIN tmp_bst_targets x ON x.id = t.id;

-- Step one: a per-row placeholder no franchise name can equal, unique within a pair.
UPDATE ibl_box_scores_teams t, tmp_bst_targets x SET t.name = CONCAT('~', t.id) WHERE t.id = x.id;

-- Step two: the franchise name.
UPDATE ibl_box_scores_teams t, tmp_bst_targets x SET t.name = x.target_name WHERE t.id = x.id;

-- Post-conditions: no placeholder left, every work-table row holds its target.
SELECT COUNT(*) INTO @left_ph
FROM ibl_box_scores_teams t JOIN tmp_bst_targets x ON x.id = t.id
WHERE t.name LIKE '~%';
SELECT x INTO @abort_left_ph FROM (SELECT 1 AS x FROM DUAL WHERE @left_ph > 0 UNION ALL SELECT 2 FROM DUAL WHERE @left_ph > 0) z;

SELECT COUNT(*) INTO @still_bad
FROM ibl_box_scores_teams t JOIN tmp_bst_targets x ON x.id = t.id
WHERE t.name <> x.target_name;
SELECT x INTO @abort_still_bad FROM (SELECT 1 AS x FROM DUAL WHERE @still_bad > 0 UNION ALL SELECT 2 FROM DUAL WHERE @still_bad > 0) z;

COMMIT;

-- Informational only: pairs with two valid names on the wrong sides and no bad row.
SELECT COUNT(*) AS swapped_valid_pairs
FROM (
    SELECT MIN(t.id) AS visitor_row_id, v.team_name AS visitor_name
    FROM ibl_box_scores_teams t
    JOIN ibl_franchise_seasons v
      ON v.franchise_id = t.visitor_teamid AND v.season_ending_year = t.season_year
    JOIN ibl_franchise_seasons h
      ON h.franchise_id = t.home_teamid AND h.season_ending_year = t.season_year
    WHERE t.visitor_teamid BETWEEN 1 AND 32
      AND t.home_teamid BETWEEN 1 AND 32
    GROUP BY t.game_date, t.visitor_teamid, t.home_teamid, t.season_year, v.team_name
    HAVING COUNT(*) = 2
       AND SUM(t.name <> v.team_name AND t.name <> h.team_name) = 0
) p
JOIN ibl_box_scores_teams r ON r.id = p.visitor_row_id
WHERE r.name <> p.visitor_name;
