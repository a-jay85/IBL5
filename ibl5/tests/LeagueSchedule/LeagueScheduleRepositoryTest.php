<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\LeagueScheduleRepository;
use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use PHPUnit\Framework\TestCase;

class LeagueScheduleRepositoryTest extends TestCase
{
    private \MockDatabase $mockDb;
    private object $mockMysqliDb;

    protected function setUp(): void
    {
        $this->mockDb = new \MockDatabase();
        $this->setupMockMysqliDb();
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
        };
    }

    public function testImplementsInterface(): void
    {
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $this->assertInstanceOf(LeagueScheduleRepositoryInterface::class, $repository);
    }

    public function testGetAllGamesWithBoxScoreInfoReturnsEmptyArrayWhenNoGames(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getAllGamesWithBoxScoreInfo();

        $this->assertSame([], $result);
    }

    public function testGetAllGamesWithBoxScoreInfoReturnsGames(): void
    {
        $this->mockDb->setMockData([
            [
                'SchedID' => 1,
                'Date' => '2025-11-01',
                'Visitor' => 1,
                'VScore' => 100,
                'Home' => 2,
                'HScore' => 95,
                'BoxID' => 101,
                'gameOfThatDay' => 1,
            ],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getAllGamesWithBoxScoreInfo();

        $this->assertCount(1, $result);
        $this->assertSame('2025-11-01', $result[0]['Date']);
        $this->assertSame(1, $result[0]['Visitor']);
        $this->assertSame(2, $result[0]['Home']);
    }

    public function testGetAllGamesNormalizesNullGameOfThatDay(): void
    {
        $this->mockDb->setMockData([
            [
                'SchedID' => 1,
                'Date' => '2025-11-01',
                'Visitor' => 1,
                'VScore' => 0,
                'Home' => 2,
                'HScore' => 0,
                'BoxID' => 101,
                'gameOfThatDay' => null,
            ],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getAllGamesWithBoxScoreInfo();

        $this->assertSame(0, $result[0]['gameOfThatDay']);
    }

    public function testGetTeamRecordsReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getTeamRecords();

        $this->assertSame([], $result);
    }

    public function testGetTeamRecordsReturnsMapOfRecords(): void
    {
        $this->mockDb->setMockData([
            ['tid' => 1, 'leagueRecord' => '25-10'],
            ['tid' => 2, 'leagueRecord' => '20-15'],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getTeamRecords();

        $this->assertSame('25-10', $result[1]);
        $this->assertSame('20-15', $result[2]);
    }
}
