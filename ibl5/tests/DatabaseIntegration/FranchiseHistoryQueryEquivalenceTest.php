<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use FranchiseHistory\FranchiseHistoryRepository;
use League\League;
use PHPUnit\Framework\Attributes\Group;

/**
 * Results-equality characterization for the four FranchiseHistoryRepository queries.
 *
 * Seeds a prod-scale synthetic volume (25 seasons, 50K+ box-score rows) inside the
 * per-test rolled-back transaction and proves each repository method returns exactly
 * what the frozen pre-change SQL returns on the same data.
 *
 * Never run DDL or ANALYZE TABLE in this class: both commit implicitly in MariaDB and
 * would leak the seed out of the rolled-back transaction into ibl5_test.
 */
#[Group('database')]
class FranchiseHistoryQueryEquivalenceTest extends DatabaseTestCase
{
    private const SEED_FIRST_ENDING_YEAR = 1990;
    private const SEED_LAST_ENDING_YEAR = 2014;
    private const SEED_CURRENT_ENDING_YEAR = 2014;
    private const REGULAR_GAMES_PER_SEASON = 1148;
    private const BULK_CHUNK_ROWS = 400;

    /*
     * Pre-change SQL frozen at master 8ea15dd1a. Never edit these to match a repository
     * change; the test exists to prove the repository still returns what these return.
     */

    private const FROZEN_SUMMARY_SQL = "SELECT ti.teamid, ti.team_name, ti.color1, ti.color2,
                    fs.totwins, fs.totloss, fs.winpct, fs.playoffs,
                    fs.div_titles, fs.conf_titles, fs.ibl_titles, fs.heat_titles
             FROM `ibl_team_info` ti
             JOIN vw_franchise_summary fs ON fs.teamid = ti.teamid
             WHERE ti.teamid <> ?
             ORDER BY ti.teamid ASC";

    private const FROZEN_WINDOW_SQL = "SELECT currentname,
                    CAST(SUM(wins) AS UNSIGNED) AS five_season_wins,
                    CAST(SUM(losses) AS UNSIGNED) AS five_season_losses
             FROM `ibl_team_win_loss`
             WHERE year BETWEEN ? AND ?
             GROUP BY currentname";

    private const FROZEN_PLAYOFF_TOTALS_SQL = "SELECT
                team_name,
                CAST(SUM(wins) AS UNSIGNED) AS total_wins,
                CAST(SUM(losses) AS UNSIGNED) AS total_losses
            FROM (
                SELECT winner AS team_name, winner_games AS wins, loser_games AS losses
                FROM vw_playoff_series_results
                UNION ALL
                SELECT loser AS team_name, loser_games AS wins, winner_games AS losses
                FROM vw_playoff_series_results
            ) AS combined
            GROUP BY team_name";

    private const FROZEN_HEAT_TOTALS_SQL = "SELECT currentname, SUM(wins) AS total_wins, SUM(losses) AS total_losses
            FROM `ibl_heat_win_loss`
            GROUP BY currentname";

