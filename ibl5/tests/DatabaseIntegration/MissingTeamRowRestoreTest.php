<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\Legacy\MissingTeamRowRestore;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for MissingTeamRowRestore.
 *
 * Runs inside DatabaseTestCase's outer transaction. The class uses transactional(),
 * which nests through a SAVEPOINT, so every test rolls back cleanly.
 *
 * @see MissingTeamRowRestore The class under test
 */
#[Group('database')]
final class MissingTeamRowRestoreTest extends DatabaseTestCase
{
    private const STAT_COLUMNS = [
        'game_2gm', 'game_2ga', 'game_ftm', 'game_fta', 'game_3gm', 'game_3ga',
        'game_orb', 'game_drb', 'game_ast', 'game_stl', 'game_tov', 'game_blk', 'game_pf',
    ];

    /** Missing-team stat totals per target index, in STAT_COLUMNS order. */
    private const TOTALS = [
        0 => [44, 84, 14, 17, 9, 17, 13, 44, 22, 11, 8, 8, 15],
        1 => [45, 83, 9, 12, 9, 17, 10, 34, 30, 12, 15, 9, 12],
    ];

    /** Survivor row id, quarters (visitor q1-q4, home q1-q4), name, evidence game. */
    private const SEEDS = [
        0 => [
            'survivor_id' => 28588,
            'survivor_name' => 'Nets',
            'visitor_q' => [32, 33, 34, 30],
            'home_q' => [22, 17, 28, 23],
            'evidence_date' => '1999-12-01',
            'evidence_visitor' => 3,
            'evidence_home' => 5,
            'evidence_name' => 'Knicks',
        ],
        1 => [
            'survivor_id' => 47483,
            'survivor_name' => 'Lakers',
            'visitor_q' => [38, 24, 26, 36],
            'home_q' => [32, 35, 31, 28],
            'evidence_date' => '2006-11-05',
            'evidence_visitor' => 21,
            'evidence_home' => 28,
            'evidence_name' => 'Mavericks',
        ],
    ];

    protected function setUp(): void
    {
        parent::setUp();

        foreach ([28587, 28588, 47483] as $id) {
            self::assertSame(0, $this->scalarCount('SELECT COUNT(*) FROM ibl_box_scores_teams WHERE id = ' . $id), 'Seed occupies id ' . $id);
        }
        foreach (MissingTeamRowRestore::TARGETS as $index => $target) {
            self::assertSame(0, $this->triple($index), 'Seed occupies ' . $target['game_date']);
        }
    }

    public function testRestoresMissingVisitorRowFor1999Game(): void
    {
        $this->seedTarget(0);
        $target = 0;
        self::assertSame(1, $this->triple($target));
        $survivorBefore = $this->rowById(28588);

        $results = $this->restore()->run(false);

        self::assertSame(MissingTeamRowRestore::OUTCOME_RESTORE, $results[0]['outcome']);
        self::assertSame(28587, $results[0]['inserted_id']);
        self::assertSame(2, $this->triple($target));
        $rows = $this->tripleRows($target);
        self::assertSame([28587, 28588], array_map(static fn (array $r): int => (int) $r['id'], $rows));
        $new = $rows[0];
        self::assertSame('Knicks', $new['name']);
        self::assertSame(array_combine(self::STAT_COLUMNS, self::TOTALS[0]), $this->stats($new));
        self::assertSame($survivorBefore['visitor_q1_points'], $new['visitor_q1_points']);
        self::assertSame($survivorBefore['home_q4_points'], $new['home_q4_points']);
        self::assertSame($survivorBefore['attendance'], $new['attendance']);
        self::assertSame($survivorBefore['visitor_wins'], $new['visitor_wins']);
        self::assertNull($new['game_min']);
        self::assertSame($survivorBefore, $this->rowById(28588));
    }

    public function testRestoresMissingHomeRowFor2006Game(): void
    {
        $this->seedTarget(1);
        $target = 1;
        self::assertSame(1, $this->triple($target));

        $results = $this->restore()->run(false);

        self::assertSame(MissingTeamRowRestore::OUTCOME_RESTORE, $results[1]['outcome']);
        self::assertSame(2, $this->triple($target));
        $rows = $this->tripleRows($target);
        self::assertSame('Lakers', $rows[0]['name']);
        self::assertSame(47483, (int) $rows[0]['id']);
        $new = $rows[1];
        self::assertGreaterThan(47483, (int) $new['id']);
        self::assertSame($results[1]['inserted_id'], (int) $new['id']);
        self::assertSame('Mavericks', $new['name']);
        self::assertSame(array_combine(self::STAT_COLUMNS, self::TOTALS[1]), $this->stats($new));
    }

