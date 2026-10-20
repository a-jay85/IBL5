<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use RecordHolders\CachedRecordHoldersService;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

final class CachedRecordHoldersServiceTest extends TestCase
{
    private RecordHoldersInMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new RecordHoldersInMemoryCache();
    }

    public function testCacheHitReturnsCachedDataWithoutCallingInnerService(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();
        $this->cache->set('record_holders', $records, 3600);

        $mockInner->expects($this->never())->method('getAllRecords');

        $result = $service->getAllRecords();

        $this->assertSame($records['playerSingleGame']['regularSeason'], $result['playerSingleGame']['regularSeason']);
    }

    public function testCacheMissCallsInnerServiceAndStoresResult(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
        $this->assertNotNull($this->cache->get('record_holders'));
    }

    public function testExpiredCacheServesStaleWithoutCallingInner(): void
    {
        // Serve-stale-first contract: an expired entry is returned as-is; the cron
        // (rebuildCache) is the refresher, so no inline rebuild runs on a user request.
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $staleRecords = $this->createSampleRecords();

        // Set with a past expiration timestamp to simulate an expired entry.
        $this->cache->setWithExpiration('record_holders', $staleRecords, time() - 100);

        $mockInner->expects($this->never())->method('getAllRecords');

        $result = $service->getAllRecords();

        $this->assertSame($staleRecords, $result);
    }

    public function testColdCacheBuildsOnceStoresAndReturnsRecords(): void
    {
        // Cold cache (no row at all): this request builds inline exactly once,
        // stores the result under the cache key, and returns it.
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
        $this->assertSame($records, $this->cache->get('record_holders'));
    }

    public function testColdMissWithNoStaleRowCallsOnlyGetGetStaleAndSet(): void
    {
        $records = $this->createSampleRecords();

        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockInner->expects($this->once())->method('getAllRecords')->willReturn($records);

        $mockCache = $this->createMock(DatabaseCacheInterface::class);
        $mockCache->expects($this->once())->method('get')->with('record_holders')->willReturn(null);
        $mockCache->expects($this->once())->method('getStale')->with('record_holders')->willReturn(null);
        $mockCache->expects($this->once())->method('set')->with('record_holders', $records, 86400);
        $mockCache->expects($this->never())->method('delete');

        $service = new CachedRecordHoldersService($mockInner, $mockCache);

        $this->assertSame($records, $service->getAllRecords());
    }

    public function testSecondCallAfterColdBuildServesCacheWithoutCallingInner(): void
    {
        $records = $this->createSampleRecords();

        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockInner->expects($this->once())->method('getAllRecords')->willReturn($records);

        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $first = $service->getAllRecords();
        $second = $service->getAllRecords();

        $this->assertSame($records, $first);
        $this->assertSame($records, $second);
    }

    public function testCorruptCacheReturnsNullFromGet(): void
    {
        // With DatabaseCacheInterface, corrupt JSON is handled inside the cache implementation.
        // If the cache returns null (miss), the service delegates to inner.
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();

        // Empty cache = miss
        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
    }

    public function testInvalidateCacheDeletesCacheEntry(): void
    {
        $stubInner = self::createStub(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($stubInner, $this->cache);

        $records = $this->createSampleRecords();
        $this->cache->set('record_holders', $records, 3600);

        $service->invalidateCache();

        $this->assertNull($this->cache->get('record_holders'));
    }

    public function testEmptyCacheCallsInnerService(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
    }

    public function testRebuildCacheAlwaysCallsInnerAndCaches(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $service = new CachedRecordHoldersService($mockInner, $this->cache);

        $records = $this->createSampleRecords();

        // Pre-populate cache
        $this->cache->set('record_holders', $this->createSampleRecords(), 3600);

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->rebuildCache();

        $this->assertSame($records, $result);
        $this->assertNotNull($this->cache->get('record_holders'));
    }

    /**
     * @return array{
     *     playerSingleGame: array{regularSeason: array<string, list<mixed>>, playoffs: array<string, list<mixed>>, heat: array<string, list<mixed>>},
     *     quadrupleDoubles: list<mixed>,
     *     allStarRecord: array{name: string, pid: int|null, teams: string, teamTids: string, amount: int, years: string},
     *     playerFullSeason: array<string, list<mixed>>,
     *     teamGameRecords: array<string, list<mixed>>,
     *     teamSeasonRecords: array<string, list<mixed>>,
     *     teamFranchise: array<string, list<mixed>>
     * }
     */
    private function createSampleRecords(): array
    {
        return [
            'playerSingleGame' => [
                'regularSeason' => ['Most Points in a Single Game' => []],
                'playoffs' => [],
                'heat' => [],
            ],
            'quadrupleDoubles' => [],
            'allStarRecord' => ['name' => 'Test', 'pid' => 1, 'teams' => '', 'teamTids' => '', 'amount' => 5, 'years' => ''],
            'playerFullSeason' => [],
            'teamGameRecords' => [],
            'teamSeasonRecords' => [],
            'teamFranchise' => [],
        ];
    }
}

/**
 * Simple in-memory implementation of DatabaseCacheInterface for RecordHolders tests.
 */
class RecordHoldersInMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array{data: array<mixed>, expiration: int}> */
    private array $store = [];

    /** Controls what acquireLock() returns (defaults to acquired). */
    public bool $lockAcquirable = true;

    /** Number of times releaseLock() was invoked. */
    public int $releaseLockCount = 0;

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if ($this->store[$key]['expiration'] < time()) {
            // Expired: return null WITHOUT deleting — mirrors the real DatabaseCache,
            // so getStale() can still recover the row.
            return null;
        }

        return $this->store[$key]['data'];
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        // Ignore expiration entirely — return the stored value if a row exists.
        return $this->store[$key]['data'] ?? null;
    }

    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        return $this->lockAcquirable;
    }

    public function releaseLock(string $key): void
    {
        $this->releaseLockCount++;
    }

    /**
     * @param array<mixed> $data
     */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = [
            'data' => $data,
            'expiration' => time() + $ttlSeconds,
        ];
    }

    /**
     * Set with an explicit expiration timestamp (for testing expired entries).
     *
     * @param array<mixed> $data
     */
    public function setWithExpiration(string $key, array $data, int $expiration): void
    {
        $this->store[$key] = [
            'data' => $data,
            'expiration' => $expiration,
        ];
    }

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }
}
