# Migration 004 Column Name Corrections

## Summary
Fixed hallucinated column names in `ibl5/migrations/004_data_type_refinements.sql` by cross-referencing with `ibl5/schema.sql`.

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
