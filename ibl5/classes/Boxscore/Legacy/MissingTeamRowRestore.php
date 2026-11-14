<?php

declare(strict_types=1);

namespace Boxscore\Legacy;

use Database\BaseMysqliRepository;

/**
 * One-shot repair for two games whose `ibl_box_scores_teams` pair lost one row.
 *
 * Each target has the survivor team row plus 24 `ibl_box_scores` player rows.
 * The missing team row is rebuilt from the 12 player rows of the missing side.
 *
 * `game_orb` / `game_drb` are the player sums. The box file credits some team
 * rebounds to no player, so those two columns are a lower bound. Every other
 * stat column sums exactly.
 *
 * Two passes. Pass 1 evaluates every target and throws before anything is
 * written. Pass 2 inserts every restorable row inside one transaction, so a
 * guard failure on any target leaves the database untouched.
 */
final class MissingTeamRowRestore extends BaseMysqliRepository
{
    public const PLAYER_ROWS_PER_GAME = 24;
    public const PLAYER_ROWS_PER_TEAM = 12;
    public const OUTCOME_ABSENT = 'absent';
    public const OUTCOME_PAIRED = 'already-paired';
    public const OUTCOME_RESTORE = 'restore';
    public const OUTCOME_ABORT = 'abort';

    /** @var list<array{game_date: string, visitor_teamid: int, home_teamid: int, season_year: int, missing_side: 'visitor'|'home', missing_teamid: int, missing_name: string, survivor_name: string, explicit_id: int|null}> */
    public const TARGETS = [
        [
            'game_date' => '1999-11-03',
            'visitor_teamid' => 3,
            'home_teamid' => 4,
            'season_year' => 2000,
            'missing_side' => 'visitor',
            'missing_teamid' => 3,
            'missing_name' => 'Knicks',
            'survivor_name' => 'Nets',
            'explicit_id' => 28587,
        ],
        [
            'game_date' => '2006-10-02',
            'visitor_teamid' => 20,
            'home_teamid' => 28,
            'season_year' => 2007,
            'missing_side' => 'home',
            'missing_teamid' => 28,
            'missing_name' => 'Mavericks',
            'survivor_name' => 'Lakers',
            'explicit_id' => null,
        ],
    ];

    /** Insert column list, mirroring Boxscore::teamInsertSql(). */
    private const COLUMNS = [
        'game_date', 'name', 'game_of_that_day', 'visitor_teamid', 'home_teamid',
        'attendance', 'capacity', 'visitor_wins', 'visitor_losses', 'home_wins', 'home_losses',
        'visitor_q1_points', 'visitor_q2_points', 'visitor_q3_points', 'visitor_q4_points', 'visitor_ot_points',
        'home_q1_points', 'home_q2_points', 'home_q3_points', 'home_q4_points', 'home_ot_points',
        'game_2gm', 'game_2ga', 'game_ftm', 'game_fta', 'game_3gm', 'game_3ga',
        'game_orb', 'game_drb', 'game_ast', 'game_stl', 'game_tov', 'game_blk', 'game_pf',
    ];

    /** Same columns as COLUMNS, as constant SQL text (the DB test pins the pairing). */
    private const COLUMN_LIST = 'game_date, name, game_of_that_day, visitor_teamid, home_teamid, attendance, capacity, visitor_wins, visitor_losses, home_wins, home_losses, visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points, home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points, game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga, game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf';

    private const INSERT_SQL = 'INSERT INTO `ibl_box_scores_teams` (' . self::COLUMN_LIST
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    private const INSERT_WITH_ID_SQL = 'INSERT INTO `ibl_box_scores_teams` (id, ' . self::COLUMN_LIST
        . ') VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)';

    private const SUM_SQL = 'SELECT COUNT(*) AS n, SUM(game_2gm) AS game_2gm, SUM(game_2ga) AS game_2ga, SUM(game_ftm) AS game_ftm, SUM(game_fta) AS game_fta, SUM(game_3gm) AS game_3gm, SUM(game_3ga) AS game_3ga, SUM(game_orb) AS game_orb, SUM(game_drb) AS game_drb, SUM(game_ast) AS game_ast, SUM(game_stl) AS game_stl, SUM(game_tov) AS game_tov, SUM(game_blk) AS game_blk, SUM(game_pf) AS game_pf
        FROM `ibl_box_scores` WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? AND teamid = ?';

    /** Stat columns summed from the missing team's player rows. */
    private const STAT_COLUMNS = [
        'game_2gm', 'game_2ga', 'game_ftm', 'game_fta', 'game_3gm', 'game_3ga',
        'game_orb', 'game_drb', 'game_ast', 'game_stl', 'game_tov', 'game_blk', 'game_pf',
    ];

