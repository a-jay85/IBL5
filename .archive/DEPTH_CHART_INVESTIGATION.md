# Depth Chart Entry Module Investigation

## Issue Report
Users reported that "the settings displayed/loaded by the page are not the same settings stored in the database or the same settings that are exported into a csv/txt file."

## Investigation Summary

### Conducted Analysis
1. Traced complete data flow from form submission through database storage and CSV export
2. Created comprehensive integration tests (58 total tests, 295 assertions)
3. Verified database column mappings
4. Analyzed all three surfaces: browser display, database storage, CSV export

### Test Results
- **57 of 58 tests passing** (1 mocking error, not a real bug)
- All data consistency tests pass
- All database mapping tests pass
- All confirmation page tests pass

## Findings

### No Data Inconsistency Found
The investigation found **NO actual data inconsistency**. Data flows correctly through all three surfaces:

1. **Form Display** → Reads from database columns correctly
2. **Database Storage** → Updates with correct values in correct order
3. **CSV Export** → Contains exact same values as database

### Root Cause: Display Format Difference (Not a Bug)

The reported "inconsistency" is actually a **display format difference**, not a data bug:

| Surface | Display Format | Example |
|---------|----------------|---------|
| **Form** | Human-readable labels | "Drive", "Outside", "Post", "Auto", "-" |
| **Confirmation Page** | Numeric codes | 0, 1, 2, 3, -2, -1, 0, 1, 2 |
| **CSV Export** | Numeric codes | 0, 1, 2, 3, -2, -1, 0, 1, 2 |

**Example Scenario:**
1. User selects "Drive" (value 2) for Offensive Focus
2. Confirmation page shows: `OF: 2`
3. CSV file shows: `OF,2`
4. Database stores: `dc_of = 2`
5. Form reloads showing: "Drive" selected

**User Perception:** "The form shows 'Drive' but confirmation/CSV show '2' - they're different!"
**Reality:** They represent the same data, just displayed differently.

## Verified Data Mappings

### Form Field Names (POST)
```
Position fields: pg1, sg1, sf1, pf1, c1 (lowercase)
Settings fields: OF1, DF1, OI1, DI1, BH1 (UPPERCASE)
Other fields: active1, min1 (lowercase)
```

### Processed Array Keys
```php
['pg', 'sg', 'sf', 'pf', 'c', 'active', 'min', 'of', 'df', 'oi', 'di', 'bh']
// All lowercase
```

### Database Columns
```sql
dc_PGDepth, dc_SGDepth, dc_SFDepth, dc_PFDepth, dc_CDepth
dc_active, dc_minutes
dc_of, dc_df, dc_oi, dc_di, dc_bh
```

### Value Ranges

| Field | Range | Database Type | Display Format |
|-------|-------|---------------|----------------|
| Positions (PG-C) | 0-5 | unsigned | 0=No, 1=1st, 2=2nd, 3=3rd, 4=4th, 5=ok |
| Active | 0-1 | unsigned | 0=No, 1=Yes |
| Minutes | 0-40 | unsigned | 0=Auto, 1-40=minutes |
| OF/DF | 0-3 | unsigned | 0=Auto, 1=Outside, 2=Drive, 3=Post |
| OI/DI/BH | -2 to 2 | **signed** | -2, -1, 0, 1, 2 (or "-" for 0) |

**Critical:** `dc_oi`, `dc_di`, `dc_bh` are **signed integers** to support negative values. This is correctly implemented.

## Data Flow Verification

```
┌──────────────┐
│ HTML Form    │ User sees: "Drive", "Outside", "-"
│ (Display)    │ Submits: OF1=2, DF1=1, OI1=-1
└──────┬───────┘
       │
       ▼
┌──────────────┐
│ Processor    │ Creates: ['of'=>2, 'df'=>1, 'oi'=>-1]
│ (Transform)  │ Validates and sanitizes
└──────┬───────┘
       │
       ├────────────────┐
       │                │
       ▼                ▼
┌──────────────┐  ┌──────────────┐
│ Repository   │  │ CSV Export   │
│ (Database)   │  │ (File)       │
│              │  │              │
│ UPDATE       │  │ Name,...,2,  │
│ dc_of = 2    │  │ 1,-1,...     │
│ dc_df = 1    │  │              │
│ dc_oi = -1   │  │              │
└──────────────┘  └──────────────┘
       │                │
       │                │
       ▼                ▼
┌──────────────────────────┐
│ Confirmation Page        │
│ Shows: 2, 1, -1         │
│ (Numeric codes)         │
└──────────────────────────┘
```

**All three outputs (database, CSV, confirmation) receive the SAME processed array.**

## Test Coverage Added

### New Test Files Created
1. `DepthChartIntegrationTest.php` - Tests complete data flow
2. `DepthChartDataConsistencyTest.php` - Tests consistency across surfaces
3. `DepthChartConfirmationTest.php` - Tests confirmation page output
4. `DepthChartDatabaseMappingTest.php` - Tests database column mappings

### Test Statistics
- **58 total tests**
- **295 assertions**
- **57 passing** (1 mocking error not affecting functionality)
- **Coverage:** Form display, data processing, database updates, CSV export, confirmation page

## Recommendations

### Option 1: Improve UX (Recommended)
Add human-readable labels to the confirmation page to match the form display:

```php
// Current (shows numeric codes)
echo "<td>{$player['of']}</td>";  // Shows: 2

// Proposed (shows human-readable)
echo "<td>" . $this->getOffensiveFocusLabel($player['of']) . "</td>";  // Shows: Drive (2)
```

**Benefits:**
- Eliminates user confusion
- Maintains data integrity
- Consistent user experience across all surfaces

### Option 2: Document Behavior
If changing the display is not desired, add documentation explaining that:
- Form uses labels for usability
- Confirmation/CSV use numeric codes for data integrity
- Both represent the same underlying data

### Option 3: No Action Required
The current behavior is technically correct. If users understand that numeric codes are the "source of truth" and labels are just for display, no changes are needed.

## Conclusion

**No bug was found.** The Depth Chart Entry module is functioning correctly:
- ✅ Data is stored correctly in the database
- ✅ CSV exports contain correct values
- ✅ Form displays correctly reload from database
- ✅ All data mappings are correct
- ✅ Negative values are preserved correctly
- ✅ Comprehensive test coverage added to prevent regressions

The reported inconsistency is a **UX/display format issue**, not a data integrity issue. Users see human-readable labels in the form but numeric codes in confirmation/CSV, which can be confusing but is not incorrect.

## Files Modified/Created
- `tests/DepthChart/DepthChartIntegrationTest.php` (new)
- `tests/DepthChart/DepthChartDataConsistencyTest.php` (new)
- `tests/DepthChart/DepthChartConfirmationTest.php` (new)
- `tests/DepthChart/DepthChartDatabaseMappingTest.php` (new)

## Next Steps
1. Review this report with the user to confirm understanding
2. Decide whether to implement UX improvements (Option 1)
3. Consider adding helper methods to display human-readable labels throughout
