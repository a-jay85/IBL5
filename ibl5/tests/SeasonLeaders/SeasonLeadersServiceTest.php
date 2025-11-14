<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use SeasonLeaders\SeasonLeadersService;

final class SeasonLeadersServiceTest extends TestCase
{
    private SeasonLeadersService $service;

    protected function setUp(): void
    {
        $this->service = new SeasonLeadersService();
    }

    public function testProcessPlayerRowCalculatesCorrectly(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 50,
            'fga' => 100,
            'ftm' => 20,
            'fta' => 25,
            'tgm' => 15,
            'tga' => 40,
            'orb' => 30,
            'reb' => 80,
            'ast' => 40,
            'stl' => 10,
            'tvr' => 15,
            'blk' => 5,
            'pf' => 20
        ];

        $stats = $this->service->processPlayerRow($row);

        // Check basic info
        $this->assertEquals(123, $stats['pid']);
        $this->assertEquals('Test Player', $stats['name']);
        $this->assertEquals(2024, $stats['year']);

        // Check points calculation: 2*50 + 20 + 15 = 135
        $this->assertEquals(135, $stats['points']);

        // Check per-game averages
        $this->assertEquals('30.0', $stats['mpg']); // 300/10
        $this->assertEquals('5.0', $stats['fgmpg']); // 50/10
        $this->assertEquals('13.5', $stats['ppg']); // 135/10

        // Check percentages (0-1 range with 3 decimals)
        $this->assertEquals('0.500', $stats['fgp']); // 50/100
        $this->assertEquals('0.800', $stats['ftp']); // 20/25
        $this->assertEquals('0.375', $stats['tgp']); // 15/40
    }

    public function testProcessPlayerRowHandlesZeroGames(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 0,
            'minutes' => 0,
            'fgm' => 0,
            'fga' => 0,
            'ftm' => 0,
            'fta' => 0,
            'tgm' => 0,
            'tga' => 0,
            'orb' => 0,
            'reb' => 0,
            'ast' => 0,
            'stl' => 0,
            'tvr' => 0,
            'blk' => 0,
            'pf' => 0
        ];

        $stats = $this->service->processPlayerRow($row);

        // Check that per-game stats default to 0.0
        $this->assertEquals('0.0', $stats['mpg']);
        $this->assertEquals('0.0', $stats['ppg']);
        $this->assertEquals('0.0', $stats['qa']);
    }

    public function testProcessPlayerRowHandlesZeroAttempts(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 0,
            'fga' => 0, // Zero attempts
            'ftm' => 0,
            'fta' => 0,
            'tgm' => 0,
            'tga' => 0,
            'orb' => 0,
            'reb' => 0,
            'ast' => 0,
            'stl' => 0,
            'tvr' => 0,
            'blk' => 0,
            'pf' => 0
        ];

        $stats = $this->service->processPlayerRow($row);

        // Check that percentages default to 0.000
        $this->assertEquals('0.000', $stats['fgp']);
        $this->assertEquals('0.000', $stats['ftp']);
        $this->assertEquals('0.000', $stats['tgp']);
    }

    public function testQualityAssessmentCalculation(): void
    {
        // QA = (pts + reb + 2*ast + 2*stl + 2*blk - (fga-fgm) - (fta-ftm) - tvr - pf) / games
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'year' => 2024,
            'team' => 'Test Team',
            'teamid' => 1,
            'games' => 10,
            'minutes' => 300,
            'fgm' => 50, // 50 misses (100-50)
            'fga' => 100,
            'ftm' => 20, // 5 misses (25-20)
            'fta' => 25,
            'tgm' => 15,
            'tga' => 40,
            'orb' => 30,
            'reb' => 80,
            'ast' => 40, // *2 = 80
            'stl' => 10, // *2 = 20
            'tvr' => 15, // -15
            'blk' => 5,  // *2 = 10
            'pf' => 20   // -20
        ];

        $stats = $this->service->processPlayerRow($row);

        // Points = 2*50 + 20 + 15 = 135
        // Positives = 135 + 80 + 80 + 20 + 10 = 325
        // Negatives = 50 + 5 + 15 + 20 = 90
        // QA = (325 - 90) / 10 = 23.5
        $this->assertEquals('23.5', $stats['qa']);
    }

    public function testGetSortOptions(): void
    {
        $options = $this->service->getSortOptions();

        $this->assertCount(20, $options);
        $this->assertEquals('PPG', $options[0]);
        $this->assertEquals('REB', $options[1]);
        $this->assertEquals('MIN', $options[19]);
    }
}
