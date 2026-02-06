# Team Module

The Team module provides comprehensive team management functionality including roster display, statistics visualization, historical records, and team accomplishments.

## Architecture

The Team module follows the Controller -> Service -> View -> Repository pattern:

```
Team/
├── Contracts/                       # Interface definitions
│   ├── TeamControllerInterface.php      # Controller contract
│   ├── TeamServiceInterface.php         # Data orchestration contract
│   ├── TeamViewInterface.php            # HTML rendering contract
│   └── TeamRepositoryInterface.php      # Data access contract
├── TeamController.php               # Thin orchestrator (implements TeamControllerInterface)
├── TeamService.php                  # Data assembly (implements TeamServiceInterface)
├── TeamView.php                     # HTML rendering (implements TeamViewInterface)
└── TeamRepository.php               # Database queries (implements TeamRepositoryInterface)
```

## Components

### TeamController

Thin orchestrator that parses request parameters and delegates to Service and View.

**Key Methods:**
- `displayTeamPage()` - Parse request, call Service for data, call View for HTML
- `displayMenu()` - Render team module main menu

### TeamService

Assembles all data needed by the view from repositories and domain objects.

**Key Methods:**
- `getTeamPageData()` - Returns structured array with all page data (tabs, table, starters, sidebar)
- `extractStartersData()` - Extract starting lineup from roster array by depth chart

Supports multiple display modes:
- **ratings** - Player ratings and skill levels
- **total_s** - Season total statistics
- **avg_s** - Season per-game averages
- **per36mins** - Per-36-minute pace-adjusted statistics
- **chunk** - Simulation period averages
- **playoffs** - Playoff period statistics (if applicable)
- **contracts** - Contract details and salary information

### TeamView

Pure HTML renderer that receives pre-computed data from TeamService.

**Key Methods:**
- `render()` - Compose full page layout from pre-rendered sub-components

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

All queries use prepared statements.

## Data Flow

```
Request -> TeamController
              -> TeamService.getTeamPageData()
                   -> TeamRepository (database queries)
                   -> Domain objects (Team, Season, Shared)
                   -> UI sub-components (tabs, tables, sidebar)
                   <- Returns structured data array
              -> TeamView.render(pageData)
                   <- Returns HTML string
           <- echo HTML + Header/Footer
```

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

## Database Tables

Key tables referenced:
- `ibl_plr` - Current player roster
- `ibl_hist` - Historical player statistics
- `ibl_power` - Power rankings and standings
- `ibl_banners` - Championship records
- `ibl_team_awards` - Team accomplishments
- `ibl_team_win_loss` - Regular season history
- `ibl_heat_win_loss` - HEAT tournament results
- `ibl_playoff_results` - Playoff results
- `ibl_gm_awards` - GM awards (normalized, one row per award per year)
- `ibl_gm_tenures` - GM tenure periods per franchise

## Testing

26 tests across four test classes:

- `TeamControllerTest` - 3 tests for instantiation and interface compliance
- `TeamRepositoryTest` - 5 tests for data access operations
- `TeamServiceTest` - 6 tests for starters extraction and data logic
- `TeamViewTest` - 12 tests for HTML rendering output

**Run tests:**
```bash
cd ibl5
vendor/bin/phpunit --testsuite "Team Module Tests"
```

## Design Patterns

- **Controller -> Service -> View** - Clean separation matching FreeAgency module
- **Repository Pattern** - Encapsulate all database operations
- **Interface Contracts** - All components implement typed interfaces
