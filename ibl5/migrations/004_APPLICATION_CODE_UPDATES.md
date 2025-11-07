# Phase 7: Application Code Updates Guide

## Overview

This document outlines all application code updates required after running the Phase 7 naming convention standardization migration (`004_naming_convention_standardization.sql`).

**Migration Impact:**
- 46 columns renamed across 14 tables
- 5 foreign key constraints updated
- 15+ indexes renamed
- 1 database view updated

**Estimated Update Time:** 3-5 days of development + testing

---

## Column Rename Reference

### Complete Mapping Table

| Table | Old Column Name | New Column Name |
|-------|----------------|-----------------|
| **ibl_schedule** (8 columns) | | |
| | `Year` | `season_year` |
| | `BoxID` | `box_score_id` |
| | `Date` | `game_date` |
| | `Visitor` | `visitor_team_id` |
| | `VScore` | `visitor_score` |
| | `Home` | `home_team_id` |
| | `HScore` | `home_score` |
| | `SchedID` | `schedule_id` |
| **ibl_box_scores** (3 columns) | | |
| | `Date` | `game_date` |
| | `homeTID` | `home_team_id` |
| | `visitorTID` | `visitor_team_id` |
| **ibl_box_scores_teams** (3 columns) | | |
| | `Date` | `game_date` |
| | `homeTeamID` | `home_team_id` |
| | `visitorTeamID` | `visitor_team_id` |
| **ibl_plr** (7 columns) | | |
| | `Clutch` | `clutch` |
| | `Consistency` | `consistency` |
| | `PGDepth` | `pg_depth` |
| | `SGDepth` | `sg_depth` |
| | `SFDepth` | `sf_depth` |
| | `PFDepth` | `pf_depth` |
| | `CDepth` | `c_depth` |
| **ibl_team_info** (10 columns) | | |
| | `Contract_Wins` | `contract_wins` |
| | `Contract_Losses` | `contract_losses` |
| | `Contract_AvgW` | `contract_avg_wins` |
| | `Contract_AvgL` | `contract_avg_losses` |
| | `Contract_Coach` | `contract_coach` |
| | `HasMLE` | `has_mle` |
| | `HasLLE` | `has_lle` |
| | `Used_Extension_This_Season` | `used_extension_this_season` |
| | `Used_Extension_This_Chunk` | `used_extension_this_chunk` |
| | `discordID` | `discord_id` |
| **ibl_power** (4 columns) | | |
| | `TeamID` | `team_id` |
| | `Team` | `team_name` |
| | `Conference` | `conference` |
| | `Division` | `division` |
| **ibl_sim_dates** (3 columns) | | |
| | `Sim` | `sim_number` |
| | `` `Start Date` `` | `start_date` |
| | `` `End Date` `` | `end_date` |
| **ibl_team_awards** (2 columns) | | |
| | `Award` | `award_name` |
| | `ID` | `award_id` |
| **ibl_awards** (1 column) | | |
| | `Award` | `award_name` |
| **ibl_gm_history** (1 column) | | |
| | `Award` | `award_name` |
| **ibl_plr_chunk** (1 column) | | |
| | `Season` | `season_chunk` |
| **ibl_team_offense_stats** (1 column) | | |
| | `teamID` | `team_id` |
| **ibl_team_defense_stats** (1 column) | | |
| | `teamID` | `team_id` |
| **ibl_trade_cash** (1 column) | | |
| | `tradeOfferID` | `trade_offer_id` |

---

## Search and Replace Strategy

### Phase 1: Automated Search and Replace (Use with Caution)

**IMPORTANT:** Always review changes before committing. Some string matches may be in comments or strings that shouldn't be changed.

#### Step 1: Find All Affected Files

```bash
# Search for files that reference the old column names
cd /home/runner/work/IBL5/IBL5/ibl5

# Most critical: ibl_schedule columns
grep -r "Year\|BoxID\|Date\|Visitor\|VScore\|Home\|HScore\|SchedID" --include="*.php" . > /tmp/schedule_references.txt

# Team info columns
grep -r "Contract_Wins\|Contract_Losses\|HasMLE\|HasLLE\|discordID" --include="*.php" . > /tmp/team_references.txt

# Box scores columns
grep -r "homeTID\|visitorTID\|homeTeamID\|visitorTeamID" --include="*.php" . > /tmp/boxscore_references.txt

# Player table columns
grep -r "Clutch\|Consistency\|PGDepth\|SGDepth\|SFDepth\|PFDepth\|CDepth" --include="*.php" . > /tmp/player_references.txt
```

