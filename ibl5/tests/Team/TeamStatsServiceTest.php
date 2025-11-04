<?php

use PHPUnit\Framework\TestCase;
use Team\TeamStatsService;

/**
 * Tests for TeamStatsService
 * 
 * Validates statistical calculations and data processing
 */
class TeamStatsServiceTest extends TestCase
{
    private $db;
    private $service;
    private $team;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->service = new TeamStatsService($this->db);
        
        // Create mock team
        $this->team = new stdClass();
        $this->team->color1 = 'FF0000';
        $this->team->color2 = '0000FF';
    }

    public function testGetLastSimsStartersReturnsHTMLTable()
    {
        $mockData = [
            [
                'pid' => 1,
                'name' => 'John Doe',
                'PGDepth' => 1,
                'SGDepth' => 0,
                'SFDepth' => 0,
                'PFDepth' => 0,
                'CDepth' => 0
            ],
            [
                'pid' => 2,
                'name' => 'Jane Smith',
                'PGDepth' => 0,
                'SGDepth' => 1,
                'SFDepth' => 0,
                'PFDepth' => 0,
                'CDepth' => 0
            ],
            [
                'pid' => 3,
                'name' => 'Bob Johnson',
                'PGDepth' => 0,
                'SGDepth' => 0,
                'SFDepth' => 1,
                'PFDepth' => 0,
                'CDepth' => 0
            ],
            [
                'pid' => 4,
                'name' => 'Mike Williams',
                'PGDepth' => 0,
                'SGDepth' => 0,
                'SFDepth' => 0,
                'PFDepth' => 1,
                'CDepth' => 0
            ],
            [
                'pid' => 5,
                'name' => 'Tom Brown',
                'PGDepth' => 0,
                'SGDepth' => 0,
                'SFDepth' => 0,
                'PFDepth' => 0,
                'CDepth' => 1
            ]
        ];
        
        $this->db->setMockData($mockData);
        $this->db->setNumRows(5);
        
        $result = new MockDatabaseResult($mockData);
        $output = $this->service->getLastSimsStarters($result, $this->team);
        
        $this->assertStringContainsString('Last Sim\'s Starters', $output);
        $this->assertStringContainsString('John Doe', $output);
        $this->assertStringContainsString('Jane Smith', $output);
        $this->assertStringContainsString('Bob Johnson', $output);
        $this->assertStringContainsString('Mike Williams', $output);
        $this->assertStringContainsString('Tom Brown', $output);
    }

    public function testGetLastSimsStartersIdentifiesStartersCorrectly()
    {
        $mockData = [
            ['pid' => 1, 'name' => 'Starter PG', 'PGDepth' => 1, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 2, 'name' => 'Backup PG', 'PGDepth' => 2, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 3, 'name' => 'Starter SG', 'PGDepth' => 0, 'SGDepth' => 1, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 4, 'name' => 'Starter SF', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 1, 'PFDepth' => 0, 'CDepth' => 0],
            ['pid' => 5, 'name' => 'Starter PF', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 1, 'CDepth' => 0],
            ['pid' => 6, 'name' => 'Starter C', 'PGDepth' => 0, 'SGDepth' => 0, 'SFDepth' => 0, 'PFDepth' => 0, 'CDepth' => 1]
        ];
        
        $this->db->setMockData($mockData);
        $this->db->setNumRows(6);
        
        $result = new MockDatabaseResult($mockData);
        $output = $this->service->getLastSimsStarters($result, $this->team);
        
        // Should only show starters, not backups
        $this->assertStringContainsString('Starter PG', $output);
        $this->assertStringNotContainsString('Backup PG', $output);
    }
}