    /** Columns copied from the survivor row (besides the quarter columns). */
    private const COPIED_COLUMNS = [
        'game_date', 'game_of_that_day', 'visitor_teamid', 'home_teamid',
        'attendance', 'capacity', 'visitor_wins', 'visitor_losses', 'home_wins', 'home_losses',
        'visitor_q1_points', 'visitor_q2_points', 'visitor_q3_points', 'visitor_q4_points', 'visitor_ot_points',
        'home_q1_points', 'home_q2_points', 'home_q3_points', 'home_q4_points', 'home_ot_points',
    ];

    public static function classify(int $teamRows, int $playerRows): string
    {
        if ($teamRows === 2) {
            return self::OUTCOME_PAIRED;
        }
        if ($teamRows === 0 && $playerRows === 0) {
            return self::OUTCOME_ABSENT;
        }
        if ($teamRows === 1 && $playerRows === self::PLAYER_ROWS_PER_GAME) {
            return self::OUTCOME_RESTORE;
        }
        return self::OUTCOME_ABORT;
    }

    /**
     * @return list<array{game_date: string, outcome: string, inserted_id: int|null}>
     *
     * @throws \RuntimeException when any guard fails; nothing has been written then
     */
    public function run(bool $dryRun): array
    {
        /** @var array<int, string> $outcomes */
        $outcomes = [];
        /** @var array<int, array<string, int|string|null>> $payloads */
        $payloads = [];

        // Pass 1: evaluate every target. Throws before any write.
        foreach (self::TARGETS as $index => $target) {
            $evaluated = $this->evaluate($target);
            $outcomes[$index] = $evaluated['outcome'];
            if ($evaluated['payload'] !== null) {
                $payloads[$index] = $evaluated['payload'];
            }
        }

        // Pass 2: insert every restorable row in one transaction.
        /** @var array<int, int> $insertedIds */
        $insertedIds = [];
        if (!$dryRun && $payloads !== []) {
            /** @var array<int, int> $insertedIds */
            $insertedIds = $this->transactional(function () use ($payloads): array {
                $ids = [];
                foreach ($payloads as $index => $payload) {
                    $ids[$index] = $this->insertRow($payload, self::TARGETS[$index]['explicit_id']);
                }
                return $ids;
            });
        }

        $results = [];
        foreach (self::TARGETS as $index => $target) {
            $results[] = [
                'game_date' => $target['game_date'],
                'outcome' => $outcomes[$index],
                'inserted_id' => $insertedIds[$index] ?? null,
            ];
        }

        return $results;
    }

