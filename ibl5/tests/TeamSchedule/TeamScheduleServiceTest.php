<?php

declare(strict_types=1);

namespace Tests\TeamSchedule;

use PHPUnit\Framework\TestCase;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;
use TeamSchedule\TeamScheduleService;

/**
 * TeamScheduleServiceTest - Tests for TeamScheduleService
 */
class TeamScheduleServiceTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    /** @var TeamScheduleRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamScheduleRepositoryInterface $stubRepository;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
        $this->stubRepository = $this->createStub(TeamScheduleRepositoryInterface::class);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['mysqli_db']);
    }

    private function setupMockMysqliDb(): void
    {
        $mockDb = $this->mockDb;

        $this->mockMysqliDb = new class($mockDb) extends \mysqli {
            private \MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(\MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): \MockPreparedStatement|false
            {
                return new \MockPreparedStatement($this->mockDb, $query);
            }

            #[\ReturnTypeWillChange]
            public function query(string $query, int $resultMode = MYSQLI_STORE_RESULT): \mysqli_result|bool
            {
                return false;
            }

            public function real_escape_string(string $string): string
            {
                return addslashes($string);
            }
        };

        $GLOBALS['mysqli_db'] = $this->mockMysqliDb;
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testServiceCanBeInstantiated(): void
    {
        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);

        $this->assertInstanceOf(TeamScheduleService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);

        $this->assertInstanceOf(
            \TeamSchedule\Contracts\TeamScheduleServiceInterface::class,
            $service
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $service2 = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);

        $this->assertNotSame($service1, $service2);
    }

    // ============================================
    // getProcessedSchedule() TESTS
    // ============================================

    public function testProcessedScheduleReturnsEmptyForNoGames(): void
    {
        $this->stubRepository->method('getSchedule')->willReturn([]);

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame([], $result);
    }

    public function testProcessedScheduleTracksWinWithGreenColor(): void
    {
        $this->setupTeamMockData();
        $this->stubRepository->method('getSchedule')->willReturn([
            $this->makeGameRow('2024-01-10', visitor: 1, home: 2, vScore: 100, hScore: 90),
        ]);

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

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

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

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

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

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

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

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

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

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

        $service = new TeamScheduleService($this->mockMysqliDb, $this->stubRepository);
        $season = new \Season($this->mockDb);

        $result = $service->getProcessedSchedule(1, $season);

        $this->assertSame(2, $result[2]['wins']);
        $this->assertSame(1, $result[2]['losses']);
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
                'discordID' => null,
                'Used_Extension_This_Chunk' => 0,
                'Used_Extension_This_Season' => 0,
                'HasMLE' => 0,
                'HasLLE' => 0,
                'leagueRecord' => '30-10',
            ],
        ]);
    }

    /**
     * @return array{Date: string, BoxID: int, Visitor: int, Home: int, VScore: int, HScore: int}
     */
    private function makeGameRow(
        string $date,
        int $visitor,
        int $home,
        int $vScore,
        int $hScore,
    ): array {
        return [
            'Date' => $date,
            'BoxID' => 1,
            'Visitor' => $visitor,
            'Home' => $home,
            'VScore' => $vScore,
            'HScore' => $hScore,
        ];
    }
}
