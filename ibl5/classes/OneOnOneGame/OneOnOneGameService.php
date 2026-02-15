<?php

declare(strict_types=1);

namespace OneOnOneGame;

use OneOnOneGame\Contracts\OneOnOneGameServiceInterface;
use OneOnOneGame\Contracts\OneOnOneGameRepositoryInterface;
use OneOnOneGame\Contracts\OneOnOneGameEngineInterface;

/**
 * OneOnOneGameService - Business logic coordinator for One-on-One games
 *
 * Acts as the main entry point for the One-on-One module, orchestrating
 * repository and game engine interactions.
 *
 * @see OneOnOneGameServiceInterface For method contracts
 * @phpstan-import-type GameRecord from OneOnOneGameRepositoryInterface
 */
class OneOnOneGameService implements OneOnOneGameServiceInterface
{
    private OneOnOneGameRepositoryInterface $repository;
    private OneOnOneGameEngineInterface $gameEngine;

    public function __construct(
        OneOnOneGameRepositoryInterface $repository,
        ?OneOnOneGameEngineInterface $gameEngine = null
    ) {
        $this->repository = $repository;
        $this->gameEngine = $gameEngine ?? new OneOnOneGameEngine();
    }

    /**
     * @see OneOnOneGameServiceInterface::playGame()
     */
    public function playGame(int $player1Id, int $player2Id, string $owner): OneOnOneGameResult
    {
        // Validate selection
        $errors = $this->validatePlayerSelection($player1Id, $player2Id);
        if ($errors !== []) {
            throw new \InvalidArgumentException(implode(' ', $errors));
        }

        // Load player data
        $player1Data = $this->repository->getPlayerForGame($player1Id);
        if ($player1Data === null) {
            throw new \RuntimeException("Player 1 with ID $player1Id not found");
        }

        $player2Data = $this->repository->getPlayerForGame($player2Id);
        if ($player2Data === null) {
            throw new \RuntimeException("Player 2 with ID $player2Id not found");
        }

        // Run game simulation
        $result = $this->gameEngine->simulateGame($player1Data, $player2Data, $owner);

        // Save game to database
        $gameId = $this->repository->saveGame($result);
        $result->gameId = $gameId;

        // Post to Discord
        $this->postToDiscord($result, $gameId);

        return $result;
    }

    /**
     * @see OneOnOneGameServiceInterface::validatePlayerSelection()
     *
     * @return array<string>
     */
    public function validatePlayerSelection(?int $player1Id, ?int $player2Id): array
    {
        $errors = [];

        if ($player1Id === null || $player1Id === 0) {
            $errors[] = "Please select a Player from the Player 1 Category.";
        }

        if ($player2Id === null || $player2Id === 0) {
            $errors[] = "Please select a Player from the Player 2 Category.";
        }

        if ($player1Id !== null && $player2Id !== null && $player1Id === $player2Id) {
            $errors[] = "Please do not select the same player for both Player 1 and Player 2.";
        }

        return $errors;
    }

    /**
     * @see OneOnOneGameServiceInterface::postToDiscord()
     */
    public function postToDiscord(OneOnOneGameResult $result, int $gameId): void
    {
        $discordText = "";
        $bang = "";
        
        // Sanitize names for Discord to prevent formatting exploits
        $player1Name = str_replace(['*', '_', '~', '`'], '', $result->player1Name);
        $player2Name = str_replace(['*', '_', '~', '`'], '', $result->player2Name);
        $owner = str_replace(['*', '_', '~', '`'], '', $result->owner);
        $gamewinner = strtoupper($result->getWinnerName());
        $gamewinner = str_replace(['*', '_', '~', '`'], '', $gamewinner);
        
        if ($result->isCloseGame()) {
            $bang = "__**BANG! BANG! OH WHAT A SHOT FROM $gamewinner!!!**__\n";
        }
        
        $discordText .= $bang;
        
        if ($result->didPlayer1Win()) {
            $discordText .= "**{$player1Name} {$result->player1Score}**, {$player2Name} {$result->player2Score}";
        } else {
            $discordText .= "{$player1Name} {$result->player1Score}, **{$player2Name} {$result->player2Score}**";
        }
        
        $discordText .= "\n\t*(Game played by {$owner})*\n";
        $discordText .= $this->getGameUrl($gameId);

        \Discord::postToChannel('#1v1-games', $discordText);
    }

    /**
     * Generate the URL for a game replay
     * 
     * Uses a static, trusted application origin to prevent host header injection
     */
    private function getGameUrl(int $gameId): string
    {
        // Use static, trusted application origin to prevent host header injection
        $baseUrl = 'https://iblhoops.net/modules.php';
        $query = http_build_query([
            'name'   => 'OneOnOneGame',
            'gameid' => $gameId,
        ]);
        
        return $baseUrl . '?' . $query;
    }
}
