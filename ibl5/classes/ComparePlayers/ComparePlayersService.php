<?php

declare(strict_types=1);

namespace ComparePlayers;

use ComparePlayers\Contracts\ComparePlayersServiceInterface;
use ComparePlayers\Contracts\ComparePlayersRepositoryInterface;

/**
 * @see ComparePlayersServiceInterface
 */
class ComparePlayersService implements ComparePlayersServiceInterface
{
    private ComparePlayersRepositoryInterface $repository;

    public function __construct(ComparePlayersRepositoryInterface $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see ComparePlayersServiceInterface::getPlayerNames()
     */
    public function getPlayerNames(): array
    {
        return $this->repository->getAllPlayerNames();
    }

    /**
     * @see ComparePlayersServiceInterface::comparePlayers()
     */
    public function comparePlayers(string $player1Name, string $player2Name): ?array
    {
        // Validate input
        $player1Name = trim($player1Name);
        $player2Name = trim($player2Name);

        if ($player1Name === '' || $player2Name === '') {
            return null;
        }

        // Retrieve both players
        $player1 = $this->repository->getPlayerByName($player1Name);
        $player2 = $this->repository->getPlayerByName($player2Name);

        // Both must exist
        if ($player1 === null || $player2 === null) {
            return null;
        }

        return [
            'player1' => $player1,
            'player2' => $player2,
        ];
    }
}