    /**
     * @param array{game_date: string, visitor_teamid: int, home_teamid: int, season_year: int, missing_side: 'visitor'|'home', missing_teamid: int, missing_name: string, survivor_name: string, explicit_id: int|null} $target
     * @return array{outcome: string, payload: array<string, int|string|null>|null}
     */
    private function evaluate(array $target): array
    {
        $date = $target['game_date'];
        $visitor = $target['visitor_teamid'];
        $home = $target['home_teamid'];
        $label = $date . ' ' . $visitor . ' at ' . $home;

        $teamRows = $this->fetchAll(
            'SELECT * FROM `ibl_box_scores_teams` WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ? ORDER BY id',
            'sii',
            $date,
            $visitor,
            $home
        );
        $playerRowCount = $this->fetchOne(
            'SELECT COUNT(*) AS c FROM `ibl_box_scores` WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?',
            'sii',
            $date,
            $visitor,
            $home
        );
        $playerRows = $this->intValue($playerRowCount['c'] ?? 0);

        $outcome = self::classify(count($teamRows), $playerRows);
        if ($outcome === self::OUTCOME_ABSENT || $outcome === self::OUTCOME_PAIRED) {
            return ['outcome' => $outcome, 'payload' => null];
        }
        if ($outcome === self::OUTCOME_ABORT) {
            throw new \RuntimeException(
                '[classify] ' . $label . ': ' . count($teamRows) . ' team row(s) and ' . $playerRows . ' player row(s)'
            );
        }

        $survivor = $teamRows[0];

        if ($survivor['name'] !== $target['survivor_name']) {
            $survivorName = is_string($survivor['name']) ? $survivor['name'] : '';
            throw new \RuntimeException(
                '[survivor-name] ' . $label . ': survivor is "' . $survivorName . '", expected "' . $target['survivor_name'] . '"'
            );
        }

        $sums = $this->sumMissingTeam($target);
        if ($sums['n'] !== self::PLAYER_ROWS_PER_TEAM) {
            throw new \RuntimeException(
                '[player-split] ' . $label . ': ' . $sums['n'] . ' player row(s) for team ' . $target['missing_teamid']
                . ', expected ' . self::PLAYER_ROWS_PER_TEAM
            );
        }

        $side = $target['missing_side'];
        $quarterTotal = 0;
        foreach (['q1', 'q2', 'q3', 'q4', 'ot'] as $period) {
            $quarterTotal += $this->intValue($survivor[$side . '_' . $period . '_points'] ?? 0);
        }
        $playerPoints = 2 * $sums['stats']['game_2gm'] + 3 * $sums['stats']['game_3gm'] + $sums['stats']['game_ftm'];
        if ($playerPoints !== $quarterTotal) {
            throw new \RuntimeException(
                '[points] ' . $label . ': player rows total ' . $playerPoints . ' points, survivor quarters total ' . $quarterTotal
            );
        }

        $evidence = $this->fetchOne(
            'SELECT COALESCE(SUM(visitor_teamid = ? OR home_teamid = ?), 0) AS part,
                    COALESCE(SUM(NOT (visitor_teamid = ? OR home_teamid = ?)), 0) AS nonpart
             FROM `ibl_box_scores_teams` WHERE season_year = ? AND name = ?',
            'iiiiis',
            $target['missing_teamid'],
            $target['missing_teamid'],
            $target['missing_teamid'],
            $target['missing_teamid'],
            $target['season_year'],
            $target['missing_name']
        );
        $part = $this->intValue($evidence['part'] ?? 0);
        $nonpart = $this->intValue($evidence['nonpart'] ?? 0);
        if (!($part >= 1 && $nonpart === 0)) {
            $token = $nonpart !== 0 ? '[name-outside-team]' : '[name-evidence]';
            throw new \RuntimeException(
                $token . ' ' . $label . ': name "' . $target['missing_name'] . '" has ' . $part
                . ' season row(s) with team ' . $target['missing_teamid'] . ' and ' . $nonpart . ' without'
            );
        }

        if ($target['explicit_id'] !== null) {
            $occupied = $this->fetchOne(
                'SELECT COUNT(*) AS c FROM `ibl_box_scores_teams` WHERE id = ?',
                'i',
                $target['explicit_id']
            );
            if ($this->intValue($occupied['c'] ?? 0) !== 0) {
                throw new \RuntimeException('[explicit-id] ' . $label . ': id ' . $target['explicit_id'] . ' is already in use');
            }
        }

        $payload = [];
        foreach (self::COPIED_COLUMNS as $column) {
            $value = $survivor[$column] ?? null;
            $payload[$column] = is_string($value) ? $value : ($value === null ? null : $this->intValue($value));
        }
        $payload['name'] = $target['missing_name'];
        foreach (self::STAT_COLUMNS as $column) {
            $payload[$column] = $sums['stats'][$column];
        }

        return ['outcome' => self::OUTCOME_RESTORE, 'payload' => $payload];
    }

    /**
     * @param array{game_date: string, visitor_teamid: int, home_teamid: int, season_year: int, missing_side: 'visitor'|'home', missing_teamid: int, missing_name: string, survivor_name: string, explicit_id: int|null} $target
     * @return array{n: int, stats: array<string, int>}
     */
    private function sumMissingTeam(array $target): array
    {
        $row = $this->fetchOne(
            self::SUM_SQL,
            'siii',
            $target['game_date'],
            $target['visitor_teamid'],
            $target['home_teamid'],
            $target['missing_teamid']
        );

        $stats = [];
        foreach (self::STAT_COLUMNS as $column) {
            $stats[$column] = $this->intValue($row[$column] ?? 0);
        }

        return ['n' => $this->intValue($row['n'] ?? 0), 'stats' => $stats];
    }

    /**
     * @param array<string, int|string|null> $payload
     */
    private function insertRow(array $payload, ?int $explicitId): int
    {
        $types = 'ss' . str_repeat('i', count(self::COLUMNS) - 2);
        $values = [];
        foreach (self::COLUMNS as $column) {
            $values[] = $payload[$column];
        }
        if ($explicitId !== null) {
            array_unshift($values, $explicitId);
            $this->execute(self::INSERT_WITH_ID_SQL, 'i' . $types, ...$values);

            return $explicitId;
        }

        $this->execute(self::INSERT_SQL, $types, ...$values);

        return $this->getLastInsertId();
    }

    private function intValue(mixed $value): int
    {
        return is_numeric($value) ? (int) $value : 0;
    }
}
