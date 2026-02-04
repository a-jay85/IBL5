<?php

declare(strict_types=1);

namespace OneOnOneGame\Contracts;

use OneOnOneGame\OneOnOneGameResult;

/**
 * OneOnOneGameRepositoryInterface - Contract for One-on-One game data access
 *
 * Defines the interface for loading and persisting One-on-One game data.
 * Handles all database operations for the ibl_one_on_one table.
 *
 * @phpstan-import-type PlayerGameData from OneOnOneGameEngineInterface
 * @phpstan-type GameRecord array{gameid: int, playbyplay: string, winner: string, loser: string, winscore: int, lossscore: int, owner: string}
 */
interface OneOnOneGameRepositoryInterface
{
    /**
     * Get all active (non-retired) players for game selection
     *
     * Returns player data needed for the player selection dropdowns.
     * Players are ordered alphabetically by name.
     *
     * @return array<int, array{pid: int, name: string}> Array of players with pid and name
     */
    public function getActivePlayers(): array;

    /**
     * Get player ratings and attributes needed for game simulation
     *
     * Retrieves all offensive and defensive ratings, shooting percentages,
     * and other attributes needed to simulate a One-on-One game.
     *
     * @param int $playerId The player's internal ID (pid)
     * @return PlayerGameData|null Player data array or null if not found
     */
    public function getPlayerForGame(int $playerId): ?array;

    /**
     * Get the next available game ID
     *
     * Queries the highest existing game ID and returns the next sequential ID.
     *
     * @return int The next available game ID
     */
    public function getNextGameId(): int;

    /**
     * Save a completed game to the database
     *
     * Persists the game result including play-by-play, winner/loser info,
     * and the user who played the game.
     *
     * @param OneOnOneGameResult $result The completed game result
     * @return int The saved game ID
     * @throws \RuntimeException If the insert fails (error code 1002)
     */
    public function saveGame(OneOnOneGameResult $result): int;

    /**
     * Get a previously played game by its ID
     *
     * Retrieves all stored data for a game replay, including play-by-play text.
     *
     * @param int $gameId The game ID to retrieve
     * @return GameRecord|null Game data array or null if not found
     */
    public function getGameById(int $gameId): ?array;
}
