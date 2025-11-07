# Phase 7: Manual Verification Steps

## Overview

This document provides step-by-step manual verification procedures to ensure the Phase 7 naming convention standardization migration was successful and the application is functioning correctly.

**Prerequisites:**
- Migration `004_naming_convention_standardization.sql` has been executed
- Application code has been updated per `004_APPLICATION_CODE_UPDATES.md`
- Application has been restarted

---

## Pre-Migration Verification (Before Running Migration)

### 1. Database Backup Verification

**Purpose:** Ensure you can rollback if needed

```sql
-- Verify backup was created
-- Run this BEFORE migration
SELECT NOW() as backup_timestamp;

-- Document the backup file name and location
-- Example: ibl5_backup_20250107_010000.sql
```

**Checklist:**
- [ ] Full database backup created
- [ ] Backup file verified (can be opened/read)
- [ ] Backup file size is reasonable (not 0 bytes)
- [ ] Backup timestamp documented
- [ ] Backup stored in secure location

### 2. Current Schema Snapshot

**Purpose:** Document current state for comparison

```sql
-- Save list of current column names
SELECT TABLE_NAME, COLUMN_NAME, COLUMN_TYPE
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'ibl_schedule', 'ibl_box_scores', 'ibl_box_scores_teams',
    'ibl_plr', 'ibl_team_info', 'ibl_power', 'ibl_sim_dates',
    'ibl_team_awards', 'ibl_awards', 'ibl_gm_history',
    'ibl_plr_chunk', 'ibl_team_offense_stats', 'ibl_team_defense_stats',
    'ibl_trade_cash'
  )
ORDER BY TABLE_NAME, ORDINAL_POSITION;
```

**Checklist:**
- [ ] Current schema documented
- [ ] Count of columns per table recorded
- [ ] Current foreign keys documented
- [ ] Current indexes documented

### 3. Sample Data Snapshot

**Purpose:** Verify data integrity after migration

```sql
-- Take sample data from key tables (using OLD column names - BEFORE migration)
-- Document these values to compare after migration
SELECT * FROM ibl_schedule ORDER BY Date DESC LIMIT 5;
SELECT * FROM ibl_box_scores ORDER BY Date DESC LIMIT 5;
SELECT * FROM ibl_team_info LIMIT 3;
SELECT * FROM ibl_plr WHERE pid IN (1, 100, 200);

-- Note: Save the actual data values, not just the column names
-- After migration, the column names will change but data values should remain the same
```

**Checklist:**
- [ ] Sample data from each affected table captured
- [ ] Record counts for each table documented

---

## Post-Migration Database Verification

### 1. Column Rename Verification

**Purpose:** Confirm all columns were renamed correctly

```sql
-- Check that NEW column names exist
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND (
    (TABLE_NAME = 'ibl_schedule' AND COLUMN_NAME IN (
      'season_year', 'box_score_id', 'game_date', 'visitor_team_id',
      'visitor_score', 'home_team_id', 'home_score', 'schedule_id'
    ))
    OR (TABLE_NAME = 'ibl_box_scores' AND COLUMN_NAME IN (
      'game_date', 'home_team_id', 'visitor_team_id'
    ))
    OR (TABLE_NAME = 'ibl_team_info' AND COLUMN_NAME IN (
      'contract_wins', 'contract_losses', 'discord_id', 'has_mle', 'has_lle'
    ))
    OR (TABLE_NAME = 'ibl_plr' AND COLUMN_NAME IN (
      'clutch', 'consistency', 'pg_depth', 'sg_depth', 'sf_depth', 'pf_depth', 'c_depth'
    ))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
```

**Expected Result:** 46 rows (all new column names should be present)

**Checklist:**
- [ ] All 46 renamed columns present in schema
- [ ] Column data types preserved
- [ ] Column constraints preserved (NOT NULL, DEFAULT, etc.)

```sql
-- Check that OLD column names no longer exist
SELECT TABLE_NAME, COLUMN_NAME
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME LIKE 'ibl_%'
  AND COLUMN_NAME IN (
    'Year', 'BoxID', 'Date', 'Visitor', 'VScore', 'Home', 'HScore', 'SchedID',
    'homeTID', 'visitorTID', 'homeTeamID', 'visitorTeamID',
    'Clutch', 'Consistency', 'PGDepth', 'SGDepth', 'SFDepth', 'PFDepth', 'CDepth',
    'Contract_Wins', 'Contract_Losses', 'discordID', 'HasMLE', 'HasLLE',
    'TeamID', 'Team', 'Conference', 'Division', 'Award', 'ID', 'Season',
    'Sim', 'Start Date', 'End Date', 'teamID', 'tradeOfferID'
  );
```

