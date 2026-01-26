<?php

declare(strict_types=1);

namespace Scripts\Contracts;

/**
 * LeaderboardRepositoryInterface - Database operations for leaderboard updates
 *
 * Provides methods for updating career totals and averages for various leaderboards
 * (H.E.A.T., Playoffs, Season).
 */
interface LeaderboardRepositoryInterface
{
    /**
     * Get all players from the database
     *
     * @return array Array of players with 'pid' and 'name' keys
     */
    public function getAllPlayers(): array;

    /**
     * Get player stats from a specific stats table
     *
     * @param string $playerName Player name to look up
     * @param string $statsTable Source table (ibl_heat_stats, ibl_playoff_stats, etc.)
     * @return array Array of stat rows for the player
     */
    public function getPlayerStats(string $playerName, string $statsTable): array;

    /**
     * Get player career stats from ibl_plr table
     *
     * @param string $playerName Player name to look up
     * @return array|null Player career stats or null if not found
     */
    public function getPlayerCareerStats(string $playerName): ?array;

    /**
     * Delete a player's career totals from a table
     *
     * @param string $playerName Player name
     * @param string $table Target table (ibl_heat_career_totals, etc.)
     * @return bool True on success
     */
    public function deletePlayerCareerTotals(string $playerName, string $table): bool;

    /**
     * Insert player career totals
     *
     * @param string $table Target table
     * @param array $data Associative array of column => value
     * @return bool True on success
     */
    public function insertPlayerCareerTotals(string $table, array $data): bool;

    /**
     * Delete a player's career averages from a table
     *
     * @param string $playerName Player name
     * @param string $table Target table (ibl_heat_career_avgs, etc.)
     * @return bool True on success
     */
    public function deletePlayerCareerAvgs(string $playerName, string $table): bool;

    /**
     * Insert player career averages
     *
     * @param string $table Target table
     * @param array $data Associative array of column => value
     * @return bool True on success
     */
    public function insertPlayerCareerAvgs(string $table, array $data): bool;
}
