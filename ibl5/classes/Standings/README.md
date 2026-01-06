# Standings Module

The Standings module displays league standings for conferences and divisions.

## Architecture

This module follows the interface-driven architecture pattern with separation of concerns:

```
Standings/
├── Contracts/
│   ├── StandingsRepositoryInterface.php  # Data access contract
│   └── StandingsViewInterface.php        # View rendering contract
├── StandingsRepository.php               # Data access implementation
├── StandingsView.php                     # HTML rendering
└── README.md                             # This file
```

## Usage

### In Module Page (index.php)

```php
<?php
global $db;

$repository = new Standings\StandingsRepository($db);
$view = new Standings\StandingsView($repository);

echo $view->render();
```

### Render a Specific Region

```php
$repository = new Standings\StandingsRepository($db);
$view = new Standings\StandingsView($repository);

// Render only Eastern Conference
echo $view->renderRegion('Eastern');

// Render only Pacific Division
echo $view->renderRegion('Pacific');
```

### Access Raw Data

```php
$repository = new Standings\StandingsRepository($db);

// Get standings for a conference
$easternStandings = $repository->getStandingsByRegion('Eastern');

// Get streak data for a team
$streakData = $repository->getTeamStreakData($teamId);
```

## Valid Regions

- **Conferences:** `Eastern`, `Western`
- **Divisions:** `Atlantic`, `Central`, `Midwest`, `Pacific`

## Clinched Indicators

Teams that have clinched playoff positions display prefixes:
- **Z-** Clinched conference
- **Y-** Clinched division
- **X-** Clinched playoffs

## Database Tables

- `ibl_standings` - Team standings data
- `ibl_power` - Team streak and last 10 games data

## Migration from StandingsHTMLGenerator

The previous implementation in `Updater\StandingsHTMLGenerator` stored pre-generated HTML in the `nuke_pages` table. This new implementation:

1. **Generates HTML dynamically** - No more stale data in database
2. **Separates concerns** - Repository for data, View for HTML
3. **Adds XSS protection** - All output is properly escaped
4. **Uses interfaces** - Enables dependency injection and testing

The `StandingsHTMLGenerator` class in `Updater/` is now obsolete and can be removed.

## Security

- All HTML output is escaped with `htmlspecialchars()`
- Team IDs are cast to integers for URL generation
- SQL queries use prepared statements

## Testing

Tests should be added to `ibl5/tests/Standings/`:
- `StandingsRepositoryTest.php` - Test data retrieval
- `StandingsViewTest.php` - Test HTML generation
