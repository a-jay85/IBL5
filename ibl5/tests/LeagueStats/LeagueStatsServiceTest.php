<?php

declare(strict_types=1);

namespace Tests\LeagueStats;

use PHPUnit\Framework\TestCase;
use LeagueStats\LeagueStatsService;
use LeagueStats\Contracts\LeagueStatsServiceInterface;
use BasketballStats\StatsFormatter;

/**
 * Tests for LeagueStatsService
 *
 * Verifies team statistics processing, league totals calculation,
 * and offense/defense differential computation.
 */
class LeagueStatsServiceTest extends TestCase
{
    private LeagueStatsService $service;

    protected function setUp(): void
    {
        $this->service = new LeagueStatsService();
    }

    /**
     * Test that service implements the interface
     */
    public function testImplementsInterface(): void
    {
        $this->assertInstanceOf(LeagueStatsServiceInterface::class, $this->service);
    }

    // ========================================
    // processTeamStats() Tests
    // ========================================

    /**
     * Test processTeamStats returns array
     */
    public function testProcessTeamStatsReturnsArray(): void
    {
        $result = $this->service->processTeamStats([]);

        $this->assertIsArray($result);
    }

    /**
     * Test processTeamStats includes expected keys
     */
    public function testProcessTeamStatsIncludesExpectedKeys(): void
    {
        $rawStats = [$this->createRawTeamRow(1, 'Boston', 'Celtics')];

        $result = $this->service->processTeamStats($rawStats);

        $this->assertCount(1, $result);
        $team = $result[0];

        $this->assertArrayHasKey('teamid', $team);
        $this->assertArrayHasKey('team_city', $team);
        $this->assertArrayHasKey('team_name', $team);
        $this->assertArrayHasKey('color1', $team);
        $this->assertArrayHasKey('color2', $team);
        $this->assertArrayHasKey('offense_totals', $team);
        $this->assertArrayHasKey('offense_averages', $team);
        $this->assertArrayHasKey('defense_totals', $team);
        $this->assertArrayHasKey('defense_averages', $team);
        $this->assertArrayHasKey('raw_offense', $team);
        $this->assertArrayHasKey('raw_defense', $team);
    }

