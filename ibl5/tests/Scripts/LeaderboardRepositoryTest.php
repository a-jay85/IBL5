<?php

declare(strict_types=1);

namespace Tests\Scripts;

use Scripts\LeaderboardRepository;
use Scripts\Contracts\LeaderboardRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Integration\Mocks\MockDatabase;

/**
 * @covers \Scripts\LeaderboardRepository
 */
class LeaderboardRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private LeaderboardRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new LeaderboardRepository($this->mockDb);
    }

    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(LeaderboardRepositoryInterface::class, $this->repository);
    }

    public function testGetAllPlayersReturnsArray(): void
    {
        $this->mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player One'],
            ['pid' => 2, 'name' => 'Player Two'],
        ]);

        $result = $this->repository->getAllPlayers();

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertEquals('Player One', $result[0]['name']);
    }

    public function testGetAllPlayersExecutesCorrectQuery(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getAllPlayers();

        $queries = $this->mockDb->getExecutedQueries();
        $this->assertNotEmpty($queries);
        $this->assertStringContainsString('SELECT pid, name FROM ibl_plr', $queries[0]);
    }

    public function testGetPlayerStatsUsesCorrectTable(): void
    {
        $this->mockDb->setMockData([
            ['games' => 10, 'minutes' => 200, 'fgm' => 50],
        ]);

        $result = $this->repository->getPlayerStats('Test Player', 'ibl_heat_stats');

        $this->assertIsArray($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('ibl_heat_stats', $queries[0]);
        $this->assertStringContainsString('name =', $queries[0]);
    }

    public function testGetPlayerStatsThrowsForInvalidTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid stats table');

        $this->repository->getPlayerStats('Test Player', 'evil_table');
    }

    public function testGetPlayerCareerStatsReturnsPlayerData(): void
    {
        $this->mockDb->setMockData([
            [
                'pid' => 1,
                'name' => 'Test Player',
                'car_gm' => 100,
                'car_pts' => 2000,
            ],
        ]);

        $result = $this->repository->getPlayerCareerStats('Test Player');

        $this->assertIsArray($result);
        $this->assertEquals(100, $result['car_gm']);
        $this->assertEquals(2000, $result['car_pts']);
    }

    public function testGetPlayerCareerStatsReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getPlayerCareerStats('Unknown Player');

        $this->assertNull($result);
    }

    public function testDeletePlayerCareerTotalsUsesCorrectTable(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->deletePlayerCareerTotals('Test Player', 'ibl_heat_career_totals');

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('DELETE FROM ibl_heat_career_totals', $queries[0]);
        $this->assertStringContainsString('name =', $queries[0]);
    }

    public function testDeletePlayerCareerTotalsThrowsForInvalidTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid career table');

        $this->repository->deletePlayerCareerTotals('Test Player', 'evil_table');
    }

    public function testInsertPlayerCareerTotalsUsesParameterizedQuery(): void
    {
        $this->mockDb->setReturnTrue(true);

        $data = [
            'pid' => 1,
            'name' => 'Test Player',
            'games' => 10,
            'pts' => 200,
        ];

        $result = $this->repository->insertPlayerCareerTotals('ibl_heat_career_totals', $data);

        $this->assertTrue($result);
        $queries = $this->mockDb->getExecutedQueries();
        $this->assertStringContainsString('INSERT INTO ibl_heat_career_totals', $queries[0]);
        $this->assertStringContainsString('`pid`', $queries[0]);
        $this->assertStringContainsString('`name`', $queries[0]);
    }

    public function testInsertPlayerCareerTotalsThrowsForInvalidTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid career table');

        $this->repository->insertPlayerCareerTotals('evil_table', ['pid' => 1]);
    }

    public function testDeletePlayerCareerAvgsWorksForValidTable(): void
    {
        $this->mockDb->setReturnTrue(true);

        $result = $this->repository->deletePlayerCareerAvgs('Test Player', 'ibl_heat_career_avgs');

        $this->assertTrue($result);
    }

    public function testInsertPlayerCareerAvgsWorksForValidTable(): void
    {
        $this->mockDb->setReturnTrue(true);

        $data = [
            'pid' => 1,
            'name' => 'Test Player',
            'games' => 10,
            'pts' => 20.5,
        ];

        $result = $this->repository->insertPlayerCareerAvgs('ibl_season_career_avgs', $data);

        $this->assertTrue($result);
    }

    public function testAllAllowedTablesAreAccepted(): void
    {
        $this->mockDb->setReturnTrue(true);

        // Stats tables
        $this->mockDb->setMockData([]);
        $this->repository->getPlayerStats('Player', 'ibl_heat_stats');
        $this->mockDb->setMockData([]);
        $this->repository->getPlayerStats('Player', 'ibl_playoff_stats');

        // Career tables
        $careerTables = [
            'ibl_heat_career_totals',
            'ibl_heat_career_avgs',
            'ibl_playoff_career_totals',
            'ibl_playoff_career_avgs',
            'ibl_season_career_avgs',
        ];

        foreach ($careerTables as $table) {
            $result = $this->repository->deletePlayerCareerTotals('Player', $table);
            $this->assertTrue($result);
        }
    }
}
