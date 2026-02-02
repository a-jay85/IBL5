<?php

declare(strict_types=1);

namespace Team\Contracts;

/**
 * TeamRepositoryInterface - Contract for Team data access operations
 *
 * Defines methods for querying team information from multiple database tables:
 * power rankings, standings, banners, history, rosters, and playoff results.
 *
 * All methods use prepared statements internally.
 * All methods return arrays, never throw exceptions.
 */
interface TeamRepositoryInterface
{
    /**
     * Get team information by team ID
     *
     * @param int $teamID Team ID from ibl_team_info
     * @return array<string, mixed>|null Team data or null if not found
     */
    public function getTeam(int $teamID): ?array;

    /**
     * Get team power ranking data
     *
     * @param string $teamName Team name to search for
     * @return array<string, mixed>|null Complete row from ibl_power or null if not found
     */
    public function getTeamPowerData(string $teamName): ?array;

    /**
     * Get all teams in a specific division with standings
     *
     * @param string $division Division name (e.g., "Atlantic", "Central", "Pacific")
     * @return array<int, array<string, mixed>> Rows ordered by gb DESC
     */
    public function getDivisionStandings(string $division): array;

    /**
     * Get all teams in a specific conference with standings
     *
     * @param string $conference Conference name (e.g., "Eastern", "Western")
     * @return array<int, array<string, mixed>> Rows ordered by gb DESC
     */
    public function getConferenceStandings(string $conference): array;

    /**
     * Get championship banners (championships won) for a team
     *
     * @param string $teamName Team name to search for
     * @return array<int, array<string, mixed>> Rows ordered by year ASC
     */
    public function getChampionshipBanners(string $teamName): array;

    /**
     * Get GM history for a team
     *
     * Records match format: "Owner Name (Team Name)"
     *
     * @param string $ownerName Owner/GM name
     * @param string $teamName Team name
     * @return array<int, array<string, mixed>> Rows ordered by year ASC
     */
    public function getGMHistory(string $ownerName, string $teamName): array;

    /**
     * Get team accomplishments and awards
     *
     * @param string $teamName Team name to search for
     * @return array<int, array<string, mixed>> Rows ordered by year DESC
     */
    public function getTeamAccomplishments(string $teamName): array;

    /**
     * Get regular season win/loss history for a team
     *
     * @param string $teamName Team name to search for
     * @return array<int, array<string, mixed>> Rows ordered by year DESC
     */
    public function getRegularSeasonHistory(string $teamName): array;

    /**
     * Get HEAT tournament results for a team
     *
     * @param string $teamName Team name to search for
     * @return array<int, array<string, mixed>> Rows ordered by year DESC
     */
    public function getHEATHistory(string $teamName): array;

    /**
     * Get playoff results for all teams
     *
     * @return array<int, array<string, mixed>> Rows ordered by year DESC
     */
    public function getPlayoffResults(): array;

    /**
     * Get free agency roster for a team (expiring contracts only)
     *
     * @param int $teamID Team ID
     * @return array<int, array<string, mixed>> Player rows ordered by ordinal, then name
     */
    public function getFreeAgencyRoster(int $teamID): array;

    /**
     * Get current season roster for a team
     *
     * @param int $teamID Team ID
     * @return array<int, array<string, mixed>> Player rows ordered by ordinal, then name
     */
    public function getRosterUnderContract(int $teamID): array;

    /**
     * Get free agents available for signing
     *
     * @param bool $includeFreeAgencyActive If true, only show expiring contracts (cyt != cy)
     * @return array<int, array<string, mixed>> Player rows ordered by ordinal ASC
     */
    public function getFreeAgents(bool $includeFreeAgencyActive = false): array;

    /**
     * Get entire league roster
     *
     * @return array<int, array<string, mixed>> Player rows ordered by ordinal ASC
     */
    public function getEntireLeagueRoster(): array;

    /**
     * Get historical roster for a team in a specific season
     *
     * @param int $teamID Team ID
     * @param string $year Season year (e.g., "2023", "2024")
     * @return array<int, array<string, mixed>> Player rows ordered by name ASC
     */
    public function getHistoricalRoster(int $teamID, string $year): array;
}
