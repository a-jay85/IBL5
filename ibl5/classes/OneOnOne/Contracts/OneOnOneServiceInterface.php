<?php

declare(strict_types=1);

namespace OneOnOne\Contracts;

use OneOnOne\OneOnOneGameResult;

/**
 * OneOnOneServiceInterface - Contract for One-on-One game business logic
 * 
 * Defines the interface for coordinating One-on-One game operations.
 * Acts as the main entry point for the module, orchestrating repository
 * and game engine interactions.
 */
interface OneOnOneServiceInterface
{
    /**
     * Get all active players for game selection
     * 
     * Returns players available for selection in the game form.
     * 
     * @return array<int, array{pid: int, name: string}> Array of players
     */
    public function getActivePlayers(): array;

    /**
     * Play a new One-on-One game between two players
     * 
     * Validates player selection, loads player data, runs the game
     * simulation, saves the result, and triggers Discord notification.
     * 
     * @param int $player1Id Player 1's database ID
     * @param int $player2Id Player 2's database ID
     * @param string $owner Username of the person running the game
     * @return OneOnOneGameResult The completed game result
     * @throws \InvalidArgumentException If player selection is invalid
     * @throws \RuntimeException If player data cannot be loaded
     */
    public function playGame(int $player1Id, int $player2Id, string $owner): OneOnOneGameResult;

    /**
     * Validate player selection for a game
     * 
     * Checks that both players are selected and are different players.
     * 
     * @param int|null $player1Id Player 1's ID (may be null if not selected)
     * @param int|null $player2Id Player 2's ID (may be null if not selected)
     * @return array<string> Array of validation error messages (empty if valid)
     */
    public function validatePlayerSelection(?int $player1Id, ?int $player2Id): array;

    /**
     * Get a previously played game for replay
     * 
     * Retrieves a stored game by its ID for display.
     * 
     * @param int $gameId The game ID to retrieve
     * @return array<string, mixed>|null Game data or null if not found
     */
    public function getGameReplay(int $gameId): ?array;

    /**
     * Post game result to Discord
     * 
     * Sends a formatted message to the #1v1-games Discord channel
     * with the game result and link to replay.
     * 
     * @param OneOnOneGameResult $result The game result to post
     * @param int $gameId The saved game ID for the replay link
     * @return void
     */
    public function postToDiscord(OneOnOneGameResult $result, int $gameId): void;
}
