<?php

declare(strict_types=1);

namespace Tests\SeasonLeaderboards;

use PHPUnit\Framework\TestCase;
use SeasonLeaderboards\Contracts\SeasonLeaderboardsRepositoryInterface;
use SeasonLeaderboards\SeasonLeaderboardsService;

final class SeasonLeaderboardsServiceTest extends TestCase
{
    private SeasonLeaderboardsRepositoryInterface $stubRepo;
    private SeasonLeaderboardsService $service;

    protected function setUp(): void
    {
        $this->stubRepo = self::createStub(SeasonLeaderboardsRepositoryInterface::class);
        $this->stubRepo->method('getSeasonLeaders')
            ->willReturn(['results' => [], 'count' => 0]);
        $this->service = new SeasonLeaderboardsService($this->stubRepo);
    }

    /**
     * @param list<array<string, mixed>> $rows
     */
    private function buildServiceWithRows(array $rows): SeasonLeaderboardsService
    {
        $stub = self::createStub(SeasonLeaderboardsRepositoryInterface::class);
        $stub->method('getSeasonLeaders')
            ->willReturn(['results' => $rows, 'count' => count($rows)]);
        return new SeasonLeaderboardsService($stub);
    }

    // --- getFilteredLeaderboard: filter tests ---

    public function testFiltersByYear(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Player A', 2024, 1, 80, 1200),
            $this->createRow(2, 'Player B', 2025, 2, 70, 1000),
            $this->createRow(3, 'Player C', 2024, 3, 60, 900),
        ]);

        $result = $service->getFilteredLeaderboard(['year' => '2024', 'sortby' => 'PPG']);

        $this->assertSame(2, $result['count']);
        $pids = array_column($result['results'], 'pid');
        $this->assertContains(1, $pids);
        $this->assertContains(3, $pids);
    }

    public function testFiltersByTeam(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Player A', 2024, 5, 80, 1200),
            $this->createRow(2, 'Player B', 2024, 10, 70, 1000),
            $this->createRow(3, 'Player C', 2024, 5, 60, 900),
        ]);

        $result = $service->getFilteredLeaderboard(['team' => 5, 'sortby' => 'PPG']);

        $this->assertSame(2, $result['count']);
        $pids = array_column($result['results'], 'pid');
        $this->assertContains(1, $pids);
        $this->assertContains(3, $pids);
    }

    // --- getFilteredLeaderboard: sort tests ---

    public function testSortsByPpgDesc(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Low Scorer', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'High Scorer', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
            $this->createRow(3, 'Mid Scorer', 2024, 3, 80, 1200, fgm: 350, ftm: 150, tgm: 75),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'PPG']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(3, $result['results'][1]['pid']);
        $this->assertSame(1, $result['results'][2]['pid']);
    }

    public function testSortsByReboundsPerGame(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Few Boards', 2024, 1, 80, 800, reb: 200),
            $this->createRow(2, 'Many Boards', 2024, 2, 80, 800, reb: 600),
            $this->createRow(3, 'Mid Boards', 2024, 3, 80, 800, reb: 400),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'REB']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(3, $result['results'][1]['pid']);
        $this->assertSame(1, $result['results'][2]['pid']);
    }

    public function testSortsByDefensiveReboundsPerGame(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Low DREB', 2024, 1, 80, 800, orb: 50, reb: 200),
            $this->createRow(2, 'High DREB', 2024, 2, 80, 800, orb: 50, reb: 600),
            $this->createRow(3, 'Mid DREB', 2024, 3, 80, 800, orb: 100, reb: 500),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'DREB']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(3, $result['results'][1]['pid']);
        $this->assertSame(1, $result['results'][2]['pid']);
    }

    public function testSortsByFgPct(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Bad Shooter', 2024, 1, 80, 800, fgm: 200, fga: 600),
            $this->createRow(2, 'Good Shooter', 2024, 2, 80, 800, fgm: 400, fga: 600),
            $this->createRow(3, 'Ok Shooter', 2024, 3, 80, 800, fgm: 300, fga: 600),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'FGP']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(3, $result['results'][1]['pid']);
        $this->assertSame(1, $result['results'][2]['pid']);
    }

    public function testSortsByGamesPlayed(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Few Games', 2024, 1, 40, 400),
            $this->createRow(2, 'Many Games', 2024, 2, 82, 800),
            $this->createRow(3, 'Mid Games', 2024, 3, 60, 600),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'GAMES']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(3, $result['results'][1]['pid']);
        $this->assertSame(1, $result['results'][2]['pid']);
    }

    public function testDefaultSortIsPpg(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'Low', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'High', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
        ]);

        $result = $service->getFilteredLeaderboard([]);

        $this->assertSame(2, $result['results'][0]['pid']);
    }

    // --- getFilteredLeaderboard: limit tests ---

    public function testLimitRestrictsResultCount(): void
    {
        $service = $this->buildServiceWithRows($this->createSampleRows());

        $result = $service->getFilteredLeaderboard(['sortby' => 'PPG'], 2);

        $this->assertSame(2, $result['count']);
    }

    public function testZeroLimitReturnsAllRows(): void
    {
        $service = $this->buildServiceWithRows($this->createSampleRows());

        $result = $service->getFilteredLeaderboard(['sortby' => 'PPG'], 0);

        $this->assertSame(3, $result['count']);
    }

    public function testCombinesYearFilterSortAndLimit(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'P1 2024', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(2, 'P2 2024', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
            $this->createRow(3, 'P3 2025', 2025, 3, 80, 1200, fgm: 400, ftm: 180, tgm: 90),
            $this->createRow(4, 'P4 2024', 2024, 1, 80, 1400, fgm: 450, ftm: 190, tgm: 95),
        ]);

        $result = $service->getFilteredLeaderboard(['year' => '2024', 'sortby' => 'PPG'], 2);

        $this->assertSame(2, $result['count']);
        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(4, $result['results'][1]['pid']);
    }

    // --- getFilteredLeaderboard: edge cases ---

    public function testTiesResolveByPidAsc(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(30, 'Player C', 2024, 1, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(10, 'Player A', 2024, 2, 80, 800, fgm: 200, ftm: 100, tgm: 50),
            $this->createRow(20, 'Player B', 2024, 3, 80, 800, fgm: 200, ftm: 100, tgm: 50),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'PPG']);

        $this->assertSame(10, $result['results'][0]['pid']);
        $this->assertSame(20, $result['results'][1]['pid']);
        $this->assertSame(30, $result['results'][2]['pid']);
    }

    public function testZeroGamesPlayerDoesNotCauseDivisionByZero(): void
    {
        $service = $this->buildServiceWithRows([
            $this->createRow(1, 'No Games', 2024, 1, 0, 0),
            $this->createRow(2, 'Has Games', 2024, 2, 80, 1600, fgm: 500, ftm: 200, tgm: 100),
        ]);

        $result = $service->getFilteredLeaderboard(['sortby' => 'PPG']);

        $this->assertSame(2, $result['results'][0]['pid']);
        $this->assertSame(1, $result['results'][1]['pid']);
    }

    // --- processPlayerRow tests ---

    public function testProcessPlayerRowCalculatesCorrectly(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 50,
            'fga' => 100,
            'ftm' => 20,
            'fta' => 25,
            'tgm' => 15,
            'tga' => 40,
            'orb' => 30,
            'reb' => 80,
            'ast' => 40,
            'stl' => 10,
            'tvr' => 15,
            'blk' => 5,
            'pf' => 20,
            'team_city' => null,
            'color1' => null,
            'color2' => null,
        ];

        $stats = $this->service->processPlayerRow($row);

        $this->assertSame(123, $stats['pid']);
        $this->assertSame('Test Player', $stats['name']);
        $this->assertSame(2024, $stats['year']);
        $this->assertSame(135, $stats['points']);
        $this->assertSame('30.0', $stats['mpg']);
        $this->assertSame('5.0', $stats['fgmpg']);
        $this->assertSame('13.5', $stats['ppg']);
        $this->assertSame('5.0', $stats['drebpg']);
        $this->assertSame('0.500', $stats['fgp']);
        $this->assertSame('0.800', $stats['ftp']);
        $this->assertSame('0.375', $stats['tgp']);
    }

    public function testProcessPlayerRowHandlesZeroGames(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 0,
            'minutes' => 0,
            'fgm' => 0,
            'fga' => 0,
            'ftm' => 0,
            'fta' => 0,
            'tgm' => 0,
            'tga' => 0,
            'orb' => 0,
            'team_city' => null,
            'color1' => null,
            'color2' => null,
            'reb' => 0,
            'ast' => 0,
            'stl' => 0,
            'tvr' => 0,
            'blk' => 0,
            'pf' => 0
        ];

        $stats = $this->service->processPlayerRow($row);

        $this->assertSame('0.0', $stats['mpg']);
        $this->assertSame('0.0', $stats['ppg']);
        $this->assertSame('0.0', $stats['qa']);
    }

    public function testProcessPlayerRowHandlesZeroAttempts(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 0,
            'fga' => 0,
            'ftm' => 0,
            'fta' => 0,
            'tgm' => 0,
            'tga' => 0,
            'orb' => 0,
            'reb' => 0,
            'ast' => 0,
            'stl' => 0,
            'tvr' => 0,
            'blk' => 0,
            'pf' => 0,
            'team_city' => null,
            'color1' => null,
            'color2' => null,
        ];

        $stats = $this->service->processPlayerRow($row);

        $this->assertSame('0.000', $stats['fgp']);
        $this->assertSame('0.000', $stats['ftp']);
        $this->assertSame('0.000', $stats['tgp']);
    }

    public function testQualityAssessmentCalculation(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 50,
            'fga' => 100,
            'ftm' => 20,
            'fta' => 25,
            'tgm' => 15,
            'tga' => 40,
            'orb' => 30,
            'reb' => 80,
            'ast' => 40,
            'stl' => 10,
            'tvr' => 15,
            'blk' => 5,
            'pf' => 20,
            'team_city' => null,
            'color1' => null,
            'color2' => null,
        ];

        $stats = $this->service->processPlayerRow($row);

        $this->assertSame('23.5', $stats['qa']);
    }

    public function testGetSortOptions(): void
    {
        $options = $this->service->getSortOptions();

        $this->assertCount(21, $options);
        $this->assertArrayHasKey('PPG', $options);
        $this->assertArrayHasKey('DREB', $options);
        $this->assertArrayHasKey('MIN', $options);
        $this->assertSame('PPG', $options['PPG']);
        $this->assertSame('FG%', $options['FGP']);
    }

    // --- Test helpers ---

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
