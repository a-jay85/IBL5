<?php

declare(strict_types=1);

namespace PlayerSearch\Contracts;

/**
 * PlayerSearchRepositoryInterface - Contract for database search operations
 * 
 * Defines what methods are available for searching players and their exact
 * return structures. This eliminates any guessing about parameter expectations
 * or result format.
 * 
 * All methods use prepared statements internally – SQL injection is prevented.
 * All methods accept validated parameters from PlayerSearchValidatorInterface.
 */
interface PlayerSearchRepositoryInterface
{
    /**
     * Search for players based on validated criteria
     * 
     * Executes a database query to find players matching all provided criteria.
     * Uses dynamic WHERE clause building with prepared statements.
     * 
     * @param array<string, int|string|null> $params Validated parameters from PlayerSearchValidatorInterface
     * @return array{
     *     results: array<int, array<string, mixed>>,
     *     count: int
     * } Search results with these guaranteed properties:
     *     - results: array of ibl_plr table rows (each row is array<string, mixed>)
     *     - count: total number of matching players
     * 
     * IMPORTANT BEHAVIORS:
     *  - Filters with null values are SKIPPED (not applied to WHERE clause)
     *  - If no filters produce conditions, all active players are returned
     *  - Each row in results is a complete ibl_plr database row (all columns)
     *  - Returns array{results: [], count: 0} if no matches (never null)
     *  - Results are ordered by: retired ASC, ordinal ASC
     *  - All string searches use LIKE with % wildcards (case-insensitive)
     *  - All numeric filters use >= (greater than or equal)
     *  - NEVER throws exceptions – returns empty results on error instead
     * 
     * PARAMETER MAPPING:
     *  - pos: exact match (PG, SG, SF, PF, C)
     *  - search_name: LIKE search on name column
     *  - college: LIKE search on college column
     *  - age: <= (less than or equal)
     *  - exp, exp_max: >= and <= (experience range)
     *  - bird, bird_max: >= and <= (bird years range)
     *  - All r_* fields: >= (rating filters)
     *  - All attribute fields: >= (clutch, skill, talent, etc.)
     *  - All skill fields: >= (oo, do, po, to, od, dd, pd, td)
     *  - active: if 0, adds "retired = 0" (active players only)
     * 
     * Examples:
     *  $result = $repo->searchPlayers(['pos' => 'PG', 'exp' => 2, 'age' => null]);
     *  // Returns active PG with 2+ years experience, any age
     *  $result['count'] = 15;
     *  $result['results'][0] = ['pid' => 123, 'name' => 'John', 'pos' => 'PG', ...]
     *  
     *  $result = $repo->searchPlayers(['pos' => null, 'exp' => null]);
     *  // All params null - returns all active players
     *  $result['count'] = 500;
     */
    public function searchPlayers(array $params): array;

    /**
     * Get a single player by ID
     * 
     * @param int $pid Player ID (pid from ibl_plr table)
     * @return array<string, mixed>|null Complete player row or null if not found
     * 
     * IMPORTANT BEHAVIORS:
     *  - Returns a complete row from ibl_plr table (all columns)
     *  - Returns null if player ID doesn't exist
     *  - Uses prepared statement (safe from SQL injection)
     *  - NEVER throws exceptions
     * 
     * Examples:
     *  $player = $repo->getPlayerById(123);
     *  // Returns ['pid' => 123, 'name' => 'John', 'pos' => 'PG', ...]
     *  
     *  $player = $repo->getPlayerById(999999);
     *  // Returns null (player doesn't exist)
     */
    public function getPlayerById(int $pid): ?array;
}
