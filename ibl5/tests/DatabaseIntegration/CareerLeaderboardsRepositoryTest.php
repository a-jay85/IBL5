<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use CareerLeaderboards\CareerLeaderboardsRepository;

/**
 * Database integration tests for CareerLeaderboardsRepository.
 *
 * Tests dual code paths: ibl_hist GROUP BY aggregation vs direct SELECT
 * from VIEW-backed tables. Dynamic SQL with whitelisted table/column names.
 */
#[Group('database')]
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

        self::assertSame(3, $result['count']);
        self::assertCount(3, $result['results']);
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

    public function testIblHistTiesResolveByPidAsc(): void
    {
        $pids = [200000035, 200000037, 200000036];
        foreach ($pids as $pid) {
            $this->insertTestPlayer($pid, "DB Tie $pid");
            $this->insertHistRow($pid, "DB Tie $pid", 2099, ['pts' => 777, 'games' => 50]);
        }

        $result = $this->repo->getLeaderboards('ibl_hist', 'pts', 0, 5000);
        $resultPids = [];
        foreach ($result['results'] as $row) {
            $p = (int) $row['pid'];
            if (in_array($p, $pids, true)) {
                $resultPids[] = $p;
            }
        }

        self::assertSame([200000035, 200000036, 200000037], $resultPids);
    }

    public function testPerSeasonTableTiesResolveByPidAsc(): void
    {
        $pids = [200000038, 200000040, 200000039];
        foreach ($pids as $pid) {
            $this->insertTestPlayer($pid, "DB PST $pid");
            $this->insertPlayerBoxscoreRow(
                '2099-01-15', $pid, "DB PST $pid", 'PG', 2, 1, 1,
                minutes: 30, points2m: 5, points2a: 10, ftm: 3, fta: 4,
                points3m: 2, points3a: 5
            );
        }

        $result = $this->repo->getLeaderboards('ibl_season_career_avgs', 'pts', 0, 5000);
        $resultPids = [];
        foreach ($result['results'] as $row) {
            $p = (int) $row['pid'];
            if (in_array($p, $pids, true)) {
                $resultPids[] = $p;
            }
        }

        self::assertSame([200000038, 200000039, 200000040], $resultPids);
    }

    public function testGetTableTypeReturnsCorrectType(): void
    {
        self::assertSame('totals', $this->repo->getTableType('ibl_hist'));
        self::assertSame('averages', $this->repo->getTableType('ibl_season_career_avgs'));
        self::assertSame('totals', $this->repo->getTableType('ibl_playoff_career_totals'));
        self::assertSame('averages', $this->repo->getTableType('ibl_playoff_career_avgs'));
    }

    // ----------------------------------------------------------------------
    // DNP (game_min > 0) guard on box-score-derived career views — maintenance-40c.
    // The CI seed has no game_min=0 rows, so each test self-seeds one played +
    // one DNP row. game_type is a STORED GENERATED column driven by the month of
    // game_date (Jan->1 season, Jun->2 playoff, Oct->3 HEAT); teamid drives the
    // All-Star/rookie/sophomore views. A DNP row must zero EVERY stat arg.
    // ----------------------------------------------------------------------

    /**
     * @param array<int, array<string, mixed>> $rows
     * @return array<string, mixed>|null
     */
    private function findRowByPid(array $rows, int $pid): ?array
    {
        foreach ($rows as $row) {
            if ((int) $row['pid'] === $pid) {
                return $row;
            }
        }

        return null;
    }

    public function testSeasonAvgsExcludesDnpRows(): void
    {
        $pid = 200000090;
        $this->insertTestPlayer($pid, 'DB DNP Season');

        // Played January game (game_type=1): 8 pts (2*4), 6 reb (2+4), 30 min.
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $pid, 'DB DNP Season', 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 8, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 2, drb: 4
        );
        // DNP January game: every stat zero.
        $this->insertPlayerBoxscoreRow(
            '2098-01-16', $pid, 'DB DNP Season', 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0,
            tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getLeaderboards('ibl_season_career_avgs', 'pts', 0, 5000);
        $row = $this->findRowByPid($result['results'], $pid);

        self::assertNotNull($row, 'Player with a played game should appear on the board');
        // FIXED (post-migration 149): DNP row excluded, so averages reflect only the
        // one played game. Pre-fix these were games 2, MIN 15.0, PTS 4.0, REB 3.0.
        self::assertSame(1, (int) $row['games']);
        self::assertEquals(30.00, $row['minutes']);
        self::assertEquals(8.00, $row['pts']);
        self::assertEquals(6.00, $row['reb']);
    }

    public function testPlayoffTotalsGamesExcludesDnpRows(): void
    {
        $pid = 200000091;
        $this->insertTestPlayer($pid, 'DB DNP Playoff');

        // Played June game (game_type=2): 8 pts.
        $this->insertPlayerBoxscoreRow(
            '2098-06-15', $pid, 'DB DNP Playoff', 'PG', 2, 1, 1,
            minutes: 30, points2m: 4, points2a: 8, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 2, drb: 4
        );
        // DNP June game: every stat zero.
        $this->insertPlayerBoxscoreRow(
            '2098-06-16', $pid, 'DB DNP Playoff', 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0,
            tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getLeaderboards('ibl_playoff_career_totals', 'pts', 0, 5000);
        $row = $this->findRowByPid($result['results'], $pid);

        self::assertNotNull($row);
        // FIXED (post-migration 149): DNP row excluded, games = 1 (was 2 pre-fix).
        // The SUM column (pts) is unchanged — DNP added 0 either way — proving the
        // totals fix is surgical: only games moves, the SUMs do not.
        self::assertSame(1, (int) $row['games']);
        self::assertSame(8, (int) $row['pts']);
    }

    public function testAllstarViewsExcludeDnpRows(): void
    {
        $pid = 200000092;
        $this->insertTestPlayer($pid, 'DB DNP Allstar');

        // teamid=50 drives the All-Star views regardless of date.
        $this->insertPlayerBoxscoreRow(
            '2098-02-15', $pid, 'DB DNP Allstar', 'PG', 2, 1, 50,
            minutes: 30, points2m: 4, points2a: 8, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 2, drb: 4
        );
        $this->insertPlayerBoxscoreRow(
            '2098-02-16', $pid, 'DB DNP Allstar', 'PG', 2, 1, 50,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0,
            tov: 0, blk: 0, pf: 0
        );

        // Averages view (COUNT/AVG family).
        $avgs = $this->repo->getLeaderboards('ibl_allstar_career_avgs', 'pts', 0, 5000);
        $avgRow = $this->findRowByPid($avgs['results'], $pid);
        self::assertNotNull($avgRow);
        self::assertSame(1, (int) $avgRow['games']);
        self::assertEquals(8.00, $avgRow['pts']);

        // Totals view (COUNT/SUM family) — same teamid WHERE shape.
        $totals = $this->repo->getLeaderboards('ibl_allstar_career_totals', 'pts', 0, 5000);
        $totRow = $this->findRowByPid($totals['results'], $pid);
        self::assertNotNull($totRow);
        self::assertSame(1, (int) $totRow['games']);
        self::assertSame(8, (int) $totRow['pts']);
    }

    public function testAllDnpPlayerAbsentFromLeaderboard(): void
    {
        $pid = 200000093;
        $this->insertTestPlayer($pid, 'DB All DNP');

        // Only DNP season rows: after the guard, all rows filtered out → no GROUP row.
        $this->insertPlayerBoxscoreRow(
            '2098-01-20', $pid, 'DB All DNP', 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0,
            tov: 0, blk: 0, pf: 0
        );
        $this->insertPlayerBoxscoreRow(
            '2098-01-21', $pid, 'DB All DNP', 'PG', 2, 1, 1,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 0, drb: 0, ast: 0, stl: 0,
            tov: 0, blk: 0, pf: 0
        );

        $result = $this->repo->getLeaderboards('ibl_season_career_avgs', 'pts', 0, 5000);
        $row = $this->findRowByPid($result['results'], $pid);

        // Genuine behavior change: a DNP-only player no longer appears at all
        // (pre-fix they showed games=2 with zeroed averages). No SQL/division error —
        // the CASE WHEN SUM(...) > 0 percentage guards protect against empty sums.
        self::assertNull($row, 'All-DNP player must be absent from the leaderboard');
    }

    public function testPlayedOnlyPlayerUnchanged(): void
    {
        $pid = 200000094;
        $this->insertTestPlayer($pid, 'DB Played Only');

        // Two played season games, no DNP rows: the guard is a no-op here.
        $this->insertPlayerBoxscoreRow(
            '2098-01-25', $pid, 'DB Played Only', 'PG', 2, 1, 1,
            minutes: 20, points2m: 3, points2a: 6, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 1, drb: 3
        );
        $this->insertPlayerBoxscoreRow(
            '2098-01-26', $pid, 'DB Played Only', 'PG', 2, 1, 1,
            minutes: 40, points2m: 5, points2a: 10, ftm: 0, fta: 0,
            points3m: 0, points3a: 0, orb: 3, drb: 5
        );

        $result = $this->repo->getLeaderboards('ibl_season_career_avgs', 'pts', 0, 5000);
        $row = $this->findRowByPid($result['results'], $pid);

        self::assertNotNull($row);
        // Both games counted; averages span the two played games unchanged.
        // pts: AVG(6, 10) = 8.0; reb: AVG(4, 8) = 6.0; min: AVG(20, 40) = 30.0.
        self::assertSame(2, (int) $row['games']);
        self::assertEquals(30.00, $row['minutes']);
        self::assertEquals(8.00, $row['pts']);
        self::assertEquals(6.00, $row['reb']);
    }
}
