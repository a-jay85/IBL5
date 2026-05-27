<?php

declare(strict_types=1);

namespace Tests\NextSim;

use PHPUnit\Framework\TestCase;
use NextSim\NextSimService;
use TeamSchedule\Contracts\TeamScheduleRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\MockPreparedStatement;

/**
 * NextSimServiceTest - Tests for NextSimService
 */
class NextSimServiceTest extends TestCase
{
    private MockDatabase $mockDb;
    private object $mockMysqliDb;

    /** @var TeamScheduleRepositoryInterface&\PHPUnit\Framework\MockObject\Stub */
    private TeamScheduleRepositoryInterface $stubRepository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
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
            private MockDatabase $mockDb;
            public int $connect_errno = 0;
            public ?string $connect_error = null;

            public function __construct(MockDatabase $mockDb)
            {
                $this->mockDb = $mockDb;
            }

            #[\ReturnTypeWillChange]
            public function prepare(string $query): MockPreparedStatement|false
            {
                return new MockPreparedStatement($this->mockDb, $query);
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
        $service = new NextSimService($this->mockMysqliDb, $this->stubRepository);

        $this->assertInstanceOf(NextSimService::class, $service);
    }

    public function testServiceImplementsCorrectInterface(): void
    {
        $service = new NextSimService($this->mockMysqliDb, $this->stubRepository);

        $this->assertInstanceOf(
            \NextSim\Contracts\NextSimServiceInterface::class,
            $service
        );
    }

    // ============================================
    // MULTIPLE INSTANCES TEST
    // ============================================

    public function testMultipleServicesCanBeInstantiated(): void
    {
        $service1 = new NextSimService($this->mockMysqliDb, $this->stubRepository);
        $service2 = new NextSimService($this->mockMysqliDb, $this->stubRepository);

        $this->assertNotSame($service1, $service2);
    }

    // ============================================
    // SEASON YEAR FILTERING
    // ============================================

    public function testGetNextSimGamesPassesSeasonEndingYearToRepository(): void
    {
        $this->setupTeamMockData();
        $season = new \Season\Season($this->mockDb);

        $mockRepository = $this->createMock(TeamScheduleRepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('getProjectedGamesNextSimResult')
            ->with(
                1,
                $season->lastSimEndDate,
                $season->projectedNextSimEndDate->format('Y-m-d'),
                $season->endingYear
            )
            ->willReturn([]);

        $service = new NextSimService($this->mockMysqliDb, $mockRepository);

        $service->getNextSimGames(1, $season);
    }

    private function setupTeamMockData(): void
    {
        $this->mockDb->setMockData([
            [
                'teamid' => 1,
                'team_city' => 'Test City',
                'team_name' => 'TestTeam',
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
}
