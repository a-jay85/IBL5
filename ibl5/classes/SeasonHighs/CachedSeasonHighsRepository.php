<?php

declare(strict_types=1);

namespace SeasonHighs;

use Cache\Contracts\DatabaseCacheInterface;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;

/**
 * CachedSeasonHighsRepository - Caching decorator for SeasonHighsRepositoryInterface.
 *
 * Caches getSeasonHighsBatch() results in the `cache` table for 15 minutes.
 * Invalidation is TTL-only. The key carries the league, because the inner
 * repository rewrites box score tables per league, plus a hash of the stats
 * map so edits to the SQL expressions self-invalidate.
 *
 * @phpstan-import-type SeasonHighEntry from \SeasonHighs\Contracts\SeasonHighsServiceInterface
 */
class CachedSeasonHighsRepository implements SeasonHighsRepositoryInterface
{
    private const CACHE_KEY_PREFIX = 'season_highs:v1:';
    private const TTL_SECONDS = 900; // 15 minutes; tighter than PageCache's 3600s anonymous TTL

    public function __construct(
        private SeasonHighsRepositoryInterface $inner,
        private DatabaseCacheInterface $cache,
        private string $league,
    ) {
    }

    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighs()
     *
     * @return list<SeasonHighEntry>
     */
    public function getSeasonHighs(
        string $statExpression,
        string $statName,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15,
        ?string $locationFilter = null
    ): array {
        return $this->inner->getSeasonHighs(
            $statExpression,
            $statName,
            $tableSuffix,
            $startDate,
            $endDate,
            $limit,
            $locationFilter
        );
    }

    /**
     * @see SeasonHighsRepositoryInterface::getSeasonHighsBatch()
     *
     * @param array<string, string> $stats Map of stat name => SQL expression
     * @return array<string, list<SeasonHighEntry>>
     */
    public function getSeasonHighsBatch(
        array $stats,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit = 15,
        ?string $locationFilter = null
    ): array {
        $key = $this->buildCacheKey($stats, $tableSuffix, $startDate, $endDate, $limit, $locationFilter);

        /** @var array<string, list<SeasonHighEntry>>|null $cached */
        $cached = $this->cache->get($key);

        // A row with missing, extra, or reordered stat keys is treated as a miss.
        if ($cached !== null && array_keys($cached) === array_keys($stats)) {
            return $cached;
        }

        $result = $this->inner->getSeasonHighsBatch(
            $stats,
            $tableSuffix,
            $startDate,
            $endDate,
            $limit,
            $locationFilter
        );
        $this->cache->set($key, $result, self::TTL_SECONDS);

        return $result;
    }

    /**
     * @see SeasonHighsRepositoryInterface::getRcbSeasonHighs()
     *
     * @return list<array{stat_category: string, ranking: int, player_name: string, player_position: string|null, stat_value: int, record_season_year: int}>
     */
    public function getRcbSeasonHighs(int $seasonYear, string $context): array
    {
        return $this->inner->getRcbSeasonHighs($seasonYear, $context);
    }

    /**
     * The md5(json_encode($stats)) segment makes edits to the stats map self-invalidate.
     *
     * @param array<string, string> $stats
     */
    private function buildCacheKey(
        array $stats,
        string $tableSuffix,
        string $startDate,
        string $endDate,
        int $limit,
        ?string $locationFilter
    ): string {
        return self::CACHE_KEY_PREFIX
            . $this->league . ':'
            . ($tableSuffix === '' ? 'players' : 'teams') . ':'
            . $startDate . ':' . $endDate . ':'
            . $limit . ':'
            . ($locationFilter ?? 'all') . ':'
            . substr(md5((string) json_encode($stats)), 0, 12);
    }
}
