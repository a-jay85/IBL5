<?php

declare(strict_types=1);

namespace Tests\CareerLeaderboards;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use CareerLeaderboards\CareerLeaderboardsService;

final class CareerLeaderboardsServiceTest extends TestCase
{
    private CareerLeaderboardsService $service;

    protected function setUp(): void
    {
        $this->service = new CareerLeaderboardsService();
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
        $this->assertSame(123, $stats['pid']);
        $this->assertSame('Test Player', $stats['name']);

        // Check totals formatting (should use number_format)
        $this->assertEquals('82', $stats['games']);
        $this->assertSame('3,000', $stats['minutes']);
        $this->assertSame('500', $stats['fgm']);
        $this->assertSame('1,000', $stats['fga']);
        
        // Check defensive rebounds (reb - orb = 500 - 100 = 400)
        $this->assertSame('400', $stats['drb']);

        // Check percentages (0-1 range with 3 decimals)
        $this->assertSame('0.500', $stats['fgp']); // 500/1000
        $this->assertSame('0.800', $stats['ftp']); // 200/250
        $this->assertSame('0.375', $stats['tgp']); // 150/400
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
        $this->assertSame(456, $stats['pid']);
        $this->assertSame('Average Player*', $stats['name']); // Has asterisk for retired

        // Check averages formatting (should use 2 decimal places)
        $this->assertEquals('82', $stats['games']); // Games are rounded
        $this->assertSame('36.50', $stats['minutes']);
        $this->assertSame('6.10', $stats['fgm']);
        $this->assertSame('12.20', $stats['fga']);
        
        // Check defensive rebounds (reb - orb = 6.1 - 1.2 = 4.9)
        $this->assertSame('4.90', $stats['drb']);

        // Check percentages (pre-calculated in db, formatted with decimals)
        $this->assertSame('0.500', $stats['fgp']);
        $this->assertSame('0.800', $stats['ftp']);
        $this->assertSame('0.375', $stats['tgp']);
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
        $this->assertSame('0.000', $stats['fgp']);
        $this->assertSame('0.000', $stats['ftp']);
        $this->assertSame('0.000', $stats['tgp']);
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
        $this->assertSame('Retired Legend*', $stats['name']);
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function tableKeyProvider(): array
    {
        return [
            'regular totals' => ['regular', 'totals', 'ibl_hist'],
            'regular averages' => ['regular', 'averages', 'ibl_season_career_avgs'],
            'playoffs totals' => ['playoffs', 'totals', 'ibl_playoff_career_totals'],
            'playoffs averages' => ['playoffs', 'averages', 'ibl_playoff_career_avgs'],
            'heat totals' => ['heat', 'totals', 'ibl_heat_career_totals'],
            'heat averages' => ['heat', 'averages', 'ibl_heat_career_avgs'],
            'olympics totals' => ['olympics', 'totals', 'ibl_olympics_career_totals'],
            'olympics averages' => ['olympics', 'averages', 'ibl_olympics_career_avgs'],
            'rookie totals' => ['rookie', 'totals', 'ibl_rookie_career_totals'],
            'rookie averages falls back' => ['rookie', 'averages', 'ibl_rookie_career_totals'],
            'sophomore totals' => ['sophomore', 'totals', 'ibl_sophomore_career_totals'],
            'sophomore averages falls back' => ['sophomore', 'averages', 'ibl_sophomore_career_totals'],
            'allstar totals' => ['allstar', 'totals', 'ibl_allstar_career_totals'],
            'allstar averages' => ['allstar', 'averages', 'ibl_allstar_career_avgs'],
            'unknown phase falls back to regular' => ['bogus', 'totals', 'ibl_hist'],
            'unknown mode falls back to totals' => ['playoffs', 'bogus', 'ibl_playoff_career_totals'],
            'unknown both' => ['', '', 'ibl_hist'],
        ];
    }

    #[DataProvider('tableKeyProvider')]
    public function testResolveTableKey(string $phase, string $mode, string $expected): void
    {
        $this->assertSame($expected, $this->service->resolveTableKey($phase, $mode));
    }

    public function testResolvedTableKeysAreAllValidRepositoryTables(): void
    {
        $repo = new \ReflectionClass(\CareerLeaderboards\CareerLeaderboardsRepository::class);
        /** @var list<string> $valid */
        $valid = $repo->getConstant('VALID_TABLES');

        foreach (array_keys($this->service->getPhases()) as $phase) {
            foreach (['totals', 'averages'] as $mode) {
                $this->assertContains($this->service->resolveTableKey($phase, $mode), $valid);
            }
        }
    }

    public function testGetPhases(): void
    {
        $this->assertSame(
            ['regular', 'playoffs', 'heat', 'olympics', 'rookie', 'sophomore', 'allstar'],
            array_keys($this->service->getPhases())
        );
        $this->assertSame('Rookie Game', $this->service->getPhases()['rookie']);
        $this->assertSame('H.E.A.T.', $this->service->getPhases()['heat']);
    }

    public function testPhaseHasAverages(): void
    {
        $this->assertTrue($this->service->phaseHasAverages('regular'));
        $this->assertTrue($this->service->phaseHasAverages('allstar'));
        $this->assertFalse($this->service->phaseHasAverages('rookie'));
        $this->assertFalse($this->service->phaseHasAverages('sophomore'));
        $this->assertTrue($this->service->phaseHasAverages('bogus'));
    }

    public function testPhaseShowsGames(): void
    {
        $this->assertTrue($this->service->phaseShowsGames('regular'));
        $this->assertTrue($this->service->phaseShowsGames('allstar'));
        $this->assertFalse($this->service->phaseShowsGames('rookie'));
        $this->assertFalse($this->service->phaseShowsGames('sophomore'));
        $this->assertTrue($this->service->phaseShowsGames('bogus'));
    }

    public function testResolveMode(): void
    {
        $this->assertSame('averages', $this->service->resolveMode('regular', 'averages'));
        $this->assertSame('totals', $this->service->resolveMode('rookie', 'averages'));
        $this->assertSame('totals', $this->service->resolveMode('regular', 'bogus'));
    }

    public function testGetSortOptionsOrderAndLabelsOnTotals(): void
    {
        $options = $this->service->getSortOptions('totals');

        $this->assertSame(
            ['PPG', 'REB', 'OREB', 'DREB', 'AST', 'STL', 'BLK', 'TO', 'FOUL', 'FGM', 'FGA', 'FGP', 'FTM', 'FTA', 'FTP', 'TGM', 'TGA', 'TGP', 'GAMES', 'MIN'],
            array_keys($options)
        );
        $this->assertArrayNotHasKey('QA', $options);
        $this->assertSame('PTS', $options['PPG']);
        $this->assertSame('FG%', $options['FGP']);
        $this->assertSame('FT%', $options['FTP']);
        $this->assertSame('TG%', $options['TGP']);
    }

    public function testGetSortOptionsLabelsPpgOnAverages(): void
    {
        $this->assertSame('PPG', $this->service->getSortOptions('averages')['PPG']);
    }

    public function testIsSortAvailableAllowsPercentagesOnTotalsAndAverages(): void
    {
        foreach (['FGP', 'FTP', 'TGP'] as $key) {
            $this->assertTrue($this->service->isSortAvailable($key, 'totals'));
            $this->assertTrue($this->service->isSortAvailable($key, 'averages'));
        }
        $this->assertTrue($this->service->isSortAvailable('PPG', 'totals'));
        $this->assertFalse($this->service->isSortAvailable('QA', 'averages'));
        $this->assertFalse($this->service->isSortAvailable('QA', 'totals'));
    }

    public function testResolveSortKeyKeepsPercentagesOnTotalsAndFallsBackOnlyForUnknown(): void
    {
        foreach (['FGP', 'FTP', 'TGP'] as $key) {
            $this->assertSame($key, $this->service->resolveSortKey($key, 'totals'));
            $this->assertSame($key, $this->service->resolveSortKey($key, 'averages'));
        }
        $this->assertSame('PPG', $this->service->resolveSortKey('bogus', 'totals'));
    }

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function sortColumnProvider(): array
    {
        return [
            'PPG' => ['PPG', 'totals', 'pts'],
            'REB' => ['REB', 'totals', 'reb'],
            'OREB' => ['OREB', 'totals', 'orb'],
            'DREB' => ['DREB', 'totals', 'drb'],
            'AST' => ['AST', 'totals', 'ast'],
            'STL' => ['STL', 'totals', 'stl'],
            'BLK' => ['BLK', 'totals', 'blk'],
            'TO' => ['TO', 'totals', 'tvr'],
            'FOUL' => ['FOUL', 'totals', 'pf'],
            'FGM' => ['FGM', 'totals', 'fgm'],
            'FGA' => ['FGA', 'totals', 'fga'],
            'FGP averages' => ['FGP', 'averages', 'fgpct'],
            'FTM' => ['FTM', 'totals', 'ftm'],
            'FTA' => ['FTA', 'totals', 'fta'],
            'FTP averages' => ['FTP', 'averages', 'ftpct'],
            'TGM' => ['TGM', 'totals', 'tgm'],
            'TGA' => ['TGA', 'totals', 'tga'],
            'TGP averages' => ['TGP', 'averages', 'tpct'],
            'GAMES' => ['GAMES', 'totals', 'games'],
            'MIN' => ['MIN', 'totals', 'minutes'],
            'FGP totals uses derived fgpct' => ['FGP', 'totals', 'fgpct'],
            'TGP totals uses derived tpct' => ['TGP', 'totals', 'tpct'],
            'unknown falls back to PPG' => ['bogus', 'averages', 'pts'],
            'QA is not a career sort' => ['QA', 'averages', 'pts'],
        ];
    }

    #[DataProvider('sortColumnProvider')]
    public function testResolveSortColumn(string $key, string $mode, string $expected): void
    {
        $this->assertSame($expected, $this->service->resolveSortColumn($key, $mode));
    }

    public function testResolvedSortColumnsAreAllValidRepositoryColumns(): void
    {
        $repo = new \ReflectionClass(\CareerLeaderboards\CareerLeaderboardsRepository::class);
        /** @var list<string> $valid */
        $valid = $repo->getConstant('VALID_SORT_COLUMNS');

        foreach (['totals', 'averages'] as $mode) {
            foreach (array_keys($this->service->getSortOptions($mode)) as $key) {
                $this->assertContains($this->service->resolveSortColumn($key, $mode), $valid);
            }
        }
    }
}
