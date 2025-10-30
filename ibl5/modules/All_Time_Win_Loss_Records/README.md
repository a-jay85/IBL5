# All_Time_Win_Loss_Records Module

## Overview
This module displays all teams' all-time win/loss records against all other IBL teams. The records are tabulated by Regular Season, Playoffs, HEAT, and all season types combined.

## Features
- **Visual Grid Layout**: Similar to the Series_Records module, displaying a matrix of win/loss records
- **Season Type Filtering**: Simple navigation menu at the top allows users to switch between:
  - **All Games Combined**: Shows cumulative records across all season types
  - **Regular Season**: Shows records for games played in November through May (months 11, 12, 1-5)
  - **Playoffs**: Shows records for games played in June (month 6)
  - **HEAT**: Shows records for games played in October (month 10)
- **Team Highlighting**: The logged-in user's team row and column are highlighted in bold
- **Color-Coded Results**: 
  - Green (#8f8) for winning records
  - Red (#f88) for losing records
  - Gray (#bbb) for even records

## Installation
1. The module is automatically discovered by the admin interface when placed in the `/modules/` directory
2. Log in as an admin and navigate to the Modules administration page
3. The module "All_Time_Win_Loss_Records" will appear in the list
4. Activate the module and optionally add it to the menu

## Technical Details
- The module queries the `ibl_schedule` table to aggregate win/loss records
- Season types are distinguished by the `Date` field's month:
  - Regular Season: Months 11, 12, 1, 2, 3, 4, 5
  - Playoffs: Month 6
  - HEAT: Month 10
- The module uses the same visual template as Series_Records for consistency

## Security
- Input validation ensures only valid season types are processed
- SQL queries are properly constructed to prevent injection attacks
- Module follows PHP-Nuke security patterns

## Usage
Users can access the module by navigating to:
```
modules.php?name=All_Time_Win_Loss_Records
```

To directly access a specific season type:
```
modules.php?name=All_Time_Win_Loss_Records&seasonType=regular
modules.php?name=All_Time_Win_Loss_Records&seasonType=playoffs
modules.php?name=All_Time_Win_Loss_Records&seasonType=heat
modules.php?name=All_Time_Win_Loss_Records&seasonType=combined
```