**Expected Result:** 0 rows (no old column names should exist)

**Checklist:**
- [ ] No old column names found
- [ ] All affected tables verified

### 2. Foreign Key Verification

**Purpose:** Confirm foreign keys were recreated with new column names

```sql
-- Check foreign keys on renamed columns
SELECT 
  TABLE_NAME,
  CONSTRAINT_NAME,
  COLUMN_NAME,
  REFERENCED_TABLE_NAME,
  REFERENCED_COLUMN_NAME
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND (
    TABLE_NAME IN ('ibl_schedule', 'ibl_box_scores', 'ibl_box_scores_teams', 
                   'ibl_power', 'ibl_team_offense_stats', 'ibl_team_defense_stats')
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
```

**Expected Results:**
- `ibl_schedule.home_team_id` → `ibl_team_info.teamid`
- `ibl_schedule.visitor_team_id` → `ibl_team_info.teamid`
- `ibl_box_scores.home_team_id` → `ibl_team_info.teamid`
- `ibl_box_scores.visitor_team_id` → `ibl_team_info.teamid`
- `ibl_box_scores_teams.home_team_id` → `ibl_team_info.teamid`
- `ibl_box_scores_teams.visitor_team_id` → `ibl_team_info.teamid`
- `ibl_power.team_name` → `ibl_team_info.team_name`
- `ibl_team_offense_stats.team_id` → `ibl_team_info.teamid`
- `ibl_team_defense_stats.team_id` → `ibl_team_info.teamid`

**Checklist:**
- [ ] All 9 foreign keys recreated successfully
- [ ] Foreign keys reference correct columns
- [ ] CASCADE actions preserved

### 3. Index Verification

**Purpose:** Confirm indexes were recreated with new column names

```sql
-- Check indexes on renamed columns
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.STATISTICS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN ('ibl_schedule', 'ibl_box_scores', 'ibl_box_scores_teams',
                     'ibl_power', 'ibl_team_offense_stats', 'ibl_team_defense_stats')
  AND COLUMN_NAME IN (
    'season_year', 'box_score_id', 'game_date', 'visitor_team_id', 
    'visitor_score', 'home_team_id', 'home_score', 'schedule_id',
    'team_id', 'team_name'
  )
ORDER BY TABLE_NAME, INDEX_NAME, SEQ_IN_INDEX;
```

**Expected Indexes for ibl_schedule:**
- `idx_box_score_id` on `box_score_id`
- `idx_season_year` on `season_year`
- `idx_game_date` on `game_date`
- `idx_visitor_team_id` on `visitor_team_id`
- `idx_home_team_id` on `home_team_id`
- `idx_season_year_game_date` on `season_year`, `game_date`

**Checklist:**
- [ ] All renamed indexes present
- [ ] Composite indexes maintained correctly
- [ ] No old index names remain

### 4. Database View Verification

**Purpose:** Confirm views were recreated correctly

```sql
-- Check that vw_schedule_upcoming exists and uses new column names
SHOW CREATE VIEW vw_schedule_upcoming;
```

**Expected:** View definition should reference:
- `sch.schedule_id`
- `sch.season_year`
- `sch.game_date`
- `sch.visitor_team_id`
- `sch.home_team_id`
- `sch.visitor_score`
- `sch.home_score`

```sql
-- Test the view returns data
SELECT * FROM vw_schedule_upcoming LIMIT 5;
```

**Checklist:**
- [ ] View exists and can be queried
- [ ] View returns expected columns
- [ ] View returns valid data
- [ ] No errors when querying view

### 5. Data Integrity Verification

**Purpose:** Confirm no data was lost during migration

```sql
-- Verify record counts unchanged
SELECT COUNT(*) as schedule_count FROM ibl_schedule;
SELECT COUNT(*) as boxscore_count FROM ibl_box_scores;
SELECT COUNT(*) as team_count FROM ibl_team_info;
SELECT COUNT(*) as player_count FROM ibl_plr;

-- Compare with pre-migration counts
```

**Checklist:**
- [ ] Record counts match pre-migration values
- [ ] Sample data matches pre-migration snapshot
- [ ] No NULL values in previously populated columns

