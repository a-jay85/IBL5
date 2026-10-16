<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;

use Team\SplitStatsRepository;

#[Group('database')]
class SplitStatsRepositoryTest extends DatabaseTestCase
{
    private SplitStatsRepository $repo;
    private const TEST_TID = 1;
    private const TEST_PID = 200090201;
    private const PERIOD_PID_A = 200000201;
    private const PERIOD_PID_B = 200000202;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new SplitStatsRepository($this->db);
    }

    /**
     * Insert the player + box_scores + schedule data needed for split stats queries.
     * Creates a home game on 2098-01-15 (team 1 at home vs team 2).
     */
    private function insertSplitStatsGameData(): void
    {
        $this->insertTestPlayer(self::TEST_PID, 'SplitStats Test');

        // Player boxscore for a home game (home_teamid=1, teamid=1)
        // Date in January 2098 → season_year=2098, game_type=1
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', self::TEST_PID, 'SplitStats Test', 'PG', 2, self::TEST_TID, self::TEST_TID,
            minutes: 32, points2m: 6, points2a: 12, ftm: 4, fta: 5, points3m: 3, points3a: 7,
            orb: 2, drb: 5, ast: 6, stl: 2, tov: 3, blk: 1, pf: 2,
        );

        // Schedule row with scores (needed for wins/losses split)
        $this->insertRow('ibl_schedule', [
            'season_year' => 2098,
            'box_id' => 90001,
            'game_date' => '2098-01-15',
            'visitor_teamid' => 2,
            'visitor_score' => 85,
            'home_teamid' => self::TEST_TID,
            'home_score' => 104,
            'uuid' => 'split-sch-0000-0000-000000090001',
        ]);
    }

    /**
     * Insert two players on TEST_TID for period-stats queries (February 2098).
     * Player A: two in-range games (02-01, 02-03) plus one out-of-range game (02-10).
     * Player B: one in-range game (02-02) with zero attempts.
     */
    private function insertPeriodStatsGameData(): void
    {
        $this->insertTestPlayer(self::PERIOD_PID_A, 'Period Alpha', ['teamid' => self::TEST_TID, 'retired' => 0]);
        $this->insertTestPlayer(self::PERIOD_PID_B, 'Period Beta', ['teamid' => self::TEST_TID, 'retired' => 0]);

        foreach (['2098-02-01', '2098-02-03'] as $date) {
            $this->insertPlayerBoxscoreRow(
                $date, self::PERIOD_PID_A, 'Period Alpha', 'PG', 2, self::TEST_TID, self::TEST_TID,
                minutes: 30, points2m: 4, points2a: 10, ftm: 2, fta: 4, points3m: 1, points3a: 2,
                orb: 1, drb: 3, ast: 5, stl: 1, tov: 2, blk: 0, pf: 3,
            );
        }

        // Outside the queried range: must not influence the averages.
        $this->insertPlayerBoxscoreRow(
            '2098-02-10', self::PERIOD_PID_A, 'Period Alpha', 'PG', 2, self::TEST_TID, self::TEST_TID,
            minutes: 40, points2m: 4, points2a: 10, ftm: 2, fta: 4, points3m: 1, points3a: 2,
            orb: 1, drb: 3, ast: 5, stl: 1, tov: 2, blk: 0, pf: 3,
        );

        $this->insertPlayerBoxscoreRow(
            '2098-02-02', self::PERIOD_PID_B, 'Period Beta', 'SG', 2, self::TEST_TID, self::TEST_TID,
            minutes: 12, points2m: 0, points2a: 0, ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0,
        );
    }

    public function testGetSplitStatsHomeReturnsHomeGamesOnly(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'home');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                self::assertSame('SplitStats Test', $row['name']);
                self::assertSame(1, $row['games']);
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in home split');
    }

    public function testGetSplitStatsRoadReturnsEmptyForHomeOnlyData(): void
    {
        $this->insertSplitStatsGameData();

        // Player's teamid=1 = home_teamid, so 'road' split should not include this game
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'road');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in road split');
    }

    public function testGetSplitStatsWinsReturnsWonGamesOnly(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'wins');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in wins split (home_score=104 > visitor_score=85)');
    }

    public function testGetSplitStatsLossesReturnsEmptyWhenTeamWon(): void
    {
        $this->insertSplitStatsGameData();

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'losses');

        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in losses split');
    }

    public function testGetSplitStatsMonthFiltersCorrectly(): void
    {
        $this->insertSplitStatsGameData();

        // January = month 1 — should find our data
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'month_1');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear in January split');

        // March = month 3 — no data
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'month_3');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertFalse($found, 'Test player should NOT appear in March split');
    }

    public function testGetSplitStatsVsOpponentFiltersCorrectly(): void
    {
        $this->insertSplitStatsGameData();

        // vs Sharks (teamid=2) — game was against team 2
        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'vs_2');
        $found = false;
        foreach ($result as $row) {
            if ($row['pid'] === self::TEST_PID) {
                $found = true;
                break;
            }
        }
        self::assertTrue($found, 'Test player should appear vs teamid=2');
    }

    public function testGetValidSplitKeysReturnsExpectedKeys(): void
    {
        $keys = $this->repo->getValidSplitKeys();

        self::assertContains('home', $keys);
        self::assertContains('road', $keys);
        self::assertContains('wins', $keys);
        self::assertContains('losses', $keys);
        self::assertContains('month_1', $keys);
        self::assertContains('div_atlantic', $keys);
        self::assertContains('conf_eastern', $keys);
        self::assertContains('vs_1', $keys);
        self::assertContains('vs_28', $keys);
    }

    public function testGetSplitLabelReturnsCorrectLabels(): void
    {
        self::assertSame('Home', $this->repo->getSplitLabel('home'));
        self::assertSame('Road', $this->repo->getSplitLabel('road'));
        self::assertSame('Wins', $this->repo->getSplitLabel('wins'));
        self::assertSame('January', $this->repo->getSplitLabel('month_1'));
        self::assertSame('vs. Atlantic', $this->repo->getSplitLabel('div_atlantic'));
        self::assertSame('vs. Eastern', $this->repo->getSplitLabel('conf_eastern'));
    }

    public function testGetSplitLabelVsTeamHitsDatabase(): void
    {
        // vs_1 should look up team name for teamid=1
        $label = $this->repo->getSplitLabel('vs_1');

        self::assertStringStartsWith('vs. ', $label);
        // Should NOT be the fallback 'vs. Team #1'
        self::assertStringNotContainsString('Team #', $label);
    }

    /**
     * Two players of team 1, each with one 2098 home game against a different opponent.
     * Each opponent's standings row is rewritten to the given division and conference, then
     * read back so the fixture is proven rather than assumed.
     *
     * @return array{near: int, far: int} Test PIDs: near faced the first opponent, far the second
     */
    private function insertTwoOpponentGames(
        string $nearDivision,
        string $nearConference,
        string $farDivision,
        string $farConference,
    ): array {
        $nearPid = 200090211;
        $farPid = 200090212;
        $this->insertTestPlayer($nearPid, 'Near Opp');
        $this->insertTestPlayer($farPid, 'Far Opp');
        $this->insertPlayerBoxscoreRow('2098-01-15', $nearPid, 'Near Opp', 'PG', 2, self::TEST_TID, self::TEST_TID);
        $this->insertPlayerBoxscoreRow('2098-01-16', $farPid, 'Far Opp', 'PG', 3, self::TEST_TID, self::TEST_TID);

        $this->setStandingsGroup(2, $nearDivision, $nearConference);
        $this->setStandingsGroup(3, $farDivision, $farConference);

        return ['near' => $nearPid, 'far' => $farPid];
    }

    private function setStandingsGroup(int $teamid, string $division, string $conference): void
    {
        $stmt = $this->db->prepare('UPDATE `ibl_standings` SET division = ?, conference = ? WHERE teamid = ?');
        self::assertNotFalse($stmt);
        $stmt->bind_param('ssi', $division, $conference, $teamid);
        $stmt->execute();
        $stmt->close();

        $check = $this->db->prepare('SELECT COUNT(*) AS n FROM `ibl_standings` WHERE teamid = ? AND division = ? AND conference = ?');
        self::assertNotFalse($check);
        $check->bind_param('iss', $teamid, $division, $conference);
        $check->execute();
        $row = $check->get_result()->fetch_assoc();
        $check->close();
        self::assertIsArray($row);
        self::assertSame(1, $row['n'], 'Standings row for team ' . $teamid . ' must exist with the test grouping');
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @param list<int> $candidatePids
     * @return list<int>
     */
    private function pidsAmong(array $rows, array $candidatePids): array
    {
        $found = [];
        foreach ($rows as $row) {
            if (in_array($row['pid'], $candidatePids, true)) {
                $found[] = $row['pid'];
            }
        }
        sort($found);

        return $found;
    }

    public function testDivisionSplitReturnsOnlyGamesAgainstThatDivision(): void
    {
        $pids = $this->insertTwoOpponentGames('Atlantic', 'Eastern', 'Pacific', 'Western');

        $atlantic = $this->repo->getSplitStats(self::TEST_TID, 2098, 'div_atlantic');
        $pacific = $this->repo->getSplitStats(self::TEST_TID, 2098, 'div_pacific');
        $central = $this->repo->getSplitStats(self::TEST_TID, 2098, 'div_central');

        self::assertSame([$pids['near']], $this->pidsAmong($atlantic, [$pids['near'], $pids['far']]));
        self::assertSame([$pids['far']], $this->pidsAmong($pacific, [$pids['near'], $pids['far']]));
        self::assertSame([], $this->pidsAmong($central, [$pids['near'], $pids['far']]));

        // Independent count: fixture games whose opponent sits in Atlantic.
        $result = $this->db->query(
            "SELECT COUNT(DISTINCT bs.pid) AS n FROM `ibl_box_scores` bs
             JOIN `ibl_standings` s ON s.teamid = bs.visitor_teamid
             WHERE bs.pid IN (200090211, 200090212) AND s.division = 'Atlantic'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $expected = $result->fetch_assoc();
        self::assertIsArray($expected);
        self::assertSame(1, $expected['n']);
        self::assertCount($expected['n'], $this->pidsAmong($atlantic, [$pids['near'], $pids['far']]));
    }

    public function testConferenceSplitReturnsOnlyGamesAgainstThatConference(): void
    {
        $pids = $this->insertTwoOpponentGames('Atlantic', 'Eastern', 'Pacific', 'Western');

        $eastern = $this->repo->getSplitStats(self::TEST_TID, 2098, 'conf_eastern');
        $western = $this->repo->getSplitStats(self::TEST_TID, 2098, 'conf_western');

        self::assertSame([$pids['near']], $this->pidsAmong($eastern, [$pids['near'], $pids['far']]));
        self::assertSame([$pids['far']], $this->pidsAmong($western, [$pids['near'], $pids['far']]));

        // Independent count: fixture games whose opponent sits in the Western conference.
        $result = $this->db->query(
            "SELECT COUNT(DISTINCT bs.pid) AS n FROM `ibl_box_scores` bs
             JOIN `ibl_standings` s ON s.teamid = bs.visitor_teamid
             WHERE bs.pid IN (200090211, 200090212) AND s.conference = 'Western'"
        );
        self::assertInstanceOf(\mysqli_result::class, $result);
        $expected = $result->fetch_assoc();
        self::assertIsArray($expected);
        self::assertSame(1, $expected['n']);
        self::assertCount($expected['n'], $this->pidsAmong($western, [$pids['near'], $pids['far']]));
    }

    public function testGetSplitStatsHomeDnpRowExcluded(): void
    {
        // Insert a DNP (game_min=0) home boxscore row for a test player — must not appear in home split
        $dnpPid = 200090299;
        $this->insertTestPlayer($dnpPid, 'DNP Home Player');
        $this->insertPlayerBoxscoreRow(
            '2098-01-15', $dnpPid, 'DNP Home Player', 'PG', 2, self::TEST_TID, self::TEST_TID,
            minutes: 0, points2m: 0, points2a: 0, ftm: 0, fta: 0, points3m: 0, points3a: 0,
            orb: 0, drb: 0, ast: 0, stl: 0, tov: 0, blk: 0, pf: 0,
        );

        $result = $this->repo->getSplitStats(self::TEST_TID, 2098, 'home');

        foreach ($result as $row) {
            self::assertNotSame($dnpPid, $row['pid'], 'DNP row must not appear in home split stats');
        }
    }

    public function testGetPeriodStatsAggregatesTwoGamesForPlayerInRange(): void
    {
        $this->insertPeriodStatsGameData();

        $rows = $this->repo->getPeriodStats(self::TEST_TID, '2098-02-01', '2098-02-05');

        self::assertCount(2, $rows);
        self::assertSame('Period Alpha', $rows[0]['name']);
        self::assertSame('Period Beta', $rows[1]['name']);

        $row = $rows[0];
        self::assertSame(self::PERIOD_PID_A, $row['pid']);
        self::assertSame(2, $row['games']);
        self::assertSame('30.0', $row['gameMINavg']);
        self::assertSame('5.00', $row['gameFGMavg']);
        self::assertSame('12.00', $row['gameFGAavg']);
        self::assertSame('0.417', $row['gameFGPavg']);
        self::assertSame('0.500', $row['gameFTPavg']);
        self::assertSame('0.500', $row['game3GPavg']);
        self::assertSame('4.0', $row['gameREBavg']);
        self::assertSame('13.0', $row['gamePTSavg']);
    }

    public function testGetPeriodStatsReturnsNullPctWhenNoAttempts(): void
    {
        $this->insertPeriodStatsGameData();

        $rows = $this->repo->getPeriodStats(self::TEST_TID, '2098-02-01', '2098-02-05');

        $row = $rows[1];
        self::assertSame(self::PERIOD_PID_B, $row['pid']);
        self::assertSame(1, $row['games']);
        self::assertSame('12.0', $row['gameMINavg']);
        self::assertNull($row['gameFGPavg']);
        self::assertNull($row['gameFTPavg']);
        self::assertNull($row['game3GPavg']);
    }

    public function testGetPeriodStatsExcludesGamesOutsideDateRange(): void
    {
        $this->insertPeriodStatsGameData();

        $rows = $this->repo->getPeriodStats(self::TEST_TID, '2098-02-01', '2098-02-05');

        $row = $rows[0];
        self::assertSame(self::PERIOD_PID_A, $row['pid']);
        self::assertSame(2, $row['games']);
        self::assertSame('30.0', $row['gameMINavg']);
    }

    public function testGetPeriodStatsPidFilterRestrictsRows(): void
    {
        $this->insertPeriodStatsGameData();

        $rows = $this->repo->getPeriodStats(self::TEST_TID, '2098-02-01', '2098-02-05', [self::PERIOD_PID_B]);

        self::assertCount(1, $rows);
        self::assertSame(self::PERIOD_PID_B, $rows[0]['pid']);
    }
}
