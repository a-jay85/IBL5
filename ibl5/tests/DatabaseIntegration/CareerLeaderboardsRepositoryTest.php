<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use CareerLeaderboards\CareerLeaderboardsRepository;

/**
 * Database integration tests for CareerLeaderboardsRepository.
 *
 * Tests dual code paths: ibl_hist GROUP BY aggregation vs direct SELECT
 * from VIEW-backed tables. Dynamic SQL with whitelisted table/column names.
 */
class CareerLeaderboardsRepositoryTest extends DatabaseTestCase
{
    private CareerLeaderboardsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new CareerLeaderboardsRepository($this->db);
    }

    public function testThrowsForInvalidTable(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->getLeaderboards('invalid_table', 'pts', 0, 10);
    }

    public function testThrowsForInvalidSortColumn(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->repo->getLeaderboards('ibl_hist', 'invalid_col', 0, 10);
    }

    public function testIblHistAggregatesByPlayer(): void
    {
        $pid = 200000010;
        $this->insertTestPlayer($pid, 'DB Hist Agg');

        // Insert 2 hist rows for same pid with known pts values
        $this->insertHistRow($pid, 'DB Hist Agg', 2096, ['pts' => 500, 'games' => 40]);
        $this->insertHistRow($pid, 'DB Hist Agg', 2097, ['pts' => 600, 'games' => 50]);

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 0);
        $rows = $result['results'];

        $found = null;
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = $row;
                break;
            }
        }

        self::assertNotNull($found, 'Player should appear in aggregated results');
        // SUM across 2 seasons
        self::assertSame('1100', (string) $found['pts']);
        self::assertSame('90', (string) $found['games']);
    }

    public function testIblHistSumsPointsCorrectly(): void
    {
        $pid = 200000011;
        $this->insertTestPlayer($pid, 'DB Pts Sum');

        $this->insertHistRow($pid, 'DB Pts Sum', 2097, ['pts' => 800]);

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 5000);
        $rows = $result['results'];

        $found = null;
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = $row;
                break;
            }
        }
        self::assertNotNull($found);
        self::assertSame('800', (string) $found['pts']);
    }

    public function testActiveOnlyFiltersRetiredPlayers(): void
    {
        $pid = 200000012;
        $this->insertTestPlayer($pid, 'DB Retired', ['retired' => 1]);
        $this->insertHistRow($pid, 'DB Retired', 2097, ['pts' => 999]);

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 1, 5000);
        $rows = $result['results'];

        $found = false;
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Retired player should be excluded with activeOnly=1');
    }

    public function testActiveOnlyZeroIncludesRetired(): void
    {
        $pid = 200000013;
        $this->insertTestPlayer($pid, 'DB RetInc', ['retired' => 1]);
        $this->insertHistRow($pid, 'DB RetInc', 2097, ['pts' => 888]);

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 5000);
        $rows = $result['results'];

        $found = false;
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Retired player should be included with activeOnly=0');
    }

    public function testNonHistPathQueriesViewTable(): void
    {
        $pid = 200000014;
        $this->insertTestPlayer($pid, 'DB View Plr');

        // Insert regular-season boxscore (Jan date = game_type 1 → ibl_season_career_avgs)
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $pid, 'DB View Plr', 'PG', 2, 1, 1,
            minutes: 30, points2m: 5, points2a: 10, ftm: 3, fta: 4,
            points3m: 2, points3a: 5
        );

        $result = $this->repo->getLeaderboards('ibl_season_career_avgs', 'pts', 0, 100);
        $rows = $result['results'];

        $found = false;
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Player should appear in season_career_avgs VIEW');
    }

    public function testLimitIsRespected(): void
    {
        // Insert 5 players with hist rows
        for ($i = 0; $i < 5; $i++) {
            $pid = 200000020 + $i;
            $this->insertTestPlayer($pid, sprintf('DB Limit %02d', $i));
            $this->insertHistRow($pid, sprintf('DB Limit %02d', $i), 2097, [
                'pts' => 100 + ($i * 100),
            ]);
        }

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 3);

        self::assertLessThanOrEqual(3, $result['count']);
    }

    public function testOrdersDescBySortColumn(): void
    {
        $pidHigh = 200000030;
        $pidLow = 200000031;
        $this->insertTestPlayer($pidHigh, 'DB High Pts');
        $this->insertTestPlayer($pidLow, 'DB Low Pts');

        $this->insertHistRow($pidHigh, 'DB High Pts', 2097, ['pts' => 9000]);
        $this->insertHistRow($pidLow, 'DB Low Pts', 2097, ['pts' => 100]);

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 5000);
        $rows = $result['results'];

        $highIdx = null;
        $lowIdx = null;
        foreach ($rows as $idx => $row) {
            if ((int) $row['pid'] === $pidHigh) {
                $highIdx = $idx;
            }
            if ((int) $row['pid'] === $pidLow) {
                $lowIdx = $idx;
            }
        }
        self::assertNotNull($highIdx);
        self::assertNotNull($lowIdx);
        self::assertLessThan($lowIdx, $highIdx, 'Higher pts player should appear first');
    }

    public function testGetTableTypeReturnsCorrectType(): void
    {
        self::assertSame('totals', $this->repo->getTableType('ibl_hist'));
        self::assertSame('averages', $this->repo->getTableType('ibl_season_career_avgs'));
        self::assertSame('totals', $this->repo->getTableType('ibl_playoff_career_totals'));
        self::assertSame('averages', $this->repo->getTableType('ibl_playoff_career_avgs'));
    }
}