    public function testSecondRunChangesNothing(): void
    {
        $this->seedTarget(0);
        $this->seedTarget(1);
        $this->restore()->run(false);
        $countBefore = $this->scalarCount('SELECT COUNT(*) FROM ibl_box_scores_teams');

        $results = $this->restore()->run(false);

        self::assertSame(MissingTeamRowRestore::OUTCOME_PAIRED, $results[0]['outcome']);
        self::assertSame(MissingTeamRowRestore::OUTCOME_PAIRED, $results[1]['outcome']);
        self::assertNull($results[0]['inserted_id']);
        self::assertSame($countBefore, $this->scalarCount('SELECT COUNT(*) FROM ibl_box_scores_teams'));
    }

    public function testAbsentTriplesAreNoOp(): void
    {
        $countBefore = $this->scalarCount('SELECT COUNT(*) FROM ibl_box_scores_teams');

        $results = $this->restore()->run(false);

        self::assertSame(MissingTeamRowRestore::OUTCOME_ABSENT, $results[0]['outcome']);
        self::assertSame(MissingTeamRowRestore::OUTCOME_ABSENT, $results[1]['outcome']);
        self::assertSame($countBefore, $this->scalarCount('SELECT COUNT(*) FROM ibl_box_scores_teams'));
    }

    public function testAbortWritesNothingForAnyTarget(): void
    {
        $this->seedTarget(0);
        $this->seedTarget(1, ['missing_players' => 11]);

        $this->assertAborts('[classify]');

        self::assertSame(1, $this->triple(0));
        self::assertSame(1, $this->triple(1));
    }

    public function testAbortsWhenPlayerSplitIsWrong(): void
    {
        $this->seedTarget(0, ['missing_players' => 11, 'survivor_players' => 13]);

        $this->assertAborts('[player-split]');

        self::assertSame(1, $this->triple(0));
    }

    public function testAbortsWhenSurvivorNameIsUnexpected(): void
    {
        $this->seedTarget(0, ['survivor_name' => 'Knicks']);

        $this->assertAborts('[survivor-name]');

        self::assertSame(1, $this->triple(0));
    }

    public function testAbortsWhenPointsDisagreeWithSurvivorQuarters(): void
    {
        $this->seedTarget(0, ['visitor_q4' => 31]);

        $this->assertAborts('[points]');

        self::assertSame(1, $this->triple(0));
    }

    public function testAbortsWhenNameEvidenceIsAbsent(): void
    {
        $this->seedTarget(0, ['evidence' => false]);

        $this->assertAborts('[name-evidence]');

        self::assertSame(1, $this->triple(0));
    }

    public function testAbortsWhenNameSeenOutsideTeam(): void
    {
        $this->seedTarget(0);
        $this->insertTeamBoxscoreRow('1999-12-02', 'Knicks', 2, 6, 7);

        $this->assertAborts('[name-outside-team]');

        self::assertSame(1, $this->triple(0));
    }

    public function testAbortsWhenExplicitIdIsOccupied(): void
    {
        $this->seedTarget(0);
        $this->insertRow('ibl_box_scores_teams', $this->teamRowData('1999-12-03', 'Other', 2, 8, 9) + ['id' => 28587]);

        $this->assertAborts('[explicit-id]');

        self::assertSame(1, $this->triple(0));
    }

    public function testDryRunWritesNothing(): void
    {
        $this->seedTarget(0);
        $this->seedTarget(1);

        $results = $this->restore()->run(true);

        self::assertSame(MissingTeamRowRestore::OUTCOME_RESTORE, $results[0]['outcome']);
        self::assertSame(MissingTeamRowRestore::OUTCOME_RESTORE, $results[1]['outcome']);
        self::assertNull($results[0]['inserted_id']);
        self::assertSame(1, $this->triple(0));
        self::assertSame(1, $this->triple(1));
    }

    private function restore(): MissingTeamRowRestore
    {
        return new MissingTeamRowRestore($this->db);
    }

    private function assertAborts(string $token): void
    {
        try {
            $this->restore()->run(false);
        } catch (\RuntimeException $e) {
            self::assertStringContainsString($token, $e->getMessage());
            return;
        }
        self::fail('Expected run() to abort with ' . $token);
    }

