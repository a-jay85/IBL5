# Player View Classes - Usage Guide

## Overview

The Player View classes provide clean separation between data fetching (via PlayerRepository) and rendering. All database queries are encapsulated in the repository, while views focus purely on HTML generation.

## Architecture

```
PlayerPageService
    ├── PlayerRepository (mysqli-ready)
    └── PlayerViewFactory
            ├── PlayerAwardsView
            ├── PlayerGameLogView
            ├── PlayerSeasonStatsView
            ├── PlayerPlayoffStatsView
            ├── PlayerHeatStatsView
            └── PlayerOlympicsStatsView
```

## Basic Usage

### Setup

```php
<?php
// In your module (e.g., 2003olympics/modules/Player/index.php)

use Player\PlayerPageService;

// Initialize service (automatically creates repository and view factory)
$playerService = new PlayerPageService($db);

// Get view factory
$viewFactory = $playerService->getViewFactory();
```

### Using Individual Views

#### Awards View

```php
// Create view instance
$awardsView = $viewFactory->createAwardsView();

// Render All-Star activity table
echo $awardsView->renderAllStarActivity($player->name);

// Render full awards list
echo $awardsView->renderAwardsList($player->name);
```

**Old Code (direct DB queries):**
```php
// ❌ Before - 40+ lines of SQL queries
$allstarquery = $db->sql_query("SELECT * FROM ibl_awards WHERE name='$player->name' AND Award LIKE '%Conference All-Star'");
$asg = $db->sql_numrows($allstarquery);
// ... more queries ...
echo "<tr><td><b>All Star Games:</b></td><td>$asg</td></tr>";
```

**New Code (repository-based):**
```php
// ✅ After - 1 line
echo $viewFactory->createAwardsView()->renderAllStarActivity($player->name);
```

#### Game Log View (Sim Stats)

```php
// Create view instance
$gameLogView = $viewFactory->createGameLogView();

// Render sim-by-sim statistics
echo $gameLogView->renderSimStats($playerID);
```

**Benefits:**
- Automatic calculation of averages per sim
- Proper HTML escaping
- Clean separation of concerns

#### Season Stats View

```php
// Create view instance
$seasonStatsView = $viewFactory->createSeasonStatsView();

// Render season totals
echo $seasonStatsView->renderSeasonTotals($playerID);

// Render season averages
echo $seasonStatsView->renderSeasonAverages($playerID);
```

**Old Code:**
```php
// ❌ Before - inline queries and rendering mixed together
$result44 = $db->sql_query("SELECT * FROM ibl_hist WHERE pid=$playerID ORDER BY year ASC");
while ($row44 = $db->sql_fetch_assoc($result44)) {
    echo "<tr><td>" . $row44['team'] . "</td>";
    // ... 50+ lines of output logic ...
}
```

**New Code:**
```php
// ✅ After - clean separation
echo $viewFactory->createSeasonStatsView()->renderSeasonTotals($playerID);
```

#### Playoff Stats View

```php
$playoffStatsView = $viewFactory->createPlayoffStatsView();

// Render playoff totals
echo $playoffStatsView->renderPlayoffTotals($player->name);

// Render playoff averages
echo $playoffStatsView->renderPlayoffAverages($player->name);
```

#### Heat Stats View

```php
$heatStatsView = $viewFactory->createHeatStatsView();

echo $heatStatsView->renderHeatTotals($player->name);
echo $heatStatsView->renderHeatAverages($player->name);
```

#### Olympics Stats View

```php
$olympicsStatsView = $viewFactory->createOlympicsStatsView();

echo $olympicsStatsView->renderOlympicsTotals($player->name);
echo $olympicsStatsView->renderOlympicsAverages($player->name);
```

## Complete Example: Player Page

```php
<?php
// modules/Player/index.php

use Player\Player;
use Player\PlayerPageService;

// Initialize
$playerID = (int) $_GET['pid'];
$spec = (int) $_GET['spec'];

$playerService = new PlayerPageService($db);
$viewFactory = $playerService->getViewFactory();

$player = Player::withPlayerID($db, $playerID);

// Page header (existing code)
NukeHeader::header();
OpenTable();

// === SIM STATS (spec == 10) ===
if ($spec == 10) {
    $gameLogView = $viewFactory->createGameLogView();
    echo $gameLogView->renderSimStats($playerID);
}

// === SEASON TOTALS (spec == 3) ===
if ($spec == 3) {
    $seasonStatsView = $viewFactory->createSeasonStatsView();
    echo $seasonStatsView->renderSeasonTotals($playerID);
}

// === SEASON AVERAGES (spec == 4) ===
if ($spec == 4) {
    $seasonStatsView = $viewFactory->createSeasonStatsView();
    echo $seasonStatsView->renderSeasonAverages($playerID);
}

// === PLAYOFF TOTALS (spec == 5) ===
if ($spec == 5) {
    $playoffStatsView = $viewFactory->createPlayoffStatsView();
    echo $playoffStatsView->renderPlayoffTotals($player->name);
}

// === AWARDS (spec == 20) ===
if ($spec == 20) {
    $awardsView = $viewFactory->createAwardsView();
    echo $awardsView->renderAwardsList($player->name);
}

CloseTable();
include "footer.php";
```

## Benefits of This Architecture

### 1. **Security**
- ✅ All queries use prepared statements via PlayerRepository
- ✅ Automatic HTML escaping via `HtmlSanitizer::safeHtmlOutput()`
- ✅ No SQL injection vulnerabilities

### 2. **Maintainability**
- ✅ Single source of truth for queries (PlayerRepository)
- ✅ Easy to update queries without touching view code
- ✅ Clear separation of concerns

### 3. **Testing**
- ✅ Views can be tested by mocking PlayerRepository
- ✅ Repository can be tested independently
- ✅ No global database dependencies

### 4. **Consistency**
- ✅ All statistics formatting via StatsFormatter
- ✅ Consistent HTML structure
- ✅ Reusable across multiple pages

## Migration Checklist

When refactoring player pages to use views:

- [ ] Identify all `$db->sql_query()` calls in the page
- [ ] Map queries to existing PlayerRepository methods
- [ ] Replace inline queries with appropriate view method calls
- [ ] Remove direct database access from page logic
- [ ] Test rendered output matches original
- [ ] Run PHPUnit tests to verify

## Repository Methods Available

- `getAllStarGameCount(string $playerName): int`
- `getThreePointContestCount(string $playerName): int`
- `getDunkContestCount(string $playerName): int`
- `getRookieSophChallengeCount(string $playerName): int`
- `getAllSimDates(): array`
- `getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array`
- `getHistoricalStats(int $playerID): array`
- `getPlayoffStats(string $playerName): array`
- `getHeatStats(string $playerName): array`
- `getOlympicsStats(string $playerName): array`
- `getAwards(string $playerName): array`

## Notes

- All views use `StatsFormatter` for consistent percentage/average formatting
- Views handle empty datasets gracefully (skip empty rows)
- HTML output is compatible with existing page structure
- No breaking changes to existing pages required

## Future Enhancements

Additional views can be created for:
- Player contract information
- Player draft details
- Player transaction history
- Player news/articles
- Ratings and salary history

Simply extend `PlayerRepository` with new methods and create corresponding view classes following the established pattern.
