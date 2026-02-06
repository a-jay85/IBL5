<?php

declare(strict_types=1);

namespace Scripts\Contracts;

/**
 * MaintenanceRepositoryInterface - Database operations for maintenance scripts
 *
 * Provides methods for updating tradition factors, franchise history,
 * and reading settings.
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
     * Get recent complete seasons for a team
     *
     * @param string $teamName Team name
     * @param int $limit Number of seasons to retrieve
     * @return array<int, array{wins: int, losses: int}> Array of season records with 'wins' and 'losses'
     */
    public function getTeamRecentCompleteSeasons(string $teamName, int $limit = 5): array;

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
     * Update division titles count for all teams
     *
     * @return bool True on success
     */
    public function updateDivisionTitles(): bool;

    /**
     * Update conference titles count for all teams
     *
     * @return bool True on success
     */
    public function updateConferenceTitles(): bool;

    /**
     * Update IBL (World) titles count for all teams
     *
     * @return bool True on success
     */
    public function updateIblTitles(): bool;

    /**
     * Update H.E.A.T. titles count for all teams
     *
     * @return bool True on success
     */
    public function updateHeatTitles(): bool;

    /**
     * Update playoff appearances count for all teams
     *
     * @return bool True on success
     */
    public function updatePlayoffAppearances(): bool;

    /**
     * Update all title counts and playoff appearances in a single query
     *
     * Combines division titles, conference titles, IBL titles, HEAT titles,
     * and playoff appearances into one UPDATE statement.
     *
     * @return bool True on success
     */
    public function updateAllTitlesAndAppearances(): bool;

    /**
     * Get a setting value by name
     *
     * @param string $name Setting name
     * @return string|null Setting value or null if not found
     */
    public function getSetting(string $name): ?string;
}
