<?php

declare(strict_types=1);

namespace ComparePlayers\Contracts;

/**
 * ComparePlayersServiceInterface - Business logic for player comparison
 * 
 * Orchestrates validation, data retrieval, and comparison processing.
 * Returns structured comparison data ready for view rendering.
 */
interface ComparePlayersServiceInterface
{
    /**
     * Get all active player names for autocomplete
     * 
     * Simple pass-through to repository for player names.
     * Used to populate JavaScript autocomplete dropdown on form.
     * 
     * @return array<int, string> Array of player names sorted alphabetically
     * 
     * Examples:
     *  $names = $service->getPlayerNames();
     *  // Returns ['Allen Iverson', 'Kobe Bryant', 'Tim Duncan', ...]
     */
    public function getPlayerNames(): array;

    /**
     * Compare two players and return structured comparison data
     * 
     * Validates player names exist, retrieves their complete data,
     * and structures it for side-by-side comparison display.
     * 
     * Returns null if either player doesn't exist (validation failure).
     * Returns comparison array with both players' data if valid.
     * 
     * @param string $player1Name Name of first player to compare
     * @param string $player2Name Name of second player to compare
     * @return array{player1: array<string, mixed>, player2: array<string, mixed>}|null
     *     Comparison data or null if either player not found
     * 
     * RETURNED STRUCTURE (when valid):
     * [
     *   'player1' => [
     *     'pid' => 123,
     *     'name' => 'Michael Jordan',
     *     'pos' => 'SG',
     *     'age' => 28,
     *     'teamname' => 'Chicago Bulls',
     *     // All ratings (r_*)
     *     'r_fga' => 85, 'r_fgp' => 90, ...
     *     // All skills
     *     'oo' => 95, 'do' => 90, 'po' => 85, 'to' => 95,
     *     'od' => 85, 'dd' => 80, 'pd' => 75, 'td' => 90,
     *     // Current season stats (stats_*)
     *     'stats_gm' => 82, 'stats_gs' => 82, 'stats_min' => 2880, ...
     *     'stats_fgm' => 820, 'stats_fga' => 1600, ...
     *     // Career stats (car_*)
     *     'car_gm' => 500, 'car_min' => 18000, ...
     *     'car_pts' => 15000, ...
     *   ],
     *   'player2' => [
     *     // Same structure for second player
     *   ]
     * ]
     * 
     * IMPORTANT BEHAVIORS:
     *  - Validates both player names are non-empty strings
     *  - Returns null if either player name is empty
     *  - Returns null if either player doesn't exist in database
     *  - Both players must be found for comparison to proceed
     *  - Returns complete player data (all ibl_plr columns)
     *  - NEVER throws exceptions - returns null on error
     * 
     * VALIDATION RULES:
     *  - Player names trimmed of whitespace
     *  - Empty strings after trim treated as invalid
     *  - Names must exist in database (exact match)
     *  - Both players required (no single-player mode)
     * 
     * Examples:
     *  $result = $service->comparePlayers('Michael Jordan', 'Kobe Bryant');
     *  // Returns ['player1' => [...], 'player2' => [...]]
     *  
     *  $result = $service->comparePlayers('NonExistent', 'Kobe Bryant');
     *  // Returns null (first player not found)
     *  
     *  $result = $service->comparePlayers('', 'Kobe Bryant');
     *  // Returns null (empty player name)
     *  
     *  $result = $service->comparePlayers("O'Neal", 'Duncan');
     *  // Returns comparison (handles apostrophes safely)
     */
    public function comparePlayers(string $player1Name, string $player2Name): ?array;
}
