<?php

declare(strict_types=1);

namespace Voting\Contracts;

/**
 * VotingResultsServiceInterface - Voting data retrieval
 *
 * Retrieves aggregated voting results for All-Star and end-of-year
 * awards from the database.
 *
 * @phpstan-type VoteRow array{name: string, votes: int, pid: int}
 * @phpstan-type VoteTable array{title: string, rows: list<VoteRow>}
 */
interface VotingResultsServiceInterface
{
    /**
     * Get All-Star voting results
     *
     * Retrieves and aggregates All-Star ballot data from ibl_votes_ASG table.
     *
     * @return list<VoteTable> Array of category results
     *
     * **Categories Returned:**
     * 1. Eastern Conference Frontcourt (columns: east_f1-f4)
     * 2. Eastern Conference Backcourt (columns: east_b1-b4)
     * 3. Western Conference Frontcourt (columns: west_f1-f4)
     * 4. Western Conference Backcourt (columns: west_b1-b4)
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
     * @return list<VoteTable> Array of category results
     *
     * **Categories Returned:**
     * 1. Most Valuable Player (columns: mvp_1, mvp_2, mvp_3)
     * 2. Sixth Man of the Year (columns: six_1, six_2, six_3)
     * 3. Rookie of the Year (columns: roy_1, roy_2, roy_3)
     * 4. GM of the Year (columns: gm_1, gm_2, gm_3)
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
