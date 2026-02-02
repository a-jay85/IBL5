<?php

declare(strict_types=1);

namespace AllStarAppearances\Contracts;

/**
 * Repository interface for All-Star Appearances module.
 *
 * Provides method to retrieve all-star appearance counts from the database.
 */
interface AllStarAppearancesRepositoryInterface
{
    /**
     * Get all-star appearance counts grouped by player name.
     *
     * @return array<int, array{name: string, pid: int|null, appearances: int}> Array of player names, IDs, and their appearance counts
     */
    public function getAllStarAppearances(): array;
}
