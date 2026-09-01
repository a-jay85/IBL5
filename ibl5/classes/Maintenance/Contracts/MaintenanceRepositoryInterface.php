<?php

declare(strict_types=1);

namespace Maintenance\Contracts;

/**
 * MaintenanceRepositoryInterface - Database operations for maintenance scripts
 *
 * Provides methods for updating tradition factors and reading settings.
 */
interface MaintenanceRepositoryInterface
{
    /**
     * Get all teams (excluding free agents)
     *
     * @return array<int, array{team_name: string}> Array of teams with 'team_name' key
     */
    public function getAllTeams(): array;

    /**
     * Get recent season records for a team up to and including the given year
     *
     * Returns rows where year <= $currentSeasonYear, ordered by year descending,
     * fetching $limit + 1 rows so the caller can drop one in-progress season and
     * still have a full window. No game-count predicate is applied — in-progress
     * detection and anomaly validation are the caller's responsibility.
     *
     * @param string $teamName Team name
     * @param int $currentSeasonYear Upper bound for year (inclusive)
     * @param int $limit Number of complete seasons needed (repository fetches $limit + 1)
     * @return array<int, array{year: int, wins: int, losses: int}> Array of season records
     */
    public function getTeamSeasonRecords(string $teamName, int $currentSeasonYear, int $limit = 5): array;

    /**
     * Update team tradition values
     *
     * @param string $teamName Team name
     * @param int $avgWins Average wins
     * @param int $avgLosses Average losses
     * @return bool True on success
     */
    public function updateTeamTradition(string $teamName, int $avgWins, int $avgLosses): bool;

    /**
     * Get a setting value by name
     *
     * @param string $name Setting name
     * @return string|null Setting value or null if not found
     */
    public function getSetting(string $name): ?string;
}
