<?php

/**
 * Trading_UIHelperInterface - Trade form UI rendering helpers
 *
 * Provides methods for building and rendering trade form components
 * including player lists, draft picks, and team selection.
 */
interface Trading_UIHelperInterface
{
    /**
     * Build team future salary data and HTML for trade form
     *
     * Iterates through team players, calculates future salary commitments,
     * and outputs HTML rows for each player in the trade form.
     *
     * @param resource $resultTeamPlayers Database result from player query
     * @param int $k Starting counter for form field numbering
     * @return array Future salary data with keys:
     *               - 'player' (array): Salary totals by future year index (0-5)
     *               - 'hold' (array): Player counts with salary by future year index
     *               - 'picks' (array): Placeholder for pick data
     *               - 'k' (int): Updated counter after processing all players
     *
     * **Behaviors:**
     * - Adjusts contract year forward during Playoffs/Draft/Free Agency phases
     * - Outputs HTML directly via echo (side effect)
     * - Players with ordinal > JSB::WAIVERS_ORDINAL shown but not checkable
     * - Players with 0 contract shown but not checkable
     * - Future years calculated from current contract year through year 6
     *
     * **HTML Output:**
     * Each player row includes hidden fields for index, contract, and type,
     * plus visible fields for position, name, and contract amount.
     */
    public function buildTeamFutureSalary($resultTeamPlayers, $k);

    /**
     * Build team future draft picks data and HTML for trade form
     *
     * Iterates through team draft picks and outputs HTML rows for each
     * pick in the trade form.
     *
     * @param resource $resultTeamPicks Database result from picks query
     * @param array $futureSalaryArray Existing future salary array to update
     * @return array Updated future salary array with 'k' counter incremented
     *
     * **Behaviors:**
     * - Outputs HTML directly via echo (side effect)
     * - All picks are checkable (tradeable)
     * - Pick notes displayed on separate row if present
     * - Uses counter 'k' from input array, returns updated value
     *
     * **HTML Output:**
     * Each pick row includes hidden fields for index and type,
     * plus visible fields showing year, team, and round.
     */
    public function buildTeamFuturePicks($resultTeamPicks, $futureSalaryArray);

    /**
     * Get list of all teams for partner selection dropdown
     *
     * Retrieves all teams from ibl_team_info ordered by city name,
     * excluding the "Free Agents" pseudo-team.
     *
     * @return array Array of team data, each element containing:
     *               - 'name' (string): Team name only (e.g., "Lakers")
     *               - 'city' (string): City name only (e.g., "Los Angeles")
     *               - 'fullName' (string): Combined city and name (e.g., "Los Angeles Lakers")
     *
     * **Behaviors:**
     * - Orders results by city alphabetically (ASC)
     * - Excludes "Free Agents" from results
     * - Returns empty array if no teams found
     */
    public function getAllTeamsForTrading();

    /**
     * Render team selection links for trading
     *
     * Generates HTML anchor links for each team that link to the
     * trade offer page with that team as the partner.
     *
     * @param array $teams Array of team data (from getAllTeamsForTrading)
     * @return string HTML string with one link per team, separated by <br>
     *
     * **Behaviors:**
     * - Links point to modules.php?name=Trading&op=offertrade&partner={name}
     * - Display text is the full team name (city + name)
     * - Returns empty string if teams array is empty
     */
    public function renderTeamSelectionLinks($teams);
}