    /**
     * Test that points are calculated using StatsFormatter::calculatePoints()
     */
    public function testProcessTeamStatsCalculatesPointsCorrectly(): void
    {
        $rawStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => 10,
                'offense_fgm' => 100,  // 2 * 100 = 200
                'offense_fga' => 200,
                'offense_ftm' => 50,   // + 50
                'offense_fta' => 60,
                'offense_tgm' => 30,   // + 30 = 280 total
                'offense_tga' => 80,
                'offense_orb' => 40,
                'offense_reb' => 100,
                'offense_ast' => 60,
                'offense_stl' => 20,
                'offense_tvr' => 30,
                'offense_blk' => 15,
                'offense_pf' => 50,
                'defense_games' => 10,
                'defense_fgm' => 90,
                'defense_fga' => 190,
                'defense_ftm' => 45,
                'defense_fta' => 55,
                'defense_tgm' => 25,
                'defense_tga' => 75,
                'defense_orb' => 35,
                'defense_reb' => 95,
                'defense_ast' => 55,
                'defense_stl' => 18,
                'defense_tvr' => 28,
                'defense_blk' => 12,
                'defense_pf' => 48,
            ],
        ];

        $result = $this->service->processTeamStats($rawStats);
        $team = $result[0];

        // Verify points calculation: (2 * fgm) + ftm + tgm
        $expectedOffensePoints = StatsFormatter::calculatePoints(100, 50, 30);
        $this->assertEquals(280, $expectedOffensePoints);
        $this->assertEquals(280, $team['raw_offense']['pts']);

        $expectedDefensePoints = StatsFormatter::calculatePoints(90, 45, 25);
        $this->assertEquals(250, $expectedDefensePoints);
        $this->assertEquals(250, $team['raw_defense']['pts']);
    }

    /**
     * Test per-game averages are formatted correctly
     */
    public function testProcessTeamStatsFormatsAverages(): void
    {
        $rawStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => 10,
                'offense_fgm' => 355,  // 35.5 per game
                'offense_fga' => 750,
                'offense_ftm' => 200,
                'offense_fta' => 250,
                'offense_tgm' => 120,
                'offense_tga' => 350,
                'offense_orb' => 100,
                'offense_reb' => 400,
                'offense_ast' => 250,
                'offense_stl' => 80,
                'offense_tvr' => 140,
                'offense_blk' => 50,
                'offense_pf' => 200,
                'defense_games' => 10,
                'defense_fgm' => 340,
                'defense_fga' => 720,
                'defense_ftm' => 190,
                'defense_fta' => 240,
                'defense_tgm' => 110,
                'defense_tga' => 330,
                'defense_orb' => 90,
                'defense_reb' => 380,
                'defense_ast' => 240,
                'defense_stl' => 75,
                'defense_tvr' => 130,
                'defense_blk' => 45,
                'defense_pf' => 190,
            ],
        ];

        $result = $this->service->processTeamStats($rawStats);
        $averages = $result[0]['offense_averages'];

        // FGM per game: 355 / 10 = 35.5
        $this->assertEquals('35.5', $averages['fgm']);

        // FG%: 355 / 750 = 0.473
        $this->assertEquals('0.473', $averages['fgp']);
    }

    /**
     * Test zero games handled without division by zero
     */
    public function testProcessTeamStatsHandlesZeroGames(): void
    {
        $rawStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => 0,
                'offense_fgm' => 0,
                'offense_fga' => 0,
                'offense_ftm' => 0,
                'offense_fta' => 0,
                'offense_tgm' => 0,
                'offense_tga' => 0,
                'offense_orb' => 0,
                'offense_reb' => 0,
                'offense_ast' => 0,
                'offense_stl' => 0,
                'offense_tvr' => 0,
                'offense_blk' => 0,
                'offense_pf' => 0,
                'defense_games' => 0,
                'defense_fgm' => 0,
                'defense_fga' => 0,
                'defense_ftm' => 0,
                'defense_fta' => 0,
                'defense_tgm' => 0,
                'defense_tga' => 0,
                'defense_orb' => 0,
                'defense_reb' => 0,
                'defense_ast' => 0,
                'defense_stl' => 0,
                'defense_tvr' => 0,
                'defense_blk' => 0,
                'defense_pf' => 0,
            ],
        ];

        // Should not throw exception
        $result = $this->service->processTeamStats($rawStats);

        $this->assertCount(1, $result);
        $this->assertEquals('0.0', $result[0]['offense_averages']['fgm']);
        $this->assertEquals('0.000', $result[0]['offense_averages']['fgp']);
    }

    /**
     * Test null values are handled correctly
     */
    public function testProcessTeamStatsHandlesNullValues(): void
    {
        $rawStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => null,
                'offense_fgm' => null,
                'offense_fga' => null,
                'offense_ftm' => null,
                'offense_fta' => null,
                'offense_tgm' => null,
                'offense_tga' => null,
                'offense_orb' => null,
                'offense_reb' => null,
                'offense_ast' => null,
                'offense_stl' => null,
                'offense_tvr' => null,
                'offense_blk' => null,
                'offense_pf' => null,
                'defense_games' => null,
                'defense_fgm' => null,
                'defense_fga' => null,
                'defense_ftm' => null,
                'defense_fta' => null,
                'defense_tgm' => null,
                'defense_tga' => null,
                'defense_orb' => null,
                'defense_reb' => null,
                'defense_ast' => null,
                'defense_stl' => null,
                'defense_tvr' => null,
                'defense_blk' => null,
                'defense_pf' => null,
            ],
        ];

        $result = $this->service->processTeamStats($rawStats);

        $this->assertCount(1, $result);
        $this->assertEquals(0, $result[0]['raw_offense']['fgm']);
    }

    // ========================================
    // calculateLeagueTotals() Tests
    // ========================================

    /**
     * Test calculateLeagueTotals returns expected structure
     */
    public function testCalculateLeagueTotalsReturnsExpectedStructure(): void
    {
        $processedStats = [$this->createProcessedTeamData(1, 'Boston', 'Celtics')];

        $result = $this->service->calculateLeagueTotals($processedStats);

        $this->assertArrayHasKey('totals', $result);
        $this->assertArrayHasKey('averages', $result);
        $this->assertArrayHasKey('games', $result);
    }

    /**
     * Test league totals sums all team stats
     */
    public function testCalculateLeagueTotalsSumsAllTeams(): void
    {
        $processedStats = [
            [
                'teamid' => 1,
                'offense_games' => 10,
                'raw_offense' => ['fgm' => 100, 'fga' => 200, 'ftm' => 50, 'fta' => 60, 'tgm' => 30, 'tga' => 80, 'orb' => 40, 'reb' => 100, 'ast' => 60, 'stl' => 20, 'tvr' => 30, 'blk' => 15, 'pf' => 50, 'pts' => 280],
            ],
            [
                'teamid' => 2,
                'offense_games' => 10,
                'raw_offense' => ['fgm' => 120, 'fga' => 220, 'ftm' => 60, 'fta' => 70, 'tgm' => 40, 'tga' => 90, 'orb' => 45, 'reb' => 110, 'ast' => 70, 'stl' => 25, 'tvr' => 35, 'blk' => 18, 'pf' => 55, 'pts' => 340],
            ],
        ];

        $result = $this->service->calculateLeagueTotals($processedStats);

        // Total FGM should be 100 + 120 = 220
        $this->assertEquals('220', $result['totals']['fgm']);

        // Total games should be 20
        $this->assertEquals(20, $result['games']);

        // Average FGM should be 220 / 20 = 11.0
        $this->assertEquals('11.0', $result['averages']['fgm']);
    }

    /**
     * Test empty stats array
     */
    public function testCalculateLeagueTotalsWithEmptyArray(): void
    {
        $result = $this->service->calculateLeagueTotals([]);

        $this->assertEquals(0, $result['games']);
        $this->assertEquals('0', $result['totals']['fgm']);
        $this->assertEquals('0.0', $result['averages']['fgm']);
    }

    // ========================================
    // calculateDifferentials() Tests
    // ========================================

    /**
     * Test calculateDifferentials returns expected structure
     */
    public function testCalculateDifferentialsReturnsExpectedStructure(): void
    {
        $processedStats = [
            [
                'teamid' => 1,
                'team_city' => 'Boston',
                'team_name' => 'Celtics',
                'color1' => '#007A33',
                'color2' => '#FFFFFF',
                'offense_games' => 10,
                'defense_games' => 10,
                'raw_offense' => ['fgm' => 100, 'fga' => 200, 'ftm' => 50, 'fta' => 60, 'tgm' => 30, 'tga' => 80, 'orb' => 40, 'reb' => 100, 'ast' => 60, 'stl' => 20, 'tvr' => 30, 'blk' => 15, 'pf' => 50, 'pts' => 280],
                'raw_defense' => ['fgm' => 90, 'fga' => 180, 'ftm' => 45, 'fta' => 55, 'tgm' => 25, 'tga' => 70, 'orb' => 35, 'reb' => 90, 'ast' => 55, 'stl' => 18, 'tvr' => 28, 'blk' => 12, 'pf' => 45, 'pts' => 250],
            ],
        ];

        $result = $this->service->calculateDifferentials($processedStats);

        $this->assertCount(1, $result);
        $this->assertArrayHasKey('teamid', $result[0]);
        $this->assertArrayHasKey('team_city', $result[0]);
        $this->assertArrayHasKey('team_name', $result[0]);
        $this->assertArrayHasKey('differentials', $result[0]);
    }

    /**
     * Test differentials calculated correctly (offense - defense)
     */
    public function testCalculateDifferentialsCalculatesCorrectly(): void
    {
        $processedStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => 10,
                'defense_games' => 10,
                'raw_offense' => ['fgm' => 400, 'fga' => 800, 'ftm' => 200, 'fta' => 250, 'tgm' => 100, 'tga' => 300, 'orb' => 100, 'reb' => 400, 'ast' => 250, 'stl' => 80, 'tvr' => 120, 'blk' => 50, 'pf' => 200, 'pts' => 1100],
                'raw_defense' => ['fgm' => 350, 'fga' => 750, 'ftm' => 180, 'fta' => 230, 'tgm' => 90, 'tga' => 280, 'orb' => 90, 'reb' => 380, 'ast' => 230, 'stl' => 70, 'tvr' => 130, 'blk' => 45, 'pf' => 190, 'pts' => 970],
            ],
        ];

        $result = $this->service->calculateDifferentials($processedStats);
        $diffs = $result[0]['differentials'];

        // FGM per game: (400/10) - (350/10) = 40 - 35 = 5.00
        $this->assertEquals('5.00', $diffs['fgm']);

        // Points per game: (1100/10) - (970/10) = 110 - 97 = 13.00
        $this->assertEquals('13.00', $diffs['pts']);
    }

    /**
     * Test negative differentials (defense better than offense)
     */
    public function testCalculateDifferentialsNegativeValues(): void
    {
        $processedStats = [
            [
                'teamid' => 1,
                'team_city' => 'Test',
                'team_name' => 'Team',
                'color1' => '#000',
                'color2' => '#FFF',
                'offense_games' => 10,
                'defense_games' => 10,
                'raw_offense' => ['fgm' => 300, 'fga' => 700, 'ftm' => 150, 'fta' => 200, 'tgm' => 80, 'tga' => 250, 'orb' => 80, 'reb' => 350, 'ast' => 200, 'stl' => 60, 'tvr' => 150, 'blk' => 40, 'pf' => 180, 'pts' => 830],
                'raw_defense' => ['fgm' => 350, 'fga' => 750, 'ftm' => 180, 'fta' => 230, 'tgm' => 90, 'tga' => 280, 'orb' => 90, 'reb' => 380, 'ast' => 230, 'stl' => 70, 'tvr' => 130, 'blk' => 45, 'pf' => 190, 'pts' => 970],
            ],
        ];

        $result = $this->service->calculateDifferentials($processedStats);
        $diffs = $result[0]['differentials'];

        // FGM per game: (300/10) - (350/10) = 30 - 35 = -5.00
        $this->assertEquals('-5.00', $diffs['fgm']);
    }

    // ========================================
    // Helper Methods
    // ========================================

    /**
     * Create a raw team stats row for testing
     */
    private function createRawTeamRow(int $teamId, string $city, string $name): array
    {
        return [
            'teamid' => $teamId,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => '#007A33',
            'color2' => '#FFFFFF',
            'offense_games' => 82,
            'offense_fgm' => 3200,
            'offense_fga' => 7000,
            'offense_ftm' => 1500,
            'offense_fta' => 2000,
            'offense_tgm' => 1000,
            'offense_tga' => 2800,
            'offense_orb' => 900,
            'offense_reb' => 3600,
            'offense_ast' => 2000,
            'offense_stl' => 600,
            'offense_tvr' => 1200,
            'offense_blk' => 400,
            'offense_pf' => 1700,
            'defense_games' => 82,
            'defense_fgm' => 3100,
            'defense_fga' => 6900,
            'defense_ftm' => 1400,
            'defense_fta' => 1900,
            'defense_tgm' => 950,
            'defense_tga' => 2700,
            'defense_orb' => 850,
            'defense_reb' => 3500,
            'defense_ast' => 1900,
            'defense_stl' => 580,
            'defense_tvr' => 1250,
            'defense_blk' => 380,
            'defense_pf' => 1650,
        ];
    }

    /**
     * Create a processed team data structure for testing
     */
    private function createProcessedTeamData(int $teamId, string $city, string $name): array
    {
        return [
            'teamid' => $teamId,
            'team_city' => $city,
            'team_name' => $name,
            'color1' => '#007A33',
            'color2' => '#FFFFFF',
            'offense_games' => 82,
            'defense_games' => 82,
            'offense_totals' => [
                'games' => '82',
                'fgm' => '3,200',
                'fga' => '7,000',
                'ftm' => '1,500',
                'fta' => '2,000',
                'tgm' => '1,000',
                'tga' => '2,800',
                'orb' => '900',
                'reb' => '3,600',
                'ast' => '2,000',
                'stl' => '600',
                'tvr' => '1,200',
                'blk' => '400',
                'pf' => '1,700',
                'pts' => '8,900',
            ],
            'offense_averages' => [
                'fgm' => '39.0',
                'fga' => '85.4',
                'fgp' => '0.457',
                'ftm' => '18.3',
                'fta' => '24.4',
                'ftp' => '0.750',
                'tgm' => '12.2',
                'tga' => '34.1',
                'tgp' => '0.357',
                'orb' => '11.0',
                'reb' => '43.9',
                'ast' => '24.4',
                'stl' => '7.3',
                'tvr' => '14.6',
                'blk' => '4.9',
                'pf' => '20.7',
                'pts' => '108.5',
            ],
            'defense_totals' => [],
            'defense_averages' => [],
            'raw_offense' => [
                'fgm' => 3200,
                'fga' => 7000,
                'ftm' => 1500,
                'fta' => 2000,
                'tgm' => 1000,
                'tga' => 2800,
                'orb' => 900,
                'reb' => 3600,
                'ast' => 2000,
                'stl' => 600,
                'tvr' => 1200,
                'blk' => 400,
                'pf' => 1700,
                'pts' => 8900,
            ],
            'raw_defense' => [
                'fgm' => 3100,
                'fga' => 6900,
                'ftm' => 1400,
                'fta' => 1900,
                'tgm' => 950,
                'tga' => 2700,
                'orb' => 850,
                'reb' => 3500,
                'ast' => 1900,
                'stl' => 580,
                'tvr' => 1250,
                'blk' => 380,
                'pf' => 1650,
                'pts' => 8600,
            ],
        ];
    }
}
