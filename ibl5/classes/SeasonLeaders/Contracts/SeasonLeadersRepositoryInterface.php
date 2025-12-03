<?php

namespace SeasonLeaders\Contracts;

/**
 * SeasonLeadersRepositoryInterface - Season leaders database operations
 *
 * Handles all database operations for historical season statistics.
 */
interface SeasonLeadersRepositoryInterface
{
    /**
     * Get season leaders based on filters
     *
     * Retrieves player statistics from ibl_hist table with optional
     * filtering by year and team, sorted by specified stat category.
     *
     * @param array $filters Associative array with keys:
     *                       - 'year' (string|null): Filter by specific year, empty for all
     *                       - 'team' (int|null): Team ID filter, 0 for all teams
     *                       - 'sortBy' (string): Sort option ID (1-20), defaults to '1' (PPG)
     * @return array Result with keys:
     *               - 'result' (resource): Database query result
     *               - 'count' (int): Number of rows returned
     *
     * **Sort Options:**
     * 1=PPG, 2=REB, 3=OREB, 4=AST, 5=STL, 6=BLK, 7=TO, 8=FOUL,
     * 9=QA, 10=FGM, 11=FGA, 12=FG%, 13=FTM, 14=FTA, 15=FT%,
     * 16=TGM, 17=TGA, 18=TG%, 19=GAMES, 20=MIN
     *
     * **Behaviors:**
     * - Filters out null names
     * - Per-game stats calculated in ORDER BY clause
     * - Invalid sortBy defaults to PPG (option 1)
     */
    public function getSeasonLeaders(array $filters): array;

    /**
     * Get all teams for dropdown
     *
     * Retrieves teams from ibl_power table for filter dropdown.
     *
     * @return mixed Query result resource with teams
     *
     * **Returned Columns:**
     * - TeamID
     * - Team (name)
     *
     * **Behaviors:**
     * - Only returns TeamID 1-32 (excludes special entries)
     * - Ordered by TeamID ASC
     */
    public function getTeams();

    /**
     * Get all distinct years from history
     *
     * Retrieves unique years that have historical data.
     *
     * @return array Array of year values (strings) ordered DESC (newest first)
     */
    public function getYears(): array;
}
