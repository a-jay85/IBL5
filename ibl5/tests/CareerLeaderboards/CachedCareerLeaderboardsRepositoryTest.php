<?php

declare(strict_types=1);

namespace Tests\CareerLeaderboards;

use Cache\Contracts\DatabaseCacheInterface;
use CareerLeaderboards\CachedCareerLeaderboardsRepository;
use CareerLeaderboards\Contracts\CareerLeaderboardsRepositoryInterface;
use PHPUnit\Framework\TestCase;

/**
 * @phpstan-import-type CareerStatsRow from CareerLeaderboardsRepositoryInterface
 */
final class CachedCareerLeaderboardsRepositoryTest extends TestCase
{
    private InMemoryCache $cache;

    protected function setUp(): void
    {
        $this->cache = new InMemoryCache();
    }

    public function testCacheHitDoesNotCallInnerRepository(): void
    {
        $mockInner = $this->createMock(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($mockInner, $this->cache);

        $rows = $this->createSampleRows();
        $this->cache->set('career_leaderboards:ibl_hist', $rows, 86400);

        $mockInner->expects($this->never())->method('getLeaderboards');

        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);

        $this->assertSame(3, $result['count']);
    }

    public function testCacheMissDelegatesToInnerAndCaches(): void
    {
        $mockInner = $this->createMock(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($mockInner, $this->cache);

        $rows = $this->createSampleRows();

        $mockInner->expects($this->once())
            ->method('getLeaderboards')
            ->with('ibl_hist', 'pts', 0, 0)
            ->willReturn(['result' => $rows, 'count' => 3]);

        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);

        $this->assertSame(3, $result['count']);

        // Verify the data was cached
        $cached = $this->cache->get('career_leaderboards:ibl_hist');
        $this->assertNotNull($cached);
    }

    public function testSortsByRequestedColumnDesc(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            ['pid' => 1, 'name' => 'Low Scorer', 'pts' => 50, 'ast' => 300, 'games' => 100, 'retired' => 0],
            ['pid' => 2, 'name' => 'Mid Scorer', 'pts' => 100, 'ast' => 200, 'games' => 80, 'retired' => 0],
            ['pid' => 3, 'name' => 'High Scorer', 'pts' => 200, 'ast' => 100, 'games' => 60, 'retired' => 0],
        ];
        $this->cache->set('career_leaderboards:ibl_hist', $rows, 86400);

        // Sort by assists
        $result = $repository->getLeaderboards('ibl_hist', 'ast', 0, 0);

