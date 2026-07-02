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
 * On cache miss or expiration, delegates to the inner service and stores the result.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 */
class CachedRecordHoldersService implements RecordHoldersServiceInterface
{
    private const CACHE_KEY = 'record_holders';
    private const TTL_SECONDS = 86400; // 24 hours
    private const LOCK_KEY = 'record_holders_rebuild';
    private const LOCK_TIMEOUT_SECONDS = 10;

    private RecordHoldersServiceInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(RecordHoldersServiceInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * Serve-stale-first, single-flight cold build.
     *
     * 1. Fresh hit → return it.
     * 2. Expired entry present → serve the stale value immediately; the cron (rebuildCache)
     *    is the refresher, so no heavy inline rebuild happens on a user's request.
     * 3. Cold/empty → only the request that wins the single-flight lock rebuilds inline;
     *    concurrent requests fall through to an empty structure (thundering-herd guard).
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

        if ($this->cache->acquireLock(self::LOCK_KEY, self::LOCK_TIMEOUT_SECONDS)) {
            try {
                // Another request may have built the entry while we waited for the lock.
                $again = $this->cache->get(self::CACHE_KEY);
                if ($again !== null) {
                    /** @var AllRecordsData $again */
                    return $again;
                }

                $records = $this->inner->getAllRecords();
                $this->cache->set(self::CACHE_KEY, $records, self::TTL_SECONDS);

                return $records;
            } finally {
                $this->cache->releaseLock(self::LOCK_KEY);
            }
        }

        // Lock timed out — degrade: serve whatever a concurrent builder produced, else empty.
        $again = $this->cache->get(self::CACHE_KEY);
        if ($again !== null) {
            /** @var AllRecordsData $again */
            return $again;
        }

        return $this->emptyRecords();
    }

    /**
     * Empty AllRecordsData structure served when the cache is cold and the single-flight
     * lock could not be acquired (a concurrent request is already rebuilding).
     *
     * @return AllRecordsData
     */
    private function emptyRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => [],
                'playoffs' => [],
                'heat' => [],
            ],
            'quadrupleDoubles' => [],
            'allStarRecord' => [
                'name' => '',
                'pid' => null,
                'teams' => '',
                'teamTids' => '',
                'amount' => 0,
                'years' => '',
            ],
            'playerFullSeason' => [],
            'teamGameRecords' => [],
            'teamSeasonRecords' => [],
            'teamFranchise' => [],
        ];
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
