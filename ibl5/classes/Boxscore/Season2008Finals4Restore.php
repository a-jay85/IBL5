<?php

declare(strict_types=1);

namespace Boxscore;

use mysqli;
use RuntimeException;
use Updater\Steps\RefreshPlayoffSeriesResultsStep;

/**
 * One-shot INSERT for the missing 2008 IBL Finals Game 4 boxscore.
 *
 * The 2008-06-25 Knicks @ Clippers game was never written to ibl_box_scores_teams
 * or ibl_box_scores, causing ibl_playoff_series_results to show "3-0" instead of
 * "Clippers 4, Knicks 0". The artifact data/finals2008-g4.rec is the durable source.
 *
 * This is a pure INSERT — no phantom rows exist, no backup tables are needed.
 */
final class Season2008Finals4Restore
{
    public const GAME_DATE        = '2008-06-25';
    public const GAME_OF_THAT_DAY = 1;
    public const VISITOR_TEAMID   = 3;    // Knicks
    public const HOME_TEAMID      = 19;   // Clippers
    public const ATTENDANCE       = 10096;
    public const CAPACITY         = 16285;
    public const VISITOR_WINS     = 58;
    public const VISITOR_LOSSES   = 24;
    public const HOME_WINS        = 56;
    public const HOME_LOSSES      = 26;
    public const VISITOR_Q1       = 31;
    public const VISITOR_Q2       = 34;
    public const VISITOR_Q3       = 39;
    public const VISITOR_Q4       = 30;
    public const VISITOR_OT       = 0;
    public const HOME_Q1          = 35;
    public const HOME_Q2          = 39;
    public const HOME_Q3          = 41;
    public const HOME_Q4          = 32;
    public const HOME_OT          = 0;
    public const VISITOR_FINAL    = 134;
    public const HOME_FINAL       = 147;
    public const TEAM_TABLE       = 'ibl_box_scores_teams';
    public const PLAYER_TABLE     = 'ibl_box_scores';

    private const EXPECTED = [
        'team_rows_2008'   => 1642,
        'player_rows_2008' => 19177,
    ];

    /**
     * Recovered team-total rows, in Boxscore::teamInsertSql() stat order.
     * [name, 2gm, 2ga, ftm, fta, 3gm, 3ga, orb, drb, ast, stl, tov, blk, pf]
     * Visitor (Knicks) = slot 14, home (Clippers) = slot 29.
     */
    private const TEAM_ROWS = [
        ['Knicks',   39, 74, 17, 18, 13, 24, 14, 38, 30,  6, 20, 12, 19],
        ['Clippers', 46, 77, 19, 24, 12, 30, 14, 33, 34, 19,  9,  9, 16],
    ];