        $this->assertSame(1, $result['result'][0]['pid']); // 300 ast
        $this->assertSame(2, $result['result'][1]['pid']); // 200 ast
        $this->assertSame(3, $result['result'][2]['pid']); // 100 ast
    }

    public function testActiveOnlyFilterExcludesRetiredPlayers(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            ['pid' => 1, 'name' => 'Active Player', 'pts' => 200, 'games' => 100, 'retired' => 0],
            ['pid' => 2, 'name' => 'Retired Player', 'pts' => 300, 'games' => 200, 'retired' => 1],
            ['pid' => 3, 'name' => 'Another Active', 'pts' => 150, 'games' => 80, 'retired' => 0],
        ];
        $this->cache->set('career_leaderboards:ibl_hist', $rows, 86400);

        $result = $repository->getLeaderboards('ibl_hist', 'pts', 1, 0);

        $this->assertSame(2, $result['count']);
        $this->assertSame(1, $result['result'][0]['pid']);
        $this->assertSame(3, $result['result'][1]['pid']);
    }

    public function testLimitRestrictsResultCount(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        $rows = $this->createSampleRows();
        $this->cache->set('career_leaderboards:ibl_hist', $rows, 86400);

        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 2);

        $this->assertSame(2, $result['count']);
    }

    public function testZeroLimitReturnsAllRows(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        $rows = $this->createSampleRows();
        $this->cache->set('career_leaderboards:ibl_hist', $rows, 86400);

        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 0);

        $this->assertSame(3, $result['count']);
    }

    public function testGetTableTypeDelegatesToInner(): void
    {
        $mockInner = $this->createMock(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($mockInner, $this->cache);

        $mockInner->expects($this->once())
            ->method('getTableType')
            ->with('ibl_season_career_avgs')
            ->willReturn('averages');

        $result = $repository->getTableType('ibl_season_career_avgs');

        $this->assertSame('averages', $result);
    }

    public function testRebuildCacheWarmsAllEightTables(): void
    {
        $mockInner = $this->createMock(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($mockInner, $this->cache);

        $sampleResult = ['result' => [['pid' => 1, 'name' => 'Test', 'pts' => 100, 'retired' => 0]], 'count' => 1];

        $mockInner->expects($this->exactly(8))
            ->method('getLeaderboards')
            ->willReturn($sampleResult);

        $repository->rebuildCache();

        // Verify all 8 keys are cached
        $tables = [
            'ibl_hist', 'ibl_season_career_avgs',
            'ibl_playoff_career_totals', 'ibl_playoff_career_avgs',
            'ibl_heat_career_totals', 'ibl_heat_career_avgs',
            'ibl_olympics_career_totals', 'ibl_olympics_career_avgs',
        ];
        foreach ($tables as $table) {
            $this->assertNotNull($this->cache->get('career_leaderboards:' . $table));
        }
    }

    public function testInvalidateCacheDeletesAllEightKeys(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        // Pre-populate cache
        $tables = [
            'ibl_hist', 'ibl_season_career_avgs',
            'ibl_playoff_career_totals', 'ibl_playoff_career_avgs',
            'ibl_heat_career_totals', 'ibl_heat_career_avgs',
            'ibl_olympics_career_totals', 'ibl_olympics_career_avgs',
        ];
        foreach ($tables as $table) {
            $this->cache->set('career_leaderboards:' . $table, [['pid' => 1]], 86400);
        }

        $repository->invalidateCache();

        foreach ($tables as $table) {
            $this->assertNull($this->cache->get('career_leaderboards:' . $table));
        }
    }

    public function testCombinesActiveFilterSortAndLimit(): void
    {
        $stubInner = $this->createStub(CareerLeaderboardsRepositoryInterface::class);
        $repository = new CachedCareerLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            ['pid' => 1, 'name' => 'Active Low', 'pts' => 50, 'games' => 10, 'retired' => 0],
            ['pid' => 2, 'name' => 'Retired High', 'pts' => 500, 'games' => 200, 'retired' => 1],
            ['pid' => 3, 'name' => 'Active High', 'pts' => 300, 'games' => 100, 'retired' => 0],
            ['pid' => 4, 'name' => 'Active Mid', 'pts' => 200, 'games' => 80, 'retired' => 0],
        ];
        $this->cache->set('career_leaderboards:ibl_season_career_avgs', $rows, 86400);

        // Active only, sorted by pts, limit 2
        $result = $repository->getLeaderboards('ibl_season_career_avgs', 'pts', 1, 2);

        $this->assertSame(2, $result['count']);
        $this->assertSame(3, $result['result'][0]['pid']); // 300 pts
        $this->assertSame(4, $result['result'][1]['pid']); // 200 pts
    }

    /**
     * @return list<array{pid: int, name: string, pts: int, games: int, retired: int}>
     */
    private function createSampleRows(): array
    {
        return [
            ['pid' => 1, 'name' => 'Player 1', 'pts' => 300, 'games' => 100, 'retired' => 0],
            ['pid' => 2, 'name' => 'Player 2', 'pts' => 200, 'games' => 80, 'retired' => 1],
            ['pid' => 3, 'name' => 'Player 3', 'pts' => 100, 'games' => 60, 'retired' => 0],
        ];
    }
}

/**
 * Simple in-memory implementation of DatabaseCacheInterface for testing.
 */
class InMemoryCache implements DatabaseCacheInterface
{
    /** @var array<string, array{data: list<array<string, mixed>>, expiration: int}> */
    private array $store = [];

    /**
     * @return list<array<string, mixed>>|null
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
     * @param list<array<string, mixed>> $data
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
}
