# Statistics Formatting and Sanitization Guide

## Overview

This guide explains the new unified statistics formatting and sanitization classes that consolidate recurring patterns across the IBL5 codebase. These classes reduce code duplication, ensure consistent display formatting, and simplify statistics handling throughout the site.

## The Problem

Before this refactoring, statistics formatting was scattered throughout the codebase with:
- Repeated ternary operators for zero-division checks: `($games) ? number_format($total / $games, 1) : "0"`
- Inconsistent decimal places across similar statistics
- Copy-pasted formatting logic in multiple files
- Manual point calculations: `2 * $fgm + $ftm + $tgm`
- Duplicate sanitization patterns

## The Solution

Two new classes provide unified formatting and sanitization:

### 1. `Statistics\StatsFormatter`

A static utility class for consistent number formatting with automatic zero-division handling.

**Location:** `/ibl5/classes/Statistics/StatsFormatter.php`

#### Key Methods

##### Percentage Formatting
```php
// Format shooting percentages (FG%, FT%, 3P%) - 3 decimal places
StatsFormatter::formatPercentage($made, $attempted);
// Example: formatPercentage(5, 10) returns "0.500"
// Example: formatPercentage(5, 0) returns "0.000" (safe)
```

##### Per-Game Averages
```php
// Format per-game statistics - 1 decimal place
StatsFormatter::formatPerGameAverage($total, $games);
// Example: formatPerGameAverage(100, 10) returns "10.0"
// Example: formatPerGameAverage(100, 0) returns "0.0" (safe)
```

##### Per-36-Minute Statistics
```php
// Format per-36-minute stats - 1 decimal place
StatsFormatter::formatPer36Stat($total, $minutes);
// Example: formatPer36Stat(10, 20) returns "18.0"
// Example: formatPer36Stat(10, 0) returns "0.0" (safe)
```

##### Total Statistics
```php
// Format counting stats (points, rebounds, etc.) - comma-separated integers
StatsFormatter::formatTotal($value);
// Example: formatTotal(1234) returns "1,234"
// Example: formatTotal(null) returns "0"
```

##### Career Averages
```php
// Format detailed averages - 2 decimal places
StatsFormatter::formatAverage($value);
// Example: formatAverage(12.3456) returns "12.35"
```

##### Points Calculation
```php
// Calculate total points from field goals, free throws, and three-pointers
StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
// Formula: (2 × FGM) + FTM + 3PM
// Example: calculatePoints(10, 3, 2) returns 25
```

##### Safe Division
```php
// Perform division with automatic zero-division protection
StatsFormatter::safeDivide($numerator, $denominator);
// Returns 0.0 if denominator is 0 or null
```

##### Custom Formatting
```php
// Format percentage with custom decimal places
StatsFormatter::formatPercentageWithDecimals($made, $attempted, $decimals);

// Format any value with custom decimal places
StatsFormatter::formatWithDecimals($value, $decimals);
```

### 2. `Statistics\StatsSanitizer`

A static utility class for safe type conversion and input sanitization.

**Location:** `/ibl5/classes/Statistics/StatsSanitizer.php`

#### Key Methods

```php
// Safely convert to integer (returns 0 for null/empty)
StatsSanitizer::sanitizeInt($value);

// Safely convert to float (returns 0.0 for null/empty)
StatsSanitizer::sanitizeFloat($value);

// Safely convert to string (returns "" for null)
StatsSanitizer::sanitizeString($value);

// Sanitize percentage to 0-1 range
StatsSanitizer::sanitizePercentage($percentage);

// Sanitize games (non-negative integer)
StatsSanitizer::sanitizeGames($games);

// Sanitize minutes (non-negative float)
StatsSanitizer::sanitizeMinutes($minutes);

// Sanitize entire database row
StatsSanitizer::sanitizeRow($row, $intFields, $floatFields);
```

## Usage Examples

### Before (Old Pattern)

```php
// TeamStats.php - old pattern
$this->seasonOffensePointsPerGame = ($this->seasonOffenseGamesPlayed) 
    ? number_format(($this->seasonOffenseTotalPoints / $this->seasonOffenseGamesPlayed), 1) 
    : "0";

$this->seasonOffenseFieldGoalPercentage = ($this->seasonOffenseTotalFieldGoalsAttempted) 
    ? number_format(($this->seasonOffenseTotalFieldGoalsMade / $this->seasonOffenseTotalFieldGoalsAttempted), 3) 
    : "0.000";
```

