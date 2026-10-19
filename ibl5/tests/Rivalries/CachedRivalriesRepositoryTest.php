<?php

declare(strict_types=1);

namespace Tests\Rivalries;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use Rivalries\CachedRivalriesRepository;
use Rivalries\Contracts\RivalriesRepositoryInterface;

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
}

final class RivalriesSpyRepository implements RivalriesRepositoryInterface
{
    /** @var array<string, int> */
    public array $calls = [];
    /** @var list<int|string> */
    public array $lastArgs = [];
    /** @var list<array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}> */
    public array $franchiseStats = [
        ['a' => '3', 'b' => '7', 'close_games' => 2, 'recent_games' => 4, 'playoff_games' => 0, 'playoff_series' => 0],
    ];
    /** @var list<array{a: string, b: string, close_games: int, recent_games: int, playoff_games: int, playoff_series: int}> */
    public array $gmStats = [
        ['a' => 'Amy', 'b' => 'Zed', 'close_games' => 1, 'recent_games' => 2, 'playoff_games' => 0, 'playoff_series' => 0],
    ];

    public function getFranchisePairStats(int $recentFromSeasonYear): array
    {
        $this->record(__FUNCTION__, [$recentFromSeasonYear]);
        return $this->franchiseStats;
    }

    public function getGmPairStats(int $recentFromSeasonYear): array
    {
        $this->record(__FUNCTION__, [$recentFromSeasonYear]);
        return $this->gmStats;
    }

    public function getFranchisePairGames(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getGmPairGames(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getFranchisePlayoffSeries(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getGmPlayoffSeries(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return [];
    }

    public function getFranchiseTopPerformers(int $a, int $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return ['totals' => [], 'highs' => []];
    }

    public function getGmTopPerformers(string $a, string $b): array
    {
        $this->record(__FUNCTION__, [$a, $b]);
        return ['totals' => [], 'highs' => []];
    }

    public function gmExists(string $gmName): bool
    {
        $this->record(__FUNCTION__, [$gmName]);
        return false;
    }

    /** @param list<int|string> $args */
    private function record(string $method, array $args): void
    {
        $this->calls[$method] = ($this->calls[$method] ?? 0) + 1;
        $this->lastArgs = $args;
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
