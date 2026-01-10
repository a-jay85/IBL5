<?php

declare(strict_types=1);

namespace Injuries\Contracts;

/**
 * Service interface for Injuries module operations.
 *
 * Provides methods to retrieve injured player data with team information.
 */
interface InjuriesServiceInterface
{
    /**
     * Get all injured players with their team information.
     *
     * @return array<int, array{
     *     playerID: int,
     *     name: string,
     *     position: string,
     *     daysRemaining: int,
     *     teamID: int,
     *     teamCity: string,
     *     teamName: string,
     *     teamColor1: string,
     *     teamColor2: string
     * }> Array of injured player data with team details
     */
    public function getInjuredPlayersWithTeams(): array;
}
