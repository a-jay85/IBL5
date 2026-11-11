<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use League\League;
use PHPUnit\Framework\Attributes\Group;
use RecordHolders\RecordStatDefinitions;
use RecordHolders\TeamRecordRepository;

/**
 * Characterization of TeamRecordRepository::getTopTeamSingleGameBatch against the real schema.
 *
 * referenceBatch() is a frozen copy of the pre-value-cut SQL. Every test asserts the
 * repository returns exactly what that reference returns, so the threshold cut cannot
 * change a result. Seeded rows use dates in 2098-03 and stay inside MARCH_FILTER.
 */
#[Group('database')]
final class TeamSingleGameBatchCharacterizationTest extends DatabaseTestCase
{
    private const MARCH_FILTER = "bs.game_date BETWEEN '2098-03-01' AND '2098-03-28'";

    private TeamRecordRepository $repo;

    /** @var array<int, string> */
    private array $names = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new TeamRecordRepository($this->db);

        $result = $this->db->query('SELECT teamid, team_name FROM ibl_team_info WHERE teamid IN (1, 2)');
        self::assertInstanceOf(\mysqli_result::class, $result);
        while ($row = $result->fetch_assoc()) {
            $this->names[(int) $row['teamid']] = (string) $row['team_name'];
        }
        self::assertCount(2, $this->names);
    }

    public function testBatchMatchesReferenceForAllStatsBothOrdersAndFilters(): void
    {
        $config = [];
        foreach (RecordStatDefinitions::STATS as $stat) {
            $config[$stat['recordLabel'] . ' DESC'] = ['expression' => $stat['expression'], 'order' => 'DESC'];
            $config[$stat['recordLabel'] . ' ASC'] = ['expression' => $stat['expression'], 'order' => 'ASC'];
        }
        self::assertCount(18, $config);

        foreach (RecordStatDefinitions::DATE_FILTERS as $filter) {
            $this->assertMatchesReference($config, $filter);
        }
    }

    public function testBatchMatchesReferenceWhenFewerThanFiveRowsMatch(): void
    {
        $this->seedAssistRows([9, 7, 5]);

        $result = $this->assertMatchesReference([
            'desc' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
            'asc' => ['expression' => 'bs.game_ast', 'order' => 'ASC'],
        ], self::MARCH_FILTER);

        self::assertCount(3, $result['desc']);
        self::assertCount(3, $result['asc']);
    }

    public function testDescBatchSkipsCutWhenNullValueSitsAtCut(): void
    {
        $this->seedAssistRows([9, 8, 7, 0, 0, 0, 0]);

        $result = $this->assertMatchesReference([
            'v' => ['expression' => 'NULLIF(bs.game_ast, 0)', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertSame([9, 8, 7, 0, 0], $this->values($result['v']));
    }

    public function testAscBatchKeepsNullRowsAheadOfCut(): void
    {
        $this->seedAssistRows([0, 0, 3, 4, 5, 6, 7, 8]);

        $result = $this->assertMatchesReference([
            'v' => ['expression' => 'NULLIF(bs.game_ast, 0)', 'order' => 'ASC'],
        ], self::MARCH_FILTER);

        self::assertSame([0, 0, 3, 4, 5], $this->values($result['v']));
    }

    public function testBatchKeepsEarliestTiedRowsStraddlingCut(): void
    {
        $this->insertTeamBoxRow('2098-03-01', 1, 2, $this->names[1], 30);
        $this->insertTeamBoxRow('2098-03-02', 1, 2, $this->names[1], 25);
        $this->insertTeamBoxRow('2098-03-03', 1, 2, $this->names[1], 20);
        foreach ([9, 8, 7, 6] as $day) {
            $this->insertTeamBoxRow('2098-03-' . sprintf('%02d', $day), 1, 2, $this->names[1], 15);
        }
        $this->insertTeamBoxRow('2098-03-06', 2, 1, $this->names[2], 15);

        $result = $this->assertMatchesReference([
            'v' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertSame(
            ['2098-03-01', '2098-03-02', '2098-03-03', '2098-03-06', '2098-03-06'],
            array_column($result['v'], 'date'),
        );
        self::assertSame($this->names[1], $result['v'][3]['team_name']);
        self::assertSame($this->names[2], $result['v'][4]['team_name']);
    }

    public function testUnjoinableTeamNameRowsDoNotRaiseCut(): void
    {
        for ($n = 0; $n < 5; $n++) {
            $this->insertTeamBoxRow($this->day($n), 1, 2, 'RhNoTeam', 50);
        }
        for ($n = 0; $n < 7; $n++) {
            $this->insertTeamBoxRow($this->day($n + 5), 1, 2, $this->names[1], 10 + $n);
        }

        $result = $this->assertMatchesReference([
            'v' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertCount(5, $result['v']);
        self::assertSame(16, $result['v'][0]['value']);
    }

    public function testNonRealTeamRowsDoNotRaiseCut(): void
    {
        for ($n = 0; $n < 5; $n++) {
            $this->insertTeamBoxRow(
                $this->day($n),
                League::ALL_STAR_AWAY_TEAMID,
                League::ALL_STAR_HOME_TEAMID,
                $this->names[1],
                50,
            );
        }
        for ($n = 0; $n < 7; $n++) {
            $this->insertTeamBoxRow($this->day($n + 5), 1, 2, $this->names[1], 10 + $n);
        }

        $result = $this->assertMatchesReference([
            'v' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertCount(5, $result['v']);
        self::assertSame(16, $result['v'][0]['value']);
    }

    public function testBatchKeepsApostropheLabel(): void
    {
        $this->seedAssistRows([6, 5, 4, 3, 2, 1]);

        $result = $this->assertMatchesReference([
            "O'Neal" => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertArrayHasKey("O'Neal", $result);
        self::assertCount(5, $result["O'Neal"]);
    }

    public function testStatIndexesMatchTeamTableIndexColumns(): void
    {
        $indexes = (new \ReflectionClassConstant(TeamRecordRepository::class, 'STAT_INDEXES'))->getValue();
        self::assertIsArray($indexes);
        self::assertNotSame([], $indexes);

        foreach ($indexes as $expression => $index) {
            $column = substr((string) $expression, strlen('bs.'));
            $columns = [];
            $result = $this->db->query(
                "SELECT SEQ_IN_INDEX, COLUMN_NAME FROM information_schema.STATISTICS
                 WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'ibl_box_scores_teams' AND INDEX_NAME = '" . (string) $index . "'"
            );
            self::assertInstanceOf(\mysqli_result::class, $result);
            while ($row = $result->fetch_assoc()) {
                $columns[(int) $row['SEQ_IN_INDEX']] = (string) $row['COLUMN_NAME'];
            }

            self::assertSame([1 => 'game_type', 2 => $column], $columns, (string) $index);
        }
    }

    public function testThresholdsMatchFifthRankedValue(): void
    {
        $this->seedAssistRows([50, 40, 30, 20, 10, 5, 1]);

        $method = new \ReflectionMethod(TeamRecordRepository::class, 'fetchSingleGameThresholds');
        $thresholds = $method->invoke($this->repo, [
            'desc' => ['expression' => 'bs.game_ast', 'order' => 'DESC'],
            'asc' => ['expression' => 'bs.game_ast', 'order' => 'ASC'],
            'sparse' => ['expression' => 'CASE WHEN bs.game_ast >= 30 THEN bs.game_ast END', 'order' => 'DESC'],
        ], self::MARCH_FILTER);

        self::assertSame(['desc' => 10, 'asc' => 30, 'sparse' => null], $thresholds);
    }

    private function day(int $n): string
    {
        return '2098-03-' . sprintf('%02d', $n + 1);
    }

    /**
     * Seed one row per value on consecutive days, teams 1 (visitor) and 2 (home).
     *
     * @param list<int> $assists
     */
    private function seedAssistRows(array $assists): void
    {
        foreach ($assists as $n => $ast) {
            $this->insertTeamBoxRow($this->day($n), 1, 2, $this->names[1], $ast);
        }
    }

    /**
     * @param list<array{value: int}> $rows
     * @return list<int>
     */
    private function values(array $rows): array
    {
        return array_map(static fn (array $row): int => $row['value'], $rows);
    }

    private function insertTeamBoxRow(string $date, int $visitorTid, int $homeTid, string $name, int $ast): void
    {
        $stmt = $this->db->prepare(
            'INSERT INTO ibl_box_scores_teams
                (game_date, name, game_of_that_day, visitor_teamid, home_teamid,
                 game_2gm, game_ftm, game_3gm, game_orb, game_drb, game_ast, game_stl, game_tov, game_blk)
             VALUES (?, ?, 1, ?, ?, 0, 0, 0, 0, 0, ?, 0, 0, 0)'
        );
        self::assertInstanceOf(\mysqli_stmt::class, $stmt);
        $stmt->bind_param('ssiii', $date, $name, $visitorTid, $homeTid, $ast);
        self::assertTrue($stmt->execute(), $this->db->error);
        $stmt->close();
    }

    /**
     * Runs the frozen pre-change SQL and the repository, asserts they match, returns the repository result.
     *
     * @param array<string, array{expression: string, order: string}> $config
     * @return array<string, list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function assertMatchesReference(array $config, string $filter): array
    {
        $actual = $this->repo->getTopTeamSingleGameBatch($config, $filter);
        self::assertSame($this->referenceBatch($config, $filter), $actual, $filter);

        return $actual;
    }

    /**
     * Frozen copy of getTopTeamSingleGameBatch() before the value cut. Never call it through the repository.
     *
     * @param array<string, array{expression: string, order: string}> $statExpressions
     * @return array<string, list<array{teamid: int, team_name: string, date: string, box_id: int, game_of_that_day: int, oppTid: int, opp_team_name: string, value: int}>>
     */
    private function referenceBatch(array $statExpressions, string $dateFilter): array
    {
        $unions = [];
        foreach ($statExpressions as $label => $config) {
            $safeLabel = str_replace("'", "''", $label);
            $safeOrder = $config['order'] === 'ASC' ? 'ASC' : 'DESC';
            $unions[] = "(SELECT
                    '" . $safeLabel . "' AS stat_type,
                    t.teamid AS teamid,
                    t.team_name,
                    bs.game_date AS `date`,
                    COALESCE(sch.box_id, 0) AS box_id,
                    COALESCE(bs.game_of_that_day, 0) AS game_of_that_day,
                    CASE WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid ELSE bs.visitor_teamid END AS oppTid,
                    opp.team_name AS opp_team_name,
                    " . $config['expression'] . " AS value
                FROM `ibl_box_scores_teams` bs
                JOIN `ibl_team_info` t ON t.team_name = bs.name
                LEFT JOIN `ibl_schedule` sch ON sch.game_date = bs.game_date
                    AND sch.visitor_teamid = bs.visitor_teamid AND sch.home_teamid = bs.home_teamid
                LEFT JOIN `ibl_team_info` opp ON opp.teamid = CASE
                    WHEN t.teamid = bs.visitor_teamid THEN bs.home_teamid
                    ELSE bs.visitor_teamid END
                WHERE " . $dateFilter . "
                    AND bs.visitor_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID . "
                    AND bs.home_teamid BETWEEN 1 AND " . League::MAX_REAL_TEAMID
                . " ORDER BY value " . $safeOrder . ", bs.game_date ASC, bs.id ASC
                LIMIT 5)";
        }

        $query = implode("\nUNION ALL\n", $unions);
        $result = $this->db->query($query);
        self::assertInstanceOf(\mysqli_result::class, $result, $this->db->error);
        $rows = [];
        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        $results = [];
        foreach (array_keys($statExpressions) as $label) {
            $results[$label] = [];
        }

        // MySQL promotes a UNION ALL column to DECIMAL when the branches alias
        // source columns of differing integer widths (here `calc_points`
        // smallint unsigned vs `game_ast` int), and mysqlnd hands DECIMAL back
        // as a PHP string. The merged type therefore depends on which stats are
        // in the batch, so cast here rather than trusting the driver's type.
        foreach ($rows as $row) {
            $label = (string) $row['stat_type'];
            $results[$label][] = [
                'teamid' => (int) $row['teamid'],
                'team_name' => (string) $row['team_name'],
                'date' => (string) $row['date'],
                'box_id' => (int) $row['box_id'],
                'game_of_that_day' => (int) $row['game_of_that_day'],
                'oppTid' => (int) $row['oppTid'],
                'opp_team_name' => (string) $row['opp_team_name'],
                'value' => (int) $row['value'],
            ];
        }

        return $results;
    }
}
