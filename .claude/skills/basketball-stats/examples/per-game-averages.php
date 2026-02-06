<?php

/**
 * Basketball Statistics Per-Game Average Examples
 *
 * Demonstrates use of BasketballStats\StatsFormatter for per-game stats.
 */

use BasketballStats\StatsFormatter;

// ============================================
// Points Per Game (PPG)
// ============================================

$totalPoints = 1845;
$gamesPlayed = 72;

echo "PPG: " . StatsFormatter::formatPerGameAverage($totalPoints, $gamesPlayed) . "\n";
// Output: PPG: 25.6


// ============================================
// Assists Per Game (APG)
// ============================================

$totalAssists = 518;
$gamesPlayed = 72;

echo "APG: " . StatsFormatter::formatPerGameAverage($totalAssists, $gamesPlayed) . "\n";
// Output: APG: 7.2


// ============================================
// Rebounds Per Game (RPG)
// ============================================

$totalRebounds = 720;
$gamesPlayed = 72;

echo "RPG: " . StatsFormatter::formatPerGameAverage($totalRebounds, $gamesPlayed) . "\n";
// Output: RPG: 10.0


// ============================================
// Steals and Blocks Per Game
// ============================================

$totalSteals = 108;
$totalBlocks = 65;

echo "SPG: " . StatsFormatter::formatPerGameAverage($totalSteals, $gamesPlayed) . "\n";
// Output: SPG: 1.5

echo "BPG: " . StatsFormatter::formatPerGameAverage($totalBlocks, $gamesPlayed) . "\n";
// Output: BPG: 0.9


// ============================================
// Zero Games (Edge Case)
// ============================================

$points = 100;
$zeroGames = 0;

echo "Zero games PPG: " . StatsFormatter::formatPerGameAverage($points, $zeroGames) . "\n";
// Output: Zero games PPG: 0.0
// Safe handling of division by zero


// ============================================
// Per-36 Minute Stats
// ============================================

$totalMinutes = 2160;  // 30 minutes per game for 72 games
$pointsIn36 = StatsFormatter::formatPer36Stat($totalPoints, $totalMinutes);
$reboundsIn36 = StatsFormatter::formatPer36Stat($totalRebounds, $totalMinutes);

echo "Points per 36: " . $pointsIn36 . "\n";
// Output: Points per 36: 30.8

echo "Rebounds per 36: " . $reboundsIn36 . "\n";
// Output: Rebounds per 36: 12.0


// ============================================
// Career Totals with Comma Separators
// ============================================

$careerPoints = 28456;
$careerRebounds = 11234;
$careerAssists = 6789;

echo "Career Points: " . StatsFormatter::formatTotal($careerPoints) . "\n";
// Output: Career Points: 28,456

echo "Career Rebounds: " . StatsFormatter::formatTotal($careerRebounds) . "\n";
// Output: Career Rebounds: 11,234


// ============================================
// Calculating Points from Components
// ============================================

$fgm = 650;  // Two-pointers count for 2 each
$ftm = 320;  // Free throws count for 1 each
$tgm = 185;  // Three-pointers count for 1 additional (3-2=1)

$calculatedPoints = StatsFormatter::calculatePoints($fgm, $ftm, $tgm);
echo "Calculated Points: " . $calculatedPoints . "\n";
// Output: Calculated Points: 1805
// Formula: (2 * 650) + 320 + 185 = 1300 + 320 + 185 = 1805


// ============================================
// Full Stat Line Example
// ============================================

function renderPlayerStatLine(array $player): string
{
    $gp = $player['gp'];
    
    return sprintf(
        "%s: %s PPG, %s RPG, %s APG (%s GP)",
        \Utilities\HtmlSanitizer::safeHtmlOutput($player['name']),
        StatsFormatter::formatPerGameAverage($player['pts'], $gp),
        StatsFormatter::formatPerGameAverage($player['reb'], $gp),
        StatsFormatter::formatPerGameAverage($player['ast'], $gp),
        StatsFormatter::formatTotal($gp)
    );
}

// Example output:
// LeBron James: 25.6 PPG, 10.0 RPG, 7.2 APG (72 GP)
