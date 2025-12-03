<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamStatsServiceInterface - Contract for team statistics calculations
 * 
 * Provides methods for extracting and rendering team statistical data,
 * particularly starting lineups and game performance metrics.
 * 
 * All methods return complete HTML strings or structured arrays, never throw exceptions.
 */
interface TeamStatsServiceInterface
{
    /**
     * Extract starting lineup data from roster result set
     * 
     * Parses a database result containing player roster with depth chart information.
     * Identifies the starting player (depth = 1) for each position.
     * 
     * @param mixed $result Database result object from roster query
     *                       Must contain fields: PGDepth, SGDepth, SFDepth, PFDepth, CDepth
     *                       And must contain fields: name, pid for each player row
     * 
     * @return array<string, array{name: string|null, pid: int|null}> Starters by position:
     *                       - Keys: 'PG', 'SG', 'SF', 'PF', 'C'
     *                       - Each has 'name' (string or null) and 'pid' (int or null)
     *                       - null values indicate position has no starter in result
     * 
     * **Behaviors:**
     * - Iterates through all rows in result
     * - For each position, finds player with depth = 1
     * - Returns null for positions with no depth-1 player
     * - Never throws exceptions
     * 
     * **Example:**
     * $starters = $service->extractStartersData($result);
     * // Returns:
     * // [
     * //   'PG' => ['name' => 'John Doe', 'pid' => 123],
     * //   'SG' => ['name' => 'Jane Smith', 'pid' => 456],
     * //   'SF' => ['name' => null, 'pid' => null],  // No starter found
     * //   'PF' => ['name' => 'Bob Jones', 'pid' => 789],
     * //   'C'  => ['name' => 'Alice Brown', 'pid' => 101]
     * // ]
     */
    public function extractStartersData(mixed $result): array;

    /**
     * Render HTML table for team's last simulation starting lineup
     * 
     * Extracts starting lineup from result and renders as styled HTML table
     * using team colors. Table shows the five starters by position.
     * 
     * @param mixed $result Database result object with player roster and depth chart
     * @param object $team Team object with color1 and color2 properties for styling
     *                     - color1: Primary team color (background)
     *                     - color2: Secondary team color (text/accent)
     * 
     * @return string Complete HTML table with last sim's starting lineup
     * 
     * **Return Structure:**
     * - HTML table with 5 rows (one per position)
     * - Styled with team's color1 and color2
     * - Shows player name and position for each starter
     * - If no starter for a position, row is empty or shows placeholder
     * 
     * **Behaviors:**
     * - All output is HTML-safe (escaped properly)
     * - Returns complete table HTML (ready to echo)
     * - Never throws exceptions
     */
    public function getLastSimsStarters(mixed $result, object $team): string;
}
