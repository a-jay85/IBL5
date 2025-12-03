<?php

declare(strict_types=1);

namespace PlayerSearch\Contracts;

use Player\PlayerData;

/**
 * PlayerSearchServiceInterface - Contract for the player search business logic
 * 
 * Defines the public API for the player search service. This is the main entry point
 * that orchestrates validation, database search, and data transformation.
 * 
 * The service returns PlayerData objects (not raw database arrays) for type-safe handling.
 */
interface PlayerSearchServiceInterface
{
    /**
     * Execute a player search based on form parameters
     * 
     * Orchestrates the complete search workflow:
     * 1. Validates all parameters using PlayerSearchValidatorInterface
     * 2. Executes search using PlayerSearchRepositoryInterface
     * 3. Converts raw database rows to PlayerData objects
     * 4. Returns results with original validated parameters
     * 
     * @param array<string, mixed> $rawParams Raw POST parameters from form
     * @return array{
     *     players: array<int, PlayerData>,
     *     count: int,
     *     params: array<string, int|string|null>
     * } Search results with these properties:
     *     - players: array of PlayerData objects (type-safe player objects)
     *     - count: total number of matching players
     *     - params: validated parameters used for search (for form re-population)
     * 
     * IMPORTANT BEHAVIORS:
     *  - If $rawParams is empty (no form submission), returns empty results
     *  - All parameters are validated before database search
     *  - Each player result is a fully-populated PlayerData object
     *  - Returns params so form can be re-populated with user's search criteria
     *  - NEVER throws exceptions – returns empty results on error
     *  - Results include active and retired players (unless filtered by 'active' param)
     * 
     * WORKFLOW EXAMPLE:
     *  1. User submits search form with POST data
     *  2. search(['pos' => 'PG', 'exp' => 2, ...])
     *  3. Validator sanitizes to ['pos' => 'PG', 'exp' => 2, ...]
     *  4. Repository finds matching players
     *  5. Service converts each to PlayerData object
     *  6. Returns [
     *       'players' => [PlayerData, PlayerData, ...],
     *       'count' => 15,
     *       'params' => ['pos' => 'PG', 'exp' => 2, ...]
     *     ]
     * 
     * Examples:
     *  $result = $service->search(['pos' => 'PG', 'exp' => 2]);
     *  $result['count'] = 15;
     *  $result['players'][0]->playerID = 123; // PlayerData object
     *  $result['params']['pos'] = 'PG';
     *  
     *  $result = $service->search([]); // No submission
     *  $result['players'] = [];
     *  $result['count'] = 0;
     *  $result['params'] = ['pos' => null, 'age' => null, ...];
     */
    public function search(array $rawParams): array;
}
