<?php

declare(strict_types=1);

namespace Tests\SeasonHighs;

use PHPUnit\Framework\TestCase;
use SeasonHighs\Contracts\SeasonHighsRepositoryInterface;
use SeasonHighs\Contracts\SeasonHighsServiceInterface;
use SeasonHighs\SeasonHighsService;

/**
 * @covers \SeasonHighs\SeasonHighsService
 */
class SeasonHighsServiceTest extends TestCase
{
    public function testImplementsServiceInterface(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $this->assertInstanceOf(SeasonHighsServiceInterface::class, $service);
    }

    public function testGetSeasonHighsDataReturnsExpectedStructure(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertArrayHasKey('playerHighs', $result);
        $this->assertArrayHasKey('teamHighs', $result);
    }

    public function testGetSeasonHighsDataReturnsNineStatCategories(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertCount(9, $result['playerHighs']);
        $this->assertCount(9, $result['teamHighs']);
    }

    public function testGetSeasonHighsDataIncludesPointsStat(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $result = $service->getSeasonHighsData('Regular Season');

        $this->assertArrayHasKey('POINTS', $result['playerHighs']);
        $this->assertArrayHasKey('REBOUNDS', $result['playerHighs']);
        $this->assertArrayHasKey('ASSISTS', $result['playerHighs']);
    }

    public function testCallsRepositoryForPlayerAndTeamHighs(): void
    {
        // Each of 9 stats calls getSeasonHighs twice (player + team) = 18 calls
        $repo = $this->createMock(SeasonHighsRepositoryInterface::class);
        $repo->expects($this->exactly(18))
            ->method('getSeasonHighs')
            ->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $service->getSeasonHighsData('Regular Season');
    }

    public function testRegularSeasonUsesCorrectDateRange(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')
            ->willReturnCallback(function (string $expr, string $name, string $suffix, string $start, string $end): array {
                // Regular season: Nov 2024 to May 2025
                $this->assertStringStartsWith('2024-11', $start);
                $this->assertStringStartsWith('2025-05', $end);
                return [];
            });
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $service->getSeasonHighsData('Regular Season');
    }

    public function testPlayoffsUsesCorrectDateRange(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')
            ->willReturnCallback(function (string $expr, string $name, string $suffix, string $start, string $end): array {
                // Playoffs: June 2025
                $this->assertStringStartsWith('2025-06', $start);
                $this->assertStringStartsWith('2025-06', $end);
                return [];
            });
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $service->getSeasonHighsData('Playoffs');
    }

    // --- Home/Away Highs Tests ---

    public function testGetHomeAwayHighsReturnsHomeAndAwayKeys(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $result = $service->getHomeAwayHighs('Regular Season');

        $this->assertArrayHasKey('home', $result);
        $this->assertArrayHasKey('away', $result);
    }

    public function testGetHomeAwayHighsHasEightStatCategories(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $result = $service->getHomeAwayHighs('Regular Season');

        $this->assertCount(8, $result['home']);
        $this->assertCount(8, $result['away']);
        $this->assertArrayNotHasKey('TURNOVERS', $result['home']);
        $this->assertArrayNotHasKey('TURNOVERS', $result['away']);
    }

    public function testGetHomeAwayHighsPassesLocationFilter(): void
    {
        /** @var list<string|null> $locationFilters */
        $locationFilters = [];

        $repo = $this->createMock(SeasonHighsRepositoryInterface::class);
        $repo->expects($this->exactly(16))
            ->method('getSeasonHighs')
            ->willReturnCallback(function (
                string $expr,
                string $name,
                string $suffix,
                string $start,
                string $end,
                int $limit = 15,
                ?string $locationFilter = null
            ) use (&$locationFilters): array {
                $locationFilters[] = $locationFilter;
                return [];
            });

        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);
        $service->getHomeAwayHighs('Regular Season');