    /**
     * Recovered player rows, 24 of them (12 visitor, 12 home).
     * [uuid, pos, name, pid, teamid, min, 2gm, 2ga, ftm, fta, 3gm, 3ga,
     *  orb, drb, ast, stl, tov, blk, pf]
     *
     * uuid placeholder is '' in the constant; insertPlayerRows() generates a real UUID4
     * per row so the UNIQUE constraint is satisfied.
     * name is pre-truncated to varchar(16), matching what the live parser writes.
     */
    private const PLAYER_ROWS = [
        // Knicks (visitor, teamid=3) — slots 0-11 (slots 12-13 empty/DNP)
        ['', 'PG', 'Immanuel Quickle',    4852,  3, 38,  6, 10,  2,  2,  4,  6,  0,  2,  5,  2,  4,  0,  1],
        ['', 'PG', 'Becky Hammon',        5648,  3, 13,  2,  3,  2,  2,  3,  5,  0,  1,  1,  0,  1,  1,  0],
        ['', 'SG', 'Drazen Dalipagic',   5265,  3, 29,  6, 13,  0,  0,  1,  4,  2,  1,  2,  1,  2,  0,  3],
        ['', 'SG', 'Tyreke Evans',        3855,  3, 37,  9, 19,  5,  6,  2,  4,  3,  3, 17,  1,  8,  0,  1],
        ['', 'SF', 'Curt Hennig',         5934,  3, 18,  4,  8,  0,  0,  0,  2,  1,  2,  1,  2,  1,  0,  5],
        ['', 'PF', 'Brandon Clarke',      6329,  3,  9,  1,  2,  0,  0,  0,  0,  0,  0,  1,  0,  0,  1,  2],
        ['', 'PF', 'Candice Dupree',      5267,  3,  7,  0,  1,  4,  4,  0,  0,  2,  3,  0,  0,  0,  0,  4],
        ['', 'C',  'Nikola Milutinov',    4503,  3, 40,  7, 10,  2,  2,  0,  0,  2, 15,  2,  0,  2,  2,  2],
        ['', 'C',  'Erick Dampier',       3568,  3, 38,  4,  5,  2,  2,  0,  0,  4,  9,  0,  0,  2,  8,  1],
        ['', 'SG', 'Desmond Bane',        4832,  3,  6,  0,  3,  0,  0,  3,  3,  0,  0,  1,  0,  0,  0,  0],
        ['', 'PF', 'Brian Skinner',       5968,  3,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        ['', 'PF', 'Jason Maxiell',       5678,  3,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        // Clippers (home, teamid=19) — slots 15-26 (slots 27-28 empty/DNP)
        ['', 'PG', 'Stephen Curry',       3851, 19, 37,  7, 10, 10, 10,  5, 11,  1,  3,  4,  5,  2,  0,  1],
        ['', 'PG', 'TJ Ford',             5294, 19,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        ['', 'SG', 'Kobe Bryant',         3552, 19, 37, 12, 19,  2,  3,  2,  5,  1,  4,  6,  3,  4,  4,  3],
        ['', 'SG', 'Terance Mann',        6359, 19,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        ['', 'SF', 'Issac Okoro',         4851, 19, 17,  2,  3,  1,  2,  2,  4,  0,  0,  2,  3,  0,  0,  3],
        ['', 'PF', 'Dirk Nowitzki',       5929, 19, 42,  7, 15,  3,  3,  2,  5,  2,  7,  4,  0,  1,  4,  2],
        ['', 'PF', 'Dejan Milojevic',     4497, 19, 37,  8, 11,  2,  4,  1,  3,  4, 14,  9,  0,  1,  0,  3],
        ['', 'C',  'J.R. Sakuragi',       6345, 19,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        ['', 'C',  'Zaza Pachulia',       5279, 19,  5,  1,  2,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
        ['', 'PF', 'Chuck Hayes',         5658, 19, 23,  2,  4,  1,  2,  0,  0,  2,  0,  4,  2,  0,  0,  2],
        ['', 'SG', 'Brandon Tomyoy',      3282, 19, 38,  7, 13,  0,  0,  0,  2,  4,  3,  5,  6,  1,  1,  2],
        ['', 'PG', 'Gisueppe Giergia',    4159, 19,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0,  0],
    ];

    private mysqli $db;

    /** @var array{team_rows_2008: int, player_rows_2008: int} */
    private array $expected;

    /**
     * @param array{team_rows_2008: int, player_rows_2008: int}|null $expectedOverride
     *        Test-only seam: lets an integration fixture assert against its own
     *        seeded counts instead of the production fingerprint.
     */
    public function __construct(mysqli $db, ?array $expectedOverride = null)
    {
        $this->db = $db;
        $this->expected = $expectedOverride ?? self::EXPECTED;
    }

    /**
     * Development helper — run once to derive TEAM_ROWS and PLAYER_ROWS.
     * Never called at deploy time.
     *
     * @return array{team_rows: list<list<scalar>>, player_rows: list<list<scalar>>}
     */
    public static function generateFromRec(string $recPath, \mysqli $db): array
    {
        $data = file_get_contents($recPath);
        if ($data === false) {
            throw new RuntimeException('Cannot read artifact: ' . $recPath);
        }

        $gameInfoLine = \JsbParser\ScoFileParser::extractGameInfo($data);
        $boxscore = Boxscore::withGameInfoLine($gameInfoLine, 2008, 'Playoffs');

        /** @var list<\Player\Stats\PlayerStats> $slots */
        $slots = [];
        for ($i = 0; $i < 30; $i++) {
            $slotLine = \JsbParser\ScoFileParser::extractPlayerSlot($data, $i);
            $slots[$i] = \Player\Stats\PlayerStats::withBoxscoreInfoLine($db, $slotLine);
        }

        $teamRows = [];
        $playerRows = [];

        $visitorTotalSlot = \JsbParser\ScoFileParser::VISITOR_SLOT_COUNT - 1;  // 14
        $homeTotalSlot    = \JsbParser\ScoFileParser::PLAYER_SLOT_COUNT - 1;   // 29

        foreach ($slots as $i => $player) {
            $pid = (int) $player->playerID;

            if ($i === $visitorTotalSlot || $i === $homeTotalSlot) {
                // Team-total slot (slot 14 = visitor, slot 29 = home)
                $teamRows[] = [
                    trim(substr($player->name, 0, Boxscore::MAX_PLAYER_NAME_LENGTH)),
                    (int) $player->gameFieldGoalsMade,
                    (int) $player->gameFieldGoalsAttempted,
                    (int) $player->gameFreeThrowsMade,
                    (int) $player->gameFreeThrowsAttempted,
                    (int) $player->gameThreePointersMade,
                    (int) $player->gameThreePointersAttempted,
                    (int) $player->gameOffensiveRebounds,
                    (int) $player->gameDefensiveRebounds,
                    (int) $player->gameAssists,
                    (int) $player->gameSteals,
                    (int) $player->gameTurnovers,
                    (int) $player->gameBlocks,
                    (int) $player->gamePersonalFouls,
                ];
            } elseif ($pid !== 0) {
                // Player row
                $teamid = \JsbParser\ScoFileParser::isHomeTeamSlot($i)
                    ? self::HOME_TEAMID
                    : self::VISITOR_TEAMID;

                $playerRows[] = [
                    '',
                    trim($player->position),
                    substr($player->name, 0, Boxscore::MAX_PLAYER_NAME_LENGTH),
                    $pid,
                    $teamid,
                    (int) $player->gameMinutesPlayed,
                    (int) $player->gameFieldGoalsMade,
                    (int) $player->gameFieldGoalsAttempted,
                    (int) $player->gameFreeThrowsMade,
                    (int) $player->gameFreeThrowsAttempted,
                    (int) $player->gameThreePointersMade,
                    (int) $player->gameThreePointersAttempted,
                    (int) $player->gameOffensiveRebounds,
                    (int) $player->gameDefensiveRebounds,
                    (int) $player->gameAssists,
                    (int) $player->gameSteals,
                    (int) $player->gameTurnovers,
                    (int) $player->gameBlocks,
                    (int) $player->gamePersonalFouls,
                ];
            }
        }

        return [
            'team_rows'   => $teamRows,
            'player_rows' => $playerRows,
            'game_of_that_day' => $boxscore->game_of_that_day,
        ];
    }

    /**
     * Three-state guard + INSERT inside one transaction.
     *
     * State 1 — already inserted: game_date match with visitor/home → noop
     * State 2 — season absent: no 2008 rows in table → noop
     * State 3 — proceed: begin_transaction, insert 2 team + 24 player rows, commit;
     *   then refresh ibl_playoff_series_results outside the transaction.
     *
     * @return array{status: string, dryRun: bool, refreshed: bool, inserted: array{teams: int, players: int}}
     */
    public function runRestore(bool $dryRun): array
    {
        // State 1: already inserted
        if ($this->alreadyInsertedCount() > 0) {
            return ['status' => 'noop', 'dryRun' => $dryRun, 'refreshed' => false, 'inserted' => ['teams' => 0, 'players' => 0]];
        }

        // State 2: season absent (fingerprint check)
        $teamRows2008  = $this->count2008TeamRows();
        $playerRows2008 = $this->count2008PlayerRows();
        if ($teamRows2008 === 0) {
            return ['status' => 'noop', 'dryRun' => $dryRun, 'refreshed' => false, 'inserted' => ['teams' => 0, 'players' => 0]];
        }

        // Fingerprint guard
        if ($teamRows2008 !== $this->expected['team_rows_2008']
            || $playerRows2008 !== $this->expected['player_rows_2008']) {
            return ['status' => 'noop', 'dryRun' => $dryRun, 'refreshed' => false, 'inserted' => ['teams' => 0, 'players' => 0]];
        }

        // State 3: proceed
        $this->db->begin_transaction();

        try {
            $teamsInserted   = $this->insertTeamRows();
            $playersInserted = $this->insertPlayerRows();
            $this->assertRecoveredScores();

            if ($dryRun) {
                $this->db->rollback();
                return ['status' => 'inserted', 'dryRun' => true, 'refreshed' => false,
                        'inserted' => ['teams' => $teamsInserted, 'players' => $playersInserted]];
            }

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        // Refresh playoff series results outside the transaction (not under dry-run).
        $step = new RefreshPlayoffSeriesResultsStep($this->db);
        $stepResult = $step->execute();

        return [
            'status'   => 'inserted',
            'dryRun'   => false,
            'refreshed' => $stepResult->success,
            'inserted' => ['teams' => $teamsInserted, 'players' => $playersInserted],
        ];
    }

    // ---------------------------------------------------------------- reads

    private function alreadyInsertedCount(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . self::TEAM_TABLE
            . ' WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
            . ' AND game_of_that_day = ?'
        );
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $this->db->error);
        }
        $date    = self::GAME_DATE;
        $visitor = self::VISITOR_TEAMID;
        $home    = self::HOME_TEAMID;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Query failed: ' . $this->db->error);
        }
        $row = $result->fetch_row();
        $stmt->close();

        return (int) ($row[0] ?? 0);
    }

    private function count2008TeamRows(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . self::TEAM_TABLE . " WHERE game_date LIKE '2008-%'"
        );
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $this->db->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Query failed: ' . $this->db->error);
        }
        $row = $result->fetch_row();
        $stmt->close();

        return (int) ($row[0] ?? 0);
    }

    private function count2008PlayerRows(): int
    {
        $stmt = $this->db->prepare(
            'SELECT COUNT(*) FROM ' . self::PLAYER_TABLE . " WHERE game_date LIKE '2008-%'"
        );
        if ($stmt === false) {
            throw new RuntimeException('Prepare failed: ' . $this->db->error);
        }
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Query failed: ' . $this->db->error);
        }
        $row = $result->fetch_row();
        $stmt->close();

        return (int) ($row[0] ?? 0);
    }