```sql
-- Verify sample data integrity (using NEW column names post-migration)
SELECT schedule_id, season_year, game_date, home_team_id, visitor_team_id, 
       home_score, visitor_score
FROM ibl_schedule 
ORDER BY game_date DESC 
LIMIT 5;

-- Compare with pre-migration sample data (which used old column names:
-- Date, Home, Visitor, HScore, VScore)
-- Verify the DATA matches even though column names changed
```

---

## Application Functionality Verification

### 1. Schedule Pages

#### Test: View Season Schedule

**Steps:**
1. Navigate to main schedule page
2. Select current season
3. Verify games are displayed

**Expected Results:**
- [ ] Schedule page loads without errors
- [ ] Game dates display correctly
- [ ] Team names display correctly
- [ ] Scores display correctly (for completed games)
- [ ] No PHP errors in error log

**Common Issues:**
- Undefined index errors for old column names
- Dates not displaying (using old `Date` column)
- Scores showing as blank (using old `HScore`/`VScore`)

#### Test: View Team Schedule

**Steps:**
1. Navigate to a team page
2. View team's schedule
3. Verify games are listed correctly

**Expected Results:**
- [ ] Team schedule loads without errors
- [ ] Home games identified correctly
- [ ] Away games identified correctly
- [ ] Game results accurate

### 2. Box Score Pages

#### Test: View Game Box Score

**Steps:**
1. Navigate to a completed game's box score
2. Verify all game details display

**Expected Results:**
- [ ] Box score page loads without errors
- [ ] Game date displays correctly
- [ ] Home team identified correctly
- [ ] Visitor team identified correctly
- [ ] Player statistics display correctly
- [ ] Team totals calculate correctly

**Common Issues:**
- Game date not showing (using old `Date` column)
- Teams misidentified (using old `homeTID`/`visitorTID`)
- Team stats not loading (using old `homeTeamID`/`visitorTeamID`)

### 3. Team Management Pages

#### Test: View Team Info

**Steps:**
1. Navigate to team management/info page
2. Verify all team details display

**Expected Results:**
- [ ] Team info page loads without errors
- [ ] Contract record displays (`contract_wins`, `contract_losses`)
- [ ] Discord ID displays if present (`discord_id`)
- [ ] MLE/LLE flags display correctly (`has_mle`, `has_lle`)
- [ ] Contract averages display (`contract_avg_wins`, `contract_avg_losses`)

**Common Issues:**
- Contract info blank (using old `Contract_*` columns)
- Discord integration broken (using old `discordID`)
- MLE/LLE toggles not working (using old `HasMLE`/`HasLLE`)

#### Test: Update Team Information

**Steps:**
1. Navigate to team edit page
2. Update contract information
3. Save changes
4. Verify changes persisted

**Expected Results:**
- [ ] Form loads with current values
- [ ] Values can be updated
- [ ] Changes save successfully
- [ ] Updated values display correctly

### 4. Player Pages

#### Test: View Player Profile

**Steps:**
1. Navigate to a player's profile page
2. Verify all player details display

**Expected Results:**
- [ ] Player profile loads without errors
- [ ] Clutch rating displays (`clutch`)
- [ ] Consistency rating displays (`consistency`)
- [ ] Depth chart positions display correctly:
  - [ ] `pg_depth`
  - [ ] `sg_depth`
  - [ ] `sf_depth`
  - [ ] `pf_depth`
  - [ ] `c_depth`

**Common Issues:**
- Attributes not displaying (using old `Clutch`/`Consistency`)
- Depth positions blank (using old `PGDepth`, etc.)

### 5. Awards Pages

#### Test: View Awards List

**Steps:**
1. Navigate to awards listing page
2. Verify awards display correctly

**Expected Results:**
- [ ] Awards page loads without errors
- [ ] Award names display (`award_name` not `Award`)
- [ ] Team awards display correctly
- [ ] GM history awards display correctly

### 6. Simulation Management

#### Test: View Simulation Schedule

**Steps:**
1. Navigate to simulation management page
2. View simulation dates

**Expected Results:**
- [ ] Simulation dates load without errors
- [ ] Sim numbers display (`sim_number` not `Sim`)
- [ ] Start dates display (`start_date` not `Start Date`)
- [ ] End dates display (`end_date` not `End Date`)

### 7. Power Rankings

#### Test: View Power Rankings

**Steps:**
1. Navigate to power rankings page
2. Verify rankings display

**Expected Results:**
- [ ] Power rankings load without errors
- [ ] Team names display (`team_name` not `Team`)
- [ ] Team IDs reference correctly (`team_id` not `TeamID`)
- [ ] Conference groupings work (`conference`)
- [ ] Division groupings work (`division`)

