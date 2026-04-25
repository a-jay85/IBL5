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
                'id' => 1,
                'game_date' => '2025-11-01',
                'visitor_teamid' => 1,
                'visitor_score' => 100,
                'home_teamid' => 2,
                'home_score' => 95,
                'box_id' => 101,
                'game_of_that_day' => 1,
            ],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getAllGamesWithBoxScoreInfo();

        $this->assertCount(1, $result);
        $this->assertSame('2025-11-01', $result[0]['game_date']);
        $this->assertSame(1, $result[0]['visitor_teamid']);
        $this->assertSame(2, $result[0]['home_teamid']);
    }

    public function testGetAllGamesNormalizesNullGameOfThatDay(): void
    {
        $this->mockDb->setMockData([
            [
                'id' => 1,
                'game_date' => '2025-11-01',
                'visitor_teamid' => 1,
                'visitor_score' => 0,
                'home_teamid' => 2,
                'home_score' => 0,
                'box_id' => 101,
                'game_of_that_day' => null,
            ],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getAllGamesWithBoxScoreInfo();

        $this->assertSame(0, $result[0]['game_of_that_day']);
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
            ['teamid' => 1, 'league_record' => '25-10'],
            ['teamid' => 2, 'league_record' => '20-15'],
        ]);
        $repository = new LeagueScheduleRepository($this->mockMysqliDb);

        $result = $repository->getTeamRecords();

        $this->assertSame('25-10', $result[1]);
        $this->assertSame('20-15', $result[2]);
    }
}
