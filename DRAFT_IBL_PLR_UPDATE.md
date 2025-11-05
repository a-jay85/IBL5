# Draft Module Enhancement: ibl_plr Table Update

## Overview
This update enhances the Draft module to update the `ibl_plr` table when players are drafted, in addition to the existing updates to `ibl_draft` and `ibl_draft_class` tables.

## Problem Statement
Previously, when a player was drafted:
- `ibl_draft.player` was updated with the player's name
- `ibl_draft_class.team` and `ibl_draft_class.drafted` were updated
- **BUT** `ibl_plr.tid` and `ibl_plr.teamname` were NOT updated

This meant drafted players weren't properly assigned to their teams in the main player table.

## Solution
Added a new `updatePlayerTable()` method to `DraftRepository` that:
1. Gets the team ID from the team name using `CommonRepository::getTidFromTeamname()`
2. Updates `ibl_plr.tid` and `ibl_plr.teamname` for the drafted player
3. Implements fuzzy name matching to handle:
   - Truncated names (ibl_plr.name is varchar(32), may be truncated)
   - Diacritical differences (names with accents like José, Nicolás, etc.)
   - Partial matches using SQL LIKE queries

## Implementation Details

### Name Matching Strategy
The method tries three approaches in order:

1. **Exact Match**: Direct name comparison
   ```sql
   UPDATE ibl_plr SET tid = ?, teamname = ? WHERE name = ?
   ```

2. **Truncated Match**: For names longer than 32 characters
   ```sql
   UPDATE ibl_plr SET tid = ?, teamname = ? WHERE name = ?
   -- Where ? is substr(playerName, 0, 32)
   ```

3. **Partial Match**: For diacritical differences
   ```sql
   UPDATE ibl_plr SET tid = ?, teamname = ? WHERE name LIKE ? LIMIT 1
   -- Where ? is the first 30 characters with % wildcard
   ```

### Files Modified

1. **`ibl5/classes/Draft/DraftRepository.php`**
   - Added `updatePlayerTable($playerName, $teamName)` method
   - Implements the three-tier name matching strategy
   - Returns boolean indicating success/failure

2. **`ibl5/classes/Draft/DraftSelectionHandler.php`**
   - Updated `processDraftSelection()` to call `updatePlayerTable()`
   - Now updates all three tables: ibl_draft, ibl_draft_class, and ibl_plr
   - Returns error if any of the three updates fail

3. **`ibl5/tests/Draft/DraftRepositoryTest.php`**
   - Added 5 new tests for `updatePlayerTable()`:
     - Test exact match
     - Test truncated name handling
     - Test partial match with diacriticals
     - Test team not found scenario
     - Test apostrophe escaping

4. **`ibl5/tests/bootstrap.php`**
   - Fixed bug in `MockDatabase::sql_query()` where `setReturnTrue()` was affecting SELECT queries
   - Added `sql_affectedrows()` method to support the new functionality
   - Added `setAffectedRows()` method for test configuration

## Test Results
- **Before**: 30 Draft tests passing
- **After**: 35 Draft tests passing (5 new tests added)
- All existing tests continue to pass
- No breaking changes to existing functionality

## Database Tables Affected

### ibl_plr
- **tid** (int): Set to the team's ID
- **teamname** (varchar): Set to the team's name

### Schema Details
- `ibl_plr.name`: varchar(32) - may be truncated
- `ibl_draft.player`: varchar(255) - full name
- `ibl_draft_class.name`: varchar(32) - may be truncated
- `ibl_team_info.team_name`: varchar(16) - team identifier
- `ibl_team_info.teamid`: int - team ID

## Error Handling
- Returns `false` if team not found (shouldn't happen in normal operation)
- Returns `false` if no player matches found
- Returns `true` if update succeeds (any of the three matching strategies)
- Error message in DraftProcessor already covers "at least one of the draft database tables" generically

## Benefits
1. **Data Consistency**: Player team assignments are now consistent across all three tables
2. **Robustness**: Handles edge cases with truncated names and diacriticals
3. **Backward Compatible**: Doesn't break existing functionality
4. **Well Tested**: Comprehensive test coverage for new functionality

## Usage
No changes required to existing code that calls the Draft module. The update happens automatically during the draft selection process in `DraftSelectionHandler::processDraftSelection()`.

## Future Considerations
- Could add logging to track which matching strategy was used
- Could add metrics to monitor truncated name scenarios
- Consider normalizing player names across tables to avoid matching issues
