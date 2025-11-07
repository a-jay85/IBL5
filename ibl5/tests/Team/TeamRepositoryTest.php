<?php

use PHPUnit\Framework\TestCase;
use Team\TeamRepository;

/**
 * Tests for TeamRepository
 * 
 * Validates database query methods for team-related data
 */
class TeamRepositoryTest extends TestCase
{
    private $db;
    private $repository;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new TeamRepository($this->db);
    }

    public function testGetTeamPowerDataReturnsData()
    {
        $mockData = [[
            'Team' => 'Boston Celtics',
            'win' => 50,
            'loss' => 32,
            'gb' => 0,
            'Division' => 'Atlantic',
            'Conference' => 'Eastern'
        ]];
        
        $this->db->setMockData($mockData);
        $this->db->setNumRows(1);
        
        $result = $this->repository->getTeamPowerData('Boston Celtics');
        
        $this->assertEquals('Boston Celtics', $result['Team']);
        $this->assertEquals(50, $result['win']);
    }

    public function testGetTeamPowerDataReturnsNullWhenNoResults()
    {
        $this->db->setMockData([]);
        $this->db->setNumRows(0);
        
        $result = $this->repository->getTeamPowerData('Nonexistent Team');
        
        $this->assertNull($result);
    }

    public function testGetRosterUnderContractExecutesQuery()
    {
        // Arrange - Set up mock data in correct sort order
        $mockData = [
            ['name' => 'Active Player A', 'ordinal' => 100],
            ['name' => 'Active Player B', 'ordinal' => 200],
            ['name' => 'Waived Player', 'ordinal' => 965]
        ];
        $this->db->setMockData($mockData);
        $this->db->setNumRows(3);
        
        // Act
        $result = $this->repository->getRosterUnderContract(2);
        
        // Assert - Verify query was executed (implementation-agnostic)
        $queries = $this->db->getExecutedQueries();
        $this->assertNotEmpty($queries, 'Should execute database query');
    }

    public function testGetFreeAgencyRosterExecutesQuery()
    {
        // Arrange
        $this->db->setMockData([]);
        $this->db->setNumRows(0);
        
        // Act
        $result = $this->repository->getFreeAgencyRoster(2);
        
        // Assert - Verify query was executed
        $queries = $this->db->getExecutedQueries();
        $this->assertNotEmpty($queries, 'Should execute database query');
    }

    public function testGetHistoricalRosterExecutesQuery()
    {
        // Arrange
        $this->db->setMockData([]);
        $this->db->setNumRows(0);
        
        // Act
        $result = $this->repository->getHistoricalRoster(2, '2023');
        
        // Assert - Verify query was executed  
        $queries = $this->db->getExecutedQueries();
        $this->assertNotEmpty($queries, 'Should execute database query');
    }
}