#### Step 2: Targeted Replacements by Table

##### ibl_schedule Updates (HIGHEST PRIORITY)

These columns are heavily used throughout the codebase:

```bash
# In SELECT, WHERE, ORDER BY clauses - be precise with context
find . -name "*.php" -type f -exec sed -i 's/\bYear\s*=/season_year =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bBoxID\s*=/box_score_id =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bDate\s*=/game_date =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bVisitor\s*=/visitor_team_id =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bVScore\s*=/visitor_score =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bHome\s*=/home_team_id =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bHScore\s*=/home_score =/g' {} +
find . -name "*.php" -type f -exec sed -i 's/\bSchedID\s*=/schedule_id =/g' {} +

# In array access and object properties
find . -name "*.php" -type f -exec sed -i "s/\['Year'\]/['season_year']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['BoxID'\]/['box_score_id']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['Date'\]/['game_date']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['Visitor'\]/['visitor_team_id']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['VScore'\]/['visitor_score']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['Home'\]/['home_team_id']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['HScore'\]/['home_score']/g" {} +
find . -name "*.php" -type f -exec sed -i "s/\['SchedID'\]/['schedule_id']/g" {} +
```

**WARNING:** The above `sed` commands are simplified examples. Do NOT run them blindly as they may:
- Replace variable names that happen to match
- Replace strings in comments
- Replace values in HTML/JavaScript

### Phase 2: Manual Code Review (REQUIRED)

After any automated replacements, you **must** manually review each change. Focus on:

#### 2.1: SQL Query Updates

Look for queries in these patterns:

**Old Pattern:**
```php
$query = "SELECT Year, BoxID, Date, Visitor, VScore, Home, HScore, SchedID 
          FROM ibl_schedule 
          WHERE Year = $year AND Date = '$date'";
```

**New Pattern:**
```php
$query = "SELECT season_year, box_score_id, game_date, visitor_team_id, 
                 visitor_score, home_team_id, home_score, schedule_id 
          FROM ibl_schedule 
          WHERE season_year = $year AND game_date = '$date'";
```

#### 2.2: Array/Object Property Access

**Old Pattern:**
```php
$year = $row['Year'];
$homeTeam = $row['Home'];
$visitorTeam = $row['Visitor'];
$homeScore = $row['HScore'];
```

**New Pattern:**
```php
$year = $row['season_year'];
$homeTeam = $row['home_team_id'];
$visitorTeam = $row['visitor_team_id'];
$homeScore = $row['home_score'];
```

#### 2.3: WHERE Clauses

**Old Pattern:**
```php
$query = "SELECT * FROM ibl_schedule WHERE Date = '$gameDate' AND Home = $teamId";
$query = "SELECT * FROM ibl_box_scores WHERE Date = '$date' AND homeTID = $teamId";
```

**New Pattern:**
```php
$query = "SELECT * FROM ibl_schedule WHERE game_date = '$gameDate' AND home_team_id = $teamId";
$query = "SELECT * FROM ibl_box_scores WHERE game_date = '$date' AND home_team_id = $teamId";
```

#### 2.4: ORDER BY Clauses

**Old Pattern:**
```php
$query = "SELECT * FROM ibl_schedule ORDER BY Year DESC, Date DESC";
```

**New Pattern:**
```php
$query = "SELECT * FROM ibl_schedule ORDER BY season_year DESC, game_date DESC";
```

#### 2.5: JOIN Conditions

**Old Pattern:**
```php
$query = "SELECT s.*, t.team_name 
          FROM ibl_schedule s 
          JOIN ibl_team_info t ON s.Home = t.teamid";
```

**New Pattern:**
```php
$query = "SELECT s.*, t.team_name 
          FROM ibl_schedule s 
          JOIN ibl_team_info t ON s.home_team_id = t.teamid";
```

---

## Files Likely to Need Updates

Based on the table impacts, these file categories will need updates:

### High Priority Files (ibl_schedule usage)

1. **Schedule Display/Management:**
   - Any file with `schedule` in the name
   - Game listing pages
   - Season calendar views
   - Box score display pages

2. **Team Pages:**
   - Team schedule views
   - Team game history
   - Standings calculations (if using schedule data)

3. **Statistics Calculation:**
   - Any code calculating wins/losses from schedule
   - Home/away record calculations
   - Season statistics aggregation

