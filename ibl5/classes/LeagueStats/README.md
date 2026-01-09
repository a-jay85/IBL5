# LeagueStats Module

League-wide team statistics display module, refactored to use the interface-driven architecture pattern.

## Overview

Displays comprehensive league statistics including:
- **Team Offense Totals** - Aggregate offensive stats for all teams
- **Team Defense Totals** - Aggregate defensive stats (opponent stats)
- **Team Offense Averages** - Per-game offensive averages and shooting percentages
- **Team Defense Averages** - Per-game defensive averages and shooting percentages
- **Off/Def Differentials** - Offense vs defense comparison for each team

## Performance Improvement

**Before refactoring:** 30+ database queries (one per team via `TeamStats::withTeamName()`)

**After refactoring:** 1 database query (bulk JOIN across all teams)

This represents a **~97% reduction** in database calls, significantly improving page load time.

## Architecture

```
LeagueStats/
├── Contracts/
│   ├── LeagueStatsRepositoryInterface.php
│   ├── LeagueStatsServiceInterface.php
│   └── LeagueStatsViewInterface.php
├── LeagueStatsRepository.php
├── LeagueStatsService.php
├── LeagueStatsView.php
└── README.md
```

### Components

| Component | Responsibility |
|-----------|----------------|
| `LeagueStatsRepository` | Bulk data fetching with single JOIN query |
| `LeagueStatsService` | Statistics processing, formatting, calculations |
| `LeagueStatsView` | HTML rendering with XSS protection |

## Usage

### Basic Usage (Module Controller)

```php
<?php
// Initialize components
$repository = new LeagueStats\LeagueStatsRepository($mysqli_db);
$service = new LeagueStats\LeagueStatsService();
$view = new LeagueStats\LeagueStatsView();

// Fetch and process data
$rawStats = $repository->getAllTeamStats();
$processedStats = $service->processTeamStats($rawStats);
$leagueTotals = $service->calculateLeagueTotals($processedStats);
$differentials = $service->calculateDifferentials($processedStats);

// Prepare data for view
$viewData = [
    'teams' => $processedStats,
    'league' => $leagueTotals,
    'differentials' => $differentials,
];

// Render output
$html = $view->render($viewData, $userTeamId);
```

### Repository Method

```php
// Returns all team stats in one query
$stats = $repository->getAllTeamStats();

// Each row contains:
// - teamid, team_city, team_name, color1, color2
// - offense_games, offense_fgm, offense_fga, ... offense_pf
// - defense_games, defense_fgm, defense_fga, ... defense_pf
```

### Service Methods

```php
// Process raw stats into formatted data
$processed = $service->processTeamStats($rawStats);
// Returns: totals (formatted), averages (formatted), raw values

// Calculate league-wide totals and averages
$league = $service->calculateLeagueTotals($processed);
// Returns: ['totals' => [...], 'averages' => [...], 'games' => int]

// Calculate offense-defense differentials
$diffs = $service->calculateDifferentials($processed);
// Returns: array with per-team differential values
```

## Database Query

The repository uses a single efficient JOIN query:

```sql
SELECT 
    ti.teamid, ti.team_city, ti.team_name, ti.color1, ti.color2,
    tos.games AS offense_games, tos.fgm AS offense_fgm, ...
    tds.games AS defense_games, tds.fgm AS defense_fgm, ...
FROM ibl_team_info ti
LEFT JOIN ibl_team_offense_stats tos ON ti.teamid = tos.teamID
LEFT JOIN ibl_team_defense_stats tds ON ti.teamid = tds.teamID
ORDER BY ti.team_city
```

## Statistics Formatting

Uses `BasketballStats\StatsFormatter` for consistent number formatting:

| Method | Output | Example |
|--------|--------|---------|
| `formatTotal()` | Comma-separated integers | "3,200" |
| `formatPerGameAverage()` | 1 decimal place | "39.0" |
| `formatPercentage()` | 3 decimal places | "0.457" |
| `formatAverage()` | 2 decimal places | "5.00" |
| `calculatePoints()` | (2×FGM) + FTM + TGM | 8900 |

## Column Naming

The database uses `tvr` for turnovers (not `to`, which is a SQL reserved word). All code references use `tvr` consistently.

## Security

- **XSS Protection:** All team names and cities are sanitized via `HtmlSanitizer::safeHtmlOutput()`
- **SQL Injection:** Uses prepared statements via `BaseMysqliRepository`

## User Experience

- **Row Highlighting:** Current user's team row is highlighted with `bgcolor="#FFA"`
- **Sortable Tables:** All tables have `class="sortable"` for client-side sorting
- **Team Links:** Each team name links to the team detail page

## Tests

Located in `tests/LeagueStats/`:

| Test File | Coverage |
|-----------|----------|
| `LeagueStatsRepositoryTest.php` | Query structure, null handling, empty results |
| `LeagueStatsServiceTest.php` | Calculations, formatting, zero-division, differentials |
| `LeagueStatsViewTest.php` | HTML structure, highlighting, sanitization |

Run tests:

```bash
cd ibl5
vendor/bin/phpunit tests/LeagueStats/
```

## Dependencies

- `BasketballStats\StatsFormatter` - Number formatting utilities
- `Utilities\HtmlSanitizer` - XSS protection
- `BaseMysqliRepository` - Database access base class

## Migration Notes

When migrating from the old procedural code:

1. The old code used `TeamStats::withTeamName()` in a loop (N+1 queries)
2. The new code uses `LeagueStatsRepository::getAllTeamStats()` (1 query)
3. Points are now calculated via `StatsFormatter::calculatePoints()` instead of relying on `TeamStats` properties
4. All output is now sanitized via `HtmlSanitizer::safeHtmlOutput()`

## Related Modules

- [Statistics](../Statistics/) - Base statistics formatting
- [Leaderboards](../Leaderboards/) - Similar pattern for player leaderboards
- [Standings](../Standings/) - Similar pattern for team standings
