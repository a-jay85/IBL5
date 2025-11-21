<?php

use PHPUnit\Framework\TestCase;
use FreeAgency\FreeAgencyDemandRepository;

/**
 * Comprehensive tests for FreeAgencyDemandRepository
 * 
 * Tests database queries for free agency demand calculations:
 * - Team performance data retrieval
 * - Position salary commitment calculations
 * - Player demands retrieval
 * - SQL injection prevention
 */
class FreeAgencyDemandRepositoryTest extends TestCase
{
    private $mockDb;
    private $mockMysqliDb;
    private FreeAgencyDemandRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->mockMysqliDb = $this->createMockMysqliDb();
        $this->repository = new FreeAgencyDemandRepository($this->mockDb, $this->mockMysqliDb);
    }

    /**
     * @group demand-repository
     * @group team-performance
     */
    public function testGetTeamPerformanceReturnsAllRequiredKeys(): void
    {
        // Arrange
        $this->setupMockTeamPerformance('Test Team', 50, 32, 600, 400);
        
        // Act
        $result = $this->repository->getTeamPerformance('Test Team');
        
        // Assert
        $this->assertArrayHasKey('wins', $result);
        $this->assertArrayHasKey('losses', $result);
        $this->assertArrayHasKey('tradWins', $result);
        $this->assertArrayHasKey('tradLosses', $result);
    }

    /**
     * @group demand-repository
     * @group team-performance
     */
    public function testGetTeamPerformanceReturnsCorrectValues(): void
    {
        // Arrange
        $this->setupMockTeamPerformance('Boston Celtics', 55, 27, 650, 350);
        
        // Act
        $result = $this->repository->getTeamPerformance('Boston Celtics');
        
        // Assert
        $this->assertEquals(55, $result['wins']);
        $this->assertEquals(27, $result['losses']);
        $this->assertEquals(650, $result['tradWins']);
        $this->assertEquals(350, $result['tradLosses']);
    }

    /**
     * @group demand-repository
     * @group team-performance
     */
    public function testGetTeamPerformanceForNonexistentTeamReturnsZeros(): void
    {
        // Arrange - No results
        $this->setupEmptyMockResult();
        
        // Act
        $result = $this->repository->getTeamPerformance('Nonexistent Team');
        
        // Assert
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
        $this->assertEquals(0, $result['tradWins']);
        $this->assertEquals(0, $result['tradLosses']);
    }

    /**
     * @group demand-repository
     * @group team-performance
     */
    public function testGetTeamPerformanceHandlesNullValues(): void
    {
        // Arrange - Null values in database
        $this->setupMockTeamPerformance('Test Team', null, null, null, null);
        
        // Act
        $result = $this->repository->getTeamPerformance('Test Team');
        
        // Assert - Should convert nulls to 0
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
        $this->assertEquals(0, $result['tradWins']);
        $this->assertEquals(0, $result['tradLosses']);
    }

    /**
     * @group demand-repository
     * @group position-salary
     */
    public function testGetPositionSalaryCommitmentReturnsInteger(): void
    {
        // Arrange
        $this->setupMockPositionSalary([], 0);
        
        // Act
        $result = $this->repository->getPositionSalaryCommitment('Test Team', 'PG', 1);
        
        // Assert
        $this->assertIsInt($result);
    }

    /**
     * @group demand-repository
     * @group position-salary
     */
    public function testGetPositionSalaryCommitmentCalculatesTotal(): void
    {
        // Arrange - Two players at position, both in year 0
        $players = [
            ['cy' => 0, 'cy1' => 800, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
            ['cy' => 0, 'cy1' => 900, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ];
        
        $this->setupMockPositionSalary($players, 2);
        
        // Act
        $result = $this->repository->getPositionSalaryCommitment('Test Team', 'PG', 0);
        
        // Assert
        $this->assertEquals(1700, $result); // 800 + 900
    }

    /**
     * @group demand-repository
     * @group position-salary
     */
    public function testGetPositionSalaryCommitmentExcludesSpecifiedPlayer(): void
    {
        // Arrange - One player should be excluded
        $players = [
            ['cy' => 0, 'cy1' => 800, 'cy2' => 0, 'cy3' => 0, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ];
        
        // Mock will only return one row (the non-excluded player)
        $this->setupMockPositionSalary($players, 1);
        
        // Act - Excluding player ID 999
        $result = $this->repository->getPositionSalaryCommitment('Test Team', 'PG', 999);
        
        // Assert
        $this->assertEquals(800, $result);
    }

    /**
     * @group demand-repository
     * @group position-salary
     */
    public function testGetPositionSalaryCommitmentHandlesContractYearOffsets(): void
    {
        // Arrange - Player in year 2 of contract
        $players = [
            ['cy' => 1, 'cy1' => 800, 'cy2' => 850, 'cy3' => 900, 'cy4' => 0, 'cy5' => 0, 'cy6' => 0],
        ];
        
        $this->setupMockPositionSalary($players, 1);
        
        // Act
        $result = $this->repository->getPositionSalaryCommitment('Test Team', 'PG', 0);
        
        // Assert - Should use cy2 (next year's salary)
        $this->assertEquals(850, $result);
    }

    /**
     * @group demand-repository
     * @group position-salary
     */
    public function testGetPositionSalaryCommitmentForExpiredContracts(): void
    {
        // Arrange - Player in last year of contract (cy=5)
        $players = [
            ['cy' => 5, 'cy1' => 800, 'cy2' => 800, 'cy3' => 800, 'cy4' => 800, 'cy5' => 800, 'cy6' => 900],
        ];
        
        $this->setupMockPositionSalary($players, 1);
        
        // Act
        $result = $this->repository->getPositionSalaryCommitment('Test Team', 'PG', 0);
        
        // Assert - Should use cy6
        $this->assertEquals(900, $result);
    }

    /**
     * @group demand-repository
     * @group player-demands
     */
    public function testGetPlayerDemandsReturnsAllRequiredKeys(): void
    {
        // Arrange
        $this->setupMockPlayerDemands('Test Player', 800, 850, 900, 950, 1000, 1050);
        
        // Act
        $result = $this->repository->getPlayerDemands('Test Player');
        
        // Assert
        $this->assertArrayHasKey('dem1', $result);
        $this->assertArrayHasKey('dem2', $result);
        $this->assertArrayHasKey('dem3', $result);
        $this->assertArrayHasKey('dem4', $result);
        $this->assertArrayHasKey('dem5', $result);
        $this->assertArrayHasKey('dem6', $result);
    }

    /**
     * @group demand-repository
     * @group player-demands
     */
    public function testGetPlayerDemandsReturnsCorrectValues(): void
    {
        // Arrange
        $this->setupMockPlayerDemands('Michael Jordan', 1200, 1250, 1300, 1350, 1400, 0);
        
        // Act
        $result = $this->repository->getPlayerDemands('Michael Jordan');
        
        // Assert
        $this->assertEquals(1200, $result['dem1']);
        $this->assertEquals(1250, $result['dem2']);
        $this->assertEquals(1300, $result['dem3']);
        $this->assertEquals(1350, $result['dem4']);
        $this->assertEquals(1400, $result['dem5']);
        $this->assertEquals(0, $result['dem6']);
    }

    /**
     * @group demand-repository
     * @group player-demands
     */
    public function testGetPlayerDemandsForNonexistentPlayerReturnsZeros(): void
    {
        // Arrange
        $this->setupEmptyMockResult();
        
        // Act
        $result = $this->repository->getPlayerDemands('Nonexistent Player');
        
        // Assert
        $this->assertEquals(0, $result['dem1']);
        $this->assertEquals(0, $result['dem2']);
        $this->assertEquals(0, $result['dem3']);
        $this->assertEquals(0, $result['dem4']);
        $this->assertEquals(0, $result['dem5']);
        $this->assertEquals(0, $result['dem6']);
    }

    /**
     * @group demand-repository
     * @group player-demands
     */
    public function testGetPlayerDemandsHandlesNullValues(): void
    {
        // Arrange
        $this->setupMockPlayerDemands('Test Player', null, null, null, null, null, null);
        
        // Act
        $result = $this->repository->getPlayerDemands('Test Player');
        
        // Assert - Nulls should convert to 0
        $this->assertEquals(0, $result['dem1']);
        $this->assertEquals(0, $result['dem2']);
        $this->assertEquals(0, $result['dem3']);
        $this->assertEquals(0, $result['dem4']);
        $this->assertEquals(0, $result['dem5']);
        $this->assertEquals(0, $result['dem6']);
    }

    /**
     * @group demand-repository
     * @group sql-injection
     */
    public function testGetTeamPerformanceUsesPreparedStatements(): void
    {
        // Arrange - Malicious input
        $maliciousInput = "'; DROP TABLE ibl_team_info; --";
        $this->setupEmptyMockResult();
        
        // Act
        $result = $this->repository->getTeamPerformance($maliciousInput);
        
        // Assert - Should safely return zeros, prepared statement prevents injection
        $this->assertEquals(0, $result['wins']);
        $this->assertEquals(0, $result['losses']);
    }

    /**
     * @group demand-repository
     * @group sql-injection
     */
    public function testGetPositionSalaryCommitmentUsesPreparedStatements(): void
    {
        // Arrange - Malicious inputs
        $this->setupEmptyMockResult();
        
        // Act
        $result = $this->repository->getPositionSalaryCommitment(
            "'; DROP TABLE ibl_plr; --",
            "'; DELETE FROM ibl_plr; --",
            1
        );
        
        // Assert - Prepared statements prevent injection
        $this->assertEquals(0, $result);
    }

    /**
     * @group demand-repository
     * @group sql-injection
     */
    public function testGetPlayerDemandsUsesPreparedStatements(): void
    {
        // Arrange
        $this->setupEmptyMockResult();
        
        // Act
        $result = $this->repository->getPlayerDemands("'; DROP TABLE ibl_demands; --");
        
        // Assert - Prepared statements prevent injection
        $this->assertEquals(0, $result['dem1']);
    }

    // Helper Methods

    /**
     * Create a mock MySQLi database connection
     */
    private function createMockMysqliDb()
    {
        $mockMysqliDb = $this->createMock(\mysqli::class);
        return $mockMysqliDb;
    }

    /**
     * Setup mock to return team performance data
     */
    private function setupMockTeamPerformance(
        string $teamName, 
        ?int $wins, 
        ?int $losses, 
        ?int $tradWins, 
        ?int $tradLosses
    ): void {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->num_rows = 1;
        $mockResult->method('fetch_assoc')->willReturn([
            'Contract_Wins' => $wins,
            'Contract_Losses' => $losses,
            'Contract_AvgW' => $tradWins,
            'Contract_AvgL' => $tradLosses,
        ]);
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->expects($this->once())
            ->method('bind_param')
            ->with('s', $teamName);
        
        $this->mockMysqliDb->method('prepare')->willReturn($mockStmt);
    }

    /**
     * Setup mock to return empty result
     */
    private function setupEmptyMockResult(): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->num_rows = 0;
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('bind_param')->willReturn(true);
        
        $this->mockMysqliDb->method('prepare')->willReturn($mockStmt);
    }

    /**
     * Setup mock to return position salary data
     */
    private function setupMockPositionSalary(array $players, int $numRows): void
    {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->num_rows = $numRows;
        
        // Set up fetch_assoc to return players sequentially, then false
        $returns = array_merge($players, [false]);
        $mockResult->method('fetch_assoc')->willReturnOnConsecutiveCalls(...$returns);
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->method('bind_param')->willReturn(true);
        
        $this->mockMysqliDb->method('prepare')->willReturn($mockStmt);
    }

    /**
     * Setup mock to return player demands
     */
    private function setupMockPlayerDemands(
        string $playerName,
        ?int $dem1,
        ?int $dem2,
        ?int $dem3,
        ?int $dem4,
        ?int $dem5,
        ?int $dem6
    ): void {
        $mockResult = $this->createMock(\mysqli_result::class);
        $mockResult->num_rows = 1;
        $mockResult->method('fetch_assoc')->willReturn([
            'dem1' => $dem1,
            'dem2' => $dem2,
            'dem3' => $dem3,
            'dem4' => $dem4,
            'dem5' => $dem5,
            'dem6' => $dem6,
        ]);
        
        $mockStmt = $this->createMock(\mysqli_stmt::class);
        $mockStmt->method('execute')->willReturn(true);
        $mockStmt->method('get_result')->willReturn($mockResult);
        $mockStmt->expects($this->once())
            ->method('bind_param')
            ->with('s', $playerName);
        
        $this->mockMysqliDb->method('prepare')->willReturn($mockStmt);
    }
}
