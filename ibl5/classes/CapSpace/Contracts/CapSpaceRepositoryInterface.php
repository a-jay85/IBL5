<?php

declare(strict_types=1);

namespace CapSpace\Contracts;

/**
 * CapSpaceRepositoryInterface - Contract for salary cap data access
 *
 * Defines methods for retrieving team salary cap information from the database.
 *
 * @see \CapSpace\CapSpaceRepository For the concrete implementation
 */
interface CapSpaceRepositoryInterface
{
    /**
     * Get all teams for salary cap display
     *
     * @return array Array of team data
     */
    public function getAllTeams(): array;

    /**
     * Get players under contract for a team after current season
     *
     * @param int $teamId Team ID
     * @return array Array of contract data
     */
    public function getPlayersUnderContractAfterSeason(int $teamId): array;
}
