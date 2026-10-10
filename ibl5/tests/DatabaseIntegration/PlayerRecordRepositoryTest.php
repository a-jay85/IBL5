<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use RecordHolders\PlayerRecordRepository;

/**
 * Direct tests for PlayerRecordRepository against the real schema.
 *
 * Isolation: pid bands 2000920xx (all-star), 2000921xx (quadruple doubles),
 * 2000922xx (single-game batch) and 2000923xx (season averages), dates in 2098,
 * and planted values far above anything in the seed. The ibl_hist team string
 * ('RhHist-<pid>') differs from ibl_team_info.team_name so a query that reads
 * the wrong table fails.
 */
#[Group('database')]
final class PlayerRecordRepositoryTest extends DatabaseTestCase
{
    private const MARCH_FILTER = "bs.game_date BETWEEN '2098-03-01' AND '2098-03-28'";

    private PlayerRecordRepository $repo;

    /** @var array<int, string> */
    private array $names = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new PlayerRecordRepository($this->db);

        $result = $this->db->query('SELECT teamid, team_name FROM ibl_team_info WHERE teamid IN (1, 2)');
        self::assertInstanceOf(\mysqli_result::class, $result);
        while ($row = $result->fetch_assoc()) {
            $this->names[(int) $row['teamid']] = (string) $row['team_name'];
        }
        self::assertCount(2, $this->names);
    }

    // --- getQuadrupleDoubles ---

    public function testGetQuadrupleDoublesReturnsExactQualifyingRowsOrderedByDate(): void
    {
        // (a) is inserted before (b) so only ORDER BY game_date fixes the order.
        $this->seedPlayerGame('2098-01-12', 200092102, 'RhQd Two', 2, 1, 2, [
            'points2m' => 6, 'ftm' => 0, 'points3m' => 0, 'orb' => 2, 'drb' => 10, 'ast' => 5, 'stl' => 11, 'blk' => 10,
        ]);
        $this->seedPlayerGame('2098-01-10', 200092101, 'RhQd One', 2, 1, 1, [
            'points2m' => 5, 'ftm' => 0, 'points3m' => 0, 'orb' => 1, 'drb' => 9, 'ast' => 10, 'stl' => 10, 'blk' => 1,
        ]);
        $this->insertScheduleRow(2098, '2098-01-10', 2, 0, 1, 0, 780);
        $this->insertTeamBoxscoreRow('2098-01-10', $this->names[2], 7, 2, 1);

        self::assertSame(
            [
                [
                    'pid' => 200092101,
                    'name' => 'RhQd One',
                    'teamid' => 1,
                    'team_name' => 'RhHist-200092101',
                    'date' => '2098-01-10',
                    'box_id' => 780,
                    'game_of_that_day' => 7,
                    'oppTid' => 2,
                    'opp_team_name' => $this->names[2],
                    'points' => 10,
                    'rebounds' => 10,
                    'assists' => 10,
                    'steals' => 10,
                    'blocks' => 1,
                ],
                [
                    'pid' => 200092102,
                    'name' => 'RhQd Two',
                    'teamid' => 2,
                    'team_name' => 'RhHist-200092102',
                    'date' => '2098-01-12',
                    'box_id' => 0,
                    'game_of_that_day' => 0,
                    'oppTid' => 1,
                    'opp_team_name' => $this->names[1],
                    'points' => 12,
                    'rebounds' => 12,
                    'assists' => 5,
                    'steals' => 11,
                    'blocks' => 10,
                ],
            ],
            $this->quadrupleDoublesInBand(),
        );
    }

    public function testGetQuadrupleDoublesExcludesNearMissesAndNonRealTeams(): void
    {
        // Three categories at 10: not a quadruple double.
        $this->seedPlayerGame('2098-01-11', 200092111, 'RhQd Near1', 2, 1, 1, [
            'points2m' => 5, 'ftm' => 0, 'points3m' => 0, 'orb' => 1, 'drb' => 9, 'ast' => 10, 'stl' => 2, 'blk' => 1,
        ]);
        // Fourth category sits one short at 9.
        $this->seedPlayerGame('2098-01-13', 200092112, 'RhQd Near2', 2, 1, 1, [
            'points2m' => 5, 'ftm' => 0, 'points3m' => 0, 'orb' => 1, 'drb' => 9, 'ast' => 10, 'stl' => 9, 'blk' => 1,
        ]);
        // A qualifying line against the Free Agents team (tid 0) is out of scope.
        $this->seedPlayerGame('2098-01-14', 200092113, 'RhQd FreeAgent', 0, 1, 1, [
            'points2m' => 5, 'ftm' => 0, 'points3m' => 0, 'orb' => 1, 'drb' => 9, 'ast' => 10, 'stl' => 10, 'blk' => 1,
        ]);

        self::assertSame([], $this->quadrupleDoublesInBand());
    }

    // --- getMostAllStarAppearances ---

    public function testGetMostAllStarAppearancesReturnsTopFiveExactRows(): void
    {
        $this->seedAllStar('RhZz Alpha', 200092001, 14, 'Eastern Conference All-Star', [2097, 2098]);
        $this->seedAllStar('RhZz Beta', 200092002, 13, 'Eastern Conference All-Star', [2098]);
        $this->seedAllStar('RhZz Gamma', 200092003, 12, 'Western Conference All-Star', [2098]);
        $this->seedAllStar('RhZz Delta', 200092004, 12, 'Eastern Conference All-Star', [2098]);
        $this->seedAllStar('RhZz Epsilon', null, 11, 'Eastern Conference All-Star', []);
        $this->seedAllStar('RhZz Zeta', 200092006, 10, 'Eastern Conference All-Star', [2098]);
        $this->seedAllStar('RhZz Eta', null, 15, 'All-Star Game MVP', []);

        self::assertSame(
            [
                ['name' => 'RhZz Alpha', 'pid' => 200092001, 'appearances' => 14],
                ['name' => 'RhZz Beta', 'pid' => 200092002, 'appearances' => 13],
                ['name' => 'RhZz Delta', 'pid' => 200092004, 'appearances' => 12],
                ['name' => 'RhZz Gamma', 'pid' => 200092003, 'appearances' => 12],
                ['name' => 'RhZz Epsilon', 'pid' => null, 'appearances' => 11],
            ],
            $this->repo->getMostAllStarAppearances(),
        );
    }

    // --- getTopPlayerSingleGameBatch ---

    public function testGetTopPlayerSingleGameBatchRanksEachLabelAndAppliesLimitFive(): void
    {
        $this->seedSingleGames();

        $result = $this->repo->getTopPlayerSingleGameBatch(
            ['Points' => 'bs.calc_points', 'Assists' => 'bs.game_ast'],
            self::MARCH_FILTER,
        );

        self::assertSame(['Points', 'Assists'], array_keys($result));
        self::assertSame($this->expectedPointRows(), $result['Points']);
        self::assertSame(
            [
                $this->singleRow(200092206, 'RhSg P6', 1, '2098-03-07', 0, 0, 2, 30),
                $this->singleRow(200092204, 'RhSg P4', 1, '2098-03-05', 0, 0, 2, 20),
                $this->singleRow(200092202, 'RhSg P2', 2, '2098-03-03', 781, 7, 1, 12),
                $this->singleRow(200092203, 'RhSg P3', 1, '2098-03-04', 0, 0, 2, 12),
                $this->singleRow(200092201, 'RhSg P1', 1, '2098-03-02', 0, 0, 2, 4),
            ],
            $result['Assists'],
        );
    }

    public function testGetTopPlayerSingleGameBatchKeepsLabelWithApostrophe(): void
    {
        $this->seedSingleGames();

        $result = $this->repo->getTopPlayerSingleGameBatch(
            ["Player's Points" => 'bs.calc_points'],
            self::MARCH_FILTER,
        );

        self::assertSame(["Player's Points"], array_keys($result));
        self::assertSame($this->expectedPointRows(), $result["Player's Points"]);
    }

    public function testGetTopPlayerSingleGameBatchEmptyInputAndNoMatchReturnEmpty(): void
    {
        self::assertSame([], $this->repo->getTopPlayerSingleGameBatch([], '1=1'));

        self::assertSame(
            ['Points' => []],
            $this->repo->getTopPlayerSingleGameBatch(
                ['Points' => 'bs.calc_points'],
                "bs.game_date BETWEEN '2097-03-01' AND '2097-03-28'",
            ),
        );
    }

    // --- getTopSeasonAverageBatch ---

    public function testGetTopSeasonAverageBatchRanksEachLabelAndExcludesIneligibleRows(): void
    {
        $this->seedSeasonAverages();

        $result = $this->repo->getTopSeasonAverageBatch([
            'PPG' => ['statColumn' => 'pts', 'gamesColumn' => 'games'],
            'APG' => ['statColumn' => 'ast', 'gamesColumn' => 'games'],
        ]);

        self::assertSame(['PPG', 'APG'], array_keys($result));
        self::assertSame($this->expectedPpgRows(), $result['PPG']);
        self::assertSame(
            [
                $this->seasonRow(200092302, '70.0'),
                $this->seasonRow(200092305, '65.0'),
                $this->seasonRow(200092301, '60.0'),
                $this->seasonRow(200092303, '50.0'),
                $this->seasonRow(200092306, '40.0'),
            ],
            $result['APG'],
        );
    }

    public function testGetTopSeasonAverageBatchMinGamesBoundaryIsInclusive(): void
    {
        $this->seedSeasonAverages();
        $ppg = ['PPG' => ['statColumn' => 'pts', 'gamesColumn' => 'games']];

        self::assertSame(
            ['PPG' => [$this->seasonRow(200092302, '350.0')]],
            $this->repo->getTopSeasonAverageBatch($ppg, 60),
        );
        self::assertSame(['PPG' => []], $this->repo->getTopSeasonAverageBatch($ppg, 61));
    }

    public function testGetTopSeasonAverageBatchStripsNonIdentifierCharacters(): void
    {
        $this->seedSeasonAverages();

        $result = $this->repo->getTopSeasonAverageBatch([
            'PPG' => ['statColumn' => 'pts;', 'gamesColumn' => 'ga mes'],
        ]);

        self::assertSame(['PPG' => $this->expectedPpgRows()], $result);
    }

    public function testGetTopSeasonAverageBatchSkipsEmptyColumnNames(): void
    {
        $this->seedSeasonAverages();
        $bad = ['statColumn' => '', 'gamesColumn' => 'games'];

        self::assertSame([], $this->repo->getTopSeasonAverageBatch(['Bad' => $bad]));
        self::assertSame(
            ['Bad' => [], 'PPG' => $this->expectedPpgRows()],
            $this->repo->getTopSeasonAverageBatch([
                'Bad' => $bad,
                'PPG' => ['statColumn' => 'pts', 'gamesColumn' => 'games'],
            ]),
        );
    }

    public function testGetTopSeasonAverageBatchEmptyInputReturnsEmpty(): void
    {
        self::assertSame([], $this->repo->getTopSeasonAverageBatch([]));
    }

    // --- seed scenarios ---

    /**
     * @return list<array<string, mixed>>
     */
    private function quadrupleDoublesInBand(): array
    {
        return array_values(array_filter(
            $this->repo->getQuadrupleDoubles(),
            static fn (array $row): bool => $row['pid'] >= 200092100 && $row['pid'] <= 200092199,
        ));
    }

    /**
     * Seed an ibl_plr row, one ibl_hist row per year, and $count awards starting at 2060.
     * A null $pid seeds awards only (no player, no history).
     *
     * @param list<int> $histYears
     */
    private function seedAllStar(string $name, ?int $pid, int $count, string $award, array $histYears): void
    {
        if ($pid !== null) {
            $this->insertTestPlayer($pid, $name);
            foreach ($histYears as $year) {
                $this->insertHistRow($pid, $name, $year, ['team' => 'RhHist-' . $pid]);
            }
        }

        for ($i = 0; $i < $count; $i++) {
            $this->insertAwardRow($name, $award, 2060 + $i);
        }
    }

    /**
     * Seven March 2098 games, visitor 2 at home 1. P7 has no ibl_hist row.
     */
    private function seedSingleGames(): void
    {
        $this->seedPlayerGame('2098-03-02', 200092201, 'RhSg P1', 2, 1, 1, ['points2m' => 30, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 4]);
        $this->seedPlayerGame('2098-03-03', 200092202, 'RhSg P2', 2, 1, 2, ['points2m' => 40, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 12]);
        $this->seedPlayerGame('2098-03-04', 200092203, 'RhSg P3', 2, 1, 1, ['points2m' => 40, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 12]);
        $this->seedPlayerGame('2098-03-05', 200092204, 'RhSg P4', 2, 1, 1, ['points2m' => 25, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 20]);
        $this->seedPlayerGame('2098-03-06', 200092205, 'RhSg P5', 2, 1, 2, ['points2m' => 35, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 1]);
        $this->seedPlayerGame('2098-03-07', 200092206, 'RhSg P6', 2, 1, 1, ['points2m' => 10, 'ftm' => 0, 'points3m' => 0, 'orb' => 0, 'ast' => 30]);

        $this->insertTestPlayer(200092207, 'RhSg P7', ['teamid' => 1]);
        $this->insertPlayerBoxscoreRow(
            '2098-03-08', 200092207, 'RhSg P7', 'PG', 2, 1, 1,
            points2m: 100, ftm: 0, points3m: 0, orb: 0, ast: 100,
            overrides: ['uuid' => sprintf('rh-pl-0000-0000-%012d', 200092207)],
        );

        $this->insertScheduleRow(2098, '2098-03-03', 2, 0, 1, 0, 781);
        $this->insertTeamBoxscoreRow('2098-03-03', $this->names[2], 7, 2, 1);
    }

    /**
     * Eight 2097 ibl_hist rows; S7 is one game short and S8 is on the Free Agents team.
     */
    private function seedSeasonAverages(): void
    {
        $rows = [
            [200092301, 1, 50, 20000, 3000],
            [200092302, 1, 60, 21000, 4200],
            [200092303, 1, 50, 17000, 2500],
            [200092304, 1, 50, 16000, 1500],
            [200092305, 1, 50, 15000, 3250],
            [200092306, 1, 50, 14000, 2000],
            [200092307, 1, 49, 40000, 90000],
            [200092308, 0, 50, 50000, 90000],
        ];

        foreach ($rows as [$pid, $tid, $games, $pts, $ast]) {
            $this->insertTestPlayer($pid, 'RhSeason ' . $pid);
            $this->insertHistRow($pid, 'RhSeason ' . $pid, 2097, [
                'teamid' => $tid,
                'team' => 'RhSeason-' . $pid,
                'games' => $games,
                'pts' => $pts,
                'ast' => $ast,
            ]);
        }
    }

    // --- expected-row builders ---

    /**
     * @return list<array<string, mixed>>
     */
    private function expectedPointRows(): array
    {
        return [
            $this->singleRow(200092202, 'RhSg P2', 2, '2098-03-03', 781, 7, 1, 80),
            $this->singleRow(200092203, 'RhSg P3', 1, '2098-03-04', 0, 0, 2, 80),
            $this->singleRow(200092205, 'RhSg P5', 2, '2098-03-06', 0, 0, 1, 70),
            $this->singleRow(200092201, 'RhSg P1', 1, '2098-03-02', 0, 0, 2, 60),
            $this->singleRow(200092204, 'RhSg P4', 1, '2098-03-05', 0, 0, 2, 50),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function singleRow(
        int $pid,
        string $name,
        int $tid,
        string $date,
        int $boxId,
        int $gameOfThatDay,
        int $oppTid,
        int $value,
    ): array {
        return [
            'pid' => $pid,
            'name' => $name,
            'teamid' => $tid,
            'team_name' => 'RhHist-' . $pid,
            'date' => $date,
            'box_id' => $boxId,
            'game_of_that_day' => $gameOfThatDay,
            'oppTid' => $oppTid,
            'opp_team_name' => $this->names[$oppTid],
            'value' => $value,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function expectedPpgRows(): array
    {
        return [
            $this->seasonRow(200092301, '400.0'),
            $this->seasonRow(200092302, '350.0'),
            $this->seasonRow(200092303, '340.0'),
            $this->seasonRow(200092304, '320.0'),
            $this->seasonRow(200092305, '300.0'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonRow(int $pid, string $value): array
    {
        // ROUND(a / b, 1) is DECIMAL, which mysqlnd returns as a string; pinned as current behavior (see PR body).
        return [
            'pid' => $pid,
            'name' => 'RhSeason ' . $pid,
            'teamid' => 1,
            'team' => 'RhSeason-' . $pid,
            'year' => 2097,
            'value' => $value,
        ];
    }

    // --- seeding helpers ---

    /**
     * Seed the player, a 2098 ibl_hist row on $playerTid, and one box-score line.
     *
     * @param array{points2m?: int, ftm?: int, points3m?: int, orb?: int, drb?: int, ast?: int, stl?: int, blk?: int} $stats
     */
    private function seedPlayerGame(
        string $date,
        int $pid,
        string $name,
        int $visitorTid,
        int $homeTid,
        int $playerTid,
        array $stats,
    ): void {
        $this->insertTestPlayer($pid, $name, ['teamid' => $playerTid]);
        $this->insertHistRow($pid, $name, 2098, ['teamid' => $playerTid, 'team' => 'RhHist-' . $pid]);
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            $name,
            'PG',
            $visitorTid,
            $homeTid,
            $playerTid,
            ...$stats,
            overrides: ['uuid' => sprintf('rh-pl-0000-0000-%012d', $pid)],
        );
    }
}
