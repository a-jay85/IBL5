<?php

declare(strict_types=1);

namespace Tests\Cache;

use Cache\DatabaseCache;
use PHPUnit\Framework\TestCase;

final class DatabaseCacheTest extends TestCase
{
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

    public function getLastWrittenExpiration(): int
    {
        return $this->lastWrittenExpiration;
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
