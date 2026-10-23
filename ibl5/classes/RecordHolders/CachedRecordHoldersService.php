<?php

declare(strict_types=1);

namespace RecordHolders;

use Cache\Contracts\DatabaseCacheInterface;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

/**
 * CachedRecordHoldersService - Caching decorator for RecordHoldersServiceInterface.
 *
 * Wraps an inner service and caches the getAllRecords() result via DatabaseCacheInterface.
 * On cache hit, returns data from the cache without calling the inner service.
 * On expiration, serves the stale entry. rebuildCache() refreshes it from two callers:
 * Updater\Steps\RefreshRecordHoldersStep after each sim's box scores are imported, and
 * the 5 AM cron. The updater step skips when its run is over budget; the cron is the backstop.
 * On a cold miss (no row at all), delegates to the inner service and stores the result.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 */
class CachedRecordHoldersService implements RecordHoldersServiceInterface
{
    private const CACHE_KEY = 'record_holders';
    private const TTL_SECONDS = 86400; // 24 hours

    private RecordHoldersServiceInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(RecordHoldersServiceInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * Serve-stale-first read.
     *
     * 1. Fresh hit: return it.
     * 2. Expired entry present: serve the stale value immediately. rebuildCache() runs from
     *    the updater step and the cron, so no heavy inline rebuild happens on a user's request.
     * 3. Cold (no row at all): build inline from the inner service, store it, return it.
     *    Two concurrent cold requests may both build; the cache write is idempotent.
     *
     * @return AllRecordsData
     */
    public function getAllRecords(): array
    {
        $fresh = $this->cache->get(self::CACHE_KEY);
        if ($fresh !== null) {
            /** @var AllRecordsData $fresh */
            return $fresh;
        }

        $stale = $this->cache->getStale(self::CACHE_KEY);
        if ($stale !== null) {
            /** @var AllRecordsData $stale */
            return $stale;
        }

        $records = $this->inner->getAllRecords();
        $this->cache->set(self::CACHE_KEY, $records, self::TTL_SECONDS);

        return $records;
    }

    /**
     * Fetch fresh records from the inner service and atomically replace the cache.
     *
     * @return AllRecordsData
     */
    public function rebuildCache(): array
    {
        $records = $this->inner->getAllRecords();
        $this->cache->set(self::CACHE_KEY, $records, self::TTL_SECONDS);

        return $records;
    }

    /**
     * Remove the cached record holders data.
     */
    public function invalidateCache(): void
    {
        $this->cache->delete(self::CACHE_KEY);
    }
}
