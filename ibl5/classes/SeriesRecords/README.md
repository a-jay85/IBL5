# SeriesRecords Module

The SeriesRecords module displays head-to-head series records between all teams in a grid format. Each cell shows the wins-losses record for the row team versus the column team.

## Architecture

The SeriesRecords module follows the Repository/Service/View/Controller pattern with comprehensive interface contracts:

```
SeriesRecords/
├── Contracts/                               # Interface definitions
│   ├── SeriesRecordsRepositoryInterface.php     # Data access contracts
│   ├── SeriesRecordsServiceInterface.php        # Business logic contracts
│   ├── SeriesRecordsViewInterface.php           # View rendering contracts
│   └── SeriesRecordsControllerInterface.php     # Controller contracts
├── SeriesRecordsRepository.php              # implements SeriesRecordsRepositoryInterface
├── SeriesRecordsService.php                 # implements SeriesRecordsServiceInterface
├── SeriesRecordsView.php                    # implements SeriesRecordsViewInterface
├── SeriesRecordsController.php              # implements SeriesRecordsControllerInterface
└── README.md                                # This file
```

## Components

### SeriesRecordsRepository

Handles all database operations for series records.

**Key Methods:**
- `getTeamsForSeriesRecords()` - Get all teams with basic info for display
- `getSeriesRecords()` - Get all head-to-head records from schedule
- `getMaxTeamId()` - Get maximum team ID for grid dimensions

All queries use prepared statements via `BaseMysqliRepository`.

### SeriesRecordsService

Provides business logic for transforming series records data.

**Key Methods:**
- `buildSeriesMatrix()` - Transform flat records into 2D lookup matrix
- `getRecordStatus()` - Determine if record is winning/losing/tied
- `getRecordBackgroundColor()` - Get color code for cell styling
- `getRecordFromMatrix()` - Lookup specific matchup from matrix

### SeriesRecordsView

Handles all HTML generation for the series records display.

**Key Methods:**
- `renderSeriesRecordsTable()` - Generate complete grid table
- `renderHeaderCell()` - Team logo header cells
- `renderTeamNameCell()` - First column with team links
- `renderRecordCell()` - Individual win-loss cells
- `renderDiagonalCell()` - Team vs itself cells

Uses `Utilities\HtmlSanitizer` for XSS protection on all output.

### SeriesRecordsController

Main controller orchestrating the page rendering.

**Key Methods:**
- `displaySeriesRecords()` - Render full page for a team context
- `displayLoginPrompt()` - Show login form for unauthenticated users
- `displayForUser()` - Handle authenticated user display
- `main()` - Entry point handling authentication routing

## Interface Contracts

Each class implements a corresponding interface documenting:
- Complete method signatures
- Parameter types and constraints
- Return value structure
- Behavioral specifications

See `Contracts/` directory for detailed interface documentation.

## Usage Example

```php
global $mysqli_db;

$controller = new \SeriesRecords\SeriesRecordsController($mysqli_db);
$controller->main($user);
```

## Display Features

- **Team logos** in header row for quick identification
- **Team names** with links to team pages in first column
- **Color-coded cells**:
  - Green (`#8f8`) - Winning record
  - Red (`#f88`) - Losing record  
  - Gray (`#bbb`) - Tied record
- **Bold styling** for logged-in user's team row and column
- **Diagonal cells** marked with 'x' (team vs itself)
- **Sortable table** class for JavaScript enhancement

## Database Tables Used

- `ibl_team_info` - Team information (teamid, team_city, team_name, colors)
- `ibl_schedule` - Game schedule with scores (Home, Visitor, HScore, VScore)

## Dependencies

- `BaseMysqliRepository` - Base class for prepared statement execution
- `Services\CommonMysqliRepository` - Team ID lookup from team name
- `Utilities\HtmlSanitizer` - XSS protection for output
- `Nuke\Header`, `Nuke\Footer` - Page frame rendering

## Related Modules

- **Team** - Team roster and statistics display
- **Standings** - League standings display