        // 8 stats, each called twice (home then away) = 16 calls
        $this->assertCount(16, $locationFilters);
        $homeCount = 0;
        $awayCount = 0;
        foreach ($locationFilters as $i => $filter) {
            if ($i % 2 === 0) {
                $this->assertSame('home', $filter, "Call {$i} should be 'home'");
                $homeCount++;
            } else {
                $this->assertSame('away', $filter, "Call {$i} should be 'away'");
                $awayCount++;
            }
        }
        $this->assertSame(8, $homeCount);
        $this->assertSame(8, $awayCount);
    }

    public function testGetHomeAwayHighsUsesLimitOfTen(): void
    {
        $repo = $this->createMock(SeasonHighsRepositoryInterface::class);
        $repo->expects($this->exactly(16))
            ->method('getSeasonHighs')
            ->willReturnCallback(function (
                string $expr,
                string $name,
                string $suffix,
                string $start,
                string $end,
                int $limit = 15,
                ?string $locationFilter = null
            ): array {
                $this->assertSame(10, $limit);
                return [];
            });

        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);
        $service->getHomeAwayHighs('Regular Season');
    }

    // --- RCB Validation Tests ---

    public function testValidateAgainstRcbReturnsEmptyWhenRcbEmpty(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = ['home' => ['POINTS' => [self::createBoxEntry()]], 'away' => []];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertSame([], $result);
    }

    public function testValidateAgainstRcbReturnsEmptyWhenBoxScoreEmpty(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([
            ['stat_category' => 'pts', 'ranking' => 1, 'player_name' => 'Test Player', 'player_position' => 'SF', 'stat_value' => 40, 'record_season_year' => 2024],
        ]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = ['home' => ['POINTS' => []], 'away' => ['POINTS' => []]];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertSame([], $result);
    }

    public function testValidateAgainstRcbDetectsValueDiscrepancy(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([
            ['stat_category' => 'pts', 'ranking' => 1, 'player_name' => 'Test Player', 'player_position' => 'SF', 'stat_value' => 50, 'record_season_year' => 2024],
        ]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = [
            'home' => ['POINTS' => [self::createBoxEntry(['name' => 'Test Player', 'value' => 40])]],
            'away' => [],
        ];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertCount(1, $result);
        $this->assertSame('home', $result[0]['context']);
        $this->assertSame('POINTS', $result[0]['stat']);
        $this->assertSame(40, $result[0]['boxValue']);
        $this->assertSame(50, $result[0]['rcbValue']);
    }

    public function testValidateAgainstRcbDetectsPlayerDiscrepancy(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([
            ['stat_category' => 'pts', 'ranking' => 1, 'player_name' => 'Other Player', 'player_position' => 'PG', 'stat_value' => 40, 'record_season_year' => 2024],
        ]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = [
            'home' => ['POINTS' => [self::createBoxEntry(['name' => 'Test Player', 'value' => 40])]],
            'away' => [],
        ];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertCount(1, $result);
        $this->assertSame('Test Player', $result[0]['boxPlayer']);
        $this->assertSame('Other Player', $result[0]['rcbPlayer']);
    }

    public function testValidateAgainstRcbAcceptsMatchingData(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([
            ['stat_category' => 'pts', 'ranking' => 1, 'player_name' => 'Test Player', 'player_position' => 'SF', 'stat_value' => 40, 'record_season_year' => 2024],
        ]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = [
            'home' => ['POINTS' => [self::createBoxEntry(['name' => 'Test Player', 'value' => 40])]],
            'away' => [],
        ];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertSame([], $result);
    }

    public function testValidateAgainstRcbHandlesTruncatedNames(): void
    {
        $repo = $this->createStub(SeasonHighsRepositoryInterface::class);
        $repo->method('getRcbSeasonHighs')->willReturn([
            ['stat_category' => 'pts', 'ranking' => 1, 'player_name' => 'Test Play', 'player_position' => 'SF', 'stat_value' => 40, 'record_season_year' => 2024],
        ]);
        $season = $this->createStubSeason(2024, 2025);
        $service = new SeasonHighsService($repo, $season);

        $homeAwayData = [
            'home' => ['POINTS' => [self::createBoxEntry(['name' => 'Test Player', 'value' => 40])]],
            'away' => [],
        ];
        $result = $service->validateAgainstRcb($homeAwayData, 2024);

        $this->assertSame([], $result);
    }

    /**
     * @return \Season&\PHPUnit\Framework\MockObject\Stub
     */
    private function createStubSeason(int $beginningYear, int $endingYear): \Season
    {
        $season = $this->createStub(\Season::class);
        $season->beginningYear = $beginningYear;
        $season->endingYear = $endingYear;

        return $season;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{name: string, date: string, value: int, pid: int, tid: int, teamname: string, color1: string, color2: string, boxId: int}
     */
    private static function createBoxEntry(array $overrides = []): array
    {
        $defaults = [
            'name' => 'Test Player',
            'date' => '2024-12-15',
            'value' => 30,
            'pid' => 1,
            'tid' => 1,
            'teamname' => 'Hawks',
            'color1' => 'FF0000',
            'color2' => '000000',
            'boxId' => 100,
        ];

        /** @var array{name: string, date: string, value: int, pid: int, tid: int, teamname: string, color1: string, color2: string, boxId: int} */
        return array_merge($defaults, $overrides);
    }
}
