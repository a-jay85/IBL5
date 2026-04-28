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
     * @param string $statExpression SQL expression for the stat (e.g., '(`game_2gm`*2) + `game_ftm` + (`game_3gm`*3)')
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
        int $limit = 15,
        ?string $locationFilter = null
    ): array;

    /**
     * Get season highs for multiple stats in a single batched UNION ALL query.
     *
     * Replaces N round-trips (one per stat) with a single query whose branches
     * each produce up to $limit rows. UNION ALL does not preserve outer order,
     * so the result groups are re-sorted in PHP by value DESC, date ASC.
     *
     * @param array<string, string> $stats Map of stat name => SQL expression
     * @param string $tableSuffix Table suffix ('_teams' for teams, empty for players)
     * @param string $startDate Start date (YYYY-MM-DD)
     * @param string $endDate End date (YYYY-MM-DD)
     * @param int $limit Number of results per stat
     * @param string|null $locationFilter 'home', 'away', or null
     * @return array<string, list<SeasonHighEntry>> Results keyed by stat name (one entry per requested stat, possibly empty)
     */
    public function getSeasonHighsBatch(
        array $stats,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15,
        ?string $locationFilter = null
    ): array;

    /**
     * Get RCB-sourced single-game records for a given context (home/away).
     *
     * @param int $seasonYear Season year
     * @param string $context 'home' or 'away'
     * @return list<array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}>
     */
    public function getRcbSeasonHighs(int $seasonYear, string $context): array;
}
