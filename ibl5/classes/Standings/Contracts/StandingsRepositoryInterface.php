<?php

declare(strict_types=1);

namespace Standings\Contracts;

/**
 * StandingsRepositoryInterface - Contract for standings data access
 *
 * Defines methods for retrieving team standings data from the database.
 * Implementations must provide data for conferences, divisions, and team streaks.
 *
 * @see \Standings\StandingsRepository For the concrete implementation
 */
interface StandingsRepositoryInterface
{
    /**
     * Get standings for a specific region (conference or division)
     *
     * @param string $region Region name (e.g., 'Eastern', 'Atlantic')
     * @return array Array of team standings data sorted by games back
     */
    public function getStandingsByRegion(string $region): array;

    /**
     * Get streak and last 10 games data for a team
     *
     * @param int $teamId Team ID
     * @return array|null Streak data or null if not found
     */
    public function getTeamStreakData(int $teamId): ?array;
}
