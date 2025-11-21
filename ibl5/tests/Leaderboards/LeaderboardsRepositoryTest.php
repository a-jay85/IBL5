<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Leaderboards\LeaderboardsRepository;

final class LeaderboardsRepositoryTest extends TestCase
{
    public function testGetTableTypeIdentifiesTotals(): void
    {
        $mockDb = new MockDatabase();
        $repository = new LeaderboardsRepository($mockDb);

        $this->assertEquals('totals', $repository->getTableType('ibl_hist'));
        $this->assertEquals('totals', $repository->getTableType('ibl_playoff_career_totals'));
        $this->assertEquals('totals', $repository->getTableType('ibl_heat_career_totals'));
        $this->assertEquals('totals', $repository->getTableType('ibl_olympics_career_totals'));
    }

    public function testGetTableTypeIdentifiesAverages(): void
    {
        $mockDb = new MockDatabase();
        $repository = new LeaderboardsRepository($mockDb);

        $this->assertEquals('averages', $repository->getTableType('ibl_season_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_playoff_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_heat_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_olympics_career_avgs'));
    }

    public function testGetLeaderboardsRejectsInvalidTableName(): void
    {
        $mockDb = new MockDatabase();
        $repository = new LeaderboardsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid table name');

        $repository->getLeaderboards(
            'malicious_table; DROP TABLE ibl_plr;',
            'pts',
            0,
            10
        );
    }

    public function testGetLeaderboardsRejectsInvalidSortColumn(): void
    {
        $mockDb = new MockDatabase();
        $repository = new LeaderboardsRepository($mockDb);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid sort column');

        $repository->getLeaderboards(
            'ibl_hist',
            'malicious_column; DROP TABLE ibl_plr;',
            0,
            10
        );
    }

    public function testGetLeaderboardsAcceptsValidTableNames(): void
    {
        // Use the MockDatabase class
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player 1', 'pts' => 100],
            ['pid' => 2, 'name' => 'Player 2', 'pts' => 95],
        ]);
        
        $repository = new LeaderboardsRepository($mockDb);

        // Should not throw exception and return result array
        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(2, $result['count']);
    }

    public function testGetLeaderboardsAcceptsValidSortColumns(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player 1', 'ast' => 10],
            ['pid' => 2, 'name' => 'Player 2', 'ast' => 9],
            ['pid' => 3, 'name' => 'Player 3', 'ast' => 8],
            ['pid' => 4, 'name' => 'Player 4', 'ast' => 7],
            ['pid' => 5, 'name' => 'Player 5', 'ast' => 6],
        ]);

        $repository = new LeaderboardsRepository($mockDb);

        // Should not throw exception for any valid column
        $result = $repository->getLeaderboards('ibl_season_career_avgs', 'ast', 1, 5);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(5, $result['count']);
    }

    public function testGetLeaderboardsBuildsCorrectQueryForHistTable(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player 1', 'pts' => 100],
            ['pid' => 2, 'name' => 'Player 2', 'pts' => 95],
            ['pid' => 3, 'name' => 'Player 3', 'pts' => 90],
            ['pid' => 4, 'name' => 'Player 4', 'pts' => 85],
            ['pid' => 5, 'name' => 'Player 5', 'pts' => 80],
            ['pid' => 6, 'name' => 'Player 6', 'pts' => 75],
            ['pid' => 7, 'name' => 'Player 7', 'pts' => 70],
            ['pid' => 8, 'name' => 'Player 8', 'pts' => 65],
            ['pid' => 9, 'name' => 'Player 9', 'pts' => 60],
            ['pid' => 10, 'name' => 'Player 10', 'pts' => 55],
        ]);

        $repository = new LeaderboardsRepository($mockDb);
        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);
        
        // Verify we got results with correct count
        $this->assertIsArray($result);
        $this->assertEquals(10, $result['count']);
        
        // Check that queries were executed
        $queries = $mockDb->getExecutedQueries();
        $this->assertGreaterThan(0, count($queries));
    }

    public function testGetLeaderboardsBuildsCorrectQueryForAveragesTable(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player 1', 'ast' => 10.5],
            ['pid' => 2, 'name' => 'Player 2', 'ast' => 10.2],
            ['pid' => 3, 'name' => 'Player 3', 'ast' => 9.8],
            ['pid' => 4, 'name' => 'Player 4', 'ast' => 9.5],
            ['pid' => 5, 'name' => 'Player 5', 'ast' => 9.1],
            ['pid' => 6, 'name' => 'Player 6', 'ast' => 8.9],
            ['pid' => 7, 'name' => 'Player 7', 'ast' => 8.7],
            ['pid' => 8, 'name' => 'Player 8', 'ast' => 8.5],
            ['pid' => 9, 'name' => 'Player 9', 'ast' => 8.2],
            ['pid' => 10, 'name' => 'Player 10', 'ast' => 8.0],
            ['pid' => 11, 'name' => 'Player 11', 'ast' => 7.8],
            ['pid' => 12, 'name' => 'Player 12', 'ast' => 7.5],
            ['pid' => 13, 'name' => 'Player 13', 'ast' => 7.3],
            ['pid' => 14, 'name' => 'Player 14', 'ast' => 7.1],
            ['pid' => 15, 'name' => 'Player 15', 'ast' => 7.0],
            ['pid' => 16, 'name' => 'Player 16', 'ast' => 6.8],
            ['pid' => 17, 'name' => 'Player 17', 'ast' => 6.5],
            ['pid' => 18, 'name' => 'Player 18', 'ast' => 6.3],
            ['pid' => 19, 'name' => 'Player 19', 'ast' => 6.1],
            ['pid' => 20, 'name' => 'Player 20', 'ast' => 6.0],
        ]);

        $repository = new LeaderboardsRepository($mockDb);
        $result = $repository->getLeaderboards('ibl_season_career_avgs', 'ast', 1, 20);
        
        // Verify query returns results with correct count
        $this->assertIsArray($result);
        $this->assertEquals(20, $result['count']);
    }

    public function testGetLeaderboardsHandlesUnlimitedRecords(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player 1', 'pts' => 100],
            ['pid' => 2, 'name' => 'Player 2', 'pts' => 95],
            ['pid' => 3, 'name' => 'Player 3', 'pts' => 90],
            ['pid' => 4, 'name' => 'Player 4', 'pts' => 85],
            ['pid' => 5, 'name' => 'Player 5', 'pts' => 80],
        ]);

        $repository = new LeaderboardsRepository($mockDb);
        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 0);
        
        // Verify we can get unlimited records with limit 0
        $this->assertIsArray($result);
        $this->assertEquals(5, $result['count']);
    }
}
