<?php

declare(strict_types=1);

namespace Tests\SeasonLeaderboards;

use Cache\Contracts\DatabaseCacheInterface;
use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\CachedSeasonLeaderboardsRepository;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;

final class CachedSeasonLeaderboardsRepositoryTest extends TestCase
{
    private SeasonLeaderboardsInMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new SeasonLeaderboardsInMemoryCache();
    }

    public function testCacheHitDoesNotCallInnerRepository(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $rows = [
            ['pid' => 1, 'name' => 'P1', 'year' => 2024, 'team' => 'T1', 'teamid' => 1, 'games' => 80, 'minutes' => 1200, 'fgm' => 400, 'fga' => 800, 'ftm' => 150, 'fta' => 200, 'tgm' => 75, 'tga' => 200, 'orb' => 50, 'reb' => 200, 'ast' => 100, 'stl' => 30, 'blk' => 20, 'tvr' => 50, 'pf' => 60, 'team_city' => null, 'color1' => null, 'color2' => null],
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $mockInner->expects($this->never())->method('getSeasonLeaders');

        $result = $repository->getSeasonLeaders([], 0);

        $this->assertSame(1, $result['count']);
        $this->assertSame(1, $result['results'][0]['pid']);
    }

    public function testCacheMissDelegatesToInnerAndCaches(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $rows = [
            ['pid' => 1, 'name' => 'P1', 'year' => 2024, 'team' => 'T1', 'teamid' => 1, 'games' => 80, 'minutes' => 1200, 'fgm' => 400, 'fga' => 800, 'ftm' => 150, 'fta' => 200, 'tgm' => 75, 'tga' => 200, 'orb' => 50, 'reb' => 200, 'ast' => 100, 'stl' => 30, 'blk' => 20, 'tvr' => 50, 'pf' => 60, 'team_city' => null, 'color1' => null, 'color2' => null],
        ];

        $mockInner->expects($this->once())
            ->method('getSeasonLeaders')
            ->with([], 0)
            ->willReturn(['results' => $rows, 'count' => 1]);

        $result = $repository->getSeasonLeaders([], 0);

        $this->assertSame(1, $result['count']);
        $this->assertNotNull($this->cache->get('season_leaderboards:leaders'));
    }

    public function testReturnsAllRowsWithoutFilteringOrSorting(): void
    {
        $stubInner = self::createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            ['pid' => 3, 'name' => 'P3', 'year' => 2025, 'team' => 'T3', 'teamid' => 3, 'games' => 60, 'minutes' => 900, 'fgm' => 250, 'fga' => 500, 'ftm' => 100, 'fta' => 150, 'tgm' => 50, 'tga' => 100, 'orb' => 30, 'reb' => 120, 'ast' => 50, 'stl' => 20, 'blk' => 10, 'tvr' => 30, 'pf' => 40, 'team_city' => null, 'color1' => null, 'color2' => null],
            ['pid' => 1, 'name' => 'P1', 'year' => 2024, 'team' => 'T1', 'teamid' => 1, 'games' => 80, 'minutes' => 1200, 'fgm' => 400, 'fga' => 800, 'ftm' => 150, 'fta' => 200, 'tgm' => 75, 'tga' => 200, 'orb' => 50, 'reb' => 200, 'ast' => 100, 'stl' => 30, 'blk' => 20, 'tvr' => 50, 'pf' => 60, 'team_city' => null, 'color1' => null, 'color2' => null],
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['year' => '2024', 'sortby' => 'PPG'], 1);

        $this->assertSame(2, $result['count']);
        $this->assertSame(3, $result['results'][0]['pid']);
        $this->assertSame(1, $result['results'][1]['pid']);
    }

    public function testGetYearsCachesResult(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $mockInner->expects($this->once())
            ->method('getYears')
            ->willReturn([2025, 2024, 2023]);

        $years1 = $repository->getYears();
        $years2 = $repository->getYears();

        $this->assertSame([2025, 2024, 2023], $years1);
        $this->assertSame([2025, 2024, 2023], $years2);
    }

    public function testGetTeamsCachesResult(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $teams = [['teamid' => 1, 'Team' => 'Hawks'], ['teamid' => 2, 'Team' => 'Celtics']];

        $mockInner->expects($this->once())
            ->method('getTeams')
            ->willReturn($teams);

        $teams1 = $repository->getTeams();
        $teams2 = $repository->getTeams();

        $this->assertSame($teams, $teams1);
        $this->assertSame($teams, $teams2);
    }

    public function testRebuildCacheWarmsAllThreeKeys(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $mockInner->expects($this->once())
            ->method('getSeasonLeaders')
            ->with([], 0)
            ->willReturn(['results' => [['pid' => 1]], 'count' => 1]);

        $mockInner->expects($this->once())
            ->method('getYears')
            ->willReturn([2025]);

        $mockInner->expects($this->once())
            ->method('getTeams')
            ->willReturn([['teamid' => 1, 'Team' => 'Hawks']]);

        $repository->rebuildCache();

        $this->assertNotNull($this->cache->get('season_leaderboards:leaders'));
        $this->assertNotNull($this->cache->get('season_leaderboards:years'));
        $this->assertNotNull($this->cache->get('season_leaderboards:teams'));
    }

    public function testInvalidateCacheDeletesAllThreeKeys(): void
    {
        $stubInner = self::createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $this->cache->set('season_leaderboards:leaders', [['pid' => 1]], 86400);
        $this->cache->set('season_leaderboards:years', [2025], 86400);
        $this->cache->set('season_leaderboards:teams', [['teamid' => 1]], 86400);

        $repository->invalidateCache();

        $this->assertNull($this->cache->get('season_leaderboards:leaders'));
        $this->assertNull($this->cache->get('season_leaderboards:years'));
        $this->assertNull($this->cache->get('season_leaderboards:teams'));
    }
}

/**
 * In-memory implementation of DatabaseCacheInterface for testing.
 */
class SeasonLeaderboardsInMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array{data: array<mixed>, expiration: int}> */
    private array $store = [];

    /**
     * @return array<mixed>|null
     */
    public function get(string $key): ?array
    {
        if (!isset($this->store[$key])) {
            return null;
        }

        if ($this->store[$key]['expiration'] < time()) {
            unset($this->store[$key]);
            return null;
        }

        return $this->store[$key]['data'];
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

    public function delete(string $key): void
    {
        unset($this->store[$key]);
    }

    /**
     * @return array<mixed>|null
     */
    public function getStale(string $key): ?array
    {
        return $this->store[$key]['data'] ?? null;
    }

    public function acquireLock(string $key, int $timeoutSeconds): bool
    {
        return true;
    }

    public function releaseLock(string $key): void
    {
    }
}
