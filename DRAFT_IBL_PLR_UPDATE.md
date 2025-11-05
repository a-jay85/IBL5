# Draft Module Enhancement: ibl_plr Table Creation

## Overview
This update enhances the Draft module to create entries in the `ibl_plr` table when players are drafted, in addition to the existing updates to `ibl_draft` and `ibl_draft_class` tables.

## Problem Statement
Previously, when a player was drafted:
- `ibl_draft.player` was updated with the player's name
- `ibl_draft_class.team` and `ibl_draft_class.drafted` were updated
- **BUT** entries in `ibl_plr` were not created until after the draft concluded

This meant drafted players weren't immediately available in the main player table, and name matching issues could occur when trying to update existing truncated entries.

## Solution
Added a new `createPlayerFromDraftClass()` method to `DraftRepository` that:
1. Gets the team ID from the team name using `CommonRepository::getTidFromTeamname()`
2. Retrieves player data from `ibl_draft_class`
3. Creates a new entry in `ibl_plr` with properly mapped column values
4. Automatically handles name truncation (32 character limit in `ibl_plr.name`)
5. Generates unique player IDs (PID) automatically

## Implementation Details

### Column Mapping from ibl_draft_class to ibl_plr

| ibl_draft_class | ibl_plr | Notes |
|----------------|---------|-------|
| `name` | `name` | Truncated to 32 characters |
| `pos` | `pos` | Position (PG, SG, SF, PF, C) |
| `age` | `age` | Player age |
| `team` | `teamname`, `tid` | Team name and ID |
| `sta` | `sta` | Stamina |
| `offo` | `oo` | Offensive Outside |
| `offd` | `od` | Offensive Drive |
| `offp` | `po` | Offensive Post |
| `offt` | `to` | Offensive Transition |
| `defo` | `do` | Defensive Outside |
| `defd` | `dd` | Defensive Drive |
| `defp` | `pd` | Defensive Post |
| `deft` | `td` | Defensive Transition |
| `tal` | `talent` | Talent rating |
| `skl` | `skill` | Skill rating |
| `int` | `intangibles` | Intangibles rating |

### Default Values Set
- `pid`: Auto-generated (MAX(pid) + 1)
- `active`: 1 (player is active)
- `bird`: 0 (no Bird rights)
- `exp`: 0 (rookie, no experience)
- `cy`: 0 (contract year)
- `cyt`: 0 (contract year total)

### Files Modified

1. **`ibl5/classes/Draft/DraftRepository.php`**
   - Added `generateUniquePid()` private method for PID generation
   - Added `getNextAvailablePid()` private method to find next available PID
   - Added `createPlayerFromDraftClass($playerName, $teamName)` method
   - Maps all relevant columns from ibl_draft_class to ibl_plr
   - Returns boolean indicating success/failure

2. **`ibl5/classes/Draft/DraftSelectionHandler.php`**
   - Updated `processDraftSelection()` to call `createPlayerFromDraftClass()`
   - Now creates player entry in ibl_plr in addition to updating ibl_draft and ibl_draft_class

3. **`ibl5/tests/Draft/DraftRepositoryTest.php`**
   - Replaced 5 tests for updatePlayerTable() with 5 new tests for createPlayerFromDraftClass():
     - Test successful player creation
     - Test team not found scenario
     - Test apostrophe handling in names
     - Test long name truncation (32 char limit)
     - Test player not in draft class scenario

4. **`ibl5/tests/bootstrap.php`**
   - Fixed MockDatabase bug for better test reliability

## Test Results
- **Before**: 30 Draft tests passing (original baseline)
- **After**: 35 Draft tests passing (5 tests for new functionality)
- All existing tests continue to pass
- No breaking changes to existing functionality

## Database Tables Affected

### ibl_plr (Created Entry)
New entry created with:
- **pid** (int): Auto-generated unique player ID
- **name** (varchar 32): Player name (truncated from ibl_draft_class if needed)
- **age** (tinyint): Player age
- **tid** (int): Team ID
- **teamname** (varchar 32): Team name
- **pos** (varchar 4): Position
- **sta** (int): Stamina
- **oo, od, po, to** (int): Offensive ratings
- **do, dd, pd, td** (int): Defensive ratings
- **talent, skill, intangibles** (int): Player ratings
- **active** (tinyint): Set to 1 (active)
- **bird, exp, cy, cyt** (int): Set to 0 (defaults for rookies)

### Schema Details
- `ibl_plr.name`: varchar(32) - automatically truncated from draft class
- `ibl_draft.player`: varchar(255) - full name stored
- `ibl_draft_class.name`: varchar(32) - source of player data
- `ibl_team_info.team_name`: varchar(16) - team identifier
- `ibl_team_info.teamid`: int - team ID

## Error Handling
- Returns `false` if team not found (shouldn't happen in normal operation)
- Returns `false` if player not found in draft class
- Returns `true` if player entry successfully created
- Error message in DraftProcessor covers "at least one of the draft database tables" generically

## Benefits
1. **Immediate Availability**: Drafted players are immediately available in ibl_plr
2. **No Name Matching Issues**: Creates fresh entry, no need to match truncated names
3. **Complete Data**: All ratings and attributes mapped from draft class
4. **Automatic PID Generation**: Handles player ID assignment automatically
5. **Backward Compatible**: Doesn't break existing functionality
6. **Well Tested**: Comprehensive test coverage for new functionality

## Usage
No changes required to existing code that calls the Draft module. The player creation happens automatically during the draft selection process in `DraftSelectionHandler::processDraftSelection()`.

## Example SQL Generated
```sql
INSERT INTO ibl_plr (
    pid, name, age, tid, teamname, pos,
    sta, oo, od, po, `to`, `do`, dd, pd, td,
    talent, skill, intangibles,
    active, bird, exp, cy, cyt
) VALUES (
    1234, 'Victor Wembanyama', 20, 27, 'San Antonio Spurs', 'C',
    90, 85, 80, 95, 75, 90, 95, 98, 85,
    98, 85, 90,
    1, 0, 0, 0, 0
)
```

## Future Considerations
- Could add contract year information if rookies have standard contracts
- Could set default depth chart positions
- Consider adding player personality traits (loyalty, playingTime, etc.)
- May want to add logging for player creation audit trail
