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

    private RecordHoldersServiceInterface $inner;
    private DatabaseCacheInterface $cache;

    public function __construct(RecordHoldersServiceInterface $inner, DatabaseCacheInterface $cache)
    {
        $this->inner = $inner;
        $this->cache = $cache;
    }

    /**
     * @return AllRecordsData
     */
    public function getAllRecords(): array
    {
        $cached = $this->cache->get(self::CACHE_KEY);
        if ($cached !== null) {
            /** @var AllRecordsData $cached */
            return $cached;
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
