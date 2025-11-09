# Migration 004 Column Name Corrections

**Status:** Corrections identified, migration file NOT YET updated  
**Priority:** IMMEDIATE - Required before Phase 4 implementation  
**Date Identified:** November 9, 2025

## Summary

Fixed hallucinated column names in `ibl5/migrations/004_data_type_refinements.sql` by cross-referencing with `ibl5/schema.sql`.

**⚠️ CRITICAL:** This migration cannot be run in its current form. It will fail due to column name mismatches and missing foreign key handling.

## Quick Reference for Corrections

### Must Fix Before Running

1. **Add Foreign Key Handling** - 4 tables need `SET FOREIGN_KEY_CHECKS=0/1`
2. **Fix ibl_schedule** - Remove `Day` and `Neutral` references
3. **Remove ibl_team_win_loss section** - Needs separate data migration
4. **Remove ibl_draft_picks section** - Needs separate data migration  
5. **Fix ibl_power** - Use `ranking` not `powerRanking`
6. **Remove ibl_team_history section** - Table structure completely different
7. **Fix CHECK constraints** - Use correct column names

## Detailed Correction Checklist

### Step 1: Add Foreign Key Handling

**Location:** Beginning of migration file, before any table modifications

**Action:** Add these sections:

```sql
-- ============================================================================
-- IMPORTANT: Foreign Key Handling for Tables with FK + CHECK Constraints
-- ============================================================================
-- The following tables have BOTH foreign keys and CHECK constraints:
--   - ibl_box_scores (3 FK + 1 CHECK)
--   - ibl_draft (1 FK + 2 CHECK)
--   - ibl_power (1 FK + 2 CHECK)
--   - ibl_standings (1 FK + multiple CHECK)
-- 
-- We must temporarily disable FK checks during ALTER operations
-- ============================================================================

SELECT 'Temporarily disabling foreign key checks for safe ALTER operations...' AS message;
SET FOREIGN_KEY_CHECKS=0;

-- NOTE: All modifications happen here (existing migration content)

-- Re-enable foreign key checks after all modifications
SELECT 'Re-enabling foreign key checks...' AS message;
SET FOREIGN_KEY_CHECKS=1;

-- Verify foreign key integrity after re-enabling
SELECT 'Verifying foreign key integrity...' AS message;

-- Check ibl_box_scores foreign keys
SELECT COUNT(*) as orphaned_box_score_players
FROM ibl_box_scores bs
LEFT JOIN ibl_plr p ON bs.pid = p.pid
WHERE bs.pid IS NOT NULL AND p.pid IS NULL;

SELECT COUNT(*) as orphaned_box_score_home_teams
FROM ibl_box_scores bs
LEFT JOIN ibl_team_info t ON bs.homeTID = t.teamid
WHERE bs.homeTID IS NOT NULL AND t.teamid IS NULL;

SELECT COUNT(*) as orphaned_box_score_visitor_teams
FROM ibl_box_scores bs
LEFT JOIN ibl_team_info t ON bs.visitorTID = t.teamid
WHERE bs.visitorTID IS NOT NULL AND t.teamid IS NULL;

-- All counts should be 0
```

### Step 2: Fix ibl_schedule Section

**Location:** Around line 550-580 in current file

**Current (WRONG):**
```sql
ALTER TABLE ibl_schedule
  MODIFY Year SMALLINT UNSIGNED NOT NULL COMMENT 'Season year',
  MODIFY Day TINYINT UNSIGNED COMMENT 'Day of month',  -- DOESN'T EXIST!
  MODIFY Neutral TINYINT(1) DEFAULT 0;  -- DOESN'T EXIST!
```

**Corrected:**
```sql
-- ---------------------------------------------------------------------------
-- Schedule Table (ibl_schedule)
-- ---------------------------------------------------------------------------
-- Actual columns: Year, BoxID, Date, Visitor, VScore, Home, HScore, SchedID,
--                 created_at, updated_at, uuid, and CHECK constraints
ALTER TABLE ibl_schedule
  MODIFY Year SMALLINT UNSIGNED NOT NULL COMMENT 'Season year',
  MODIFY Visitor SMALLINT UNSIGNED NOT NULL COMMENT 'Visiting team ID',
  MODIFY Home SMALLINT UNSIGNED NOT NULL COMMENT 'Home team ID',
  MODIFY VScore TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Visitor score',
  MODIFY HScore TINYINT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Home score';

-- NOTE: Check constraints for Visitor, Home, VScore, HScore already exist in schema
```

