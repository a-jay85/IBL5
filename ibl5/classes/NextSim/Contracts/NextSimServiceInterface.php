<?php

declare(strict_types=1);

namespace NextSim\Contracts;

/**
 * NextSimServiceInterface - Contract for next sim business logic
 *
 * Defines methods for processing next simulation game data.
 *
 * @see \NextSim\NextSimService For the concrete implementation
 */
interface NextSimServiceInterface
{
    /**
     * Get processed next sim games for a team
     *
     * @param int $teamId Team ID
     * @param \Season $season Current season
     * @return array Processed game data with opposing starters
     */
    public function getNextSimGames(int $teamId, \Season $season): array;

    /**
     * Get user's starting lineup
     *
     * @param \Team $team User's team
     * @return array Starting players by position
     */
    public function getUserStartingLineup(\Team $team): array;
}