### Medium Priority Files (ibl_box_scores, ibl_team_info)

1. **Box Score Processing:**
   - Box score import/export
   - Game result pages
   - Player game logs

2. **Team Management:**
   - Contract management pages
   - Discord integration (uses `discord_id`)
   - Team settings pages
   - Free agency pages (MLE/LLE flags)

### Lower Priority Files (ibl_plr, ibl_awards, etc.)

1. **Player Display:**
   - Player profile pages (depth chart positions)
   - Player attributes (Clutch, Consistency)

2. **Awards Management:**
   - Award listing pages
   - Award history

3. **Simulation:**
   - Sim date management (`ibl_sim_dates`)

---

## Repository-Specific Update Checklist

### Step-by-Step Update Process

#### Step 1: Identify All Repository Classes

```bash
# Find repository classes that might query these tables
find /home/runner/work/IBL5/IBL5/ibl5/classes -name "*Repository.php" -o -name "*Service.php"
```

Expected files to check:
- `CommonRepository.php`
- `DraftRepository.php`
- `DepthChartRepository.php`
- `TeamRepository.php`
- `PlayerRepository.php` (if exists)

#### Step 2: Update Repository Methods

For each repository class:

1. Search for raw SQL queries using old column names
2. Update SELECT lists
3. Update WHERE clauses
4. Update ORDER BY clauses
5. Update array key accesses in result processing

**Example from a schedule repository:**

**Before:**
```php
public function getScheduleByDate($date)
{
    $query = "SELECT Year, BoxID, Date, Visitor, VScore, Home, HScore 
              FROM ibl_schedule 
              WHERE Date = '$date' 
              ORDER BY BoxID";
    $result = $this->db->sql_query($query);
    
    while ($row = $this->db->sql_fetchrow($result)) {
        $games[] = [
            'year' => $row['Year'],
            'box_id' => $row['BoxID'],
            'date' => $row['Date'],
            'visitor' => $row['Visitor'],
            'home' => $row['Home'],
            'visitor_score' => $row['VScore'],
            'home_score' => $row['HScore'],
        ];
    }
    return $games;
}
```

**After:**
```php
public function getScheduleByDate($date)
{
    $query = "SELECT season_year, box_score_id, game_date, visitor_team_id, 
                     visitor_score, home_team_id, home_score 
              FROM ibl_schedule 
              WHERE game_date = '$date' 
              ORDER BY box_score_id";
    $result = $this->db->sql_query($query);
    
    while ($row = $this->db->sql_fetchrow($result)) {
        $games[] = [
            'year' => $row['season_year'],
            'box_id' => $row['box_score_id'],
            'date' => $row['game_date'],
            'visitor' => $row['visitor_team_id'],
            'home' => $row['home_team_id'],
            'visitor_score' => $row['visitor_score'],
            'home_score' => $row['home_score'],
        ];
    }
    return $games;
}
```

#### Step 3: Update View/Display Files

Any PHP file that outputs data will need to reference the new column names when processing query results.

#### Step 4: Update JavaScript/AJAX Endpoints

If any JavaScript makes AJAX calls that return database results, the JSON keys will change:

**Before:**
```javascript
$.get('/api/schedule.php', function(data) {
    data.forEach(function(game) {
        console.log(game.Year, game.Date, game.Home, game.Visitor);
    });
});
```

**After:**
```javascript
$.get('/api/schedule.php', function(data) {
    data.forEach(function(game) {
        console.log(game.season_year, game.game_date, game.home_team_id, game.visitor_team_id);
    });
});
```

---

## Testing Strategy

### Unit Tests

If unit tests exist, update them to use new column names:

```php
// Before
$this->assertEquals(2024, $schedule['Year']);
$this->assertEquals('2024-01-15', $schedule['Date']);

// After
$this->assertEquals(2024, $schedule['season_year']);
$this->assertEquals('2024-01-15', $schedule['game_date']);
```

### Integration Testing Checklist

After all code updates, test these critical paths:

- [ ] **Schedule Display**
  - [ ] View full season schedule
  - [ ] View team-specific schedule
  - [ ] View games by date range
  - [ ] Verify game dates display correctly
  - [ ] Verify scores display correctly

- [ ] **Box Scores**
  - [ ] View individual game box scores
  - [ ] Verify home/visitor team identification
  - [ ] Verify game date displays correctly

