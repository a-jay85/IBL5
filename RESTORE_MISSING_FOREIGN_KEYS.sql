-- ============================================================================
-- SQL Queries to Re-establish Missing Foreign Key Constraints
-- ============================================================================
-- These foreign keys were defined in Phase 2 (002_add_foreign_keys.sql) but
-- are missing from the current schema.sql file. They need to be re-established.
--
-- IMPORTANT: Before running these queries, verify data integrity by running
-- the verification queries at the end of this file.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Foreign Key 1: Player to Team Relationship (fk_plr_team)
-- ---------------------------------------------------------------------------
-- Ensures that every player's team ID (tid) references a valid team in ibl_team_info
-- Note: tid = 0 means free agent, which may not exist in ibl_team_info
-- We use RESTRICT to prevent accidental team deletions that would affect players

ALTER TABLE ibl_plr 
  ADD CONSTRAINT fk_plr_team 
  FOREIGN KEY (tid) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- Foreign Key 2: Schedule Visitor Team (fk_schedule_visitor)
-- ---------------------------------------------------------------------------
-- Ensures that the visiting team ID in the schedule references a valid team

ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_visitor
  FOREIGN KEY (Visitor) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ---------------------------------------------------------------------------
-- Foreign Key 3: Schedule Home Team (fk_schedule_home)
-- ---------------------------------------------------------------------------
-- Ensures that the home team ID in the schedule references a valid team

ALTER TABLE ibl_schedule
  ADD CONSTRAINT fk_schedule_home
  FOREIGN KEY (Home) REFERENCES ibl_team_info(teamid)
  ON DELETE RESTRICT 
  ON UPDATE CASCADE;

-- ============================================================================
-- VERIFICATION QUERIES
-- ============================================================================
-- Run these queries BEFORE attempting to add the foreign keys to identify
-- any data integrity issues that need to be resolved first.
-- ============================================================================

-- ---------------------------------------------------------------------------
-- Verify Player Team References
-- ---------------------------------------------------------------------------
-- Check for players with invalid team IDs (excluding free agents with tid=0)
SELECT 'Checking ibl_plr for invalid team IDs...' AS verification_step;
SELECT p.pid, p.name, p.tid 
FROM ibl_plr p 
LEFT JOIN ibl_team_info t ON p.tid = t.teamid 
WHERE p.tid != 0 AND t.teamid IS NULL;
-- Expected result: 0 rows (no orphaned player records)

-- If rows are returned, fix them with one of these approaches:
-- Option 1: Set invalid team IDs to 0 (free agent)
-- UPDATE ibl_plr SET tid = 0 WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;
-- 
-- Option 2: Delete orphaned player records (only if safe to do so)
-- DELETE FROM ibl_plr WHERE tid NOT IN (SELECT teamid FROM ibl_team_info) AND tid != 0;

-- ---------------------------------------------------------------------------
-- Verify Schedule Visitor Team References
-- ---------------------------------------------------------------------------
-- Check for schedule entries with invalid visitor team IDs
SELECT 'Checking ibl_schedule for invalid visitor team IDs...' AS verification_step;
SELECT s.SchedID, s.Year, s.Visitor, s.Home 
FROM ibl_schedule s 
LEFT JOIN ibl_team_info tv ON s.Visitor = tv.teamid 
WHERE tv.teamid IS NULL;
-- Expected result: 0 rows (no orphaned visitor references)

-- If rows are returned, fix them:
-- Option 1: Delete orphaned schedule entries
-- DELETE FROM ibl_schedule WHERE Visitor NOT IN (SELECT teamid FROM ibl_team_info);
--
-- Option 2: Update to valid team IDs (requires manual review)
-- UPDATE ibl_schedule SET Visitor = <valid_teamid> WHERE Visitor = <invalid_teamid>;

-- ---------------------------------------------------------------------------
-- Verify Schedule Home Team References
-- ---------------------------------------------------------------------------
-- Check for schedule entries with invalid home team IDs
SELECT 'Checking ibl_schedule for invalid home team IDs...' AS verification_step;
SELECT s.SchedID, s.Year, s.Visitor, s.Home 
FROM ibl_schedule s 
LEFT JOIN ibl_team_info th ON s.Home = th.teamid 
WHERE th.teamid IS NULL;
-- Expected result: 0 rows (no orphaned home references)

-- If rows are returned, fix them:
-- Option 1: Delete orphaned schedule entries
-- DELETE FROM ibl_schedule WHERE Home NOT IN (SELECT teamid FROM ibl_team_info);
--
-- Option 2: Update to valid team IDs (requires manual review)
-- UPDATE ibl_schedule SET Home = <valid_teamid> WHERE Home = <invalid_teamid>;

-- ---------------------------------------------------------------------------
-- Verify Team ID 0 Exists (for free agents)
-- ---------------------------------------------------------------------------
-- Check if there's a record in ibl_team_info for teamid=0 (free agents)
SELECT 'Checking if teamid=0 exists for free agents...' AS verification_step;
SELECT * FROM ibl_team_info WHERE teamid = 0;
-- Expected result: 1 row with teamid=0

-- If no row is returned, you have two options:
-- Option 1: Create a free agent "team" entry
-- INSERT INTO ibl_team_info (teamid, team_name, team_city) 
-- VALUES (0, 'Free Agents', 'Free Agency');
--
-- Option 2: Modify the fk_plr_team constraint to allow tid=0 without FK check
-- This would require a CHECK constraint instead: CHECK (tid = 0 OR tid IN (SELECT teamid FROM ibl_team_info))
-- However, MySQL doesn't support subqueries in CHECK constraints, so Option 1 is recommended.

-- ============================================================================
-- POST-ADDITION VERIFICATION
-- ============================================================================
-- Run these queries AFTER adding the foreign keys to confirm they exist

SELECT 'Verifying foreign keys were added...' AS verification_step;
SELECT 
  CONSTRAINT_NAME,
  TABLE_NAME,
  REFERENCED_TABLE_NAME,
  DELETE_RULE,
  UPDATE_RULE
FROM information_schema.REFERENTIAL_CONSTRAINTS
WHERE CONSTRAINT_SCHEMA = DATABASE()
  AND CONSTRAINT_NAME IN ('fk_plr_team', 'fk_schedule_visitor', 'fk_schedule_home')
ORDER BY CONSTRAINT_NAME;
-- Expected result: 3 rows showing the newly added foreign keys

-- ============================================================================
-- ROLLBACK PROCEDURES
-- ============================================================================
-- If you need to remove these foreign keys, run:
/*
ALTER TABLE ibl_plr DROP FOREIGN KEY fk_plr_team;
ALTER TABLE ibl_schedule DROP FOREIGN KEY fk_schedule_visitor;
ALTER TABLE ibl_schedule DROP FOREIGN KEY fk_schedule_home;
*/

-- ============================================================================
-- NOTES
-- ============================================================================
-- 1. These foreign keys enforce referential integrity at the database level
-- 2. ON DELETE RESTRICT prevents deletion of teams that have associated records
-- 3. ON UPDATE CASCADE automatically updates related records when team IDs change
-- 4. The tid field in ibl_plr was changed to SMALLINT UNSIGNED in Phase 4
--    but still references ibl_team_info.teamid which is INT
--    MySQL allows this as long as the data ranges are compatible
-- ============================================================================
