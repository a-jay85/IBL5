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
}
