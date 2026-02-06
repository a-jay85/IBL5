---
name: basketball-stats
description: Basketball statistics formatting using BasketballStats\StatsFormatter for percentages, averages, and totals. Use when displaying stats, calculating averages, or formatting basketball numbers.
---

# Basketball Statistics Formatting

Use `BasketballStats\StatsFormatter` for ALL statistic formatting. Never use `number_format()` directly.

## Available Methods

### formatPercentage($made, $attempted)
Shooting percentages with 3 decimal places.
```php
echo StatsFormatter::formatPercentage($fgm, $fga);  // "0.523"
echo StatsFormatter::formatPercentage($ftm, $fta);  // "0.875"
echo StatsFormatter::formatPercentage($tgm, $tga);  // "0.412"
```

### formatPerGameAverage($total, $games)
Per-game stats with 1 decimal place.
```php
echo StatsFormatter::formatPerGameAverage($points, $gamesPlayed);  // "25.3" PPG
echo StatsFormatter::formatPerGameAverage($assists, $gamesPlayed); // "7.2" APG
echo StatsFormatter::formatPerGameAverage($rebounds, $gamesPlayed); // "10.1" RPG
```

### formatPer36Stat($total, $minutes)
Per-36-minute stats with 1 decimal place.
```php
echo StatsFormatter::formatPer36Stat($points, $totalMinutes);  // "18.5"
echo StatsFormatter::formatPer36Stat($rebounds, $totalMinutes); // "8.3"
```

### formatTotal($value)
Counting stats with comma separators.
```php
echo StatsFormatter::formatTotal($careerPoints);   // "12,345"
echo StatsFormatter::formatTotal($careerRebounds); // "5,678"
```

### formatAverage($value)
General averages with 2 decimal places.
```php
echo StatsFormatter::formatAverage($playerRating); // "85.23"
```

### calculatePoints($fgm, $ftm, $tgm)
Calculate point totals from shot makes.
```php
$points = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
// Formula: (2 * FGM) + FTM + TGM
```

### safeDivide($numerator, $denominator)
Division with zero-division handling.
```php
$avg = StatsFormatter::safeDivide($total, $games); // Returns 0 if games=0
```

### formatPercentageWithDecimals($made, $attempted, $decimals = 3)
Shooting percentages with custom decimal places.
```php
echo StatsFormatter::formatPercentageWithDecimals($fgm, $fga, 1);  // "0.5"
echo StatsFormatter::formatPercentageWithDecimals($fgm, $fga, 4);  // "0.5230"
```

### formatWithDecimals($value, $decimals)
Format any value with custom decimal places.
```php
echo StatsFormatter::formatWithDecimals($rating, 1);  // "85.2"
echo StatsFormatter::formatWithDecimals($rating, 3);  // "85.230"
```

### calculatePythagoreanWinPercentage($pointsScored, $pointsAllowed)
Team win percentage using Daryl Morey's exponent (13.91).
```php
$winPct = StatsFormatter::calculatePythagoreanWinPercentage($ptsFor, $ptsAgainst);
// Returns formatted percentage string
```

## Usage Example

```php
use BasketballStats\StatsFormatter;

// Player stat line
$fgPct = StatsFormatter::formatPercentage($player['fgm'], $player['fga']);
$ppg = StatsFormatter::formatPerGameAverage($player['pts'], $player['gp']);
$rpg = StatsFormatter::formatPerGameAverage($player['reb'], $player['gp']);

echo "FG%: $fgPct | PPG: $ppg | RPG: $rpg";
// Output: "FG%: 0.485 | PPG: 18.5 | RPG: 7.2"
```

## Input Validation

Use `BasketballStats\StatsSanitizer` for input validation and sanitization:
```php
use BasketballStats\StatsSanitizer;

$playerId = StatsSanitizer::sanitizeInt($_GET['pid']);       // Returns 0 for null/empty
$rating = StatsSanitizer::sanitizeFloat($_POST['rating']);   // Returns 0.0 for null/empty
$name = StatsSanitizer::sanitizeString($_POST['name']);      // Returns '' for null

// Sanitize entire database rows
$row = StatsSanitizer::sanitizeRow($row, intFields: ['pid', 'tid'], floatFields: ['rating']);

// Domain-specific sanitizers
$pct = StatsSanitizer::sanitizePercentage($value);   // Clamps between 0 and 1
$gp = StatsSanitizer::sanitizeGames($value);          // Ensures non-negative int
$min = StatsSanitizer::sanitizeMinutes($value);        // Ensures non-negative float
```

## Examples

See [examples/](./examples/) for more patterns:
- [percentage-formatting.php](./examples/percentage-formatting.php)
- [per-game-averages.php](./examples/per-game-averages.php)
