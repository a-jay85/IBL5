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

    public function testGetRosterUnderContractSortsByOrdinalThenName()
    {
        $this->repository->getRosterUnderContract(2);
        
        $queries = $this->db->getExecutedQueries();
        $lastQuery = end($queries);
        
        // Verify the query sorts regular players alphabetically, with waived players at the end
        $this->assertStringContainsString('ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC', $lastQuery);
        $this->assertStringContainsString("tid = '2'", $lastQuery);
        $this->assertStringContainsString('retired = 0', $lastQuery);
    }

    public function testGetFreeAgencyRosterSortsByOrdinalThenName()
    {
        $this->repository->getFreeAgencyRoster(2);
        
        $queries = $this->db->getExecutedQueries();
        $lastQuery = end($queries);
        
        // Verify the query sorts regular players alphabetically, with waived players at the end
        $this->assertStringContainsString('ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC', $lastQuery);
        $this->assertStringContainsString("tid = '2'", $lastQuery);
        $this->assertStringContainsString('cyt != cy', $lastQuery);
    }

    public function testGetHistoricalRosterSortsByOrdinalThenName()
    {
        $this->repository->getHistoricalRoster(2, '2023');
        
        $queries = $this->db->getExecutedQueries();
        $lastQuery = end($queries);
        
        // Verify the query sorts regular players alphabetically, with waived players at the end
        $this->assertStringContainsString('ORDER BY CASE WHEN ordinal > 960 THEN 1 ELSE 0 END, name ASC', $lastQuery);
        $this->assertStringContainsString("teamid = '2'", $lastQuery);
        $this->assertStringContainsString("year = '2023'", $lastQuery);
    }
}
