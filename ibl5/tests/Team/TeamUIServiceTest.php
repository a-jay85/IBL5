<?php

use PHPUnit\Framework\TestCase;
use Team\TeamUIService;
use Team\TeamRepository;

/**
 * Tests for TeamUIService
 * 
 * Validates UI rendering and display logic
 */
class TeamUIServiceTest extends TestCase
{
    private $db;
    private $repository;
    private $service;
    private $team;
    private $season;

    protected function setUp(): void
    {
        $this->db = new MockDatabase();
        $this->repository = new TeamRepository($this->db);
        $this->service = new TeamUIService($this->db, $this->repository);
        
        // Create mock team
        $teamRow = [
            'teamid' => 1,
            'team_city' => 'Boston',
            'team_name' => 'Celtics',
            'color1' => 'FF0000',
            'color2' => '0000FF',
            'arena' => 'TD Garden',
            'capacity' => 18000,
            'formerly_known_as' => '',
            'owner_name' => 'John Doe',
            'owner_email' => 'john@example.com',
            'discordID' => '123456',
            'Used_Extension_This_Chunk' => 0,
            'Used_Extension_This_Season' => 0,
            'HasMLE' => 1,
            'HasLLE' => 0,
            'leagueRecord' => '50-32'
        ];
        $this->team = Team::initialize($this->db, $teamRow);
        
        // Create mock season
        $this->season = new Season($this->db);
        $this->season->phase = 'Regular Season';
    }

    public function testRenderTabsContainsAllBasicTabs()
    {
        $tabs = $this->service->renderTabs(1, 'ratings', '', $this->season);
        
        $this->assertStringContainsString('Ratings</a>', $tabs);
        $this->assertStringContainsString('Season Totals</a>', $tabs);
        $this->assertStringContainsString('Season Averages</a>', $tabs);
        $this->assertStringContainsString('Per 36 Minutes</a>', $tabs);
        $this->assertStringContainsString('Sim Averages</a>', $tabs);
    }

    public function testRenderTabsExcludesPlayoffTabDuringRegularSeason()
    {
        $this->season->phase = 'Regular Season';
        $tabs = $this->service->renderTabs(1, 'ratings', '', $this->season);
        
        $this->assertStringNotContainsString('Playoffs Averages</a>', $tabs);
    }

    public function testAddPlayoffTabDuringOffseasonPhases()
    {
        // Test that playoff tab appears during all offseason phases
        $offseasonPhases = ['Playoffs', 'Draft', 'Free Agency'];
        
        foreach ($offseasonPhases as $phase) {
            $this->season->phase = $phase;
            $tabs = $this->service->renderTabs(1, 'playoffs', '', $this->season);
            
            $this->assertStringContainsString('Playoffs Averages</a>', $tabs, "Failed for phase: $phase");
        }
    }

    public function testRenderTeamInfoRightReturnsArray()
    {
        // Mock the necessary data
        $this->db->setMockData([
            ['Team' => 'Celtics', 'win' => 50, 'loss' => 32, 'Division' => 'Atlantic', 'Conference' => 'Eastern']
        ]);
        $this->db->setNumRows(1);
        
        $result = $this->service->renderTeamInfoRight($this->team);
        
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertStringContainsString('<table', $result[0]); // Main content
    }
}