---

## Database Query Performance Verification

### 1. Index Usage Verification

**Purpose:** Ensure queries are using new indexes

```sql
-- Enable query profiling
SET profiling = 1;

-- Test schedule queries
EXPLAIN SELECT * FROM ibl_schedule 
WHERE season_year = 2024 AND game_date = '2024-01-15';

EXPLAIN SELECT * FROM ibl_schedule 
WHERE home_team_id = 1 AND season_year = 2024;

-- Check index usage
SHOW PROFILE FOR QUERY 1;
SHOW PROFILE FOR QUERY 2;
```

**Expected Results:**
- Queries should use indexes (`key` column shows index name)
- `type` should be `ref` or `range`, not `ALL`
- Execution time should be minimal (< 0.01s for small datasets)

**Checklist:**
- [ ] Schedule queries use `idx_season_year_game_date`
- [ ] Team queries use `idx_home_team_id` or `idx_visitor_team_id`
- [ ] No full table scans on large tables

### 2. View Performance Verification

```sql
-- Test view performance
EXPLAIN SELECT * FROM vw_schedule_upcoming 
WHERE season_year = 2024 
LIMIT 10;

-- Compare with direct table query
EXPLAIN SELECT s.*, t1.team_name, t2.team_name
FROM ibl_schedule s
JOIN ibl_team_info t1 ON s.home_team_id = t1.teamid
JOIN ibl_team_info t2 ON s.visitor_team_id = t2.teamid
WHERE s.season_year = 2024
LIMIT 10;
```

**Checklist:**
- [ ] View performance is acceptable
- [ ] View uses indexes effectively
- [ ] No significant performance degradation

---

## Error Log Verification

### 1. PHP Error Log

**Check for:**
- Undefined index errors (indicates missed column name update)
- Database query errors
- Warning messages about missing columns

```bash
# Check recent errors
tail -100 /var/log/php/error.log | grep -i "undefined index\|mysql\|column"

# Or application-specific log location
tail -100 /home/runner/work/IBL5/IBL5/ibl5/logs/error.log
```

**Checklist:**
- [ ] No undefined index errors for old column names
- [ ] No SQL errors about missing columns
- [ ] No new warnings since migration

### 2. MySQL Error Log

```bash
# Check MySQL error log
tail -100 /var/log/mysql/error.log
```

**Checklist:**
- [ ] No constraint violation errors
- [ ] No foreign key errors
- [ ] No index errors

---

## Integration Test Checklist

### Critical Path Testing

Complete the following user journeys:

#### Journey 1: View Schedule and Game Results
- [ ] Navigate to main page
- [ ] Click on "Schedule" or "Games"
- [ ] View current season schedule
- [ ] Click on a completed game
- [ ] View box score
- [ ] Navigate back to schedule
- [ ] Filter by team
- [ ] Filter by date range

#### Journey 2: Team Management
- [ ] Navigate to team list
- [ ] Click on a team
- [ ] View team information
- [ ] View team roster
- [ ] View team schedule
- [ ] View team statistics
- [ ] Edit team information (if authorized)

#### Journey 3: Player Management
- [ ] Navigate to player list
- [ ] Click on a player
- [ ] View player profile
- [ ] View player statistics
- [ ] View player game log
- [ ] Check depth chart positions
- [ ] Check player attributes

#### Journey 4: Awards and Recognition
- [ ] Navigate to awards page
- [ ] View season awards
- [ ] View team awards
- [ ] View GM history

---

## Performance Benchmarking

### Before/After Comparison

If you documented query times before migration, compare them:

| Query Type | Before (ms) | After (ms) | Change |
|------------|-------------|------------|--------|
| Schedule by date | _____ | _____ | _____ |
| Schedule by team | _____ | _____ | _____ |
| Box score load | _____ | _____ | _____ |
| Team info load | _____ | _____ | _____ |
| Player profile | _____ | _____ | _____ |

**Expected:** Performance should be equal or better (indexes should be equivalent)

**Checklist:**
- [ ] No queries show significant slowdown (> 20% increase)
- [ ] Most queries show similar performance
- [ ] No new slow query log entries

---

## Final Sign-Off Checklist

### Database Verification
- [ ] All 46 columns renamed successfully
- [ ] All foreign keys recreated and working
- [ ] All indexes recreated and being used
- [ ] Database views updated and functional
- [ ] No data loss (record counts match)
- [ ] Sample data integrity verified

