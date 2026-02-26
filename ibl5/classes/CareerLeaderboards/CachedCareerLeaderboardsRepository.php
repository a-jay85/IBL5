<?php

declare(strict_types=1);

namespace CareerLeaderboards;

use Cache\Contracts\DatabaseCacheInterface;
use CareerLeaderboards\Contracts\CareerLeaderboardsRepositoryInterface;

/**
 * CachedCareerLeaderboardsRepository - Caching decorator for CareerLeaderboardsRepositoryInterface.
 *
 * Caches full unsorted result sets per table key (12 entries total).
 * On cache hit, filters by active-only, sorts by the requested column,
 * and slices for limit — all in PHP. This avoids running slow database
 * view queries on every sort/filter change.
 *
 * @phpstan-import-type CareerStatsRow from CareerLeaderboardsRepositoryInterface
 * @phpstan-import-type LeaderboardResult from CareerLeaderboardsRepositoryInterface
 */
class CachedCareerLeaderboardsRepository implements CareerLeaderboardsRepositoryInterface
{
    private const CACHE_KEY_PREFIX = 'career_leaderboards:';
    private const TTL_SECONDS = 86400; // 24 hours

    private const VALID_TABLES = [
        'ibl_hist',
        'ibl_season_career_avgs',
        'ibl_playoff_career_totals',
        'ibl_playoff_career_avgs',
        'ibl_heat_career_totals',
        'ibl_heat_career_avgs',
        'ibl_olympics_career_totals',
        'ibl_olympics_career_avgs',
        'ibl_rookie_career_totals',
        'ibl_sophomore_career_totals',
        'ibl_allstar_career_totals',
        'ibl_allstar_career_avgs',
    ];

    private CareerLeaderboardsRepositoryInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(CareerLeaderboardsRepositoryInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * @see CareerLeaderboardsRepositoryInterface::getLeaderboards()
     *
     * @return LeaderboardResult
     */
    public function getLeaderboards(
        string $tableKey,
        string $sortColumn,
        int $activeOnly,
        int $limit
    ): array {
        $cacheKey = self::CACHE_KEY_PREFIX . $tableKey;

        /** @var list<CareerStatsRow>|null $rows */
        $rows = $this->cache->get($cacheKey);

        if ($rows === null) {
            // Cache miss — fetch all rows from the inner repository (no active filter, no limit, default sort)
            $innerResult = $this->inner->getLeaderboards($tableKey, 'pts', 0, 0);
            $rows = $innerResult['result'];
            $this->cache->set($cacheKey, $rows, self::TTL_SECONDS);
        }

        // Filter by active-only in PHP
        if ($activeOnly === 1) {
            $rows = array_values(array_filter(
                $rows,
                static fn (array $row): bool => $row['retired'] === 0
            ));
        }

        // Sort by the requested column DESC in PHP
        usort($rows, static function (array $a, array $b) use ($sortColumn): int {
            $aVal = (float) ($a[$sortColumn] ?? 0);
            $bVal = (float) ($b[$sortColumn] ?? 0);
            return $bVal <=> $aVal;
        });

        // Apply limit
        if ($limit > 0) {
            $rows = array_slice($rows, 0, $limit);
        }

        return [
            'result' => $rows,
            'count' => count($rows),
        ];
    }

    /**
     * @see CareerLeaderboardsRepositoryInterface::getTableType()
     */
    public function getTableType(string $tableKey): string
    {
        return $this->inner->getTableType($tableKey);
    }

    /**
     * Rebuild cache for all 12 table keys.
     *
     * Fetches full result sets from the inner repository and stores them.
     * Called by the warm-cache CLI script and optionally after game simulations.
     */
    public function rebuildCache(): void
    {
        foreach (self::VALID_TABLES as $tableKey) {
            $innerResult = $this->inner->getLeaderboards($tableKey, 'pts', 0, 0);
            $this->cache->set(
                self::CACHE_KEY_PREFIX . $tableKey,
                $innerResult['result'],
                self::TTL_SECONDS
            );
        }
    }

    /**
     * Invalidate cache for all 12 table keys.
     */
    public function invalidateCache(): void
    {
        foreach (self::VALID_TABLES as $tableKey) {
            $this->cache->delete(self::CACHE_KEY_PREFIX . $tableKey);
        }
    }
}
