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

        $this->cache->set('season_leaderboards:leaders', $this->createSampleRows(), 86400);

        $mockInner->expects($this->never())->method('getSeasonLeaders');

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG'], 0);

        $this->assertSame(3, $result['count']);
    }

    public function testCacheMissDelegatesToInnerAndCaches(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $rows = $this->createSampleRows();

        $mockInner->expects($this->once())
            ->method('getSeasonLeaders')
            ->with([], 0)
            ->willReturn(['results' => $rows, 'count' => 3]);

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG'], 0);

        $this->assertSame(3, $result['count']);
        $this->assertNotNull($this->cache->get('season_leaderboards:leaders'));
    }

    public function testFiltersByYear(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Player A', 2024, 1, 80, 1200),
            $this->createRow(2, 'Player B', 2025, 2, 70, 1000),
            $this->createRow(3, 'Player C', 2024, 3, 60, 900),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['year' => '2024', 'sortby' => 'PPG']);

        $this->assertSame(2, $result['count']);
        $pids = array_column($result['results'], 'pid');
        $this->assertContains(1, $pids);
        $this->assertContains(3, $pids);
    }

    public function testFiltersByTeam(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Player A', 2024, 5, 80, 1200),
            $this->createRow(2, 'Player B', 2024, 10, 70, 1000),
            $this->createRow(3, 'Player C', 2024, 5, 60, 900),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['team' => 5, 'sortby' => 'PPG']);

        $this->assertSame(2, $result['count']);
        $pids = array_column($result['results'], 'pid');
        $this->assertContains(1, $pids);
        $this->assertContains(3, $pids);
    }

    public function testSortsByPpgDesc(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Low Scorer', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'High Scorer', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
            $this->createRow(3, 'Mid Scorer', 2024, 3, 80, 1200, fgm: 350, ftm: 150, tgm: 75),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG']);

        $this->assertSame(2, $result['results'][0]['pid']); // High Scorer: (2*500+200+100)/80 = 16.25
        $this->assertSame(3, $result['results'][1]['pid']); // Mid Scorer: (2*350+150+75)/80 = 11.5625
        $this->assertSame(1, $result['results'][2]['pid']); // Low Scorer: (2*200+100+50)/80 = 6.875
    }

    public function testSortsByReboundsPerGame(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Few Boards', 2024, 1, 80, 800, reb: 200),
            $this->createRow(2, 'Many Boards', 2024, 2, 80, 800, reb: 600),
            $this->createRow(3, 'Mid Boards', 2024, 3, 80, 800, reb: 400),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'REB']);

        $this->assertSame(2, $result['results'][0]['pid']); // 600/80 = 7.5
        $this->assertSame(3, $result['results'][1]['pid']); // 400/80 = 5.0
        $this->assertSame(1, $result['results'][2]['pid']); // 200/80 = 2.5
    }

    public function testSortsByDefensiveReboundsPerGame(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Low DREB', 2024, 1, 80, 800, orb: 50, reb: 200),  // dreb=150, /80=1.875
            $this->createRow(2, 'High DREB', 2024, 2, 80, 800, orb: 50, reb: 600), // dreb=550, /80=6.875
            $this->createRow(3, 'Mid DREB', 2024, 3, 80, 800, orb: 100, reb: 500), // dreb=400, /80=5.0
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'DREB']);

        $this->assertSame(2, $result['results'][0]['pid']); // 6.875
        $this->assertSame(3, $result['results'][1]['pid']); // 5.0
        $this->assertSame(1, $result['results'][2]['pid']); // 1.875
    }

    public function testSortsByFgPct(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Bad Shooter', 2024, 1, 80, 800, fgm: 200, fga: 600),
            $this->createRow(2, 'Good Shooter', 2024, 2, 80, 800, fgm: 400, fga: 600),
            $this->createRow(3, 'Ok Shooter', 2024, 3, 80, 800, fgm: 300, fga: 600),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'FGP']);

        $this->assertSame(2, $result['results'][0]['pid']); // 400/600 = .667
        $this->assertSame(3, $result['results'][1]['pid']); // 300/600 = .500
        $this->assertSame(1, $result['results'][2]['pid']); // 200/600 = .333
    }

    public function testSortsByGamesPlayed(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Few Games', 2024, 1, 40, 400),
            $this->createRow(2, 'Many Games', 2024, 2, 82, 800),
            $this->createRow(3, 'Mid Games', 2024, 3, 60, 600),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'GAMES']);

        $this->assertSame(2, $result['results'][0]['pid']); // 82 games
        $this->assertSame(3, $result['results'][1]['pid']); // 60 games
        $this->assertSame(1, $result['results'][2]['pid']); // 40 games
    }

    public function testDefaultSortIsPpg(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'Low', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'High', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        // No sortby in filters — should default to PPG
        $result = $repository->getSeasonLeaders([]);

        $this->assertSame(2, $result['results'][0]['pid']);
    }

    public function testLimitRestrictsResultCount(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $this->cache->set('season_leaderboards:leaders', $this->createSampleRows(), 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG'], 2);

        $this->assertSame(2, $result['count']);
    }

    public function testZeroLimitReturnsAllRows(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $this->cache->set('season_leaderboards:leaders', $this->createSampleRows(), 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG'], 0);

        $this->assertSame(3, $result['count']);
    }

    public function testCombinesYearFilterSortAndLimit(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'P1 2024', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'P2 2024', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
            $this->createRow(3, 'P3 2025', 2025, 3, 80, 1200, fgm: 400, ftm: 180, tgm: 90),
            $this->createRow(4, 'P4 2024', 2024, 1, 80, 1400, fgm: 450, ftm: 190, tgm: 95),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        // Year 2024, sorted by PPG, limit 2
        $result = $repository->getSeasonLeaders(['year' => '2024', 'sortby' => 'PPG'], 2);

        $this->assertSame(2, $result['count']);
        $this->assertSame(2, $result['results'][0]['pid']); // Highest PPG in 2024
        $this->assertSame(4, $result['results'][1]['pid']); // Second highest
    }

    public function testGetYearsCachesResult(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $mockInner->expects($this->once())
            ->method('getYears')
            ->willReturn([2025, 2024, 2023]);

        // First call — cache miss
        $years1 = $repository->getYears();
        // Second call — cache hit
        $years2 = $repository->getYears();

        $this->assertSame([2025, 2024, 2023], $years1);
        $this->assertSame([2025, 2024, 2023], $years2);
    }

    public function testGetTeamsCachesResult(): void
    {
        $mockInner = $this->createMock(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($mockInner, $this->cache);

        $teams = [['TeamID' => 1, 'Team' => 'Hawks'], ['TeamID' => 2, 'Team' => 'Celtics']];

        $mockInner->expects($this->once())
            ->method('getTeams')
            ->willReturn($teams);

        // First call — cache miss
        $teams1 = $repository->getTeams();
        // Second call — cache hit
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
            ->willReturn([['TeamID' => 1, 'Team' => 'Hawks']]);

        $repository->rebuildCache();

        $this->assertNotNull($this->cache->get('season_leaderboards:leaders'));
        $this->assertNotNull($this->cache->get('season_leaderboards:years'));
        $this->assertNotNull($this->cache->get('season_leaderboards:teams'));
    }

    public function testInvalidateCacheDeletesAllThreeKeys(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $this->cache->set('season_leaderboards:leaders', [['pid' => 1]], 86400);
        $this->cache->set('season_leaderboards:years', [2025], 86400);
        $this->cache->set('season_leaderboards:teams', [['TeamID' => 1]], 86400);

        $repository->invalidateCache();

        $this->assertNull($this->cache->get('season_leaderboards:leaders'));
        $this->assertNull($this->cache->get('season_leaderboards:years'));
        $this->assertNull($this->cache->get('season_leaderboards:teams'));
    }

    public function testZeroGamesPlayerDoesNotCauseDivisionByZero(): void
    {
        $stubInner = $this->createStub(SeasonLeaderboardsRepositoryInterface::class);
        $repository = new CachedSeasonLeaderboardsRepository($stubInner, $this->cache);

        $rows = [
            $this->createRow(1, 'No Games', 2024, 1, 0, 0),
            $this->createRow(2, 'Has Games', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
        ];
        $this->cache->set('season_leaderboards:leaders', $rows, 86400);

        $result = $repository->getSeasonLeaders(['sortby' => 'PPG']);

        $this->assertSame(2, $result['results'][0]['pid']); // Has games sorts first
        $this->assertSame(1, $result['results'][1]['pid']); // Zero games gets 0.0
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function createSampleRows(): array
    {
        return [
            $this->createRow(1, 'Player 1', 2024, 1, 80, 1200, fgm: 400, ftm: 150, tgm: 75),
            $this->createRow(2, 'Player 2', 2024, 2, 70, 1000, fgm: 300, ftm: 120, tgm: 60),
            $this->createRow(3, 'Player 3', 2025, 3, 60, 900, fgm: 250, ftm: 100, tgm: 50),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createRow(
        int $pid,
        string $name,
        int $year,
        int $teamid,
        int $games,
        int $minutes,
        int $fgm = 0,
        int $fga = 0,
        int $ftm = 0,
        int $fta = 0,
        int $tgm = 0,
        int $tga = 0,
        int $orb = 0,
        int $reb = 0,
        int $ast = 0,
        int $stl = 0,
        int $blk = 0,
        int $tvr = 0,
        int $pf = 0,
    ): array {
        return [
            'pid' => $pid,
            'name' => $name,
            'year' => $year,
            'team' => "Team $teamid",
            'teamid' => $teamid,
            'games' => $games,
            'minutes' => $minutes,
            'fgm' => $fgm,
            'fga' => $fga,
            'ftm' => $ftm,
            'fta' => $fta,
            'tgm' => $tgm,
            'tga' => $tga,
            'orb' => $orb,
            'reb' => $reb,
            'ast' => $ast,
            'stl' => $stl,
            'blk' => $blk,
            'tvr' => $tvr,
            'pf' => $pf,
            'team_city' => null,
            'color1' => null,
            'color2' => null,
        ];
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
}