    /**
     * Seeds one target's survivor state: survivor team row, player rows for both
     * teams, and the same-season name-evidence row.
     *
     * @param array{missing_players?: int, survivor_players?: int, survivor_name?: string, visitor_q4?: int, evidence?: bool} $opts
     */
    private function seedTarget(int $index, array $opts = []): void
    {
        $target = MissingTeamRowRestore::TARGETS[$index];
        $seed = self::SEEDS[$index];
        $date = $target['game_date'];
        $visitor = $target['visitor_teamid'];
        $home = $target['home_teamid'];
        $survivorTid = $target['missing_side'] === 'visitor' ? $home : $visitor;
        $missingTid = $target['missing_teamid'];

        $data = $this->teamRowData($date, $opts['survivor_name'] ?? $seed['survivor_name'], 3, $visitor, $home);
        $data['id'] = $seed['survivor_id'];
        foreach ([1, 2, 3, 4] as $i) {
            $data['visitor_q' . $i . '_points'] = $seed['visitor_q'][$i - 1];
            $data['home_q' . $i . '_points'] = $seed['home_q'][$i - 1];
        }
        $data['visitor_ot_points'] = 0;
        $data['home_ot_points'] = 0;
        if (isset($opts['visitor_q4'])) {
            $data['visitor_q4_points'] = $opts['visitor_q4'];
        }
        $this->insertRow('ibl_box_scores_teams', $data);

        $base = 200100000 + $index * 100;
        $survivorPlayers = $opts['survivor_players'] ?? 12;
        $missingPlayers = $opts['missing_players'] ?? 12;
        for ($n = 1; $n <= $survivorPlayers; $n++) {
            $this->seedPlayer($base + $n, $date, $visitor, $home, $survivorTid, array_fill(0, 13, 0));
        }
        $totals = self::TOTALS[$index];
        for ($n = 1; $n <= $missingPlayers; $n++) {
            $stats = match ($n) {
                1 => array_map(static fn (int $t): int => $t - 1, $totals),
                2 => array_fill(0, 13, 1),
                default => array_fill(0, 13, 0),
            };
            $this->seedPlayer($base + 20 + $n, $date, $visitor, $home, $missingTid, $stats);
        }

        if (($opts['evidence'] ?? true) === true) {
            $this->insertTeamBoxscoreRow($seed['evidence_date'], $seed['evidence_name'], 1, $seed['evidence_visitor'], $seed['evidence_home']);
        }
    }

    /**
     * @param list<int> $s Stats in STAT_COLUMNS order
     */
    private function seedPlayer(int $pid, string $date, int $visitor, int $home, int $teamId, array $s): void
    {
        $this->insertTestPlayer($pid, 'Seed' . $pid);
        $this->insertPlayerBoxscoreRow(
            $date,
            $pid,
            'Seed' . $pid,
            'PG',
            $visitor,
            $home,
            $teamId,
            30,
            $s[0],
            $s[1],
            $s[2],
            $s[3],
            $s[4],
            $s[5],
            $s[6],
            $s[7],
            $s[8],
            $s[9],
            $s[10],
            $s[11],
            $s[12],
            ['game_of_that_day' => 3],
        );
    }

    /**
     * @return array<string, int|string>
     */
    private function teamRowData(string $date, string $name, int $gameOfDay, int $visitor, int $home): array
    {
        return [
            'game_date' => $date,
            'name' => $name,
            'game_of_that_day' => $gameOfDay,
            'visitor_teamid' => $visitor,
            'home_teamid' => $home,
            'attendance' => 10000,
            'capacity' => 15000,
            'visitor_wins' => 20,
            'visitor_losses' => 10,
            'home_wins' => 25,
            'home_losses' => 5,
            'visitor_q1_points' => 20,
            'visitor_q2_points' => 22,
            'visitor_q3_points' => 18,
            'visitor_q4_points' => 25,
            'visitor_ot_points' => 0,
            'home_q1_points' => 28,
            'home_q2_points' => 24,
            'home_q3_points' => 22,
            'home_q4_points' => 30,
            'home_ot_points' => 0,
            'game_2gm' => 30,
            'game_2ga' => 60,
            'game_ftm' => 15,
            'game_fta' => 20,
            'game_3gm' => 8,
            'game_3ga' => 22,
            'game_orb' => 10,
            'game_drb' => 30,
            'game_ast' => 20,
            'game_stl' => 8,
            'game_tov' => 12,
            'game_blk' => 5,
            'game_pf' => 18,
        ];
    }

    private function triple(int $index): int
    {
        $target = MissingTeamRowRestore::TARGETS[$index];

        return $this->scalarCount(
            "SELECT COUNT(*) FROM ibl_box_scores_teams WHERE game_date = '" . $target['game_date']
            . "' AND visitor_teamid = " . $target['visitor_teamid'] . ' AND home_teamid = ' . $target['home_teamid']
        );
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function tripleRows(int $index): array
    {
        $target = MissingTeamRowRestore::TARGETS[$index];

        $result = $this->db->query(
            "SELECT * FROM ibl_box_scores_teams WHERE game_date = '" . $target['game_date']
            . "' AND visitor_teamid = " . $target['visitor_teamid'] . ' AND home_teamid = ' . $target['home_teamid']
            . ' ORDER BY id'
        );
        self::assertInstanceOf(\mysqli_result::class, $result);

        return array_values($result->fetch_all(MYSQLI_ASSOC));
    }

    /**
     * @return array<string, mixed>
     */
    private function rowById(int $id): array
    {
        $result = $this->db->query('SELECT * FROM ibl_box_scores_teams WHERE id = ' . $id);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        self::assertIsArray($row);

        return $row;
    }

    /**
     * @param array<string, mixed> $row
     * @return array<string, mixed>
     */
    private function stats(array $row): array
    {
        $stats = [];
        foreach (self::STAT_COLUMNS as $column) {
            $stats[$column] = $row[$column];
        }

        return $stats;
    }

    private function scalarCount(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_row();
        self::assertIsArray($row);

        return (int) $row[0];
    }
}