    // --------------------------------------------------------------- writes

    private function insertTeamRows(): int
    {
        $inserted = 0;
        $stmt = $this->db->prepare(Boxscore::teamInsertSql(self::TEAM_TABLE));
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare team insert: ' . $this->db->error);
        }

        foreach (self::TEAM_ROWS as $team) {
            [$name, $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf] = $team;

            $values = [
                self::GAME_DATE, $name, self::GAME_OF_THAT_DAY,
                self::VISITOR_TEAMID, self::HOME_TEAMID,
                self::ATTENDANCE, self::CAPACITY,
                self::VISITOR_WINS, self::VISITOR_LOSSES, self::HOME_WINS, self::HOME_LOSSES,
                self::VISITOR_Q1, self::VISITOR_Q2, self::VISITOR_Q3, self::VISITOR_Q4, self::VISITOR_OT,
                self::HOME_Q1, self::HOME_Q2, self::HOME_Q3, self::HOME_Q4, self::HOME_OT,
                $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf,
            ];

            $this->bindAndExecute($stmt, 'ss' . str_repeat('i', 32), $values);
            $inserted += $stmt->affected_rows;
        }
        $stmt->close();

        return $inserted;
    }

    private function insertPlayerRows(): int
    {
        $inserted = 0;
        $stmt = $this->db->prepare(Boxscore::playerInsertSql(self::PLAYER_TABLE));
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare player insert: ' . $this->db->error);
        }

        foreach (self::PLAYER_ROWS as $player) {
            [, $pos, $name, $pid, $teamid, $min, $twoGm, $twoGa, $ftm, $fta,
                $threeGm, $threeGa, $orb, $drb, $ast, $stl, $tov, $blk, $pf] = $player;

            $uuid = $this->generateUuid4();

            $values = [
                self::GAME_DATE, $uuid, $name, $pos, $pid,
                self::VISITOR_TEAMID, self::HOME_TEAMID, self::GAME_OF_THAT_DAY,
                self::ATTENDANCE, self::CAPACITY,
                self::VISITOR_WINS, self::VISITOR_LOSSES, self::HOME_WINS, self::HOME_LOSSES,
                $teamid, $min,
                $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf,
            ];

            $this->bindAndExecute($stmt, 'ssss' . str_repeat('i', 25), $values);
            $inserted += $stmt->affected_rows;
        }
        $stmt->close();

        return $inserted;
    }

    private function assertRecoveredScores(): void
    {
        $sql = 'SELECT teamid, SUM(calc_points) AS pts FROM ' . self::PLAYER_TABLE
            . ' WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?'
            . ' AND game_of_that_day = ?'
            . ' GROUP BY teamid';

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare score assertion: ' . $this->db->error);
        }

        $date    = self::GAME_DATE;
        $visitor = self::VISITOR_TEAMID;
        $home    = self::HOME_TEAMID;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get result set: ' . $this->db->error);
        }
        $scores = [];
        $rows = $result->fetch_all(MYSQLI_NUM);
        foreach ($rows as $row) {
            $scores[(int) ($row[0] ?? 0)] = (int) ($row[1] ?? 0);
        }
        $stmt->close();

        $expected = [
            self::VISITOR_TEAMID => self::VISITOR_FINAL,
            self::HOME_TEAMID    => self::HOME_FINAL,
        ];

        foreach ($expected as $teamid => $points) {
            $actual = $scores[$teamid] ?? null;
            if ($actual !== $points) {
                throw new RuntimeException(
                    'Score assertion failed: teamid ' . $teamid . ' sums to '
                    . var_export($actual, true) . ', expected ' . $points . '.'
                );
            }
        }
    }

    // ------------------------------------------------------------- plumbing

    private function generateUuid4(): string
    {
        return sprintf(
            '%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
    }

    /**
     * bind_param() takes values by reference — a row from a class constant cannot
     * be splatted into it directly.
     *
     * @param list<mixed> $values
     */
    private function bindAndExecute(\mysqli_stmt $stmt, string $types, array $values): void
    {
        $refs = [];
        foreach (array_keys($values) as $key) {
            $refs[$key] = &$values[$key];
        }

        $stmt->bind_param($types, ...$refs);
        $stmt->execute();
    }
}