### After (New Pattern)

```php
// TeamStats.php - new pattern
use Statistics\StatsFormatter;

$this->seasonOffensePointsPerGame = StatsFormatter::formatPerGameAverage(
    $this->seasonOffenseTotalPoints, 
    $this->seasonOffenseGamesPlayed
);

$this->seasonOffenseFieldGoalPercentage = StatsFormatter::formatPercentage(
    $this->seasonOffenseTotalFieldGoalsMade, 
    $this->seasonOffenseTotalFieldGoalsAttempted
);
```

### PlayerStats Example

```php
// PlayerStats.php
use Statistics\StatsFormatter;

// Calculate points
$this->seasonPoints = StatsFormatter::calculatePoints(
    $this->seasonFieldGoalsMade, 
    $this->seasonFreeThrowsMade, 
    $this->seasonThreePointersMade
);

// Format per-game stats
$this->seasonPointsPerGame = StatsFormatter::formatPerGameAverage(
    $this->seasonPoints, 
    $this->seasonGamesPlayed
);

// Format percentages
$this->seasonFieldGoalPercentage = StatsFormatter::formatPercentage(
    $this->seasonFieldGoalsMade, 
    $this->seasonFieldGoalsAttempted
);
```

### UI Display Example

```php
// UI.php
use Statistics\StatsFormatter;

// Per-36 minute stats
$stats_ppg = StatsFormatter::formatPer36Stat(
    $playerStats->seasonPoints, 
    $playerStats->seasonMinutes
);

$stats_fgp = StatsFormatter::formatPercentage(
    $stats_fgm, 
    $stats_fga
);
```

### Leaderboards Example

```php
// modules/Leaderboards/index.php
use Statistics\StatsFormatter;

// For averages
$minutes = StatsFormatter::formatAverage($row["minutes"]);
$points = StatsFormatter::formatAverage($row["pts"]);

// For totals
$games = StatsFormatter::formatTotal($row["games"]);
$rebounds = StatsFormatter::formatTotal($row["reb"]);

// For percentages
$fgp = StatsFormatter::formatPercentage($row["fgm"], $row["fga"]);
```

## Refactored Files

The following files have been updated to use the new formatter:

1. **TeamStats.php** - All offense and defense per-game averages and percentages
2. **PlayerStats.php** - Both `fill()` and `fillHistorical()` methods
3. **UI.php** - `per36Minutes()` function for per-36 stat calculations
4. **modules/Leaderboards/index.php** - Both totals and averages displays

## Testing

Comprehensive unit tests ensure the formatters work correctly:

- **tests/Statistics/StatsFormatterTest.php** - 9 tests covering all formatting methods
- **tests/Statistics/StatsSanitizerTest.php** - 7 tests covering all sanitization methods

Run tests with:
```bash
cd /home/runner/work/IBL5/IBL5/ibl5
vendor/bin/phpunit tests/Statistics/
```

All 330 existing tests continue to pass, confirming no regressions.

## Benefits

1. **Reduced Code Duplication** - Eliminates hundreds of lines of repeated formatting logic
2. **Consistent Display** - Guarantees uniform decimal places across the site
3. **Safer Operations** - Automatic zero-division handling prevents errors
4. **Easier Maintenance** - Single source of truth for formatting rules
5. **Better Testing** - Formatting logic is now thoroughly unit tested
6. **Improved Readability** - Self-documenting method names make code clearer

## Formatting Standards

The formatter enforces these consistent standards:

| Stat Type | Decimal Places | Example | Method |
|-----------|----------------|---------|--------|
| Shooting % (FG%, FT%, 3P%) | 3 | "0.523" | `formatPercentage()` |
| Per-game averages | 1 | "12.5" | `formatPerGameAverage()` |
| Per-36 stats | 1 | "18.3" | `formatPer36Stat()` |
| Totals (games, points, etc.) | 0 (comma-separated) | "1,234" | `formatTotal()` |
| Career averages | 2 | "15.23" | `formatAverage()` |

## Future Enhancements

Additional files that could benefit from these formatters:

- Player view files (RegularSeasonAveragesView.php, etc.)
- Boxscore displays
- Team comparison pages
- Season leaders pages
- Any other files with statistics display logic

## Questions?

For questions or issues with the statistics formatters, please refer to:
- The unit tests for usage examples
- The class documentation comments
- This guide for standard patterns
