<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerStatsRepositoryInterface - Contract for player statistics data access
 * 
 * Defines the interface for loading player statistics from various database tables.
 * Covers current season stats, historical stats, box scores, and specialized stats
 * (playoffs, HEAT, Olympics).
 */
interface PlayerStatsRepositoryInterface
{
    /**
     * Get player statistics by player ID from ibl_plr table
     *
     * Returns all columns from the current player table including
     * season stats, career totals, and season/career highs.
     *
     * @param int $playerID Player ID (pid)
     * @return array|null Player statistics row or null if not found
     */
    public function getPlayerStats(int $playerID): ?array;

    /**
     * Get historical stats for a player ordered by year
     * 
     * Queries ibl_hist table for all historical season records.
     * Each record contains season totals for games, minutes, and all stat categories.
     * 
     * @param int $playerID Player ID
     * @return array<array<string, mixed>> Array of historical stat records ordered by year ASC
     */
    public function getHistoricalStats(int $playerID): array;

    /**
     * Get box scores for a player between specific dates
     * 
     * Queries ibl_box_scores table for game-by-game statistics within a date range.
     * Used for game logs and sim-by-sim stat calculations.
     * 
     * @param int $playerID Player ID
     * @param string $startDate Start date (YYYY-MM-DD format)
     * @param string $endDate End date (YYYY-MM-DD format)
     * @return array<array<string, mixed>> Array of box score records ordered by Date ASC
     */
    public function getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array;

    /**
     * Get all simulation date ranges
     * 
     * Returns all records from ibl_sim_dates table containing sim numbers
     * and their corresponding date ranges.
     * 
     * @param int $limit Maximum number of sim records to return (default 20)
     * @return array<array<string, mixed>> Array of sim date records with keys: Sim, 'Start Date', 'End Date'
     */
    public function getSimDates(int $limit = 20): array;

    /**
     * Get playoff stats for a player ordered by year
     * 
     * Queries ibl_playoff_stats table for all playoff season records.
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of playoff stat records ordered by year ASC
     */
    public function getPlayoffStats(string $playerName): array;

    /**
     * Get playoff career totals for a player
     * 
     * Queries ibl_playoff_career_totals table for aggregated career totals.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career totals row or null if not found
     */
    public function getPlayoffCareerTotals(string $playerName): ?array;

    /**
     * Get playoff career averages for a player
     * 
     * Queries ibl_playoff_career_avgs table for aggregated career averages.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career averages row or null if not found
     */
    public function getPlayoffCareerAverages(string $playerName): ?array;

    /**
     * Get HEAT stats for a player ordered by year
     * 
     * Queries ibl_heat_stats table for all HEAT tournament season records.
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of HEAT stat records ordered by year ASC
     */
    public function getHeatStats(string $playerName): array;

    /**
     * Get HEAT career totals for a player
     * 
     * Queries ibl_heat_career_totals table for aggregated career totals.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career totals row or null if not found
     */
    public function getHeatCareerTotals(string $playerName): ?array;

    /**
     * Get HEAT career averages for a player
     * 
     * Queries ibl_heat_career_avgs table for aggregated career averages.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career averages row or null if not found
     */
    public function getHeatCareerAverages(string $playerName): ?array;

    /**
     * Get Olympics stats for a player ordered by year
     * 
     * Queries ibl_olympics_stats table for all Olympics season records.
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of Olympics stat records ordered by year ASC
     */
    public function getOlympicsStats(string $playerName): array;

    /**
     * Get Olympics career totals for a player
     * 
     * Queries ibl_olympics_career_totals table for aggregated career totals.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career totals row or null if not found
     */
    public function getOlympicsCareerTotals(string $playerName): ?array;

    /**
     * Get Olympics career averages for a player
     * 
     * Queries ibl_olympics_career_avgs table for aggregated career averages.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career averages row or null if not found
     */
    public function getOlympicsCareerAverages(string $playerName): ?array;

    /**
     * Get regular season career averages for a player
     * 
     * Queries ibl_season_career_avgs table for aggregated career averages.
     * 
     * @param string $playerName Player name (exact match)
     * @return array|null Career averages row or null if not found
     */
    public function getSeasonCareerAverages(string $playerName): ?array;

    /**
     * Get regular season career averages for a player by ID
     * 
     * Queries ibl_season_career_avgs table for aggregated career averages.
     * 
     * @param int $playerID Player ID (pid)
     * @return array|null Career averages row or null if not found
     */
    public function getSeasonCareerAveragesById(int $playerID): ?array;
}
