<?php

declare(strict_types=1);

namespace Voting;

/**
 * Contract for retrieving aggregated voting results for public display.
 */
interface VotingResultsProvider
{
    /**
     * Retrieves All-Star Game voting totals grouped by ballot position.
     *
     * @return array<int, array{title: string, rows: array<int, array{name: string, votes: int}>}>
     */
    public function getAllStarResults(): array;

    /**
     * Retrieves end-of-year awards voting totals grouped by award type.
     *
     * @return array<int, array{title: string, rows: array<int, array{name: string, votes: int}>}>
     */
    public function getEndOfYearResults(): array;
}
