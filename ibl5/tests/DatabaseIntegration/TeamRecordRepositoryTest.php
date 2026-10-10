<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use RecordHolders\TeamRecordRepository;

/**
 * Direct tests for TeamRecordRepository against the real schema.
 *
 * Isolation: each test plants rows in the year-2098 window with values no
 * seeded game reaches. Team names are loaded from ibl_team_info because the
 * repository joins ibl_team_info.team_name to ibl_box_scores_teams.name.
 */
#[Group('database')]
final class TeamRecordRepositoryTest extends DatabaseTestCase
{
    private TeamRecordRepository $repo;

    /** @var array<int, string> */
    private array $names = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamRecordRepository($this->db);

        $result = $this->db->query('SELECT teamid, team_name FROM ibl_team_info WHERE teamid IN (1, 2, 3)');
        self::assertInstanceOf(\mysqli_result::class, $result);
        while ($row = $result->fetch_assoc()) {
            $this->names[(int) $row['teamid']] = (string) $row['team_name'];
        }
        self::assertCount(3, $this->names);
    }

    // --- getTopTeamHalfScore ---

    public function testGetTopTeamHalfScoreFirstHalfDescReturnsTopFourExactRows(): void
    {
        $this->seedHalfScoreGames();

        self::assertSame(
            [
                $this->halfRow(2, '2098-01-05', 777, 1, 150),
                $this->halfRow(2, '2098-01-06', 0, 1, 140),
                $this->halfRow(1, '2098-01-05', 777, 2, 135),
                $this->halfRow(1, '2098-01-06', 0, 2, 130),
            ],
            array_slice($this->repo->getTopTeamHalfScore('first', 'DESC'), 0, 4),
        );
    }

    public function testGetTopTeamHalfScoreFirstHalfAscReturnsLowestExactRows(): void
    {
        $this->seedHalfScoreGames();

        self::assertSame(
            [
                $this->halfRow(2, '2098-01-07', 0, 1, 0),
                $this->halfRow(1, '2098-01-07', 0, 2, 1),
            ],
            array_slice($this->repo->getTopTeamHalfScore('first', 'ASC'), 0, 2),
        );
    }

    public function testGetTopTeamHalfScoreSecondHalfDescIncludesOvertime(): void
    {
        $this->seedHalfScoreGames();

        self::assertSame(
            [
                $this->halfRow(2, '2098-01-07', 0, 1, 140),
                $this->halfRow(1, '2098-01-07', 0, 2, 130),
            ],
            array_slice($this->repo->getTopTeamHalfScore('second', 'DESC'), 0, 2),
        );
    }

    public function testGetTopTeamHalfScoreSecondHalfAscReturnsLowestExactRows(): void
    {
        $this->seedHalfScoreGames();

        self::assertSame(
            [
                $this->halfRow(2, '2098-01-05', 777, 1, 2),
                $this->halfRow(1, '2098-01-05', 777, 2, 4),
                $this->halfRow(2, '2098-01-06', 0, 1, 6),
                $this->halfRow(1, '2098-01-06', 0, 2, 14),
            ],
            array_slice($this->repo->getTopTeamHalfScore('second', 'ASC'), 0, 4),
        );
    }

    public function testGetTopTeamHalfScoreUnknownOrderFallsBackToDesc(): void
    {
        $this->seedHalfScoreGames();

        self::assertSame(
            $this->repo->getTopTeamHalfScore('first', 'DESC'),
            $this->repo->getTopTeamHalfScore('first', 'bogus; DROP'),
        );
    }

    // --- getLargestMarginOfVictory ---

    public function testGetLargestMarginOfVictoryRanksByMarginThenDateAndExcludesOutOfScope(): void
    {
        $freeAgentsName = $this->teamName(0);

        $this->seedGame('2098-02-03', 2, 1, $this->quarters(100), $this->quarters(120));
        $this->seedGame('2098-02-04', 3, 1, $this->quarters(130), $this->quarters(100));
        $this->insertScheduleRow(2098, '2098-02-04', 3, 0, 1, 0, 778);
        $this->seedGame('2098-02-05', 1, 2, $this->quarters(101), $this->quarters(100));
        $this->seedGame('2098-02-06', 3, 2, $this->quarters(80), $this->quarters(100));
        $this->seedGameWithNames('2098-02-07', 0, 1, $freeAgentsName, $this->names[1], $this->quarters(200), $this->quarters(100));
        $this->seedGame('2098-03-01', 2, 1, $this->quarters(0), $this->quarters(150));

        $result = $this->repo->getLargestMarginOfVictory("bs.game_date BETWEEN '2098-02-01' AND '2098-02-28'");

        self::assertSame(
            [
                $this->marginRow(3, 1, '2098-02-04', 778, 30),
                $this->marginRow(1, 2, '2098-02-03', 0, 20),
                $this->marginRow(2, 3, '2098-02-06', 0, 20),
                $this->marginRow(1, 2, '2098-02-05', 0, 1),
            ],
            $result,
        );
    }

    public function testGetLargestMarginOfVictoryEmptyRangeReturnsEmptyList(): void
    {
        $this->seedGame('2098-02-03', 2, 1, $this->quarters(100), $this->quarters(120));

        self::assertSame(
            [],
            $this->repo->getLargestMarginOfVictory("bs.game_date BETWEEN '2097-01-01' AND '2097-01-31'"),
        );
    }

    // --- getBestWorstSeasonRecord ---

    public function testGetBestWorstSeasonRecordDescOrdersByPctThenWins(): void
    {
        $this->seedSevenZeroSeason();

        $mine = $this->recordsForYear($this->repo->getBestWorstSeasonRecord('DESC'));

        self::assertGreaterThanOrEqual(2, count($mine));
        self::assertSame(
            array_slice($this->expectedDescRecords(), 0, count($mine)),
            $mine,
        );
    }

    public function testGetBestWorstSeasonRecordAscOrdersWorstFirst(): void
    {
        $this->seedSevenZeroSeason();

        $mine = $this->recordsForYear($this->repo->getBestWorstSeasonRecord('ASC'));

        self::assertGreaterThanOrEqual(2, count($mine));
        self::assertSame(
            array_slice(array_reverse($this->expectedDescRecords()), 0, count($mine)),
            $mine,
        );
    }

    public function testGetBestWorstSeasonRecordUnknownOrderFallsBackToDesc(): void
    {
        $this->seedSevenZeroSeason();

        self::assertSame(
            $this->repo->getBestWorstSeasonRecord('DESC'),
            $this->repo->getBestWorstSeasonRecord('bogus'),
        );
    }

    // --- getTopTeamSingleGameBatch ---

    public function testGetTopTeamSingleGameBatchOrdersEachLabelAndAppliesLimitFive(): void
    {
        $this->seedSingleGames();

        $result = $this->repo->getTopTeamSingleGameBatch($this->singleGameStats('team_points'), $this->aprilFilter());

        self::assertSame(['team_points', 'team_assists'], array_keys($result));
        self::assertSame($this->expectedPointRows(), $result['team_points']);
        self::assertSame(
            [
                $this->singleRow(3, '2098-04-04', 0, 2, 1),
                $this->singleRow(1, '2098-04-03', 0, 3, 5),
                $this->singleRow(2, '2098-04-02', 779, 1, 10),
                $this->singleRow(3, '2098-04-03', 0, 1, 25),
                $this->singleRow(2, '2098-04-04', 0, 3, 25),
            ],
            $result['team_assists'],
        );
    }

    public function testGetTopTeamSingleGameBatchKeepsLabelWithApostrophe(): void
    {
        $this->seedSingleGames();

        $result = $this->repo->getTopTeamSingleGameBatch(
            ["Team's Points" => ['expression' => 'bs.calc_points', 'order' => 'DESC']],
            $this->aprilFilter(),
        );

        self::assertSame(["Team's Points"], array_keys($result));
        self::assertSame($this->expectedPointRows(), $result["Team's Points"]);
    }

    public function testGetTopTeamSingleGameBatchEmptyInputsReturnEmpty(): void
    {
        self::assertSame([], $this->repo->getTopTeamSingleGameBatch([], '1=1'));

        self::assertSame(
            ['team_points' => []],
            $this->repo->getTopTeamSingleGameBatch(
                ['team_points' => ['expression' => 'bs.calc_points', 'order' => 'DESC']],
                "bs.game_date BETWEEN '2097-01-01' AND '2097-01-31'",
            ),
        );
    }

    // --- Streaks and season starts ---

    public function testGetLongestStreakWinningReturnsSingleExactRecord(): void
    {
        $this->seedStreakScenario();

        self::assertSame(
            [$this->streakRow(1, 12, '2098-01-01', '2098-01-12')],
            $this->repo->getLongestStreak('winning'),
        );
    }

    public function testGetLongestStreakLosingReturnsSingleExactRecord(): void
    {
        $this->seedStreakScenario();

        self::assertSame(
            [$this->streakRow(2, 12, '2098-01-01', '2098-01-12')],
            $this->repo->getLongestStreak('losing'),
        );
    }

    public function testGetLongestStreakIgnoresNonRegularSeasonGames(): void
    {
        $this->seedStreakScenario();
        $this->seedRun('2098-06-01', 14, 2, 1, false);

        self::assertSame(
            [$this->streakRow(1, 12, '2098-01-01', '2098-01-12')],
            $this->repo->getLongestStreak('winning'),
        );
    }

    public function testGetBestWorstSeasonStartBestReturnsExactRecord(): void
    {
        $this->seedStreakScenario();

        self::assertSame(
            [['team_name' => $this->names[1], 'year' => 2098, 'wins' => 12, 'losses' => 0]],
            $this->repo->getBestWorstSeasonStart('best'),
        );
    }

    public function testGetBestWorstSeasonStartWorstReturnsExactRecord(): void
    {
        $this->seedStreakScenario();

        self::assertSame(
            [['team_name' => $this->names[2], 'year' => 2098, 'wins' => 0, 'losses' => 12]],
            $this->repo->getBestWorstSeasonStart('worst'),
        );
    }

    public function testRegularSeasonGamesAreMemoizedPerInstance(): void
    {
        $this->seedStreakScenario();

        $first = $this->repo->getLongestStreak('winning');
        $this->seedRun('2098-01-17', 14, 1, 2, true);

        self::assertSame($first, $this->repo->getLongestStreak('winning'));

        $fresh = new TeamRecordRepository($this->db);
        self::assertSame(
            [$this->streakRow(1, 17, '2098-01-14', '2098-01-30')],
            $fresh->getLongestStreak('winning'),
        );
    }

    // --- seed scenarios ---

    /**
     * T2 visitor at T1 home on 01-05, 01-06, 01-07 with distinct quarter splits.
     */
    private function seedHalfScoreGames(): void
    {
        $this->seedGame('2098-01-05', 2, 1, [80, 70, 1, 1, 0], [75, 60, 2, 2, 0]);
        $this->insertScheduleRow(2098, '2098-01-05', 2, 0, 1, 0, 777);
        $this->seedGame('2098-01-06', 2, 1, [70, 70, 3, 3, 0], [65, 65, 5, 0, 9]);
        $this->seedGame('2098-01-07', 2, 1, [0, 0, 60, 60, 20], [0, 1, 50, 50, 30]);
    }

    /**
     * T1 goes 7-0, T3 1-1, T2 0-7 in January 2098.
     */
    private function seedSevenZeroSeason(): void
    {
        for ($day = 11; $day <= 16; $day++) {
            $this->seedGame(sprintf('2098-01-%02d', $day), 2, 1, $this->quarters(80), $this->quarters(100));
        }
        $this->seedGame('2098-01-17', 3, 1, $this->quarters(80), $this->quarters(100));
        $this->seedGame('2098-01-18', 2, 3, $this->quarters(80), $this->quarters(100));
    }

    /**
     * @return list<array{team_name: string, year: int, wins: int, losses: int}>
     */
    private function expectedDescRecords(): array
    {
        return [
            ['team_name' => $this->names[1], 'year' => 2098, 'wins' => 7, 'losses' => 0],
            ['team_name' => $this->names[3], 'year' => 2098, 'wins' => 1, 'losses' => 1],
            ['team_name' => $this->names[2], 'year' => 2098, 'wins' => 0, 'losses' => 7],
        ];
    }

    /**
     * @param list<array{team_name: string, year: int, wins: int, losses: int}> $records
     * @return list<array{team_name: string, year: int, wins: int, losses: int}>
     */
    private function recordsForYear(array $records): array
    {
        return array_values(array_filter(
            $records,
            static fn (array $record): bool => $record['year'] === 2098,
        ));
    }

    /**
     * 04-02 T2@T1, 04-03 T3@T1, 04-04 T2@T3 with per-row 2-point makes and assists.
     */
    private function seedSingleGames(): void
    {
        $this->seedGame(
            '2098-04-02', 2, 1, $this->quarters(100), $this->quarters(100), 1,
            ['game_2gm' => 40, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 10],
            ['game_2gm' => 50, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 30],
        );
        $this->insertScheduleRow(2098, '2098-04-02', 2, 0, 1, 0, 779);
        $this->seedGame(
            '2098-04-03', 3, 1, $this->quarters(100), $this->quarters(100), 1,
            ['game_2gm' => 45, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 25],
            ['game_2gm' => 30, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 5],
        );
        $this->seedGame(
            '2098-04-04', 2, 3, $this->quarters(100), $this->quarters(100), 1,
            ['game_2gm' => 45, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 25],
            ['game_2gm' => 20, 'game_ftm' => 0, 'game_3gm' => 0, 'game_ast' => 1],
        );
    }

    /**
     * T1 wins 12 straight at home over T2, T2 wins once, then T1 wins 3 more.
     */
    private function seedStreakScenario(): void
    {
        $this->seedRun('2098-01-01', 12, 1, 2, true);
        $this->seedGame('2098-01-13', 2, 1, $this->quarters(100), $this->quarters(80));
        $this->seedRun('2098-01-14', 3, 1, 2, true);
    }

    // --- expected-row builders ---

    /**
     * @return array<string, int|string>
     */
    private function halfRow(int $tid, string $date, int $boxId, int $oppTid, int $value): array
    {
        return [
            'teamid' => $tid,
            'team_name' => $this->names[$tid],
            'date' => $date,
            'box_id' => $boxId,
            'game_of_that_day' => 1,
            'oppTid' => $oppTid,
            'opp_team_name' => $this->names[$oppTid],
            'value' => $value,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function marginRow(int $winnerTid, int $loserTid, string $date, int $boxId, int $margin): array
    {
        return [
            'winner_tid' => $winnerTid,
            'winner_name' => $this->names[$winnerTid],
            'loser_tid' => $loserTid,
            'loser_name' => $this->names[$loserTid],
            'date' => $date,
            'box_id' => $boxId,
            'game_of_that_day' => 1,
            'margin' => $margin,
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function singleRow(int $tid, string $date, int $boxId, int $oppTid, int $value): array
    {
        return [
            'teamid' => $tid,
            'team_name' => $this->names[$tid],
            'date' => $date,
            'box_id' => $boxId,
            'game_of_that_day' => 1,
            'oppTid' => $oppTid,
            'opp_team_name' => $this->names[$oppTid],
            'value' => $value,
        ];
    }

    /**
     * @return list<array<string, int|string>>
     */
    private function expectedPointRows(): array
    {
        return [
            $this->singleRow(1, '2098-04-02', 779, 2, 100),
            $this->singleRow(3, '2098-04-03', 0, 1, 90),
            $this->singleRow(2, '2098-04-04', 0, 3, 90),
            $this->singleRow(2, '2098-04-02', 779, 1, 80),
            $this->singleRow(1, '2098-04-03', 0, 3, 60),
        ];
    }

    /**
     * @return array<string, int|string>
     */
    private function streakRow(int $tid, int $streak, string $start, string $end): array
    {
        return [
            'team_name' => $this->names[$tid],
            'streak' => $streak,
            'start_date' => $start,
            'end_date' => $end,
            'start_year' => 2098,
            'end_year' => 2098,
        ];
    }

    /**
     * @return array<string, array{expression: string, order: string}>
     */
    private function singleGameStats(string $pointsLabel): array
    {
        return [
            $pointsLabel => ['expression' => 'bs.calc_points', 'order' => 'DESC'],
            'team_assists' => ['expression' => 'bs.game_ast', 'order' => 'ASC'],
        ];
    }

    private function aprilFilter(): string
    {
        return "bs.game_date BETWEEN '2098-04-01' AND '2098-04-28'";
    }

    // --- seeding helpers ---

    private function teamName(int $tid): string
    {
        $result = $this->db->query('SELECT team_name FROM ibl_team_info WHERE teamid = ' . $tid);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);

        return (string) $row['team_name'];
    }

    /**
     * Split a team total into [q1, q2, q3, q4, ot] with q2..q4 equal.
     *
     * @return list<int>
     */
    private function quarters(int $total): array
    {
        $base = intdiv($total, 4);

        return [$total - 3 * $base, $base, $base, $base, 0];
    }

    /**
     * @param array<string, int> $cols
     */
    private function patchTeamRow(int $id, array $cols): void
    {
        $assignments = implode(', ', array_map(static fn (string $col): string => "`$col` = ?", array_keys($cols)));
        $values = array_values($cols);
        $values[] = $id;

        $stmt = $this->db->prepare("UPDATE ibl_box_scores_teams SET $assignments WHERE id = ?");
        self::assertNotFalse($stmt, 'Failed to prepare UPDATE: ' . $this->db->error);
        $stmt->bind_param(str_repeat('i', count($values)), ...$values);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * @param list<int> $visitorQ [q1, q2, q3, q4, ot]
     * @param list<int> $homeQ [q1, q2, q3, q4, ot]
     * @param array<string, int> $visitorCols
     * @param array<string, int> $homeCols
     */
    private function seedGame(
        string $date,
        int $visitorTid,
        int $homeTid,
        array $visitorQ,
        array $homeQ,
        int $gotd = 1,
        array $visitorCols = [],
        array $homeCols = [],
    ): void {
        $this->seedGameWithNames(
            $date,
            $visitorTid,
            $homeTid,
            $this->names[$visitorTid],
            $this->names[$homeTid],
            $visitorQ,
            $homeQ,
            $gotd,
            $visitorCols,
            $homeCols,
        );
    }

    /**
     * @param list<int> $visitorQ [q1, q2, q3, q4, ot]
     * @param list<int> $homeQ [q1, q2, q3, q4, ot]
     * @param array<string, int> $visitorCols
     * @param array<string, int> $homeCols
     */
    private function seedGameWithNames(
        string $date,
        int $visitorTid,
        int $homeTid,
        string $visitorName,
        string $homeName,
        array $visitorQ,
        array $homeQ,
        int $gotd = 1,
        array $visitorCols = [],
        array $homeCols = [],
    ): void {
        $visitorId = $this->insertTeamBoxscoreRow($date, $visitorName, $gotd, $visitorTid, $homeTid);
        $homeId = $this->insertTeamBoxscoreRow($date, $homeName, $gotd, $visitorTid, $homeTid);

        $scores = [
            'visitor_q1_points' => $visitorQ[0],
            'visitor_q2_points' => $visitorQ[1],
            'visitor_q3_points' => $visitorQ[2],
            'visitor_q4_points' => $visitorQ[3],
            'visitor_ot_points' => $visitorQ[4],
            'home_q1_points' => $homeQ[0],
            'home_q2_points' => $homeQ[1],
            'home_q3_points' => $homeQ[2],
            'home_q4_points' => $homeQ[3],
            'home_ot_points' => $homeQ[4],
        ];

        $this->patchTeamRow($visitorId, array_merge($scores, $visitorCols));
        $this->patchTeamRow($homeId, array_merge($scores, $homeCols));
    }

    /**
     * Seed $count consecutive-day games where the winner scores 100 to the loser's 80.
     */
    private function seedRun(string $firstDate, int $count, int $winnerTid, int $loserTid, bool $winnerHome): void
    {
        $date = new \DateTimeImmutable($firstDate);

        for ($i = 0; $i < $count; $i++) {
            $day = $date->modify('+' . $i . ' day')->format('Y-m-d');
            if ($winnerHome) {
                $this->seedGame($day, $loserTid, $winnerTid, $this->quarters(80), $this->quarters(100));
            } else {
                $this->seedGame($day, $winnerTid, $loserTid, $this->quarters(100), $this->quarters(80));
            }
        }
    }
}
