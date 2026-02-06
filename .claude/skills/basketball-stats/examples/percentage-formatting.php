<?php

/**
 * Basketball Statistics Percentage Formatting Examples
 *
 * Demonstrates use of BasketballStats\StatsFormatter for percentage stats.
 */

use BasketballStats\StatsFormatter;

// ============================================
// Field Goal Percentage (FG%)
// ============================================

$fgm = 245;  // Field Goals Made
$fga = 520;  // Field Goals Attempted

echo "FG%: " . StatsFormatter::formatPercentage($fgm, $fga) . "\n";
// Output: FG%: 0.471


// ============================================
// Three-Point Percentage (3P%)
// ============================================

$tgm = 89;   // Three-Pointers Made
$tga = 241;  // Three-Pointers Attempted

echo "3P%: " . StatsFormatter::formatPercentage($tgm, $tga) . "\n";
// Output: 3P%: 0.369


// ============================================
// Free Throw Percentage (FT%)
// ============================================

$ftm = 156;  // Free Throws Made
$fta = 178;  // Free Throws Attempted

echo "FT%: " . StatsFormatter::formatPercentage($ftm, $fta) . "\n";
// Output: FT%: 0.876


// ============================================
// Handling Zero Attempts (Edge Case)
// ============================================

$zeroFgm = 0;
$zeroFga = 0;

echo "No shots FG%: " . StatsFormatter::formatPercentage($zeroFgm, $zeroFga) . "\n";
// Output: No shots FG%: 0.000
// Safe division - returns 0.000 instead of division error


// ============================================
// Handling Null Values
// ============================================

$nullMade = null;
$nullAttempted = 10;

echo "Null made FG%: " . StatsFormatter::formatPercentage($nullMade, $nullAttempted) . "\n";
// Output: Null made FG%: 0.000
// Null is treated as 0


// ============================================
// Using safeDivide for Custom Calculations
// ============================================

$total = 250;
$games = 0;

// Direct safeDivide for custom calculations
$average = StatsFormatter::safeDivide($total, $games);
echo "Safe average: " . $average . "\n";
// Output: Safe average: 0
// No division by zero error


// ============================================
// Custom Decimal Places
// ============================================

echo "FG% (1 decimal): " . StatsFormatter::formatPercentageWithDecimals($fgm, $fga, 1) . "\n";
// Output: FG% (1 decimal): 0.5

echo "FG% (4 decimals): " . StatsFormatter::formatPercentageWithDecimals($fgm, $fga, 4) . "\n";
// Output: FG% (4 decimals): 0.4712


// ============================================
// Rendering in a Table
// ============================================

function renderPlayerShootingStats(array $player): string
{
    return sprintf(
        "<tr><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>",
        \Utilities\HtmlSanitizer::safeHtmlOutput($player['name']),
        StatsFormatter::formatPercentage($player['fgm'], $player['fga']),
        StatsFormatter::formatPercentage($player['tgm'], $player['tga']),
        StatsFormatter::formatPercentage($player['ftm'], $player['fta'])
    );
}

// Example output:
// <tr><td>John Smith</td><td>0.471</td><td>0.369</td><td>0.876</td></tr>
