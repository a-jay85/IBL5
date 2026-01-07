<?php

declare(strict_types=1);

namespace Player\Contracts;

/**
 * PlayerAwardsRepositoryInterface - Contract for player awards data access
 * 
 * Defines database operations for fetching player awards data including
 * All-Star selections, Three-Point Contests, Slam Dunk Competitions, and
 * Rookie-Sophomore Challenges. All methods use prepared statements for
 * SQL injection prevention.
 */
interface PlayerAwardsRepositoryInterface
{
    /**
     * Get all awards for a player by name
     * 
     * @param string $playerName Player's full name (exact match)
     * @return array<int, array<string, mixed>> Array of award rows
     * 
     * Each row contains:
     *  - name: Player name
     *  - Award: Award description
     *  - year: Year of award
     */
    public function getPlayerAwards(string $playerName): array;

    /**
     * Count All-Star Game selections for a player
     * 
     * Counts awards matching pattern '%Conference All-Star'
     * 
     * @param string $playerName Player's full name
     * @return int Number of All-Star selections
     */
    public function countAllStarSelections(string $playerName): int;

    /**
     * Count Three-Point Contest appearances for a player
     * 
     * Counts awards matching pattern 'Three-Point Contest%'
     * 
     * @param string $playerName Player's full name
     * @return int Number of Three-Point Contest appearances
     */
    public function countThreePointContests(string $playerName): int;

    /**
     * Count Slam Dunk Competition appearances for a player
     * 
     * Counts awards matching pattern 'Slam Dunk Competition%'
     * 
     * @param string $playerName Player's full name
     * @return int Number of Slam Dunk Competition appearances
     */
    public function countDunkContests(string $playerName): int;

    /**
     * Count Rookie-Sophomore Challenge appearances for a player
     * 
     * Counts awards matching exact 'Rookie-Sophomore Challenge'
     * 
     * @param string $playerName Player's full name
     * @return int Number of Rookie-Sophomore Challenge appearances
     */
    public function countRookieSophomoreChallenges(string $playerName): int;

    /**
     * Get All-Star Activity summary for a player
     * 
     * Convenience method to get all All-Star related counts in one call.
     * 
     * @param string $playerName Player's full name
     * @return array{
     *     allStarGames: int,
     *     threePointContests: int,
     *     dunkContests: int,
     *     rookieSophomoreChallenges: int
     * }
     */
    public function getAllStarActivity(string $playerName): array;

    /**
     * Get awards for a player matching a specific pattern
     * 
     * @param string $playerName Player's full name
     * @param string $awardPattern SQL LIKE pattern for award name
     * @return array<int, array<string, mixed>> Matching award rows
     */
    public function getPlayerAwardsByPattern(string $playerName, string $awardPattern): array;

    /**
     * Get news articles mentioning a player
     * 
     * @param string $playerName Player's full name
     * @param int $limit Maximum number of articles to return
     * @return array<int, array<string, mixed>> Array of news article rows
     */
    public function getPlayerNews(string $playerName, int $limit = 10): array;
}
