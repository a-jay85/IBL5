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

    public function testStatsMissStoresWithMonthTtlAndHitSkipsInner(): void
    {
        $first = $this->repo->getFranchisePairStats(2022);
        $second = $this->repo->getFranchisePairStats(2022);

        self::assertSame($first, $second);
        self::assertSame(1, $this->inner->calls['getFranchisePairStats'] ?? 0);
        self::assertSame(2592000, $this->cache->ttls['rivalries:franchises:2022'] ?? null);
    }

    /**
     * Plan row 11 name. Stats keys moved to a 30-day TTL (test above), so the
     * 24-hour TTL this name promises now belongs to the pair keys.
     */
    public function testPairMissStoresWithDayTtlAndHitSkipsInner(): void
    {
        $first = $this->repo->getFranchisePairGames(3, 7);
        $second = $this->repo->getFranchisePairGames(3, 7);

        self::assertSame($first, $second);
        self::assertSame(1, $this->inner->calls['getFranchisePairGames'] ?? 0);
        self::assertSame(86400, $this->cache->ttls['rivalries:pair:teams:3-7:games'] ?? null);
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

    /**
     * @return array<string, array{'getFranchisePairGames'|'getFranchisePlayoffSeries'|'getFranchiseTopPerformers'|'getGmPairGames'|'getGmPlayoffSeries'|'getGmTopPerformers', int|string, int|string, list<int|string>}>
     */
    public static function pairLookupProvider(): array
    {
        return [
            'franchise games' => ['getFranchisePairGames', 7, 3, [3, 7]],
            'franchise series' => ['getFranchisePlayoffSeries', 7, 3, [3, 7]],
            'franchise performers' => ['getFranchiseTopPerformers', 7, 3, [3, 7]],
            'gm games' => ['getGmPairGames', 'Zed', 'Amy', ['Amy', 'Zed']],
            'gm series' => ['getGmPlayoffSeries', 'Zed', 'Amy', ['Amy', 'Zed']],
            'gm performers' => ['getGmTopPerformers', 'Zed', 'Amy', ['Amy', 'Zed']],
        ];
    }

    /**
     * @param 'getFranchisePairGames'|'getFranchisePlayoffSeries'|'getFranchiseTopPerformers'|'getGmPairGames'|'getGmPlayoffSeries'|'getGmTopPerformers' $method
     * @param int|string $a
     * @param int|string $b
     * @param list<int|string> $canonical
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('pairLookupProvider')]
    public function testPairLookupHitsCacheInEitherOrderWithDayTtl(string $method, int|string $a, int|string $b, array $canonical): void
    {
        $call = match ($method) {
            'getFranchisePairGames' => fn (int|string $x, int|string $y): array => $this->repo->getFranchisePairGames((int) $x, (int) $y),
            'getFranchisePlayoffSeries' => fn (int|string $x, int|string $y): array => $this->repo->getFranchisePlayoffSeries((int) $x, (int) $y),
            'getFranchiseTopPerformers' => fn (int|string $x, int|string $y): array => $this->repo->getFranchiseTopPerformers((int) $x, (int) $y),
            'getGmPairGames' => fn (int|string $x, int|string $y): array => $this->repo->getGmPairGames((string) $x, (string) $y),
            'getGmPlayoffSeries' => fn (int|string $x, int|string $y): array => $this->repo->getGmPlayoffSeries((string) $x, (string) $y),
            'getGmTopPerformers' => fn (int|string $x, int|string $y): array => $this->repo->getGmTopPerformers((string) $x, (string) $y),
        };

        $first = $call($a, $b);
        $second = $call($b, $a);

        self::assertSame($first, $second);
        self::assertSame(1, $this->inner->calls[$method] ?? 0);
        self::assertSame($canonical, $this->inner->lastArgs);
        self::assertSame([86400], array_values($this->cache->ttls));
    }

    public function testGmStatsMissStoresWithMonthTtlAndHitSkipsInner(): void
    {
        $first = $this->repo->getGmPairStats(2022);
        $second = $this->repo->getGmPairStats(2022);

        self::assertSame($this->inner->gmStats, $first);
        self::assertSame($first, $second);
        self::assertSame(1, $this->inner->calls['getGmPairStats'] ?? 0);
        self::assertSame(2592000, $this->cache->ttls['rivalries:gms:2022'] ?? null);
    }

    public function testExpiredStatsEntryIsServedWithoutInnerScan(): void
    {
        $stale = [['a' => '1', 'b' => '2', 'close_games' => 0, 'recent_games' => 0, 'playoff_games' => 0, 'playoff_series' => 0]];
        $this->cache->set('rivalries:franchises:2022', $stale, 1);
        $this->cache->set('rivalries:gms:2022', $stale, 1);
        $this->cache->expire('rivalries:franchises:2022');
        $this->cache->expire('rivalries:gms:2022');

        self::assertSame($stale, $this->repo->getFranchisePairStats(2022));
        self::assertSame($stale, $this->repo->getGmPairStats(2022));
        self::assertArrayNotHasKey('getFranchisePairStats', $this->inner->calls);
        self::assertArrayNotHasKey('getGmPairStats', $this->inner->calls);
    }

    public function testExpiredPairEntryIsRefetched(): void
    {
        $this->cache->set('rivalries:pair:teams:3-7:games', [['stale' => true]], 1);
        $this->cache->expire('rivalries:pair:teams:3-7:games');

        self::assertSame([], $this->repo->getFranchisePairGames(3, 7));
        self::assertSame(1, $this->inner->calls['getFranchisePairGames'] ?? 0);
    }

    public function testGmLookupsPassThroughWithoutCaching(): void
    {
        $this->inner->colorFranchises = ['Amy' => 7];

        self::assertNull($this->repo->findGmName('Amy'));
        self::assertNull($this->repo->findGmName('Amy'));
        self::assertSame(7, $this->repo->findColorFranchise('Amy'));
        self::assertSame(7, $this->repo->findColorFranchise('Amy'));

        self::assertSame(2, $this->inner->calls['findGmName'] ?? 0);
        self::assertSame(2, $this->inner->calls['findColorFranchise'] ?? 0);
        self::assertSame([], $this->cache->ttls);
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

    /** @var array<string, true> keys whose TTL has run out */
    private array $expired = [];

    public function expire(string $key): void
    {
        $this->expired[$key] = true;
    }

    /** @return array<mixed>|null */
    public function get(string $key): ?array
    {
        return isset($this->expired[$key]) ? null : $this->store[$key] ?? null;
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
        unset($this->expired[$key]);
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
