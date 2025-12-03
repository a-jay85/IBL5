# Team Module

The Team module provides comprehensive team management functionality including roster display, statistics visualization, historical records, and team accomplishments.

## Architecture

The Team module follows the Repository/Service/Controller pattern with comprehensive interface contracts:

```
Team/
├── Contracts/                       # Interface definitions
│   ├── TeamRepositoryInterface.php      # Data access contracts
│   ├── TeamStatsServiceInterface.php    # Statistics calculation contracts
│   ├── TeamUIServiceInterface.php       # UI rendering contracts
│   └── TeamControllerInterface.php      # Controller contracts
├── TeamRepository.php               # implements TeamRepositoryInterface
├── TeamStatsService.php             # implements TeamStatsServiceInterface
├── TeamUIService.php                # implements TeamUIServiceInterface
└── TeamController.php               # implements TeamControllerInterface
```

## Components

### TeamRepository

Handles all database operations for team-related data.

**Key Methods:**
- `getTeamPowerData()` - Get team power ranking information
- `getDivisionStandings()` / `getConferenceStandings()` - Get standings by division or conference
- `getFreeAgencyRoster()` / `getRosterUnderContract()` - Get team rosters filtered by contract status
- `getChampionshipBanners()` / `getTeamAccomplishments()` - Get team historical achievements
- `getRegularSeasonHistory()` / `getHEATHistory()` / `getPlayoffResults()` - Get historical results
- `getFreeAgents()` / `getEntireLeagueRoster()` - Get league-wide roster data
- `getHistoricalRoster()` - Get team roster for a specific historical season

All queries use safe escaping and prepared statements.

### TeamStatsService

Provides team-level statistics calculations and starting lineup extraction.

**Key Methods:**
- `extractStartersData()` - Parse database result to extract starting 5 players by position
- `getLastSimsStarters()` - Render HTML table of starting lineup with team colors

### TeamUIService

Handles all presentation logic for team pages.

**Key Methods:**
- `renderTeamInfoRight()` - Generate right sidebar with team history, accomplishments, and records
- `renderTabs()` - Generate tab navigation for different display modes (ratings, totals, averages, contracts, etc.)
- `getTableOutput()` - Route to appropriate UI rendering function based on selected display type

Supports multiple display modes:
- **ratings** - Player ratings and skill levels
- **total_s** - Season total statistics
- **avg_s** - Season per-game averages
- **per36mins** - Per-36-minute pace-adjusted statistics
- **chunk** - Simulation period averages
- **playoffs** - Playoff period statistics (if applicable)
- **contracts** - Contract details and salary information

### TeamController

Main controller orchestrating the complete team page rendering.

**Key Methods:**
- `displayTeamPage()` - Render complete team page with selected display mode
- `displayMenu()` - Render team module main menu

Handles multiple contexts:
- Specific team roster (current season, free agency, or historical year)
- Free agents available for signing (teamID = 0)
- Entire league roster (teamID = -1)

## Interface Contracts

Each class implements a corresponding interface documenting:
- Complete method signatures
- Parameter types and constraints
- Return value structure
- Behavioral specifications
- Edge cases and error handling

See `Contracts/` directory for detailed interface documentation.

## Usage Examples

### Display Team Page

```php
$controller = new TeamController($db);
$controller->displayTeamPage(5);  // Display Chicago Bulls
```

### Display Free Agents

```php
$controller = new TeamController($db);
$controller->displayTeamPage(0);  // Display free agents available for signing
```

### Display Entire League

```php
$controller = new TeamController($db);
$controller->displayTeamPage(-1);  // Display entire league roster
```

### Display Historical Season

The controller checks `$_REQUEST['yr']` for historical year queries:

```
modules.php?name=Team&op=team&teamID=5&yr=2023
```

## Database Queries

All database queries use safe escaping via `DatabaseService::escapeString()`.

Key tables referenced:
- `ibl_plr` - Current player roster
- `ibl_hist` - Historical player statistics
- `ibl_power` - Power rankings and standings
- `ibl_banners` - Championship records
- `ibl_team_awards` - Team accomplishments
- `ibl_team_win_loss` - Regular season history
- `ibl_heat_win_loss` - HEAT tournament results
- `ibl_playoff_results` - Playoff results
- `ibl_gm_history` - GM history records

## Testing

Comprehensive test suite with 13 tests across four test classes:

- `TeamRepositoryTest` - 5 tests for data access operations
- `TeamStatsServiceTest` - 4 tests for statistics calculations
- `TeamUIServiceTest` - 4 tests for UI rendering logic

All tests pass without warnings or errors.

**Run tests:**
```bash
cd ibl5
vendor/bin/phpunit --testsuite "Team Module Tests"
```

## Code Quality

- **Type Hints:** All parameters and return types explicitly declared
- **Error Handling:** No exceptions thrown; methods return null or empty results on error
- **Security:** All user input escaped; safe database escaping throughout
- **Documentation:** Comprehensive PHPDoc with interface `@see` references
- **Maintainability:** Clear separation of concerns between Repository, Service, and Controller

## Design Patterns

- **Repository Pattern** - Encapsulate all database operations
- **Service Pattern** - Provide business logic and calculations
- **Controller Pattern** - Orchestrate user interactions and page flow
- **Facade Pattern** - Interface contracts provide clear boundaries and contracts