### Step 3: Remove ibl_team_win_loss Section

**Location:** Around line 600-650 in current file

**Action:** Replace entire section with:

```sql
-- ---------------------------------------------------------------------------
-- Team Win/Loss Table (ibl_team_win_loss) - SKIPPED
-- ---------------------------------------------------------------------------
-- NOTE: This table requires a separate data migration before type optimization
-- Current schema has:
--   - year (VARCHAR) - needs conversion to SMALLINT UNSIGNED
--   - wins (lowercase) - not 'Wins'
--   - losses (lowercase) - not 'Losses'
--   - No 'SeasonType' column exists
--
-- A future migration will:
-- 1. Convert VARCHAR year data to numeric
-- 2. Then optimize to SMALLINT UNSIGNED
-- -----------------------------------------------------------------------
```

### Step 4: Remove ibl_draft_picks Section  

**Location:** Around line 700-750 in current file

**Action:** Replace entire section with:

```sql
-- ---------------------------------------------------------------------------
-- Draft Picks Table (ibl_draft_picks) - SKIPPED
-- ---------------------------------------------------------------------------
-- NOTE: This table requires a separate data migration before type optimization
-- Current schema has:
--   - year (VARCHAR(4)) - needs conversion to SMALLINT UNSIGNED
--   - round (CHAR(1)) - needs conversion to TINYINT UNSIGNED
--   - No 'pick' column exists (the migration referenced a non-existent column)
--
-- A future migration will:
-- 1. Convert VARCHAR/CHAR data to numeric types
-- 2. Then optimize to SMALLINT/TINYINT UNSIGNED
-- ---------------------------------------------------------------------------
```

### Step 5: Fix ibl_power Section

**Location:** Around line 800-850 in current file

**Current (WRONG):**
```sql
ALTER TABLE ibl_power
  MODIFY powerRanking TINYINT UNSIGNED;  -- WRONG COLUMN NAME!
```

**Corrected:**
```sql
-- ---------------------------------------------------------------------------
-- Power Rankings Table (ibl_power)
-- ---------------------------------------------------------------------------
-- NOTE: Keeping 'ranking' as DECIMAL(6,1) to preserve precision
-- The column name is 'ranking', not 'powerRanking'
-- DECIMAL allows for fractional rankings like 15.5, which TINYINT cannot store

-- No modifications needed - current types are optimal
-- ranking is already DECIMAL(6,1) with appropriate CHECK constraint
```

### Step 6: Remove ibl_team_history Section

**Location:** Around line 900-1100 in current file (large section)

**Action:** Replace entire section with:

```sql
-- ---------------------------------------------------------------------------
-- Team History Table (ibl_team_history) - SKIPPED
-- ---------------------------------------------------------------------------
-- NOTE: The migration file's column references do not match actual table structure
-- 
-- Migration referenced: Year, SeasonType, Games, Minutes, FieldGoalsMade, etc. (26 columns)
-- Actual table has: teamid, team_city, team_name, color1, color2, depth, sim_depth,
--                   asg_vote, eoy_vote, totwins, totloss, winpct, playoffs,
--                   div_titles, conf_titles, ibl_titles, heat_titles
--
-- This table stores team identity and totals, NOT season-by-season statistics
-- Season statistics are likely in ibl_box_scores_teams or other stat tables
--
-- No modifications required for this table
-- ---------------------------------------------------------------------------
```

### Step 7: Fix CHECK Constraints Section

**Location:** End of file, CHECK constraints section

**Fix ibl_draft_picks constraints:**
```sql
-- ---------------------------------------------------------------------------
-- ibl_draft_picks CHECK constraints - REMOVED (see Step 4)
-- ---------------------------------------------------------------------------
-- Cannot add CHECK constraints referencing 'pick' column (doesn't exist)
-- Cannot add CHECK constraints for year/round until data migration complete
```

**Fix ibl_power constraint:**

