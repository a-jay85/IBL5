<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Leaderboards\LeaderboardsRepository;

final class LeaderboardsRepositoryTest extends TestCase
{
    public function testGetTableTypeIdentifiesTotals(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $repository = new LeaderboardsRepository($mockDb);

        $this->assertEquals('totals', $repository->getTableType('ibl_hist'));
        $this->assertEquals('totals', $repository->getTableType('ibl_playoff_career_totals'));
        $this->assertEquals('totals', $repository->getTableType('ibl_heat_career_totals'));
        $this->assertEquals('totals', $repository->getTableType('ibl_olympics_career_totals'));
    }

    public function testGetTableTypeIdentifiesAverages(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $repository = new LeaderboardsRepository($mockDb);

        $this->assertEquals('averages', $repository->getTableType('ibl_season_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_playoff_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_heat_career_avgs'));
        $this->assertEquals('averages', $repository->getTableType('ibl_olympics_career_avgs'));
    }

    public function testGetLeaderboardsRejectsInvalidTableName(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
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
        $mockDb = $this->createMock(\stdClass::class);
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
        // Mock the database methods
        $mockDb = $this->createMock(\stdClass::class);
        $mockResult = new \stdClass();
        
        $mockDb->expects($this->once())
            ->method('sql_query')
            ->willReturn($mockResult);
            
        $mockDb->expects($this->once())
            ->method('sql_numrows')
            ->with($mockResult)
            ->willReturn(10);

        $repository = new LeaderboardsRepository($mockDb);

        // Should not throw exception
        $result = $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);
        
        $this->assertIsArray($result);
        $this->assertArrayHasKey('result', $result);
        $this->assertArrayHasKey('count', $result);
        $this->assertEquals(10, $result['count']);
    }

    public function testGetLeaderboardsAcceptsValidSortColumns(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $mockResult = new \stdClass();
        
        $mockDb->expects($this->once())
            ->method('sql_query')
            ->willReturn($mockResult);
            
        $mockDb->expects($this->once())
            ->method('sql_numrows')
            ->with($mockResult)
            ->willReturn(5);

        $repository = new LeaderboardsRepository($mockDb);

        // Should not throw exception for any valid column
        $result = $repository->getLeaderboards('ibl_season_career_avgs', 'ast', 1, 5);
        
        $this->assertIsArray($result);
        $this->assertEquals(5, $result['count']);
    }

    public function testGetLeaderboardsBuildsCorrectQueryForHistTable(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $mockResult = new \stdClass();
        
        // Capture the query to verify it's correct
        $capturedQuery = '';
        $mockDb->expects($this->once())
            ->method('sql_query')
            ->willReturnCallback(function($query) use ($mockResult, &$capturedQuery) {
                $capturedQuery = $query;
                return $mockResult;
            });
            
        $mockDb->expects($this->once())
            ->method('sql_numrows')
            ->willReturn(10);

        $repository = new LeaderboardsRepository($mockDb);
        $repository->getLeaderboards('ibl_hist', 'pts', 0, 10);
        
        // Verify query structure
        $this->assertStringContainsString('SELECT', $capturedQuery);
        $this->assertStringContainsString('FROM ibl_hist', $capturedQuery);
        $this->assertStringContainsString('GROUP BY pid', $capturedQuery);
        $this->assertStringContainsString('ORDER BY pts DESC', $capturedQuery);
        $this->assertStringContainsString('LIMIT 10', $capturedQuery);
    }

    public function testGetLeaderboardsBuildsCorrectQueryForAveragesTable(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $mockResult = new \stdClass();
        
        $capturedQuery = '';
        $mockDb->expects($this->once())
            ->method('sql_query')
            ->willReturnCallback(function($query) use ($mockResult, &$capturedQuery) {
                $capturedQuery = $query;
                return $mockResult;
            });
            
        $mockDb->expects($this->once())
            ->method('sql_numrows')
            ->willReturn(20);

        $repository = new LeaderboardsRepository($mockDb);
        $repository->getLeaderboards('ibl_season_career_avgs', 'ast', 1, 20);
        
        // Verify query structure
        $this->assertStringContainsString('FROM ibl_season_career_avgs', $capturedQuery);
        $this->assertStringContainsString("p.retired = '0'", $capturedQuery);
        $this->assertStringContainsString('ORDER BY ast DESC', $capturedQuery);
        $this->assertStringContainsString('LIMIT 20', $capturedQuery);
    }

    public function testGetLeaderboardsHandlesUnlimitedRecords(): void
    {
        $mockDb = $this->createMock(\stdClass::class);
        $mockResult = new \stdClass();
        
        $capturedQuery = '';
        $mockDb->expects($this->once())
            ->method('sql_query')
            ->willReturnCallback(function($query) use ($mockResult, &$capturedQuery) {
                $capturedQuery = $query;
                return $mockResult;
            });
            
        $mockDb->expects($this->once())
            ->method('sql_numrows')
            ->willReturn(100);

        $repository = new LeaderboardsRepository($mockDb);
        $repository->getLeaderboards('ibl_hist', 'pts', 0, 0);
        
        // Verify no LIMIT clause
        $this->assertStringNotContainsString('LIMIT', $capturedQuery);
    }
}
