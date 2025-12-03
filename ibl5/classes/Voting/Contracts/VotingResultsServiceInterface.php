<?php

namespace Voting\Contracts;

/**
 * VotingResultsServiceInterface - Voting data retrieval
 *
 * Retrieves aggregated voting results for All-Star and end-of-year
 * awards from the database.
 */
interface VotingResultsServiceInterface
{
    /**
     * Get All-Star voting results
     *
     * Retrieves and aggregates All-Star ballot data from ibl_votes_ASG table.
     *
     * @return array Array of category results, each element containing:
     *               - 'title' (string): Category name (e.g., "Eastern Conference Frontcourt")
     *               - 'rows' (array): Array of ['name' => string, 'votes' => int] sorted by votes DESC
     *
     * **Categories Returned:**
     * 1. Eastern Conference Frontcourt (columns: East_F1-F4)
     * 2. Eastern Conference Backcourt (columns: East_B1-B4)
     * 3. Western Conference Frontcourt (columns: West_F1-F4)
     * 4. Western Conference Backcourt (columns: West_B1-B4)
     *
     * **Behaviors:**
     * - Each ballot column counts as 1 vote
     * - Empty/blank entries shown as "(No Selection Recorded)"
     * - Results sorted by votes DESC, then name ASC
     * - Only entries with votes > 0 returned
     */
    public function getAllStarResults(): array;

    /**
     * Get end-of-year awards voting results
     *
     * Retrieves and aggregates end-of-year ballot data from ibl_votes_EOY table
     * with weighted scoring (1st place = 3pts, 2nd = 2pts, 3rd = 1pt).
     *
     * @return array Array of category results, each element containing:
     *               - 'title' (string): Award name (e.g., "Most Valuable Player")
     *               - 'rows' (array): Array of ['name' => string, 'votes' => int] sorted by votes DESC
     *
     * **Categories Returned:**
     * 1. Most Valuable Player (columns: MVP_1, MVP_2, MVP_3)
     * 2. Sixth Man of the Year (columns: Six_1, Six_2, Six_3)
     * 3. Rookie of the Year (columns: ROY_1, ROY_2, ROY_3)
     * 4. GM of the Year (columns: GM_1, GM_2, GM_3)
     *
     * **Scoring:**
     * - First place (_1): 3 points
     * - Second place (_2): 2 points
     * - Third place (_3): 1 point
     *
     * **Behaviors:**
     * - Votes are summed with weights applied
     * - Empty/blank entries shown as "(No Selection Recorded)"
     * - Results sorted by votes DESC, then name ASC
     * - Only entries with total score > 0 returned
     */
    public function getEndOfYearResults(): array;
}