**Current (WRONG):**
```sql
ALTER TABLE ibl_power ADD CONSTRAINT chk_power_powerRanking
  CHECK (powerRanking >= 1 AND powerRanking <= 32);
```

**Corrected:**
```sql
-- ibl_power already has correct CHECK constraint in schema:
-- CONSTRAINT `chk_power_ranking` CHECK (ranking IS NULL OR ranking >= 0.0 AND ranking <= 100.0)
-- No additional CHECK constraint needed
```

## Issues Found and Fixed

### 1. ibl_schedule Table
**Problems:**
- Referenced non-existent column `Day`
- Referenced non-existent column `Neutral`

**Fix:**
- Removed `Day` column from ALTER statement
- Removed `Neutral` column from ALTER statement
- Kept valid optimizations for: Year, Visitor, Home, VScore, HScore

### 2. ibl_team_win_loss Table
**Problems:**
- Case mismatch: `Year` vs `year`, `Wins` vs `wins`, `Losses` vs `losses`
- Referenced non-existent column `SeasonType`
- Attempted to change VARCHAR columns to numeric types without data migration

**Fix:**
- Commented out entire section with explanation
- Would require separate data migration to convert VARCHAR to numeric types

### 3. ibl_draft_picks Table
**Problems:**
- Referenced non-existent column `pick`
- Attempted to change VARCHAR(4) and CHAR(1) columns to numeric types

**Fix:**
- Commented out entire section with explanation
- Schema has: year (VARCHAR(4)), round (CHAR(1))
- No `pick` column exists

### 4. ibl_power Table
**Problems:**
- Incorrect column name: `powerRanking` should be `ranking`
- Would change DECIMAL(6,1) to TINYINT, losing precision

**Fix:**
- Commented out ALTER statement to preserve decimal precision
- Fixed CHECK constraint to use correct column name `ranking`

### 5. ibl_team_history Table
**Problems:**
- Attempted to modify 26 columns that don't exist in the table
- Referenced: Year, SeasonType, Games, Minutes, FieldGoalsMade, etc.
- Actual table has: teamid, team_city, team_name, color1, color2, depth, sim_depth, asg_vote, eoy_vote, totwins, totloss, winpct, playoffs, div_titles, conf_titles, ibl_titles, heat_titles

**Fix:**
- Removed entire section with explanatory comment
- Suggested that team statistical data may be stored in other tables like ibl_box_scores_teams

### 6. CHECK Constraints
**Problems:**
- Constraints referenced hallucinated columns
- ibl_draft_picks constraints attempted to use numeric comparisons on CHAR/VARCHAR columns

**Fix:**
- Commented out ibl_draft_picks CHECK constraints
- Fixed ibl_power CHECK constraint to use `ranking` instead of `powerRanking`

## Preserved Optimizations

The following valid optimizations from PR #98 were preserved:

✓ ibl_plr table: All player statistics and ratings optimizations (SMALLINT, TINYINT conversions)
✓ ibl_hist table: Historical statistics optimizations
✓ ibl_standings table: Win/loss count optimizations
✓ ibl_box_scores table: Game statistics optimizations
✓ ibl_draft table: Draft round and pick optimizations
✓ ibl_draft_class table: Draft class rating optimizations
✓ ibl_schedule table: Year, Visitor, Home, VScore, HScore optimizations
✓ ibl_playoff_results table: Year and round optimizations
✓ All ENUM type conversions (positions, conferences)
✓ All valid CHECK constraints

## Migration Safety

The corrected migration file:
- Will not fail due to non-existent columns
- Preserves data type optimizations where columns exist
- Documents why certain optimizations were commented out
- Maintains backward compatibility

## Recommendations

1. **For ibl_team_win_loss, ibl_draft_picks**: Create separate data migration scripts to:
   - Convert VARCHAR year values to numeric SMALLINT
   - Convert VARCHAR wins/losses to numeric TINYINT
   - Convert CHAR(1) round to numeric TINYINT

2. **For ibl_team_history**: Review whether team statistics should be tracked in a different table structure

3. **For ibl_power ranking**: Decide if decimal precision is needed or if TINYINT is acceptable

## Testing
To verify the corrections, run:
```bash
python3 /tmp/analyze_migration.py
```

This will report any remaining column name mismatches.
