<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use Team\TeamRepository;
use Tests\WideUnit\Mocks\MockDatabase;

/**
 * Tests for TeamRepository
 *
 * Validates database query methods for team-related data
 */
class TeamRepositoryTest extends TestCase
{
    private MockDatabase $db;
    private TeamRepository $repository;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new TeamRepository($this->db);
    }

    public function testGetTeamPowerDataReturnsData()
    {
        $mockData = [[
            'teamid' => 1,
            'team_name' => 'Boston Celtics',
            'league_record' => '50-32',
            'wins' => 50,
            'losses' => 32,
            'pct' => 0.609,
            'conference' => 'Eastern',
            'division' => 'Atlantic',
            'conf_record' => '30-20',
            'div_record' => '10-5',
            'div_gb' => 0.0,
            'home_record' => '28-13',
            'away_record' => '22-19',
            'games_unplayed' => 0,
            'ranking' => 1.0,
            'last_win' => 50,
            'last_loss' => 32,
            'streak_type' => 'W',
            'streak' => 3,
            'sos' => 0.5,
            'remaining_sos' => 0.5,
        ]];

        $this->db->setMockData($mockData);
        $this->db->setNumRows(1);

        $result = $this->repository->getTeamPowerData('Boston Celtics');

        $this->assertEquals('Boston Celtics', $result['team_name']);
        $this->assertEquals(50, $result['wins']);
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
