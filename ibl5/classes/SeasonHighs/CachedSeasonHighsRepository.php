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

    /** 30 days. Applies only to keys written by warmKeys(); RefreshSeasonHighsCacheStep deletes them after every IBL sim. */
    public const WARM_TTL_SECONDS = 2592000;

    private const MODE_READ = 'read';
    private const MODE_PLAN = 'plan';
    private const MODE_WARM = 'warm';

    private string $mode = self::MODE_READ;

    /** @var list<string> */
    private array $recordedKeys = [];

    public function __construct(
        private SeasonHighsRepositoryInterface $inner,
        private DatabaseCacheInterface $cache,
        private string $league,
    ) {
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

        if ($this->mode === self::MODE_PLAN) {
            $this->recordKey($key);

            return array_fill_keys(array_keys($stats), []);
        }

        if ($this->mode === self::MODE_WARM) {
            $warmed = $this->inner->getSeasonHighsBatch(
                $stats,
                $tableSuffix,
                $startDate,
                $endDate,
                $limit,
                $locationFilter
            );
            $this->cache->set($key, $warmed, self::WARM_TTL_SECONDS);
            $this->recordKey($key);

            return $warmed;
        }

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
     * Runs $calls with cache reads and the inner repository bypassed; returns every key the calls would request.
     *
     * @param callable(): void $calls
     * @return list<string>
     */
    public function planKeys(callable $calls): array
    {
        return $this->runInMode(self::MODE_PLAN, $calls);
    }

    /**
     * Runs $calls computing every result from the inner repository and overwriting its key with WARM_TTL_SECONDS.
     *
     * @param callable(): void $calls
     * @return list<string>
     */
    public function warmKeys(callable $calls): array
    {
        return $this->runInMode(self::MODE_WARM, $calls);
    }

    /**
     * @param list<string> $keys
     */
    public function deleteKeys(array $keys): int
    {
        foreach ($keys as $key) {
            $this->cache->delete($key);
        }

        return count($keys);
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
     * @param callable(): void $calls
     * @return list<string>
     */
    private function runInMode(string $mode, callable $calls): array
    {
        $this->mode = $mode;
        $this->recordedKeys = [];
        try {
            $calls();

            return $this->recordedKeys;
        } finally {
            $this->mode = self::MODE_READ;
            $this->recordedKeys = [];
        }
    }

    private function recordKey(string $key): void
    {
        if (!in_array($key, $this->recordedKeys, true)) {
            $this->recordedKeys[] = $key;
        }
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
