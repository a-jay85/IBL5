<?php

declare(strict_types=1);

namespace Voting;

/**
 * Contract for retrieving aggregated voting results for public display
 */
interface VotingResultsProvider
{
    /**
     * Retrieves All-Star Game voting totals grouped by ballot position
     * 
     * @return array Array of tables with title and rows containing name and votes
     */
    public function getAllStarResults(): array;

    /**
     * Retrieves end-of-year awards voting totals grouped by award type
     * 
     * @return array Array of tables with title and rows containing name and votes
     */
    public function getEndOfYearResults(): array;
}
