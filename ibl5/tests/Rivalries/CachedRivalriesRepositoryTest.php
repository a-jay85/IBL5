<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use Rivalries\CachedRivalriesRepository;

final class CachedRivalriesRepositoryTest extends TestCase
{
    private RivalriesInMemoryCache $cache;
    private RivalriesSpyRepository $inner;
    private CachedRivalriesRepository $repo;

    protected function setUp(): void
    {
        $this->cache = new RivalriesInMemoryCache();
        $this->inner = new RivalriesSpyRepository();
        $this->repo = new CachedRivalriesRepository($this->inner, $this->cache);
    }

    public function testStatsMissStoresWithDayTtlAndHitSkipsInner(): void
    {
        $first = $this->repo->getFranchisePairStats(2022);
        $second = $this->repo->getFranchisePairStats(2022);

        self::assertSame($first, $second);
        self::assertSame(1, $this->inner->calls['getFranchisePairStats'] ?? 0);
        self::assertSame(86400, $this->cache->ttls['rivalries:franchises:2022'] ?? null);
    }

    public function testPairKeysAreCanonicalAcrossArgumentOrder(): void
    {
        $this->repo->getFranchisePairGames(7, 3);
        $this->repo->getFranchisePairGames(3, 7);

        self::assertSame(1, $this->inner->calls['getFranchisePairGames'] ?? 0);
        self::assertSame([3, 7], $this->inner->lastArgs);
        self::assertArrayHasKey('rivalries:pair:teams:3-7:games', $this->cache->ttls);
    }

    public function testGmPairKeyIsHashedAndBounded(): void
    {
        $this->repo->getGmPairGames('Zed <b>', 'Amy');
        $this->repo->getGmTopPerformers('Zed <b>', 'Amy');

        $keys = array_keys($this->cache->ttls);
        self::assertCount(2, $keys);
        foreach ($keys as $key) {
            self::assertStringNotContainsString('Zed', $key);
            self::assertStringNotContainsString('Amy', $key);
            self::assertLessThanOrEqual(64, strlen($key));
        }
        self::assertSame(['Amy', 'Zed <b>'], $this->inner->lastArgs);
    }

    public function testRebuildCacheRefreshesStatsAndDropsPairKeys(): void
    {
        $this->cache->set('rivalries:franchises:2022', [['stale' => true]], 86400);
        $this->cache->set('rivalries:pair:teams:3-7:games', [['stale' => true]], 86400);

        $written = $this->repo->rebuildCache(2022);

        self::assertSame(2 + 6, $written);
        self::assertSame($this->inner->franchiseStats, $this->cache->get('rivalries:franchises:2022'));
        self::assertSame($this->inner->gmStats, $this->cache->get('rivalries:gms:2022'));
        self::assertNull($this->cache->get('rivalries:pair:teams:3-7:games'));
    }

    public function testRebuildCacheDropsGmPairKeyWhenSqlOrderDiffersFromStrcmp(): void
    {
        // A case-insensitive collation sorts 'alice' before 'Bob'; strcmp sorts 'Bob' first.
        $this->inner->gmStats = [['a' => 'alice', 'b' => 'Bob', 'close_games' => 1, 'recent_games' => 1, 'playoff_games' => 0, 'playoff_series' => 0]];
        $this->repo->getGmPairGames('alice', 'Bob');
        $this->repo->getGmPlayoffSeries('Bob', 'alice');
        $this->repo->getGmTopPerformers('alice', 'Bob');
        self::assertCount(3, $this->cache->ttls);

        $this->repo->rebuildCache(2022);

        self::assertEqualsCanonicalizing(['rivalries:franchises:2022', 'rivalries:gms:2022'], array_keys($this->cache->ttls));
    }
}


final class RivalriesInMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array<mixed>> */
    private array $store = [];
    /** @var array<string, int> */
    public array $ttls = [];

    /** @return array<mixed>|null */
    public function get(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }

    /** @return array<mixed>|null */
    public function getStale(string $key): ?array
    {
        return $this->store[$key] ?? null;
    }

    /** @param array<mixed> $data */
    public function set(string $key, array $data, int $ttlSeconds): void
    {
        $this->store[$key] = $data;
        $this->ttls[$key] = $ttlSeconds;
    }

    public function delete(string $key): void
    {
        unset($this->store[$key], $this->ttls[$key]);
    }

    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        return true;
    }

    public function releaseLock(string $key): void
    {
        // no-op
    }
}