    private FranchiseHistoryRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new FranchiseHistoryRepository($this->db);
    }

    public function testRepositoryMatchesFrozenQueriesOnProdScaleSeed(): void
    {
        $stats = $this->seedProdScaleVolume();

        self::assertGreaterThanOrEqual(50000, $stats['boxRows']);

        $year = self::SEED_CURRENT_ENDING_YEAR;
        self::assertNotSame([], $this->runFrozen(self::FROZEN_WINDOW_SQL, 'ii', $year - 4, $year), 'window is vacuous');
        self::assertNotSame([], $this->runFrozen(self::FROZEN_PLAYOFF_TOTALS_SQL, ''), 'playoff totals are vacuous');
        self::assertNotSame([], $this->runFrozen(self::FROZEN_HEAT_TOTALS_SQL, ''), 'HEAT totals are vacuous');

        $summary = $this->runFrozen(self::FROZEN_SUMMARY_SQL, 'i', League::FREE_AGENTS_TEAMID);
        $anyWins = array_filter($summary, static fn (array $row): bool => (int) $row['totwins'] > 0);
        self::assertNotSame([], $anyWins, 'no summary row has totwins > 0');

        $this->assertAllFourMatchFrozen($year);
    }

    public function testDuplicateGameOfThatDayReplayCountedOnce(): void
    {
        [$t1, $t2, $name1, $name2] = $this->firstTwoTeams();

        $this->insertTeamBoxscoreRow('2013-11-05', 'Visitor', 3, $t1, $t2);
        $this->insertTeamBoxscoreRow('2013-11-05', 'Home', 3, $t1, $t2);

        // Phantom replay: higher game_of_that_day, swapped score so the visitor would win.
        foreach (['Visitor', 'Home'] as $label) {
            $this->insertRow('ibl_box_scores_teams', [
                'game_date' => '2013-11-05',
                'name' => $label,
                'game_of_that_day' => 7,
                'visitor_teamid' => $t1,
                'home_teamid' => $t2,
                'visitor_q1_points' => 28,
                'visitor_q2_points' => 24,
                'visitor_q3_points' => 22,
                'visitor_q4_points' => 30,
                'visitor_ot_points' => 0,
                'home_q1_points' => 20,
                'home_q2_points' => 22,
                'home_q3_points' => 18,
                'home_q4_points' => 25,
                'home_ot_points' => 0,
                'game_2gm' => 30,
                'game_ftm' => 15,
                'game_3gm' => 8,
                'game_orb' => 10,
                'game_drb' => 30,
            ]);
        }

        $window = $this->indexBy($this->repository->getFiveSeasonWindowRows(2014), 'currentname');

        self::assertArrayHasKey($name1, $window);
        self::assertArrayHasKey($name2, $window);
        self::assertSame(1, (int) $window[$name1]['five_season_wins'] + (int) $window[$name1]['five_season_losses']);
        self::assertSame(0, (int) $window[$name1]['five_season_wins'], 'visitor won: the replay was counted');
        self::assertSame(1, (int) $window[$name2]['five_season_wins'], 'home side should keep the canonical win');

        $this->assertAllFourMatchFrozen(2014);
    }

    public function testWindowBoundaryDatesMatchFrozenQuery(): void
    {
        [$t1, $t2, $name1] = $this->firstTwoTeams();

        $dates = [
            '2009-08-31', // type 1, season 2009: outside
            '2009-09-30', // type 4: excluded
            '2009-10-15', // type 3 HEAT: excluded from regular season
            '2009-11-01', // type 1, season 2010: inside
            '2014-06-10', // type 2 playoffs: excluded
            '2014-08-31', // type 1, season 2014: inside
            '2014-11-01', // type 1, season 2015: outside
        ];
        foreach ($dates as $date) {
            $this->insertTeamBoxscoreRow($date, 'Visitor', 1, $t1, $t2);
            $this->insertTeamBoxscoreRow($date, 'Home', 1, $t1, $t2);
        }

        $window = $this->indexBy($this->repository->getFiveSeasonWindowRows(2014), 'currentname');

        self::assertArrayHasKey($name1, $window);
        self::assertSame(2, (int) $window[$name1]['five_season_wins'] + (int) $window[$name1]['five_season_losses']);

        $this->assertAllFourMatchFrozen(2014);
    }

    public function testEmptyWindowReturnsEmptyList(): void
    {
        self::assertSame([], $this->repository->getFiveSeasonWindowRows(1900));
        self::assertSame([], $this->runFrozen(self::FROZEN_WINDOW_SQL, 'ii', 1896, 1900));
    }

    public function testWritesExplainAndTimingReport(): void
    {
        $stats = $this->seedProdScaleVolume();
        $year = self::SEED_CURRENT_ENDING_YEAR;

        $versionResult = $this->db->query('SELECT VERSION()');
        self::assertInstanceOf(\mysqli_result::class, $versionResult);
        $versionRow = $versionResult->fetch_row();
        self::assertIsArray($versionRow);

        $metricLines = [];
        $keyLines = [];
        $explainBlocks = [];
        foreach ($this->currentSqlMap($year) as $method => $entry) {
            $explainRows = $this->runFrozen('EXPLAIN ' . $entry['sql'], $entry['types'], ...$entry['params']);
            self::assertNotSame([], $explainRows, "$method EXPLAIN empty");

            $keys = [];
            $block = ["=== EXPLAIN $method", "id\tselect_type\ttable\ttype\tpossible_keys\tkey\tkey_len\tref\trows\tExtra"];
            foreach ($explainRows as $row) {
                if ($row['key'] !== null) {
                    $keys[] = self::stringOf($row['key']);
                }
                $block[] = implode("\t", array_map(
                    static fn (mixed $value): string => $value === null ? 'NULL' : self::stringOf($value),
                    array_values($row)
                ));
            }
            $keyLines[] = "KEYS $method " . implode(',', $keys);
            $explainBlocks[] = implode("\n", $block);

            $runs = $this->timeRepositoryMethod($method, $year);
            $sorted = $runs;
            sort($sorted);
            $median = $sorted[intdiv(count($sorted), 2)];
            self::assertGreaterThan(0.0, $median);
            $metricLines[] = sprintf(
                'METRIC %s median_ms=%.1f runs_ms=%s',
                $method,
                $median,
                implode(',', array_map(static fn (float $ms): string => sprintf('%.1f', $ms), $runs))
            );
        }

        $text = "# franchise-history perf report\n"
            . sprintf("SEED boxRows=%d teams=%d version=%s\n", $stats['boxRows'], $stats['teams'], self::stringOf($versionRow[0]))
            . implode("\n", $metricLines) . "\n"
            . implode("\n", $keyLines) . "\n"
            . implode("\n", $explainBlocks) . "\n";

        self::assertNotFalse(file_put_contents('/tmp/franchise-history-perf-report.txt', $text));
    }

    /**
     * The SQL each repository method currently executes, keyed by method name.
     *
     * @return array<string, array{sql: string, types: string, params: list<int>}>
     */
    private function currentSqlMap(int $currentEndingYear): array
    {
        return [
            'getFranchiseSummaryRows' => ['sql' => self::FROZEN_SUMMARY_SQL, 'types' => 'i', 'params' => [League::FREE_AGENTS_TEAMID]],
            'getFiveSeasonWindowRows' => ['sql' => self::FROZEN_WINDOW_SQL, 'types' => 'ii', 'params' => [$currentEndingYear - 4, $currentEndingYear]],
            'getRawPlayoffTotals' => ['sql' => self::FROZEN_PLAYOFF_TOTALS_SQL, 'types' => '', 'params' => []],
            'getRawHeatTotals' => ['sql' => self::FROZEN_HEAT_TOTALS_SQL, 'types' => '', 'params' => []],
        ];
    }

    /**
     * Two untimed warm-ups, then seven timed calls of one repository method.
     *
     * @return list<float> elapsed milliseconds per timed call
     */
    private function timeRepositoryMethod(string $method, int $currentEndingYear): array
    {
        $call = match ($method) {
            'getFranchiseSummaryRows' => fn (): array => $this->repository->getFranchiseSummaryRows($currentEndingYear),
            'getFiveSeasonWindowRows' => fn (): array => $this->repository->getFiveSeasonWindowRows($currentEndingYear),
            'getRawPlayoffTotals' => fn (): array => $this->repository->getRawPlayoffTotals(),
            'getRawHeatTotals' => fn (): array => $this->repository->getRawHeatTotals(),
            default => self::fail("unknown repository method $method"),
        };

        $call();
        $call();
        $runs = [];
        for ($i = 0; $i < 7; $i++) {
            $start = hrtime(true);
            $call();
            $runs[] = (hrtime(true) - $start) / 1e6;
        }

        return $runs;
    }

    private function assertAllFourMatchFrozen(int $currentEndingYear): void
    {
        self::assertSame(
            $this->runFrozen(self::FROZEN_SUMMARY_SQL, 'i', League::FREE_AGENTS_TEAMID),
            $this->repository->getFranchiseSummaryRows($currentEndingYear),
            'getFranchiseSummaryRows diverged from frozen SQL'
        );
        self::assertSame(
            $this->sortedBy($this->runFrozen(self::FROZEN_WINDOW_SQL, 'ii', $currentEndingYear - 4, $currentEndingYear), 'currentname'),
            $this->sortedBy($this->repository->getFiveSeasonWindowRows($currentEndingYear), 'currentname'),
            'getFiveSeasonWindowRows diverged from frozen SQL'
        );
        self::assertSame(
            $this->sortedBy($this->runFrozen(self::FROZEN_PLAYOFF_TOTALS_SQL, ''), 'team_name'),
            $this->sortedBy($this->repository->getRawPlayoffTotals(), 'team_name'),
            'getRawPlayoffTotals diverged from frozen SQL'
        );
        self::assertSame(
            $this->sortedBy($this->runFrozen(self::FROZEN_HEAT_TOTALS_SQL, ''), 'currentname'),
            $this->sortedBy($this->repository->getRawHeatTotals(), 'currentname'),
            'getRawHeatTotals diverged from frozen SQL'
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function runFrozen(string $sql, string $types, int ...$params): array
    {
        $stmt = $this->db->prepare($sql);
        self::assertNotFalse($stmt, 'prepare failed: ' . $this->db->error);
        if ($types !== '') {
            $stmt->bind_param($types, ...$params);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result, 'get_result failed: ' . $stmt->error);
        /** @var list<array<string, mixed>> $rows */
        $rows = $result->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return list<array<string, mixed>>
     */
    private function sortedBy(array $rows, string $key): array
    {
        usort(
            $rows,
            static fn (array $a, array $b): int => strcmp(self::stringOf($a[$key] ?? ''), self::stringOf($b[$key] ?? ''))
        );

        return $rows;
    }

    /**
     * @param list<array<string, mixed>> $rows
     * @return array<string, array<string, mixed>>
     */
    private function indexBy(array $rows, string $key): array
    {
        $indexed = [];
        foreach ($rows as $row) {
            $indexed[self::stringOf($row[$key] ?? '')] = $row;
        }

        return $indexed;
    }

    private static function stringOf(mixed $value): string
    {
        return is_scalar($value) ? (string) $value : '';
    }

    /**
     * @return array{0: int, 1: int, 2: string, 3: string}
     */
    private function firstTwoTeams(): array
    {
        $teams = $this->realTeams();
        self::assertGreaterThanOrEqual(2, count($teams));

        return [$teams[0]['teamid'], $teams[1]['teamid'], $teams[0]['team_name'], $teams[1]['team_name']];
    }

    /**
     * @return list<array{teamid: int, team_name: string}>
     */
    private function realTeams(): array
    {
        $result = $this->db->query('SELECT teamid, team_name FROM ibl_team_info WHERE teamid BETWEEN 1 AND 30 ORDER BY teamid');
        self::assertInstanceOf(\mysqli_result::class, $result);
        $teams = [];
        foreach ($result->fetch_all(MYSQLI_ASSOC) as $row) {
            $teams[] = ['teamid' => (int) $row['teamid'], 'team_name' => self::stringOf($row['team_name'])];
        }

        return $teams;
    }

    private function scalarCount(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_row();
        self::assertIsArray($row);

        return (int) $row[0];
    }

    /**
     * Seed 25 seasons of regular-season, playoff and HEAT box scores plus playoff
     * series, franchise renames and team awards.
     *
     * @return array{teams: int, boxRows: int}
     */
    private function seedProdScaleVolume(): array
    {
        $guards = [
            'ibl_box_scores_teams' => "SELECT COUNT(*) FROM ibl_box_scores_teams WHERE game_date BETWEEN '1989-07-01' AND '2014-12-31'",
            'ibl_playoff_series_results' => 'SELECT COUNT(*) FROM ibl_playoff_series_results WHERE year BETWEEN 1990 AND 2014',
            'ibl_team_awards' => 'SELECT COUNT(*) FROM ibl_team_awards WHERE year BETWEEN 1990 AND 2014',
            'ibl_franchise_seasons' => 'SELECT COUNT(*) FROM ibl_franchise_seasons WHERE season_ending_year BETWEEN 1990 AND 2014',
        ];
        foreach ($guards as $table => $sql) {
            if ($this->scalarCount($sql) !== 0) {
                self::fail("seed range 1990-2014 already populated in $table; pick a free range");
            }
        }

        $teams = $this->realTeams();
        $teamCount = count($teams);
        self::assertGreaterThanOrEqual(4, $teamCount);

        $pairs = [];
        foreach ($teams as $visitor) {
            foreach ($teams as $home) {
                if ($visitor['teamid'] !== $home['teamid']) {
                    $pairs[] = [$visitor['teamid'], $home['teamid']];
                }
            }
        }
        $pairCount = count($pairs);

        /** @var list<list<int|string|null>> $rows */
        $rows = [];
        for ($y = self::SEED_FIRST_ENDING_YEAR; $y <= self::SEED_LAST_ENDING_YEAR; $y++) {
            // Regular season (game_type 1): Nov 1 of the prior year through mid-April.
            $perDay = min($pairCount, (int) ceil(self::REGULAR_GAMES_PER_SEASON / 170));
            $seasonStart = new \DateTimeImmutable(sprintf('%04d-11-01', $y - 1));
            for ($g = 0; $g < self::REGULAR_GAMES_PER_SEASON; $g++) {
                $day = intdiv($g, $perDay);
                $slot = $g % $perDay;
                [$visitorTid, $homeTid] = $pairs[($day * $perDay * 7 + $slot) % $pairCount];
                $date = $seasonStart->modify("+$day days")->format('Y-m-d');
                $visitorPts = $this->quarterPoints($g, 7, 3);
                $homePts = $this->quarterPoints($g, 11, 5);
                $gameOfDay = $g === self::REGULAR_GAMES_PER_SEASON - 1 ? null : $slot + 1;

                $this->appendGameRows($rows, $date, $gameOfDay, $visitorTid, $homeTid, $visitorPts, $homePts);

                if ($g % 40 === 0) {
                    // Phantom replay with swapped score; the view's MIN(game_of_that_day) must ignore it.
                    $this->appendGameRows($rows, $date, $slot + 501, $visitorTid, $homeTid, $homePts, $visitorPts);
                }
            }

            // Playoffs (game_type 2): June box scores plus series results.
            $rotation = $y % $teamCount;
            for ($r = 1; $r <= 4; $r++) {
                $seriesInRound = max(1, intdiv(min(16, $teamCount), 2 ** $r));
                for ($s = 0; $s < $seriesInRound; $s++) {
                    $winner = $teams[($rotation + 2 * $s) % $teamCount];
                    $loser = $teams[($rotation + 2 * $s + 1) % $teamCount];
                    $gamesTotal = 4 + ($y + $r + $s) % 4;
                    for ($gi = 0; $gi < $gamesTotal; $gi++) {
                        $date = (new \DateTimeImmutable(sprintf('%04d-06-01', $y)))
                            ->modify('+' . (($r - 1) * 7 + $gi) . ' days')
                            ->format('Y-m-d');
                        $this->appendGameRows(
                            $rows,
                            $date,
                            $s + 1,
                            $winner['teamid'],
                            $loser['teamid'],
                            $this->quarterPoints($gi + $s, 7, 3),
                            $this->quarterPoints($gi + $s, 11, 5)
                        );
                    }
                    $this->insertPlayoffSeriesResultRow(
                        $y,
                        $r,
                        $winner['teamid'],
                        $loser['teamid'],
                        $winner['team_name'],
                        $loser['team_name'],
                        4,
                        $gamesTotal - 4
                    );
                }
            }

            // HEAT (game_type 3): Oct 2-9 of the prior calendar year.
            $heatPerDay = intdiv($teamCount, 2);
            $heatStart = new \DateTimeImmutable(sprintf('%04d-10-02', $y - 1));
            for ($day = 0; $day < 8; $day++) {
                $date = $heatStart->modify("+$day days")->format('Y-m-d');
                for ($slot = 0; $slot < $heatPerDay; $slot++) {
                    $g = $day * $heatPerDay + $slot;
                    [$visitorTid, $homeTid] = $pairs[($day * $heatPerDay * 7 + $slot) % $pairCount];
                    $this->appendGameRows(
                        $rows,
                        $date,
                        $slot + 1,
                        $visitorTid,
                        $homeTid,
                        $this->quarterPoints($g, 7, 3),
                        $this->quarterPoints($g, 11, 5)
                    );
                }
            }

            // Franchise renames for the first three teams in the 1990s.
            if ($y <= 1999) {
                for ($k = 0; $k < 3; $k++) {
                    $tid = $teams[$k]['teamid'];
                    $this->insertFranchiseSeasonRow($tid, $y, 'Legacy' . $tid);
                }
            }

            $awards = [
                'Atlantic Division Champions',
                'Pacific Division Champions',
                'Eastern Conference Champions',
                'Western Conference Champions',
            ];
            foreach ($awards as $k => $award) {
                $this->insertTeamAwardRow($teams[($y + $k) % $teamCount]['team_name'], $award, $y);
            }
        }

        $this->bulkInsertBoxScores($rows);

        return [
            'teams' => $teamCount,
            'boxRows' => $this->scalarCount(
                "SELECT COUNT(*) FROM ibl_box_scores_teams WHERE game_date BETWEEN '1989-07-01' AND '2014-12-31'"
            ),
        ];
    }

    /**
     * @return array{0: int, 1: int, 2: int, 3: int}
     */
    private function quarterPoints(int $g, int $gameFactor, int $quarterFactor): array
    {
        $points = [];
        for ($q = 1; $q <= 4; $q++) {
            $points[] = 15 + (($g * $gameFactor + $q * $quarterFactor) % 20);
        }

        return [$points[0], $points[1], $points[2], $points[3]];
    }

    /**
     * Append the 'Visitor' and 'Home' rows of one game.
     *
     * @param list<list<int|string|null>> $rows
     * @param array{0: int, 1: int, 2: int, 3: int} $visitorPts
     * @param array{0: int, 1: int, 2: int, 3: int} $homePts
     */
    private function appendGameRows(
        array &$rows,
        string $date,
        ?int $gameOfDay,
        int $visitorTid,
        int $homeTid,
        array $visitorPts,
        array $homePts,
    ): void {
        foreach (['Visitor', 'Home'] as $label) {
            $rows[] = [
                $date, $label, $gameOfDay, $visitorTid, $homeTid,
                $visitorPts[0], $visitorPts[1], $visitorPts[2], $visitorPts[3], 0,
                $homePts[0], $homePts[1], $homePts[2], $homePts[3], 0,
                30, 15, 8, 10, 30,
            ];
        }
    }

    /**
     * Insert box-score rows in multi-row prepared INSERTs. Never lists the STORED
     * generated columns (game_type, season_year, calc_*): an explicit value errors.
     *
     * @param list<list<int|string|null>> $rows
     */
    private function bulkInsertBoxScores(array $rows): void
    {
        $columns = 'game_date, name, game_of_that_day, visitor_teamid, home_teamid, '
            . 'visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points, '
            . 'home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points, '
            . 'game_2gm, game_ftm, game_3gm, game_orb, game_drb';
        $rowPlaceholder = '(' . implode(', ', array_fill(0, 20, '?')) . ')';
        $rowTypes = 'ss' . str_repeat('i', 18);

        foreach (array_chunk($rows, self::BULK_CHUNK_ROWS) as $chunk) {
            $sql = "INSERT INTO ibl_box_scores_teams ($columns) VALUES "
                . implode(', ', array_fill(0, count($chunk), $rowPlaceholder));
            $stmt = $this->db->prepare($sql);
            self::assertNotFalse($stmt, 'bulk insert prepare failed: ' . $this->db->error);
            $values = array_merge(...$chunk);
            $stmt->bind_param(str_repeat($rowTypes, count($chunk)), ...$values);
            $stmt->execute();
            $stmt->close();
        }
    }
}
