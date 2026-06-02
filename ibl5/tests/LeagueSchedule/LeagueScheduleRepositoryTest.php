<?php

declare(strict_types=1);

namespace Tests\LeagueSchedule;

use LeagueSchedule\LeagueScheduleRepository;
use LeagueSchedule\Contracts\LeagueScheduleRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;

class LeagueScheduleRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
    }

    public function testGetAllGamesWithBoxScoreInfoReturnsEmptyArrayWhenNoGames(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new LeagueScheduleRepository($this->mockDb);

        $result = $repository->getAllGamesWithBoxScoreInfo(2026);

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
        $repository = new LeagueScheduleRepository($this->mockDb);

        $result = $repository->getAllGamesWithBoxScoreInfo(2026);

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
        $repository = new LeagueScheduleRepository($this->mockDb);

        $result = $repository->getAllGamesWithBoxScoreInfo(2026);

        $this->assertSame(0, $result[0]['game_of_that_day']);
    }

    public function testGetTeamRecordsReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockDb->setMockData([]);
        $repository = new LeagueScheduleRepository($this->mockDb);

        $result = $repository->getTeamRecords();

        $this->assertSame([], $result);
    }

    public function testGetTeamRecordsReturnsMapOfRecords(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'league_record' => '25-10'],
            ['teamid' => 2, 'league_record' => '20-15'],
        ]);
        $repository = new LeagueScheduleRepository($this->mockDb);

        $result = $repository->getTeamRecords();

        $this->assertSame('25-10', $result[1]);
        $this->assertSame('20-15', $result[2]);
    }
}
