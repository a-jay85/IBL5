<?php

declare(strict_types=1);

namespace Tests\Player;

use PHPUnit\Framework\TestCase;
use Player\PlayerStatsRepository;

/**
 * PlayerStatsRepositoryTest - Tests for the PlayerStatsRepository class
 * 
 * Verifies database queries for player statistics retrieval.
 */
class PlayerStatsRepositoryTest extends TestCase
{
    /** @var \mysqli&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli $mockDb;
    
    /** @var \mysqli_stmt&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli_stmt $mockStmt;
    
    /** @var \mysqli_result&\PHPUnit\Framework\MockObject\MockObject */
    private \mysqli_result $mockResult;
    
    private PlayerStatsRepository $repository;

    protected function setUp(): void
    {
        // Create mock database connection
        $this->mockDb = $this->createMock(\mysqli::class);
        $this->mockStmt = $this->createMock(\mysqli_stmt::class);
        $this->mockResult = $this->createMock(\mysqli_result::class);
        
        // Configure mock to return prepared statement
        $this->mockDb->method('prepare')->willReturn($this->mockStmt);
        $this->mockStmt->method('bind_param')->willReturn(true);
        $this->mockStmt->method('execute')->willReturn(true);
        $this->mockStmt->method('get_result')->willReturn($this->mockResult);
        $this->mockStmt->method('close')->willReturn(true);
        
        // Create repository with mocked database
        $this->repository = new PlayerStatsRepository($this->mockDb);
    }

    public function testGetHistoricalStatsReturnsArrayOfStats(): void
    {
        // Configure mock to return test data
        $testData = [
            ['year' => 2024, 'team' => 'TEST', 'games' => 82, 'pts' => 1500],
            ['year' => 2023, 'team' => 'TEST', 'games' => 80, 'pts' => 1400],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], $testData[1], null);
        
        $result = $this->repository->getHistoricalStats(1);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    public function testGetPlayoffStatsReturnsArrayOfStats(): void
    {
        $testData = [
            ['year' => 2024, 'team' => 'TEST', 'games' => 16, 'pts' => 400],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], null);
        
        $result = $this->repository->getPlayoffStats('Test Player');
        
        $this->assertIsArray($result);
        $this->assertCount(1, $result);
    }

    public function testGetHeatStatsReturnsArrayOfStats(): void
    {
        $testData = [
            ['year' => 2024, 'team' => 'USA', 'games' => 5, 'pts' => 100],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], null);
        
        $result = $this->repository->getHeatStats('Test Player');
        
        $this->assertIsArray($result);
    }

    public function testGetOlympicsStatsReturnsArrayOfStats(): void
    {
        $testData = [
            ['year' => 2024, 'team' => 'USA', 'games' => 8, 'pts' => 200],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], null);
        
        $result = $this->repository->getOlympicsStats('Test Player');
        
        $this->assertIsArray($result);
    }

    public function testGetSimDatesReturnsArrayOfDates(): void
    {
        $testData = [
            ['Sim' => 1, 'Start Date' => '2024-10-01', 'End Date' => '2024-10-07'],
            ['Sim' => 2, 'Start Date' => '2024-10-08', 'End Date' => '2024-10-14'],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], $testData[1], null);
        
        $result = $this->repository->getSimDates(10);
        
        $this->assertIsArray($result);
    }

    public function testGetBoxScoresBetweenDatesReturnsArrayOfBoxScores(): void
    {
        $testData = [
            ['Date' => '2024-10-01', 'gameMIN' => 30, 'game2GM' => 5, 'game2GA' => 10],
        ];
        
        $this->mockResult->method('fetch_assoc')
            ->willReturnOnConsecutiveCalls($testData[0], null);
        
        $result = $this->repository->getBoxScoresBetweenDates(1, '2024-10-01', '2024-10-31');
        
        $this->assertIsArray($result);
    }

    public function testGetSeasonCareerAveragesReturnsNullWhenNoData(): void
    {
        $this->mockResult->method('fetch_assoc')->willReturn(null);
        
        $result = $this->repository->getSeasonCareerAverages('Test Player');
        
        $this->assertNull($result);
    }

    public function testGetSeasonCareerAveragesReturnsArrayWhenDataExists(): void
    {
        $testData = ['games' => 500, 'pts' => 15.5, 'reb' => 6.5, 'ast' => 3.2];
        
        $this->mockResult->method('fetch_assoc')->willReturn($testData);
        
        $result = $this->repository->getSeasonCareerAverages('Test Player');
        
        $this->assertIsArray($result);
        $this->assertEquals(500, $result['games']);
    }

    public function testGetPlayoffCareerAveragesReturnsNullWhenNoData(): void
    {
        $this->mockResult->method('fetch_assoc')->willReturn(null);
        
        $result = $this->repository->getPlayoffCareerAverages('Test Player');
        
        $this->assertNull($result);
    }

    public function testGetHeatCareerAveragesReturnsNullWhenNoData(): void
    {
        $this->mockResult->method('fetch_assoc')->willReturn(null);
        
        $result = $this->repository->getHeatCareerAverages('Test Player');
        
        $this->assertNull($result);
    }

    public function testGetOlympicsCareerAveragesReturnsNullWhenNoData(): void
    {
        $this->mockResult->method('fetch_assoc')->willReturn(null);
        
        $result = $this->repository->getOlympicsCareerAverages('Test Player');
        
        $this->assertNull($result);
    }

    public function testGetSeasonCareerAveragesByIdReturnsNullWhenNoData(): void
    {
        $this->mockResult->method('fetch_assoc')->willReturn(null);
        
        $result = $this->repository->getSeasonCareerAveragesById(999);
        
        $this->assertNull($result);
    }

    public function testGetSeasonCareerAveragesByIdReturnsArrayWhenDataExists(): void
    {
        $testData = ['pid' => 1, 'games' => 500, 'pts' => 15.5, 'reb' => 6.5, 'ast' => 3.2];
        
        $this->mockResult->method('fetch_assoc')->willReturn($testData);
        
        $result = $this->repository->getSeasonCareerAveragesById(1);
        
        $this->assertIsArray($result);
        $this->assertEquals(1, $result['pid']);
        $this->assertEquals(500, $result['games']);
    }
}
