<?php

declare(strict_types=1);

namespace PowerRankings\Contracts;

/**
 * PowerRankingsRepositoryInterface - Contract for power rankings data access
 *
 * Defines methods for retrieving power rankings data from the database.
 *
 * @see \PowerRankings\PowerRankingsRepository For the concrete implementation
 */
interface PowerRankingsRepositoryInterface
{
    /**
     * Get all power rankings ordered by ranking
     *
     * @return array Array of team power rankings
     */
    public function getPowerRankings(): array;
}
