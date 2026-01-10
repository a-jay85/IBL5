<?php

declare(strict_types=1);

namespace DraftPickLocator\Contracts;

/**
 * DraftPickLocatorRepositoryInterface - Contract for draft pick data access
 *
 * Defines methods for retrieving draft pick ownership data.
 *
 * @see \DraftPickLocator\DraftPickLocatorRepository For the concrete implementation
 */
interface DraftPickLocatorRepositoryInterface
{
    /**
     * Get all teams with basic info
     *
     * @return array Array of team data
     */
    public function getAllTeams(): array;

    /**
     * Get draft picks for a specific team
     *
     * @param string $teamName Team name
     * @return array Array of draft pick data
     */
    public function getDraftPicksForTeam(string $teamName): array;
}
