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

    public function testGetTeamPowerDataReturnsData(): void
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

        $this->assertSame('Boston Celtics', $result['team_name']);
        $this->assertSame(50, $result['wins']);
    }

    public function testGetTeamPowerDataReturnsNullWhenNoResults(): void
    {
        $this->db->setMockData([]);
        $this->db->setNumRows(0);
        
        $result = $this->repository->getTeamPowerData('Nonexistent Team');
        
        $this->assertNull($result);
    }

    public function testGetRosterUnderContractExecutesQuery(): void
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

    public function testGetFreeAgencyRosterExecutesQuery(): void
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

    public function testGetHistoricalRosterExecutesQuery(): void
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

    // ── Season history (regular vs H.E.A.T.) ─────────────────────
    // Both methods delegate to a shared getSeasonHistory(teamName, gameType);
    // these characterize that game_type 1 maps to regular-season rows and
    // game_type 3 maps to H.E.A.T. rows, by routing on the resolved filter.

    public function testGetRegularSeasonHistoryReturnsRegularSeasonRows(): void
    {
        $regularRows = [
            ['year' => 2024, 'currentname' => 'Miami HEAT', 'namethatyear' => 'Miami HEAT', 'wins' => 50, 'losses' => 32],
            ['year' => 2023, 'currentname' => 'Miami HEAT', 'namethatyear' => 'Miami HEAT', 'wins' => 44, 'losses' => 38],
        ];
        $this->db->onQuery('game_type = 1', $regularRows);

        $result = $this->repository->getRegularSeasonHistory('Miami HEAT');

        $this->assertSame($regularRows, $result);
    }

    public function testGetHEATHistoryReturnsHeatRows(): void
    {
        $heatRows = [
            ['year' => 2024, 'currentname' => 'Miami HEAT', 'namethatyear' => 'Miami HEAT', 'wins' => 6, 'losses' => 1],
        ];
        $this->db->onQuery('game_type = 3', $heatRows);

        $result = $this->repository->getHEATHistory('Miami HEAT');

        $this->assertSame($heatRows, $result);
    }

    public function testSeasonHistoryMethodsRouteToDistinctGameTypes(): void
    {
        // Regression: the two methods must not collapse onto the same game_type.
        $regularRows = [
            ['year' => 2024, 'currentname' => 'Miami HEAT', 'namethatyear' => 'Miami HEAT', 'wins' => 50, 'losses' => 32],
        ];
        $heatRows = [
            ['year' => 2024, 'currentname' => 'Miami HEAT', 'namethatyear' => 'Miami HEAT', 'wins' => 6, 'losses' => 1],
        ];
        $this->db->onQuery('game_type = 1', $regularRows);
        $this->db->onQuery('game_type = 3', $heatRows);

        $this->assertSame($regularRows, $this->repository->getRegularSeasonHistory('Miami HEAT'));
        $this->assertSame($heatRows, $this->repository->getHEATHistory('Miami HEAT'));
    }
}
