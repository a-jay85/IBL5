<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\TestCase;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;
use TeamSchedule\TeamScheduleService;
use Season\Season;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * TeamScheduleServiceTest - Tests for TeamScheduleService
 */
class TeamScheduleServiceTest extends TestCase
{
    private MockDatabase $mockDb;

    /** @var TeamScheduleRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamScheduleRepositoryInterface $stubRepository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $GLOBALS['mysqli_db'] = $this->mockDb;
        $this->stubRepository = self::createStub(TeamScheduleRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $service2 = new TeamScheduleService($this->mockDb, $this->stubRepository);

        $this->assertNotSame($service1, $service2);
    }

    // ============================================
    // getProcessedSchedule() TESTS
    // ============================================

    public function testProcessedScheduleReturnsEmptyForNoGames(): void
    {
        $this->stubRepository->method('getSchedule')->willReturn([]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame([], $result);
    }

    public function testProcessedScheduleTracksWinWithGreenColor(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 100, hScore: 90),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertCount(1, $result);
        $this->assertSame('W', $result[0]['gameResult']);
        $this->assertSame('green', $result[0]['winLossColor']);
        $this->assertSame(1, $result[0]['wins']);
        $this->assertSame(0, $result[0]['losses']);
    }

    public function testProcessedScheduleTracksLossWithRedColor(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 80, hScore: 95),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('L', $result[0]['gameResult']);
        $this->assertSame('red', $result[0]['winLossColor']);
        $this->assertSame(0, $result[0]['wins']);
        $this->assertSame(1, $result[0]['losses']);
    }

    public function testProcessedScheduleCalculatesWinStreak(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 100, hScore: 90),
            $this->makeGameRow('2024-01-12', visitor: 2, home: 1, vScore: 85, hScore: 95),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('W 2', $result[1]['streak']);
    }

    public function testProcessedScheduleResetsStreakOnOppositeResult(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 100, hScore: 90),
            $this->makeGameRow('2024-01-12', visitor: 1, home: 2, vScore: 80, hScore: 95),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('W 1', $result[0]['streak']);
        $this->assertSame('L 1', $result[1]['streak']);
    }

    public function testProcessedScheduleMarksUnplayedGamesWithEmptyResult(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-02-10', visitor: 1, home: 2, vScore: 0, hScore: 0),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertTrue($result[0]['isUnplayed']);
        $this->assertSame('', $result[0]['gameResult']);
    }

    public function testProcessedScheduleCumulativeRecord(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 100, hScore: 90),
            $this->makeGameRow('2024-01-12', visitor: 1, home: 2, vScore: 80, hScore: 95),
            $this->makeGameRow('2024-01-14', visitor: 2, home: 1, vScore: 85, hScore: 105),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame(2, $result[2]['wins']);
        $this->assertSame(1, $result[2]['losses']);
    }

    // ============================================
    // SEASON YEAR FILTERING
    // ============================================

    public function testGetProcessedSchedulePassesSeasonEndingYearToRepository(): void
    {
        $mockRepository = $this->createMock(TeamScheduleRepositoryInterface::class);
        $season = new Season($this->mockDb);

        $mockRepository->expects($this->once())
            ->method('getSchedule')
            ->with(1, $season->endingYear)
            ->willReturn([]);

        $service = new TeamScheduleService($this->mockDb, $mockRepository);

        $service->getProcessedSchedule(1, $season);
    }

    // ============================================
    // OPPONENT LOOKUP TESTS
    // ============================================

    public function testOpponentLookupPopulatesOpponentTextWithRecord(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 2, home: 1, vScore: 90, hScore: 100),
            $this->makeGameRow('2024-01-12', visitor: 1, home: 2, vScore: 105, hScore: 95),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertCount(2, $result);
        $this->assertSame('Opponents', $result[0]['opposingTeam']->name);
        $this->assertSame('Opponents', $result[1]['opposingTeam']->name);
        $this->assertStringContainsString('Opponents', $result[0]['opponentText']);
        $this->assertStringContainsString('(30-10)', $result[0]['opponentText']);
        $this->assertStringContainsString('Opponents', $result[1]['opponentText']);
    }

    public function testOpponentTierAssignedWhenPowerRankingsProvided(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 2, home: 1, vScore: 90, hScore: 100),
        ]);

        $service = new TeamScheduleService(
            $this->mockDb,
            $this->stubRepository,
            [1 => 50.0, 2 => 90.0]
        );
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertNotSame('', $result[0]['opponentTier']);
    }

    public function testOpponentTierEmptyWhenNoPowerRankings(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 2, home: 1, vScore: 90, hScore: 100),
        ]);

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $season = new Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('', $result[0]['opponentTier']);
    }

    public function testUnplayedGameWithinNextSimWindowIsHighlighted(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2025-01-15', visitor: 2, home: 1, vScore: 0, hScore: 0),
        ]);

        $season = self::createStub(Season::class);
        $season->endingYear = 2025;
        $season->projectedNextSimEndDate = new \DateTime('2025-01-20');

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('next-sim', $result[0]['highlight']);
    }

    public function testUnplayedGameBeyondNextSimWindowIsNotHighlighted(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2025-02-10', visitor: 2, home: 1, vScore: 0, hScore: 0),
        ]);

        $season = self::createStub(Season::class);
        $season->endingYear = 2025;
        $season->projectedNextSimEndDate = new \DateTime('2025-01-20');

        $service = new TeamScheduleService($this->mockDb, $this->stubRepository);
        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame('', $result[0]['highlight']);
    }

    // ============================================
    // HELPERS
    // ============================================

    private function setupTeamMockData(): void
    {
        $this->mockDb->setMockData([
            [
                'teamid' => 2,
                'team_city' => 'Test City',
                'team_name' => 'Opponents',
                'color1' => 'FF0000',
                'color2' => '0000FF',
                'arena' => 'Test Arena',
                'capacity' => 20000,
                'owner_name' => 'Test Owner',
                'owner_email' => 'test@test.com',
                'discord_id' => null,
                'used_extension_this_chunk' => 0,
                'used_extension_this_season' => 0,
                'has_mle' => 0,
                'has_lle' => 0,
                'league_record' => '30-10',
            ],
        ]);
    }

    /**
     * @return array{game_date: string, box_id: int, visitor_teamid: int, home_teamid: int, visitor_score: int, home_score: int, game_of_that_day: int}
     */
    private function makeGameRow(
        string $date,
        int $visitor,
        int $home,
        int $vScore,
        int $hScore,
    ): array {
        return [
            'game_date' => $date,
            'box_id' => 1,
            'visitor_teamid' => $visitor,
            'home_teamid' => $home,
            'visitor_score' => $vScore,
            'home_score' => $hScore,
            'game_of_that_day' => 0,
        ];
    }
}
