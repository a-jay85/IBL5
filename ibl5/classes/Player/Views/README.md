# Player View Classes - Usage Guide

## Overview

The Player View classes provide clean separation between data fetching (via PlayerRepository) and rendering. All database queries are encapsulated in the repository, while views focus purely on HTML generation.

## Centralized Styles

All Player Views use centralized CSS defined in `design/components/player-views.css` and `design/components/player-cards.css`. No PHP style classes are needed — CSS is loaded via the Tailwind build pipeline.

## Architecture

```
PlayerPageService
    ├── PlayerRepository (mysqli-ready)
    ├── PlayerStatsRepository
    └── PlayerViewFactory
            ├── PlayerOverviewView
            ├── PlayerAwardsAndNewsView
            ├── PlayerSimStatsView
            ├── PlayerRegularSeasonTotalsView
            ├── PlayerRegularSeasonAveragesView
            ├── PlayerPlayoffTotalsView
            ├── PlayerPlayoffAveragesView
            ├── PlayerHeatTotalsView
            ├── PlayerHeatAveragesView
            ├── PlayerOlympicTotalsView
            ├── PlayerOlympicAveragesView
            ├── PlayerRatingsAndSalaryView
            └── PlayerOneOnOneView
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

#### Awards & News View

```php
// Create view instance
$awardsView = $viewFactory->createAwardsAndNewsView();

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
echo $viewFactory->createAwardsAndNewsView()->renderAwardsList($player->name);
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

#### Season Stats Views

```php
// Season totals
echo $viewFactory->createRegularSeasonTotalsView()->render($playerID);

// Season averages
echo $viewFactory->createRegularSeasonAveragesView()->render($playerID);
```

#### Playoff Stats Views

```php
echo $viewFactory->createPlayoffTotalsView()->render($player->name);
echo $viewFactory->createPlayoffAveragesView()->render($player->name);
```

#### Heat Stats Views

```php
echo $viewFactory->createHeatTotalsView()->render($player->name);
echo $viewFactory->createHeatAveragesView()->render($player->name);
```

#### Olympic Stats Views

```php
echo $viewFactory->createOlympicTotalsView()->render($player->name);
echo $viewFactory->createOlympicAveragesView()->render($player->name);
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
    echo $viewFactory->createSimStatsView()->render($playerID);
}

// === SEASON TOTALS (spec == 3) ===
if ($spec == 3) {
    echo $viewFactory->createRegularSeasonTotalsView()->render($playerID);
}

// === SEASON AVERAGES (spec == 4) ===
if ($spec == 4) {
    echo $viewFactory->createRegularSeasonAveragesView()->render($playerID);
}

// === PLAYOFF TOTALS (spec == 5) ===
if ($spec == 5) {
    echo $viewFactory->createPlayoffTotalsView()->render($player->name);
}

// === AWARDS (spec == 20) ===
if ($spec == 20) {
    echo $viewFactory->createAwardsAndNewsView()->renderAwardsList($player->name);
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
