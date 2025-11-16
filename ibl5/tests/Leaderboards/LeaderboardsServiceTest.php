<?php

declare(strict_types=1);

use PHPUnit\Framework\TestCase;
use Leaderboards\LeaderboardsService;

final class LeaderboardsServiceTest extends TestCase
{
    private LeaderboardsService $service;

    protected function setUp(): void
    {
        $this->service = new LeaderboardsService();
    }

    public function testProcessPlayerRowWithTotals(): void
    {
        $row = [
            'pid' => 123,
            'name' => 'Test Player',
            'retired' => 0,
            'games' => 82,
            'minutes' => 3000,
            'fgm' => 500,
            'fga' => 1000,
            'ftm' => 200,
            'fta' => 250,
            'tgm' => 150,
            'tga' => 400,
            'orb' => 100,
            'reb' => 500,
            'ast' => 400,
            'stl' => 80,
            'tvr' => 150,
            'blk' => 50,
            'pf' => 200,
            'pts' => 1350
        ];

        $stats = $this->service->processPlayerRow($row, 'totals');

        // Check basic info
        $this->assertEquals(123, $stats['pid']);
        $this->assertEquals('Test Player', $stats['name']);

        // Check totals formatting (should use number_format)
        $this->assertEquals('82', $stats['games']);
        $this->assertEquals('3,000', $stats['minutes']);
        $this->assertEquals('500', $stats['fgm']);
        $this->assertEquals('1,000', $stats['fga']);
        
        // Check percentages (0-1 range with 3 decimals)
        $this->assertEquals('0.500', $stats['fgp']); // 500/1000
        $this->assertEquals('0.800', $stats['ftp']); // 200/250
        $this->assertEquals('0.375', $stats['tgp']); // 150/400
    }

    public function testProcessPlayerRowWithAverages(): void
    {
        $row = [
            'pid' => 456,
            'name' => 'Average Player',
            'retired' => 1,
            'games' => 82,
            'minutes' => 36.5,
            'fgm' => 6.1,
            'fga' => 12.2,
            'fgpct' => 0.500,
            'ftm' => 2.4,
            'fta' => 3.0,
            'ftpct' => 0.800,
            'tgm' => 1.8,
            'tga' => 4.9,
            'tpct' => 0.375,
            'orb' => 1.2,
            'reb' => 6.1,
            'ast' => 4.9,
            'stl' => 1.0,
            'tvr' => 1.8,
            'blk' => 0.6,
            'pf' => 2.4,
            'pts' => 16.5
        ];

        $stats = $this->service->processPlayerRow($row, 'averages');

        // Check basic info
        $this->assertEquals(456, $stats['pid']);
        $this->assertEquals('Average Player*', $stats['name']); // Has asterisk for retired

        // Check averages formatting (should use 2 decimal places)
        $this->assertEquals('82', $stats['games']); // Games are rounded
        $this->assertEquals('36.50', $stats['minutes']);
        $this->assertEquals('6.10', $stats['fgm']);
        $this->assertEquals('12.20', $stats['fga']);
        
        // Check percentages (pre-calculated in db, formatted with decimals)
        $this->assertEquals('0.500', $stats['fgp']);
        $this->assertEquals('0.800', $stats['ftp']);
        $this->assertEquals('0.375', $stats['tgp']);
    }

    public function testProcessPlayerRowHandlesZeroAttempts(): void
    {
        $row = [
            'pid' => 789,
            'name' => 'No Shots',
            'retired' => 0,
            'games' => 10,
            'minutes' => 100,
            'fgm' => 0,
            'fga' => 0, // Zero attempts
            'ftm' => 0,
            'fta' => 0,
            'tgm' => 0,
            'tga' => 0,
            'orb' => 10,
            'reb' => 20,
            'ast' => 5,
            'stl' => 2,
            'tvr' => 1,
            'blk' => 0,
            'pf' => 5,
            'pts' => 0
        ];

        $stats = $this->service->processPlayerRow($row, 'totals');

        // Check that percentages default to 0.000
        $this->assertEquals('0.000', $stats['fgp']);
        $this->assertEquals('0.000', $stats['ftp']);
        $this->assertEquals('0.000', $stats['tgp']);
    }

    public function testProcessPlayerRowMarksRetiredPlayers(): void
    {
        $row = [
            'pid' => 999,
            'name' => 'Retired Legend',
            'retired' => 1, // Retired
            'games' => 1000,
            'minutes' => 40000,
            'fgm' => 10000,
            'fga' => 20000,
            'ftm' => 5000,
            'fta' => 6000,
            'tgm' => 2000,
            'tga' => 6000,
            'orb' => 2000,
            'reb' => 10000,
            'ast' => 8000,
            'stl' => 1500,
            'tvr' => 2000,
            'blk' => 1000,
            'pf' => 3000,
            'pts' => 27000
        ];

        $stats = $this->service->processPlayerRow($row, 'totals');

        // Check that retired players have asterisk
        $this->assertEquals('Retired Legend*', $stats['name']);
    }

    public function testGetBoardTypes(): void
    {
        $boardTypes = $this->service->getBoardTypes();

        $this->assertIsArray($boardTypes);
        $this->assertCount(8, $boardTypes);
        $this->assertArrayHasKey('ibl_hist', $boardTypes);
        $this->assertEquals('Regular Season Totals', $boardTypes['ibl_hist']);
        $this->assertArrayHasKey('ibl_season_career_avgs', $boardTypes);
        $this->assertEquals('Regular Season Averages', $boardTypes['ibl_season_career_avgs']);
    }

    public function testGetSortCategories(): void
    {
        $sortCategories = $this->service->getSortCategories();

        $this->assertIsArray($sortCategories);
        $this->assertCount(19, $sortCategories);
        $this->assertArrayHasKey('pts', $sortCategories);
        $this->assertEquals('Points', $sortCategories['pts']);
        $this->assertArrayHasKey('fgpct', $sortCategories);
        $this->assertEquals('FG Percentage (avgs only)', $sortCategories['fgpct']);
    }
}
