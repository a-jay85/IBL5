# BasketballStats Module - Basketball Statistics Formatting and Utilities

## Overview

This module provides unified formatting and sanitization for basketball statistics throughout the IBL5 application. These classes consolidate recurring patterns, reduce code duplication, and ensure consistent display formatting.

## Classes

### StatsFormatter

A static utility class for consistent number formatting with automatic zero-division handling.

**Location:** `/ibl5/classes/BasketballStats/StatsFormatter.php`  
**Namespace:** `BasketballStats`

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
// Format counting stats with comma separators
StatsFormatter::formatTotal($value);
// Example: formatTotal(1234) returns "1,234"
```

##### Average Statistics
```php
// Format averages with 2 decimal places
StatsFormatter::formatAverage($value);
// Example: formatAverage(15.234) returns "15.23"
```

##### Point Calculation
```php
// Calculate total points from FG, FT, 3P
StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
// Example: calculatePoints(10, 5, 2) returns 27
// Formula: (FGM * 2) + FTM + TGM
```

##### Safe Division
```php
// Division with zero-division protection
StatsFormatter::safeDivide($numerator, $denominator);
// Example: safeDivide(10, 2) returns 5.0
// Example: safeDivide(10, 0) returns 0 (safe)
```

### StatsSanitizer

A static utility class for safe type conversion and input validation.

**Location:** `/ibl5/classes/BasketballStats/StatsSanitizer.php`  
**Namespace:** `BasketballStats`

#### Key Methods

##### Integer Sanitization
```php
// Safely convert to integer
StatsSanitizer::sanitizeInt($value);
// Example: sanitizeInt("10") returns 10
// Example: sanitizeInt(null) returns 0
```

##### Float Sanitization
```php
// Safely convert to float
StatsSanitizer::sanitizeFloat($value);
// Example: sanitizeFloat("10.5") returns 10.5
// Example: sanitizeFloat(null) returns 0.0
```

##### String Sanitization
```php
// Safely convert to string
StatsSanitizer::sanitizeString($value);
// Example: sanitizeString("test") returns "test"
// Example: sanitizeString(null) returns ""
```

## Usage Examples

### Before Refactoring
```php
// Old way - repeated everywhere
$ppg = ($games > 0) ? number_format($points / $games, 1) : "0.0";
$fgp = ($fga > 0) ? number_format($fgm / $fga, 3) : "0.000";
$points = (2 * $fgm) + $ftm + $tgm;
```

### After Refactoring
```php
use BasketballStats\StatsFormatter;

// New way - clean and consistent
$ppg = StatsFormatter::formatPerGameAverage($points, $games);
$fgp = StatsFormatter::formatPercentage($fgm, $fga);
$points = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
```

## Benefits

1. **Zero-Division Safety**: All methods handle division by zero automatically
2. **Null Safety**: All methods handle null values gracefully
3. **Consistency**: Same formatting rules applied everywhere
4. **Maintainability**: Update formatting in one place
5. **Readability**: Clear, self-documenting method names
6. **Type Safety**: Proper return types and parameters

## Testing

Comprehensive tests are provided in `/ibl5/tests/BasketballStats/`:
- `StatsFormatterTest.php` - 100+ test cases covering all formatting scenarios
- `StatsSanitizerTest.php` - 50+ test cases covering all sanitization scenarios

## Related Documentation

- For site/website statistics, see `SiteStatistics\` module
- For player statistics display, see `Player\` module
- For team statistics, see `TeamStats` class
