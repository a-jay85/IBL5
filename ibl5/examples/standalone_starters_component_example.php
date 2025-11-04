<?php
/**
 * Example: Using StartersLineupComponent as a Standalone Component
 * 
 * This script demonstrates how to use the StartersLineupComponent
 * independently of TeamStatsService or any database interactions.
 */

// Autoload classes (in production, this would be handled by your autoloader)
require_once __DIR__ . '/../classes/UI/Components/StartersLineupComponent.php';

use UI\Components\StartersLineupComponent;

// Example 1: Render a complete starting lineup
echo "=== Example 1: Complete Starting Lineup ===\n";

$starters = [
    'PG' => ['name' => 'Stephen Curry', 'pid' => 101],
    'SG' => ['name' => 'Klay Thompson', 'pid' => 102],
    'SF' => ['name' => 'Andrew Wiggins', 'pid' => 103],
    'PF' => ['name' => 'Draymond Green', 'pid' => 104],
    'C' => ['name' => 'Kevon Looney', 'pid' => 105]
];

$component = new StartersLineupComponent();
$html = $component->render($starters, 'FFC72C', '006BB6'); // Warriors colors
echo "HTML Output (truncated):\n";
echo substr($html, 0, 200) . "...\n\n";

// Example 2: Render with partial lineup
echo "=== Example 2: Partial Lineup (Some positions empty) ===\n";

$partialStarters = [
    'PG' => ['name' => 'LeBron James', 'pid' => 201],
    'SG' => ['name' => '', 'pid' => ''],  // Empty position
    'SF' => ['name' => 'Anthony Davis', 'pid' => 203],
    'PF' => ['name' => '', 'pid' => ''],  // Empty position
    'C' => ['name' => 'Dwight Howard', 'pid' => 205]
];

$html = $component->render($partialStarters, 'FDB927', '552583'); // Lakers colors
echo "HTML Output (truncated):\n";
echo substr($html, 0, 200) . "...\n\n";

// Example 3: Different team colors
echo "=== Example 3: Different Team Colors ===\n";

$celtics = [
    'PG' => ['name' => 'Marcus Smart', 'pid' => 301],
    'SG' => ['name' => 'Jaylen Brown', 'pid' => 302],
    'SF' => ['name' => 'Jayson Tatum', 'pid' => 303],
    'PF' => ['name' => 'Al Horford', 'pid' => 304],
    'C' => ['name' => 'Robert Williams', 'pid' => 305]
];

$html = $component->render($celtics, '007A33', 'FFFFFF'); // Celtics colors
echo "Rendered successfully with Celtics colors (green/white)\n\n";

// Example 4: Integration with data from other sources
echo "=== Example 4: Data from API or JSON ===\n";

// Simulate data coming from an API
$apiResponse = json_encode([
    'PG' => ['name' => 'Damian Lillard', 'pid' => 401],
    'SG' => ['name' => 'CJ McCollum', 'pid' => 402],
    'SF' => ['name' => 'Norman Powell', 'pid' => 403],
    'PF' => ['name' => 'Robert Covington', 'pid' => 404],
    'C' => ['name' => 'Jusuf Nurkic', 'pid' => 405]
]);

$startersFromAPI = json_decode($apiResponse, true);
$html = $component->render($startersFromAPI, 'E03A3E', '000000'); // Blazers colors
echo "Successfully rendered lineup from API data\n\n";

echo "=== All Examples Completed Successfully ===\n";
echo "The component is fully standalone and can be used in any context.\n";