- [ ] **Team Pages**
  - [ ] View team info page
  - [ ] Verify contract information displays
  - [ ] Verify MLE/LLE flags work
  - [ ] Test Discord integration (if applicable)

- [ ] **Player Pages**
  - [ ] View player profile
  - [ ] Verify depth chart positions display
  - [ ] Verify Clutch/Consistency attributes display

- [ ] **Awards**
  - [ ] View awards list
  - [ ] Verify award names display correctly

- [ ] **Simulation System**
  - [ ] Check simulation date ranges
  - [ ] Verify start/end dates work correctly

- [ ] **Database Views**
  - [ ] Test `vw_schedule_upcoming` view
  - [ ] Verify view returns expected data structure

---

## Rollback Strategy

If critical issues are discovered after deployment:

### Option 1: Immediate Database Rollback (Production)

1. Stop the application
2. Restore database from pre-migration backup
3. Restart application with old code

### Option 2: Code-Only Rollback (If database can't be rolled back)

If the database migration cannot be reversed, you can create a compatibility layer:

```php
/**
 * Temporary compatibility wrapper for old column names
 * Maps new column names back to old names for backward compatibility
 * Remove this after full migration to new names
 */
class LegacyColumnMapper
{
    private static $columnMap = [
        'ibl_schedule' => [
            'season_year' => 'Year',
            'box_score_id' => 'BoxID',
            'game_date' => 'Date',
            'visitor_team_id' => 'Visitor',
            'visitor_score' => 'VScore',
            'home_team_id' => 'Home',
            'home_score' => 'HScore',
            'schedule_id' => 'SchedID',
        ],
        // ... other tables
    ];
    
    public static function mapRow($table, $row)
    {
        if (!isset(self::$columnMap[$table])) {
            return $row;
        }
        
        $mapped = $row;
        foreach (self::$columnMap[$table] as $new => $old) {
            if (isset($row[$new])) {
                $mapped[$old] = $row[$new];
            }
        }
        return $mapped;
    }
}
```

**Note:** This is a temporary solution only. Plan to remove the compatibility layer and fully migrate to new names.

---

## Validation Queries

After completing all code updates, run these validation queries:

### Check for Old Column Name References in Code

```bash
# These should return NO results after migration
grep -r "\\['Year'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['BoxID'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['Date'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['Home'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['Visitor'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['HScore'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
grep -r "\\['VScore'\\]" --include="*.php" /home/runner/work/IBL5/IBL5/ibl5/
```

### Database Validation

```sql
-- Verify all columns were renamed
SELECT TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND TABLE_NAME IN ('ibl_schedule', 'ibl_plr', 'ibl_team_info')
ORDER BY TABLE_NAME, ORDINAL_POSITION;

-- Verify no old column names exist
SELECT TABLE_NAME, COLUMN_NAME 
FROM INFORMATION_SCHEMA.COLUMNS 
WHERE TABLE_SCHEMA = DATABASE() 
  AND COLUMN_NAME IN ('Year', 'BoxID', 'Date', 'Home', 'Visitor', 'HScore', 'VScore', 'SchedID')
  AND TABLE_NAME LIKE 'ibl_%';
-- Should return 0 rows
```

---

## Timeline Estimate

| Phase | Task | Estimated Time |
|-------|------|----------------|
| 1 | File identification and analysis | 4-6 hours |
| 2 | Repository class updates | 8-12 hours |
| 3 | View/Controller updates | 8-12 hours |
| 4 | JavaScript/AJAX updates | 4-6 hours |
| 5 | Testing (unit + integration) | 8-16 hours |
| 6 | Bug fixes and refinements | 4-8 hours |
| **Total** | | **36-60 hours (4.5-7.5 days)** |

---

## Additional Resources

- **Migration SQL:** `004_naming_convention_standardization.sql`
- **Database Guide:** `/DATABASE_GUIDE.md`
- **Development Guide:** `/DEVELOPMENT_GUIDE.md`
- **Original Schema Improvements:** `/.archive/DATABASE_SCHEMA_IMPROVEMENTS.md` (Section 2.2)

---

## Support and Questions

For issues during migration:
1. Check this document for common patterns
2. Review the migration SQL file for exact column mappings
3. Test changes in development environment first
4. Keep pre-migration database backup for at least 30 days

**Remember:** This is a BREAKING CHANGE that affects the entire application. Thorough testing in a staging environment is essential before production deployment.
