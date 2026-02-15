<?php

declare(strict_types=1);

namespace Tests\RecordHolders;

use PHPUnit\Framework\TestCase;
use RecordHolders\CachedRecordHoldersService;
use RecordHolders\Contracts\RecordHoldersServiceInterface;

final class CachedRecordHoldersServiceTest extends TestCase
{
    public function testCacheHitReturnsCachedDataWithoutCallingInnerService(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $records = $this->createSampleRecords();
        $mockDb->setCacheData('record_holders', (string) json_encode($records), time() + 3600);

        $mockInner->expects($this->never())->method('getAllRecords');

        $result = $service->getAllRecords();

        $this->assertSame($records['playerSingleGame']['regularSeason'], $result['playerSingleGame']['regularSeason']);
    }

    public function testCacheMissCallsInnerServiceAndStoresResult(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $records = $this->createSampleRecords();

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
        $this->assertTrue($mockDb->wasWriteCalled());
    }

    public function testExpiredCacheTriggersRefresh(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $staleRecords = $this->createSampleRecords();
        $freshRecords = $this->createSampleRecords();
        $freshRecords['playerSingleGame']['regularSeason'] = [];

        $mockDb->setCacheData('record_holders', (string) json_encode($staleRecords), time() - 100);

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($freshRecords);

        $result = $service->getAllRecords();

        $this->assertSame([], $result['playerSingleGame']['regularSeason']);
    }

    public function testCorruptCacheFallsBackToInnerService(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $records = $this->createSampleRecords();

        $mockDb->setCacheData('record_holders', '{invalid json!!!', time() + 3600);

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
    }

    public function testInvalidateCacheDeletesCacheEntry(): void
    {
        $stubInner = $this->createStub(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($stubInner, $mockDb);

        $records = $this->createSampleRecords();
        $mockDb->setCacheData('record_holders', (string) json_encode($records), time() + 3600);

        $service->invalidateCache();

        $this->assertTrue($mockDb->wasDeleteCalled());
    }

    public function testEmptyCacheCallsInnerService(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $records = $this->createSampleRecords();

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
    }

    public function testDbPrepareFailureGracefullyFallsBackToInner(): void
    {
        $mockInner = $this->createMock(RecordHoldersServiceInterface::class);
        $mockDb = new MockCacheDb();
        $service = new CachedRecordHoldersService($mockInner, $mockDb);

        $records = $this->createSampleRecords();

        $mockDb->setPrepareShouldFail(true);

        $mockInner->expects($this->once())
            ->method('getAllRecords')
            ->willReturn($records);

        $result = $service->getAllRecords();

        $this->assertSame($records, $result);
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
 * Minimal mock of a mysqli-like object for testing the cache layer.
 *
 * Simulates prepare/execute/get_result/fetch_assoc for SELECT, REPLACE, and DELETE
 * queries against the `cache` table.
 */
class MockCacheDb extends \mysqli
{
    /** @var array<string, array{value: string, expiration: int}> */
    private array $cacheStore = [];
    private bool $writeCalled = false;
    private bool $deleteCalled = false;
    private bool $prepareShouldFail = false;

    public int $connect_errno = 0;
    public ?string $connect_error = null;

    public function __construct()
    {
        // Don't call parent::__construct() to avoid real DB connection
    }

    public function setCacheData(string $key, string $value, int $expiration): void
    {
        $this->cacheStore[$key] = ['value' => $value, 'expiration' => $expiration];
    }

    public function setPrepareShouldFail(bool $fail): void
    {
        $this->prepareShouldFail = $fail;
    }

    public function wasWriteCalled(): bool
    {
        return $this->writeCalled;
    }

    public function wasDeleteCalled(): bool
    {
        return $this->deleteCalled;
    }

    /**
     * @return MockCacheStmt|false
     */
    #[\ReturnTypeWillChange]
    public function prepare(string $query): MockCacheStmt|false
    {
        if ($this->prepareShouldFail) {
            return false;
        }

        return new MockCacheStmt($this, $query);
    }

    /**
     * @return array{value: string, expiration: int}|null
     */
    public function getCacheEntry(string $key): ?array
    {
        return $this->cacheStore[$key] ?? null;
    }

    public function markWriteCalled(): void
    {
        $this->writeCalled = true;
    }

    public function markDeleteCalled(): void
    {
        $this->deleteCalled = true;
    }

    public function deleteKey(string $key): void
    {
        unset($this->cacheStore[$key]);
    }
}

class MockCacheStmt
{
    private MockCacheDb $db;
    private string $query;
    private string $boundKey = '';

    public function __construct(MockCacheDb $db, string $query)
    {
        $this->db = $db;
        $this->query = $query;
    }

    public function bind_param(string $types, mixed &...$params): bool
    {
        if ($params !== []) {
            $this->boundKey = (string) $params[0];
        }
        if (str_contains($this->query, 'REPLACE')) {
            $this->db->markWriteCalled();
        }
        return true;
    }

    public function execute(): bool
    {
        if (str_contains($this->query, 'DELETE')) {
            $this->db->markDeleteCalled();
            $this->db->deleteKey($this->boundKey);
        }
        return true;
    }

    public function get_result(): MockCacheResult
    {
        $entry = $this->db->getCacheEntry($this->boundKey);
        return new MockCacheResult($entry);
    }

    public function close(): void
    {
    }
}

class MockCacheResult
{
    /** @var array{value: string, expiration: int}|null */
    private ?array $data;
    private bool $fetched = false;

    /**
     * @param array{value: string, expiration: int}|null $data
     */
    public function __construct(?array $data)
    {
        $this->data = $data;
    }

    /**
     * @return array{value: string, expiration: int}|null
     */
    public function fetch_assoc(): ?array
    {
        if ($this->fetched || $this->data === null) {
            return null;
        }
        $this->fetched = true;
        return $this->data;
    }
}
