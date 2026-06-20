<?php

declare(strict_types=1);

namespace Tests\Cache;

use Cache\DatabaseCache;
use PHPUnit\Framework\TestCase;
use Tests\Clock\FixedClock;

final class DatabaseCacheTest extends TestCase
{
    /**
     * Boundary hit: with the clock fixed at T, a row whose expiration equals T
     * is still served (the check is `expiration < now`, strict).
     */
    public function testGetReturnsHitWhenExpirationEqualsClockNow(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb, null, new FixedClock(1_000_000));

        $data = [['pid' => 1, 'name' => 'Player 1']];
        $mockDb->setCacheData('boundary_key', (string) json_encode($data), 1_000_000);

        $this->assertSame($data, $cache->get('boundary_key'));
    }

    /**
     * Boundary miss (negative path): with the clock fixed at T, a row whose
     * expiration is T-1 is expired and returns null.
     */
    public function testGetReturnsMissWhenExpirationOneSecondBeforeClockNow(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb, null, new FixedClock(1_000_000));

        $data = [['pid' => 1, 'name' => 'Player 1']];
        $mockDb->setCacheData('expired_key', (string) json_encode($data), 999_999);

        $this->assertNull($cache->get('expired_key'));
    }

    public function testGetReturnsCachedDataOnHit(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [['pid' => 1, 'name' => 'Player 1', 'pts' => 100]];
        $mockDb->setCacheData('test_key', (string) json_encode($data), time() + 3600);

        $result = $cache->get('test_key');

        $this->assertSame($data, $result);
    }

    public function testGetReturnsNullOnMiss(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $result = $cache->get('nonexistent_key');

        $this->assertNull($result);
    }

    public function testGetReturnsNullOnExpiredEntry(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [['pid' => 1, 'name' => 'Player 1']];
        $mockDb->setCacheData('expired_key', (string) json_encode($data), time() - 100);

        $result = $cache->get('expired_key');

        $this->assertNull($result);
    }

    public function testGetReturnsNullOnCorruptJson(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $mockDb->setCacheData('corrupt_key', '{invalid json!!!', time() + 3600);

        $result = $cache->get('corrupt_key');

        $this->assertNull($result);
    }

    public function testGetReturnsNullOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $mockDb->setPrepareShouldFail(true);

        $result = $cache->get('any_key');

        $this->assertNull($result);
    }

    public function testSetWritesToCache(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [['pid' => 1, 'name' => 'Player 1', 'pts' => 100]];
        $cache->set('write_key', $data, 3600);

        $this->assertTrue($mockDb->wasWriteCalled());
    }

    public function testSetWritesCorrectExpiration(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [['pid' => 1, 'name' => 'Player 1']];
        $before = time();
        $cache->set('ttl_key', $data, 7200);
        $after = time();

        $expiration = $mockDb->getLastWrittenExpiration();
        $this->assertGreaterThanOrEqual($before + 7200, $expiration);
        $this->assertLessThanOrEqual($after + 7200, $expiration);
    }

    public function testSetSilentlyFailsOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $mockDb->setPrepareShouldFail(true);

        // Should not throw
        $cache->set('key', [['pid' => 1]], 3600);

        $this->assertFalse($mockDb->wasWriteCalled());
    }

    public function testDeleteRemovesEntry(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [['pid' => 1, 'name' => 'Player 1']];
        $mockDb->setCacheData('delete_key', (string) json_encode($data), time() + 3600);

        $cache->delete('delete_key');

        $this->assertTrue($mockDb->wasDeleteCalled());
        $this->assertNull($cache->get('delete_key'));
    }

    public function testDeleteSilentlyFailsOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $mockDb->setPrepareShouldFail(true);

        // Should not throw
        $cache->delete('any_key');

        $this->assertFalse($mockDb->wasDeleteCalled());
    }

    public function testSetThenGetRoundTrips(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        $data = [
            ['pid' => 1, 'name' => 'Player 1', 'pts' => 100],
            ['pid' => 2, 'name' => 'Player 2', 'pts' => 95],
        ];

        $cache->set('round_trip', $data, 3600);

        $result = $cache->get('round_trip');

        $this->assertSame($data, $result);
    }

    public function testGetLogsErrorOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('cache_get_failed', \PHPUnit\Framework\Assert::anything());
        $cache = new DatabaseCache($mockDb, $logger);

        $mockDb->setPrepareShouldFail(true);
        $result = $cache->get('any_key');

        $this->assertNull($result);
    }

    public function testGetLogsWarningOnCorruptJson(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('cache_corrupt_json', \PHPUnit\Framework\Assert::anything());
        $cache = new DatabaseCache($mockDb, $logger);

        $mockDb->setCacheData('corrupt_key', '{invalid json!!!', time() + 3600);
        $result = $cache->get('corrupt_key');

        $this->assertNull($result);
    }

    public function testGetDoesNotLogOnCacheMiss(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');
        $cache = new DatabaseCache($mockDb, $logger);

        $result = $cache->get('nonexistent');

        $this->assertNull($result);
    }

    public function testGetDoesNotLogOnExpiredEntry(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->never())->method('error');
        $logger->expects($this->never())->method('warning');
        $cache = new DatabaseCache($mockDb, $logger);

        $mockDb->setCacheData('expired_key', (string) json_encode(['x' => 1]), time() - 100);
        $result = $cache->get('expired_key');

        $this->assertNull($result);
    }

    public function testSetLogsErrorOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('cache_set_failed', \PHPUnit\Framework\Assert::anything());
        $cache = new DatabaseCache($mockDb, $logger);

        $mockDb->setPrepareShouldFail(true);
        $cache->set('key', [['pid' => 1]], 3600);

        $this->assertFalse($mockDb->wasWriteCalled());
    }

    public function testDeleteLogsErrorOnPrepareFailure(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('error')->with('cache_delete_failed', \PHPUnit\Framework\Assert::anything());
        $cache = new DatabaseCache($mockDb, $logger);

        $mockDb->setPrepareShouldFail(true);
        $cache->delete('any_key');

        $this->assertFalse($mockDb->wasDeleteCalled());
    }

    public function testConstructsWithoutLoggerArg(): void
    {
        $mockDb = new MockCacheDb();
        $cache = new DatabaseCache($mockDb);

        // Un-injected production call shape: fallback logger fires, no TypeError
        $data = [['pid' => 1]];
        $cache->set('noarg_key', $data, 3600);
        $result = $cache->get('noarg_key');
        $this->assertSame($data, $result);

        $cache->delete('noarg_key');
        $this->assertNull($cache->get('noarg_key'));
    }

    public function testSetLogsOnEncodeFailure(): void
    {
        $mockDb = new MockCacheDb();
        $logger = $this->createMock(\Psr\Log\LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with('cache_set_encode_failed', \PHPUnit\Framework\Assert::anything());
        $cache = new DatabaseCache($mockDb, $logger);

        // NAN is not JSON-encodable
        $cache->set('encode_fail_key', ['x' => NAN], 3600);

        $this->assertFalse($mockDb->wasWriteCalled());
    }
}

