<?php

declare(strict_types=1);

namespace SeasonHighs\Contracts;

/**
 * Repository interface for Season Highs module.
 *
 * Provides method to retrieve season high stats from box scores.
 *
 * @phpstan-import-type SeasonHighEntry from SeasonHighsServiceInterface
 */
interface SeasonHighsRepositoryInterface
{
    /**
     * Get season highs for a specific stat.
     *
     * @param string $statExpression SQL expression for the stat (e.g., '(`game2GM`*2) + `gameFTM` + (`game3GM`*3)')
     * @param string $statName Name of the stat column alias
     * @param string $tableSuffix Table suffix ('_teams' for teams, empty for players)
     * @param string $startDate Start date for the query (YYYY-MM-DD)
     * @param string $endDate End date for the query (YYYY-MM-DD)
     * @param int $limit Number of results to return
     * @return list<SeasonHighEntry> Array of season highs
     */
    public function getSeasonHighs(
        string $statExpression,
        string $statName,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15
    ): array;
}
