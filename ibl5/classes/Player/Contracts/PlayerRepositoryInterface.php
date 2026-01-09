<?php

namespace Player\Contracts;

use Player\PlayerData;

/**
 * PlayerRepositoryInterface - Contract for Player data access
 * 
 * Defines the interface for loading and persisting player data from/to the database.
 * Handles all data transformation from raw database rows to PlayerData objects.
 */
interface PlayerRepositoryInterface
{
    /**
     * Load a player by their ID from the current player table
     * 
     * Queries ibl_plr table for a player matching the given ID.
     * Returns a fully hydrated PlayerData object with all fields populated
     * from the database row.
     * 
     * @param int $playerID The player's internal ID (pid)
     * @return PlayerData Complete player data object
     */
    public function loadByID(int $playerID): PlayerData;

    /**
     * Fill a PlayerData object from a current player database row
     * 
     * Transforms a raw database row from ibl_plr table into a PlayerData object.
     * Maps all database columns to PlayerData properties, including:
     * - Basic fields: pid, name, nickname, position, etc.
     * - Ratings: 22 individual skill/preference ratings
     * - Contract: years and salaries for years 1-6
     * - Draft info: year, round, pick number, college, team
     * - Physical: height, weight
     * 
     * @param array<string, mixed> $plrRow Database row from ibl_plr
     * @return PlayerData Fully populated PlayerData object
     */
    public function fillFromCurrentRow(array $plrRow): PlayerData;

    /**
     * Fill a PlayerData object from a historical player database row
     * 
     * Transforms a raw database row from ibl_hist table (historical statistics)
     * into a PlayerData object representing a player in a previous season.
     * Similar to fillFromCurrentRow but handles historical data structure.
     * 
     * @param array<string, mixed> $histRow Database row from ibl_hist
     * @return PlayerData Fully populated PlayerData object with historical data
     */
    public function fillFromHistoricalRow(array $histRow): PlayerData;

    /**
     * Get a player's free agency demands
     * 
     * Queries the database for the player's base salary demands for each contract year.
     * Returns an array with dem1-dem6 keys representing the base demand for years 1-6.
     * These are the demands before team/player modifiers are applied.
     * 
     * @param string $playerName The player's name (exact match required)
     * @return array{dem1: int, dem2: int, dem3: int, dem4: int, dem5: int, dem6: int} Base demands by year
     */
    public function getFreeAgencyDemands(string $playerName): array;

    /**
     * Get player statistics by player ID
     *
     * Queries ibl_plr table for all player data by ID.
     * Returns raw database row with all statistics columns.
     *
     * @param int $playerID Player ID
     * @return array|null Player statistics row or null if not found
     */
    public function getPlayerStats(int $playerID): ?array;

    /**
     * Get All-Star Game appearances count for a player
     * 
     * Counts awards where Award contains 'Conference All-Star'.
     * 
     * @param string $playerName Player name (exact match)
     * @return int Number of All-Star Game appearances
     */
    public function getAllStarGameCount(string $playerName): int;

    /**
     * Get Three-Point Contest appearances count for a player
     * 
     * Counts awards where Award starts with 'Three-Point Contest'.
     * 
     * @param string $playerName Player name (exact match)
     * @return int Number of Three-Point Contest appearances
     */
    public function getThreePointContestCount(string $playerName): int;

    /**
     * Get Slam Dunk Competition appearances count for a player
     * 
     * Counts awards where Award starts with 'Slam Dunk Competition'.
     * 
     * @param string $playerName Player name (exact match)
     * @return int Number of Slam Dunk Competition appearances
     */
    public function getDunkContestCount(string $playerName): int;

    /**
     * Get Rookie-Sophomore Challenge appearances count for a player
     * 
     * Counts awards where Award is exactly 'Rookie-Sophomore Challenge'.
     * 
     * @param string $playerName Player name (exact match)
     * @return int Number of Rookie-Sophomore Challenge appearances
     */
    public function getRookieSophChallengeCount(string $playerName): int;

    /**
     * Get all awards for a player ordered by year
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of award records ordered by year ASC
     */
    public function getAwards(string $playerName): array;

    /**
     * Get historical stats for a player ordered by year
     * 
     * @param int $playerID Player ID
     * @return array<array<string, mixed>> Array of historical stat records ordered by year ASC
     */
    public function getHistoricalStats(int $playerID): array;

    /**
     * Get box scores for a player between specific dates
     * 
     * @param int $playerID Player ID
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @return array<array<string, mixed>> Array of box score records ordered by Date ASC
     */
    public function getBoxScoresBetweenDates(int $playerID, string $startDate, string $endDate): array;

    /**
     * Get playoff stats for a player ordered by year
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of playoff stat records ordered by year ASC
     */
    public function getPlayoffStats(string $playerName): array;

    /**
     * Get HEAT stats for a player ordered by year
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of HEAT stat records ordered by year ASC
     */
    public function getHeatStats(string $playerName): array;

    /**
     * Get Olympics stats for a player ordered by year
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of Olympics stat records ordered by year ASC
     */
    public function getOlympicsStats(string $playerName): array;

    /**
     * Get news articles mentioning a player
     * 
     * Searches nuke_stories for articles mentioning the player name.
     * Excludes articles that mention "player II" to avoid false matches.
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of article records (sid, title, time) ordered by time DESC
     */
    public function getPlayerNews(string $playerName): array;

    /**
     * Get one-on-one game wins for a player
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of one-on-one game records where player won
     */
    public function getOneOnOneWins(string $playerName): array;

    /**
     * Get one-on-one game losses for a player
     * 
     * @param string $playerName Player name (exact match)
     * @return array<array<string, mixed>> Array of one-on-one game records where player lost
     */
    public function getOneOnOneLosses(string $playerName): array;
}