### Application Verification
- [ ] Schedule pages work correctly
- [ ] Box score pages work correctly
- [ ] Team pages work correctly
- [ ] Player pages work correctly
- [ ] Awards pages work correctly
- [ ] Simulation pages work correctly
- [ ] Power rankings work correctly
- [ ] No PHP errors in logs
- [ ] No MySQL errors in logs

### Performance Verification
- [ ] Indexes being used effectively
- [ ] No performance degradation
- [ ] View queries performant

### Code Verification
- [ ] All repository classes updated
- [ ] All view files updated
- [ ] All JavaScript/AJAX updated
- [ ] No references to old column names in code

### Documentation
- [ ] Migration execution documented
- [ ] Issues encountered documented
- [ ] Rollback procedure tested (in dev/staging)
- [ ] Team notified of changes

---

## Rollback Decision Matrix

If issues are found, use this matrix to decide on rollback:

| Issue Severity | Action | Timeframe |
|----------------|--------|-----------|
| Critical - Site down | Immediate rollback | < 15 minutes |
| High - Major feature broken | Rollback if can't fix in 1 hour | 1 hour |
| Medium - Minor feature broken | Fix forward, rollback if fix fails | 4 hours |
| Low - Cosmetic issues | Fix forward | Next release |

**Critical Issues (Immediate Rollback):**
- Database queries failing across the board
- Cannot load any pages
- Data corruption detected
- Foreign key constraint violations preventing writes

**High Issues (Rollback if no quick fix):**
- Schedule not displaying
- Box scores not loading
- Cannot create/edit games
- Cannot update team information

**Medium Issues (Fix Forward):**
- Some attributes not displaying
- Minor display issues
- Non-critical features affected

**Low Issues (Fix in Next Release):**
- Cosmetic display issues
- Minor text formatting
- Non-user-facing issues

---

## Post-Verification Actions

### If Successful
1. Document completion time and any issues encountered
2. Monitor application for 24-48 hours
3. Keep backup for at least 30 days
4. Update team on successful migration
5. Plan next phase (if any)

### If Issues Found
1. Classify issue severity using matrix above
2. Attempt quick fix if Medium or Low severity
3. Execute rollback if Critical or High severity
4. Document all issues for review
5. Plan remediation strategy
6. Re-schedule migration after fixes

---

## Contact and Escalation

**For Issues During Verification:**
1. Stop verification process
2. Document exact steps that caused issue
3. Capture error messages (screenshots/logs)
4. Assess severity using decision matrix
5. Execute rollback if necessary
6. Contact development team lead

**Information to Gather:**
- Exact error message
- Steps to reproduce
- Affected pages/features
- Browser/environment details
- Any relevant log entries
- Time issue was first noticed

---

## Appendix: Quick Reference SQL

### Revert Single Column (Emergency)

```sql
-- Example: Revert ibl_schedule.game_date to Date
-- WARNING: Only use in emergency, will break application code updates
ALTER TABLE ibl_schedule
  CHANGE COLUMN game_date `Date` DATE NOT NULL;

-- Must also revert indexes and foreign keys
ALTER TABLE ibl_schedule DROP INDEX idx_game_date;
ALTER TABLE ibl_schedule ADD INDEX idx_date (`Date`);
```

### Check Current Schema State

```sql
-- Quick check of all affected tables
SELECT 
  TABLE_NAME,
  COUNT(*) as column_count,
  GROUP_CONCAT(COLUMN_NAME ORDER BY ORDINAL_POSITION) as columns
FROM INFORMATION_SCHEMA.COLUMNS
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME IN (
    'ibl_schedule', 'ibl_box_scores', 'ibl_team_info', 'ibl_plr'
  )
GROUP BY TABLE_NAME
ORDER BY TABLE_NAME;
```

### Verify All Foreign Keys Intact

```sql
-- Quick foreign key check
SELECT COUNT(*) as fk_count
FROM information_schema.KEY_COLUMN_USAGE
WHERE TABLE_SCHEMA = DATABASE()
  AND REFERENCED_TABLE_NAME IS NOT NULL
  AND TABLE_NAME IN (
    'ibl_schedule', 'ibl_box_scores', 'ibl_box_scores_teams',
    'ibl_power', 'ibl_team_offense_stats', 'ibl_team_defense_stats'
  );
-- Should return 9
```

---

**Remember:** Verification is not complete until all sections are checked and signed off. Document any deviations or issues discovered.
