<?php

declare(strict_types=1);

namespace RecordHolders\Contracts;

/**
 * Cache-maintenance operations for the Record Holders cache row.
 *
 * Kept apart from RecordHoldersServiceInterface because the live RecordHoldersService
 * has no cache to rebuild or invalidate. Only the caching decorator implements this.
 *
 * @phpstan-import-type AllRecordsData from RecordHoldersServiceInterface
 */
interface RecordHoldersCacheInterface
{
    /**
     * Fetch fresh records from the underlying service and atomically replace the cache.
     *
     * @return AllRecordsData
     */
    public function rebuildCache(): array;

    /**
     * Remove the cached record holders data.
     */
    public function invalidateCache(): void;
}
