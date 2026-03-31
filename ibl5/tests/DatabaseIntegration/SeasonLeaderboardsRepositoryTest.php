<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use SeasonLeaderboards\SeasonLeaderboardsRepository;

/**
 * Database integration tests for SeasonLeaderboardsRepository.
 *
 * Tests dynamic WHERE via QueryConditions, LEFT JOIN ibl_hist + team_info,
 * and whitelisted ORDER BY expressions.
 */
class SeasonLeaderboardsRepositoryTest extends DatabaseTestCase
{
    private SeasonLeaderboardsRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SeasonLeaderboardsRepository($this->db);
    }

    public function testGetSeasonLeadersReturnsInsertedRows(): void
    {
        $pid = 200000040;
        $this->insertTestPlayer($pid, 'DB SznLdr');
        $this->insertHistRow($pid, 'DB SznLdr', 2097);

        $result = $this->repo->getSeasonLeaders([], 0);

        self::assertGreaterThan(0, $result['count']);
        $found = false;
        foreach ($result['results'] as $row) {
            if ((int) $row['pid'] === $pid) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Inserted player should appear in season leaders');
    }

    public function testFiltersByYear(): void
    {
        $pid98 = 200000041;
        $pid97 = 200000042;
        $this->insertTestPlayer($pid98, 'DB Year98');
        $this->insertTestPlayer($pid97, 'DB Year97');
        $this->insertHistRow($pid98, 'DB Year98', 2098);
        $this->insertHistRow($pid97, 'DB Year97', 2097);

        $result = $this->repo->getSeasonLeaders(['year' => '2098'], 0);

        $pids = array_map(static fn (array $r): int => (int) $r['pid'], $result['results']);
        self::assertContains($pid98, $pids);
        self::assertNotContains($pid97, $pids);
    }

    public function testFiltersByTeam(): void
    {
        $pid1 = 200000043;
        $pid2 = 200000044;
        $this->insertTestPlayer($pid1, 'DB Team1', ['tid' => 1]);
        $this->insertTestPlayer($pid2, 'DB Team2', ['tid' => 2]);
        $this->insertHistRow($pid1, 'DB Team1', 2097, ['teamid' => 1]);
        $this->insertHistRow($pid2, 'DB Team2', 2097, ['teamid' => 2, 'team' => 'Sharks']);

        $result = $this->repo->getSeasonLeaders(['team' => 1], 0);

        $pids = array_map(static fn (array $r): int => (int) $r['pid'], $result['results']);
        self::assertContains($pid1, $pids);
        self::assertNotContains($pid2, $pids);
    }

    public function testSortByPpg(): void
    {
        $pidHigh = 200000045;
        $pidLow = 200000046;
        $this->insertTestPlayer($pidHigh, 'DB Hi PPG');
        $this->insertTestPlayer($pidLow, 'DB Lo PPG');

        // High PPG: pts=2000, games=50 → PPG=40.0
        $this->insertHistRow($pidHigh, 'DB Hi PPG', 2097, [
            'pts' => 2000, 'fgm' => 700, 'ftm' => 200, 'tgm' => 100, 'games' => 50,
        ]);
        // Low PPG: pts=500, games=50 → PPG=10.0
        $this->insertHistRow($pidLow, 'DB Lo PPG', 2097, [
            'pts' => 500, 'fgm' => 150, 'ftm' => 50, 'tgm' => 50, 'games' => 50,
        ]);

        $result = $this->repo->getSeasonLeaders(['sortby' => '1'], 0);
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
        self::assertLessThan($lowIdx, $highIdx, 'Higher PPG player should appear first');
    }

    public function testRespectsLimit(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $pid = 200000050 + $i;
            $this->insertTestPlayer($pid, sprintf('DB Lim%02d', $i));
            $this->insertHistRow($pid, sprintf('DB Lim%02d', $i), 2097);
        }

        $result = $this->repo->getSeasonLeaders([], 2);

        self::assertSame(2, $result['count']);
        self::assertCount(2, $result['results']);
    }

    public function testInvalidSortDefaultsToPpg(): void
    {
        $pid = 200000060;
        $this->insertTestPlayer($pid, 'DB DefSort');
        $this->insertHistRow($pid, 'DB DefSort', 2097);

        // 'invalid' should default to PPG without crashing
        $result = $this->repo->getSeasonLeaders(['sortby' => 'invalid'], 0);

        self::assertGreaterThan(0, $result['count']);
    }

    public function testGetTeamsReturnsOnlyRealTeams(): void
    {
        $result = $this->repo->getTeams();

        self::assertNotEmpty($result);
        foreach ($result as $row) {
            self::assertGreaterThanOrEqual(1, $row['TeamID']);
            self::assertLessThanOrEqual(League::MAX_REAL_TEAMID, $row['TeamID']);
        }
    }

    public function testGetSeasonLeadersReturnsEmptyForNoMatchingData(): void
    {
        $result = $this->repo->getSeasonLeaders(['year' => '8888'], 0);

        self::assertSame(0, $result['count']);
        self::assertSame([], $result['results']);
    }

    public function testGetYearsReturnsEmptyWhenNoHistData(): void
    {
        $this->db->query("DELETE FROM ibl_hist_archive");

        $years = $this->repo->getYears();

        self::assertSame([], $years);
    }

    public function testGetYearsReturnsDistinctDescending(): void
    {
        $pid = 200000061;
        $this->insertTestPlayer($pid, 'DB Years');
        $this->insertHistRow($pid, 'DB Years', 2096);
        $this->insertHistRow($pid, 'DB Years', 2097);
        $this->insertHistRow($pid, 'DB Years', 2098);

        $years = $this->repo->getYears();

        self::assertContains(2096, $years);
        self::assertContains(2097, $years);
        self::assertContains(2098, $years);

        // Verify descending order
        for ($i = 1; $i < count($years); $i++) {
            self::assertGreaterThan($years[$i], $years[$i - 1], 'Years should be in descending order');
        }
    }
}