/**
 * Mock mysqli-like object for testing DatabaseCache.
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
    private int $lastWrittenExpiration = 0;

    public function __construct()
    {
        // No-arg parent constructor allocates an unconnected mysqli shell; the
        // real connection is never used because prepare() is overridden.
        parent::__construct();
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

    public function getLastWrittenExpiration(): int
    {
        return $this->lastWrittenExpiration;
    }

    /**
     * MockCacheStmt cannot extend mysqli_stmt (mysqli_result has read-only
     * properties a mock must write), so this override cannot be covariant with
     * mysqli::prepare()'s mysqli_stmt|false. ReturnTypeWillChange suppresses the
     * runtime LSP deprecation; the ignore below is its static-analysis twin.
     *
     * @return MockCacheStmt|false
     * @phpstan-ignore method.childReturnType
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

    public function markWriteCalled(string $key, string $value, int $expiration): void
    {
        $this->writeCalled = true;
        $this->lastWrittenExpiration = $expiration;
        // Store the data so round-trip tests work
        $this->cacheStore[$key] = ['value' => $value, 'expiration' => $expiration];
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
    public int $affected_rows = 1;
    private MockCacheDb $db;
    private string $query;
    private string $boundKey = '';
    private string $boundValue = '';
    private int $boundExpiration = 0;

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
        if (count($params) >= 2) {
            $this->boundValue = (string) $params[1];
        }
        if (count($params) >= 3) {
            $this->boundExpiration = (int) $params[2];
        }
        return true;
    }

    public function execute(): bool
    {
        if (str_contains($this->query, 'REPLACE')) {
            $this->db->markWriteCalled($this->boundKey, $this->boundValue, $this->boundExpiration);
        }
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
