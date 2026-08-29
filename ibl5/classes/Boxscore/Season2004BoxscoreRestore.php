<?php

declare(strict_types=1);

namespace Boxscore;

use mysqli;
use RuntimeException;

/**
 * One-shot repair for the season-2004 boxscore corruption at 2004-02-09.
 *
 * A single 2004 sim wrote a DUPLICATE `Aces @ Jazz` boxscore into the
 * `game_of_that_day = 5` ordinal slot and never wrote the `Suns @ Heat`
 * boxscore that belonged there. Net effect on the standings surface: Aces (16)
 * and Jazz (13) show 83 games, Heat (2) and Suns (23) show 81.
 *
 * The recovered `Suns @ Heat` payload is EMBEDDED HERE AS LITERALS on purpose.
 * Its source (a private, git-excluded repo) is not reachable from a worktree,
 * from CI, or from prod, so nothing may be read at deploy time.
 *
 * Known, deliberate gaps in the recovered payload — do NOT fabricate these:
 *   - `attendance` is NULL (not carried in the source).
 *   - All ten quarter/OT point columns are NULL (not carried in the source).
 * The before-game W/L records ARE known: they were reconstructed from the
 * league's own running-record chain, which counted this game even though its
 * boxscore row was never written.
 *
 * Two faithfully-transcribed source quirks that are NOT transcription slips:
 *   - Team-total rebounds exceed the sum of the player rows (Suns orb 16 vs 15,
 *     drb 41 vs 39; Heat orb 21 vs 20, drb 43 vs 38). That is a team-rebound
 *     artifact of the source format. Every other stat sums exactly, and both
 *     final scores reconcile.
 *   - `Boxscore::teamInsertSql()` does not carry `game_min`, so the restored
 *     team rows land NULL there. That matches the established insert path.
 *
 * @see \Boxscore\PhantomBoxscoreRepair the season-2008 sibling repair
 */
final class Season2004BoxscoreRestore
{
    public const GAME_DATE = '2004-02-09';
    public const GAME_OF_THAT_DAY = 5;

    /** The phantom (duplicate `Aces @ Jazz`) rows that must be removed. */
    public const PHANTOM_VISITOR_TEAMID = 16;
    public const PHANTOM_HOME_TEAMID = 13;

    /** The recovered (`Suns @ Heat`) game that must take the vacated slot. */
    public const RESTORED_VISITOR_TEAMID = 23;
    public const RESTORED_HOME_TEAMID = 2;

    public const VISITOR_WINS = 26;
    public const VISITOR_LOSSES = 15;
    public const HOME_WINS = 15;
    public const HOME_LOSSES = 34;
    public const CAPACITY = 20000;

    public const VISITOR_FINAL_SCORE = 108;
    public const HOME_FINAL_SCORE = 136;

    public const TEAM_TABLE = 'ibl_box_scores_teams';
    public const PLAYER_TABLE = 'ibl_box_scores';
    public const TEAM_BACKUP_TABLE = 'ibl_box_scores_teams_season2004_backup';
    public const PLAYER_BACKUP_TABLE = 'ibl_box_scores_season2004_backup';
    private const RECAP_TABLE = 'ibl_sim_game_recaps';

    /**
     * Fingerprint of the exact rows this repair was designed against, captured
     * from the live database while the plan was written. If the database no
     * longer matches, the repair aborts rather than deleting rows it does not
     * recognise.
     */
    private const EXPECTED = [
        'phantom_ids' => [40833, 40834],
        'phantom_player_rows' => 24,
    ];

    /**
     * Recovered team-total rows, in `Boxscore::teamInsertSql()` stat order.
     * [name, 2gm, 2ga, ftm, fta, 3gm, 3ga, orb, drb, ast, stl, tov, blk, pf]
     */
    private const TEAM_ROWS = [
        ['Suns', 42, 92,  9, 11,  5, 12, 16, 41, 11,  6, 17,  3, 20],
        ['Heat', 45, 87, 13, 22, 11, 26, 21, 43, 21, 11,  7, 11, 13],
    ];

    /**
     * Recovered player rows, 23 of them (11 visitor, 12 home).
     * [uuid, pos, name, pid, teamid, min, 2gm, 2ga, ftm, fta, 3gm, 3ga,
     *  orb, drb, ast, stl, tov, blk, pf]
     *
     * `name` is pre-truncated to the column's varchar(16); three names are
     * shorter here than in the source for exactly that reason.
     */
    private const PLAYER_ROWS = [
        ['11e0c515-79c6-4c9c-b662-cb661289f928', 'PG', 'Mookie Blaylock',  3280, 23, 43,  6, 12, 0,  0, 1,  3, 2,  2,  6, 3,  3, 0, 1],
        ['f3264be5-a80d-49e8-af5a-ff6ebd51ac7f', 'SG', 'Allan Houston',    4167, 23,  1,  1,  1, 0,  0, 0,  0, 0,  0,  0, 0,  0, 0, 0],
        ['6cb10299-af2a-4774-9534-5c75ed94e579', 'SG', 'Todd Day',         2989, 23, 11,  1,  6, 0,  0, 0,  0, 0,  0,  0, 0,  2, 0, 0],
        ['24058acd-5efd-4233-b057-39be602880cb', 'SF', 'Alex English',     4496, 23, 11,  3,  6, 2,  2, 0,  0, 1,  3,  1, 0,  2, 0, 3],
        ['fa4d0363-6571-445c-840e-118920a9e069', 'SF', 'Chet Walker',      4834, 23,  3,  0,  1, 0,  0, 0,  0, 0,  0,  0, 0,  0, 0, 0],
        ['4c5fff2a-c9c6-4dfe-b3a8-ea015baee4b5', 'PF', 'Brittney Griner',  3553, 23,  5,  2,  6, 0,  0, 0,  0, 1,  2,  0, 0,  1, 0, 2],
        ['35e397fa-f70b-48e5-b5c8-32aa95dd442a', 'PF', 'Vin Baker',        4164, 23, 30,  8, 19, 0,  0, 0,  0, 4,  8,  0, 0,  3, 0, 4],
        ['16daeb6b-3ac0-46d6-ad76-2bcbf5ae2e4e', 'C',  'Jalen Smith',      4844, 23, 22,  1,  3, 1,  2, 1,  2, 1,  4,  1, 0,  0, 0, 0],
        ['46a9c4f3-c504-4251-9369-defb61af3056', 'C',  'Darryl Dawkins',   1758, 23, 27,  4,  5, 0,  1, 0,  0, 2,  8,  0, 0,  0, 0, 5],
        ['4d00c6c7-7446-4c49-a639-ac1ecded4544', 'SF', 'Tracy McGrady',    1480, 23, 44, 10, 19, 0,  0, 2,  4, 3,  5,  2, 2,  3, 3, 3],
        ['25334308-20fd-402a-b03c-fda69fcbe35e', 'SF', 'Purvis Short',     2714, 23, 38,  6, 14, 6,  6, 1,  3, 1,  7,  1, 1,  3, 0, 2],
        ['ae730512-6f32-47e3-bae8-d1d2d77a3fdc', 'PG', 'Immanuel Quickle', 4852,  2,  1,  0,  0, 0,  0, 0,  0, 0,  1,  2, 0,  0, 0, 0],
        ['625c165b-fb4d-4724-bb34-a264cb78ef0c', 'PG', 'Ty Lawson',        3872,  2,  0,  0,  0, 0,  0, 0,  0, 0,  0,  0, 0,  0, 0, 0],
        ['b9cd450b-7e59-4826-b7aa-4426a30a2f88', 'SG', 'Anthony Edwards',  4826,  2, 39, 10, 20, 2,  3, 3,  7, 1,  2,  6, 3,  1, 0, 2],
        ['b0affa8b-269c-4ad9-adc9-ae50992b26cf', 'SG', 'Devin Vassell',    4845,  2, 20,  3,  8, 1,  2, 2,  4, 0,  1,  2, 0,  1, 0, 0],
        ['241bbc46-639c-459d-b6ff-c51d62cf88f7', 'SF', 'Clifford Robinso', 3285,  2, 38, 13, 25, 3,  5, 4, 10, 3,  2,  0, 1,  1, 0, 2],
        ['657927eb-4b62-40a1-a20e-df5e67adf235', 'C',  'Nick Richards',    4843,  2, 26,  5,  8, 2,  2, 0,  0, 5,  6,  0, 0,  0, 2, 1],
        ['03a7fec3-974d-4762-9113-0c3fc4f51d70', 'C',  'Ben Wallace',      2983,  2, 30,  2,  5, 0,  0, 0,  0, 5, 13,  0, 1,  2, 5, 4],
        ['93f24357-243e-49fc-8907-0a2726b663c8', 'SG', 'Nick Galis',       1235,  2,  1,  0,  0, 0,  0, 1,  1, 0,  0,  0, 0,  0, 0, 0],
        ['44dff89b-9fd0-4b91-a66c-6c63f17448b9', 'PG', 'Maurice Cheeks',   3279,  2, 40,  4,  8, 0,  2, 1,  4, 1,  1, 11, 4,  0, 0, 0],
        ['68ae0798-f889-48df-86e1-9fc4b23b2836', 'C',  'Ralph Sampson',    3857,  2, 41,  8, 13, 5,  8, 0,  0, 5, 12,  0, 2,  2, 4, 4],
        ['568b8c65-495f-4dbe-9406-2eedea69df9d', 'PG', 'Pierluigi Marzor', 1253,  2,  0,  0,  0, 0,  0, 0,  0, 0,  0,  0, 0,  0, 0, 0],
        ['848c75f4-d727-4779-906d-a1facc0eae84', 'PG', 'Dejounte Murray',  1236,  2,  0,  0,  0, 0,  0, 0,  0, 0,  0,  0, 0,  0, 0, 0],
    ];

    private mysqli $db;

    /** @var array{phantom_ids: list<int>, phantom_player_rows: int} */
    private array $expected;

    /**
     * @param array{phantom_ids: list<int>, phantom_player_rows: int}|null $expectedOverride
     *        Test-only seam: lets an integration fixture assert against its own
     *        seeded ids instead of the production fingerprint.
     */
    public function __construct(mysqli $db, ?array $expectedOverride = null)
    {
        $this->db = $db;
        $this->expected = $expectedOverride ?? self::EXPECTED;
    }

    /**
     * Four-state precondition guard. Returns 'proceed' or 'noop'; throws on any
     * state this repair was not designed for.
     *
     * | phantom | restored | verdict                                   |
     * |---------|----------|-------------------------------------------|
     * | present | absent   | proceed — the repair is needed            |
     * | absent  | present  | noop — already restored (idempotent)      |
     * | absent  | absent   | noop — season not present in this database |
     * | present | present  | ABORT — ambiguous, needs a human           |
     */
    public function assertPreconditions(): string
    {
        $phantomIds = $this->phantomTeamRowIds();
        $restoredCount = $this->restoredTeamRowCount();

        if ($phantomIds === []) {
            // Already restored, or this database simply has no 2004 season.
            // Both are a clean no-op; neither is an error.
            return 'noop';
        }

        if ($restoredCount > 0) {
            throw new RuntimeException(
                'Refusing to run: BOTH the phantom Aces @ Jazz rows and the restored '
                . 'Suns @ Heat rows are present at ' . self::GAME_DATE . ' ordinal '
                . self::GAME_OF_THAT_DAY . '. This is not a state this repair was '
                . 'designed for; a human must reconcile it.'
            );
        }

        $expectedIds = $this->expected['phantom_ids'];
        sort($expectedIds);
        if ($phantomIds !== $expectedIds) {
            throw new RuntimeException(
                'Refusing to delete: phantom team-row ids [' . implode(', ', $phantomIds)
                . '] do not match the ids this repair was designed against ['
                . implode(', ', $expectedIds) . ']. The database has changed since the '
                . 'repair was written.'
            );
        }

        $playerRows = $this->phantomPlayerRowCount();
        if ($playerRows !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Refusing to delete: found ' . $playerRows . ' phantom player rows, '
                . 'expected ' . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $recaps = $this->phantomRecapCount();
        if ($recaps !== 0) {
            throw new RuntimeException(
                'Refusing to delete: ' . $recaps . ' sim game recap(s) reference the '
                . 'phantom coordinate. Deleting the boxscore would orphan them.'
            );
        }

        return 'proceed';
    }

    /**
     * Back up, delete the phantom, insert the recovered game — all inside one
     * transaction.
     *
     * ORDER IS LOAD-BEARING ACROSS OPERATIONS, not only leaves-first within the
     * delete. Backup precedes delete so the removed rows are always recoverable.
     * Delete precedes insert because both games occupy the same natural-key
     * coordinate: inserting first would put two games in one slot and render
     * every page that reads that date nondeterministic.
     *
     * @return array{status: string, deleted: array{teams: int, players: int}, inserted: array{teams: int, players: int}}
     */
    public function runRestore(bool $dryRun): array
    {
        $status = $this->assertPreconditions();

        if ($status === 'noop') {
            return [
                'status' => 'noop',
                'deleted' => ['teams' => 0, 'players' => 0],
                'inserted' => ['teams' => 0, 'players' => 0],
            ];
        }

        $this->db->begin_transaction();

        try {
            $this->fillBackups();
            $this->assertBackupCounts();

            $deleted = $this->deletePhantom();
            $inserted = $this->insertRecoveredGame();
            $this->assertRecoveredScores();

            if ($dryRun) {
                $this->db->rollback();
            } else {
                $this->db->commit();
            }
        } catch (\Throwable $e) {
            $this->db->rollback();
            throw $e;
        }

        return [
            'status' => 'proceed',
            'deleted' => $deleted,
            'inserted' => $inserted,
        ];
    }

    // ---------------------------------------------------------------- reads

    /** @return list<int> ascending phantom team-row ids */
    private function phantomTeamRowIds(): array
    {
        $sql = 'SELECT id FROM ' . self::TEAM_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?
                ORDER BY id';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return array_map('intval', $rows);
    }

    private function restoredTeamRowCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::TEAM_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::RESTORED_VISITOR_TEAMID, self::RESTORED_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    private function phantomPlayerRowCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::PLAYER_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    private function phantomRecapCount(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . self::RECAP_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    /**
     * Runs one of the four-placeholder coordinate queries above.
     *
     * @return list<string>
     */
    private function selectScalars(string $sql, int $visitorTeamid, int $homeTeamid): array
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare query: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitorTeamid, $homeTeamid, $ordinal);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get result set: ' . $this->db->error);
        }
        $values = [];
        /** @var list<array<int, string|null>> $allRows */
        $allRows = $result->fetch_all(MYSQLI_NUM);
        foreach ($allRows as $row) {
            $values[] = (string) ($row[0] ?? '');
        }
        $stmt->close();

        return $values;
    }

    // --------------------------------------------------------------- writes

    /** Copy the phantom rows into the season-2004 backup tables. */
    private function fillBackups(): void
    {
        $this->execCoordinate(
            'INSERT INTO ' . self::TEAM_BACKUP_TABLE . '
             SELECT * FROM ' . self::TEAM_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        $this->execCoordinate(
            'INSERT INTO ' . self::PLAYER_BACKUP_TABLE . '
             SELECT * FROM ' . self::PLAYER_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );
    }

    /** The delete is irreversible without these; verify before running it. */
    private function assertBackupCounts(): void
    {
        $teams = $this->countBackup(self::TEAM_BACKUP_TABLE);
        $players = $this->countBackup(self::PLAYER_BACKUP_TABLE);

        $expectedTeams = count($this->expected['phantom_ids']);
        $expectedPlayers = $this->expected['phantom_player_rows'];

        if ($teams !== $expectedTeams || $players !== $expectedPlayers) {
            throw new RuntimeException(
                'Backup verification failed before delete: backed up ' . $teams
                . ' team rows and ' . $players . ' player rows, expected '
                . $expectedTeams . ' and ' . $expectedPlayers . '.'
            );
        }
    }

    private function countBackup(string $table): int
    {
        $safeTable = match($table) {
            self::TEAM_BACKUP_TABLE => self::TEAM_BACKUP_TABLE,
            self::PLAYER_BACKUP_TABLE => self::PLAYER_BACKUP_TABLE,
            default => throw new \InvalidArgumentException('Unknown backup table: ' . $table),
        };
        $sql = 'SELECT COUNT(*) FROM ' . $safeTable . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?';
        $rows = $this->selectScalars($sql, self::PHANTOM_VISITOR_TEAMID, self::PHANTOM_HOME_TEAMID);

        return (int) ($rows[0] ?? 0);
    }

    /**
     * Leaves first: player rows carry no FK to the team row, but deleting them
     * first keeps the intermediate state readable rather than half-orphaned.
     *
     * @return array{teams: int, players: int}
     */
    private function deletePhantom(): array
    {
        $players = $this->execCoordinate(
            'DELETE FROM ' . self::PLAYER_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        if ($players !== $this->expected['phantom_player_rows']) {
            throw new RuntimeException(
                'Aborting: phantom player delete removed ' . $players . ' rows, expected '
                . $this->expected['phantom_player_rows'] . '.'
            );
        }

        $teams = $this->execCoordinate(
            'DELETE FROM ' . self::TEAM_TABLE . '
             WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
               AND game_of_that_day = ?',
            self::PHANTOM_VISITOR_TEAMID,
            self::PHANTOM_HOME_TEAMID
        );

        $expectedTeams = count($this->expected['phantom_ids']);
        if ($teams !== $expectedTeams) {
            throw new RuntimeException(
                'Aborting: phantom team delete removed ' . $teams . ' rows, expected '
                . $expectedTeams . '.'
            );
        }

        return ['teams' => $teams, 'players' => $players];
    }

    /**
     * Insert the recovered game through the same SQL builders the live import
     * path uses, so the column order can never drift from it.
     *
     * @return array{teams: int, players: int}
     */
    private function insertRecoveredGame(): array
    {
        $teams = 0;
        $stmt = $this->db->prepare(Boxscore::teamInsertSql(self::TEAM_TABLE));
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare team insert: ' . $this->db->error);
        }

        foreach (self::TEAM_ROWS as $team) {
            [$name, $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf] = $team;

            $values = [
                self::GAME_DATE, $name, self::GAME_OF_THAT_DAY,
                self::RESTORED_VISITOR_TEAMID, self::RESTORED_HOME_TEAMID,
                null, self::CAPACITY,
                self::VISITOR_WINS, self::VISITOR_LOSSES, self::HOME_WINS, self::HOME_LOSSES,
                null, null, null, null, null,
                null, null, null, null, null,
                $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf,
            ];

            $this->bindAndExecute($stmt, 'ss' . str_repeat('i', 32), $values);
            $teams += $stmt->affected_rows;
        }
        $stmt->close();

        $players = 0;
        $stmt = $this->db->prepare(Boxscore::playerInsertSql(self::PLAYER_TABLE));
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare player insert: ' . $this->db->error);
        }

        foreach (self::PLAYER_ROWS as $player) {
            [$uuid, $pos, $name, $pid, $teamid, $min, $twoGm, $twoGa, $ftm, $fta,
                $threeGm, $threeGa, $orb, $drb, $ast, $stl, $tov, $blk, $pf] = $player;

            $values = [
                self::GAME_DATE, $uuid, $name, $pos, $pid,
                self::RESTORED_VISITOR_TEAMID, self::RESTORED_HOME_TEAMID, self::GAME_OF_THAT_DAY,
                null, self::CAPACITY,
                self::VISITOR_WINS, self::VISITOR_LOSSES, self::HOME_WINS, self::HOME_LOSSES,
                $teamid, $min,
                $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa,
                $orb, $drb, $ast, $stl, $tov, $blk, $pf,
            ];

            $this->bindAndExecute($stmt, 'ssss' . str_repeat('i', 25), $values);
            $players += $stmt->affected_rows;
        }
        $stmt->close();

        if ($teams !== count(self::TEAM_ROWS) || $players !== count(self::PLAYER_ROWS)) {
            throw new RuntimeException(
                'Aborting: inserted ' . $teams . ' team rows and ' . $players
                . ' player rows, expected ' . count(self::TEAM_ROWS) . ' and '
                . count(self::PLAYER_ROWS) . '.'
            );
        }

        return ['teams' => $teams, 'players' => $players];
    }

    /**
     * Last line of defence against a transcription slip, run inside the
     * transaction so a mismatch rolls the whole repair back. The two final
     * scores are the only fully independent check on 23 hand-copied stat lines.
     */
    private function assertRecoveredScores(): void
    {
        $sql = 'SELECT teamid, SUM(calc_points) FROM ' . self::PLAYER_TABLE . '
                WHERE game_date = ? AND visitor_teamid = ? AND home_teamid = ?
                  AND game_of_that_day = ?
                GROUP BY teamid';

        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare score assertion: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $visitor = self::RESTORED_VISITOR_TEAMID;
        $home = self::RESTORED_HOME_TEAMID;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitor, $home, $ordinal);
        $stmt->execute();

        $result = $stmt->get_result();
        if ($result === false) {
            throw new RuntimeException('Failed to get result set: ' . $this->db->error);
        }
        $scores = [];
        /** @var list<array<int, string|null>> $scoreRows */
        $scoreRows = $result->fetch_all(MYSQLI_NUM);
        foreach ($scoreRows as $row) {
            $scores[(int) ($row[0] ?? '0')] = (int) ($row[1] ?? '0');
        }
        $stmt->close();

        $expected = [
            self::RESTORED_VISITOR_TEAMID => self::VISITOR_FINAL_SCORE,
            self::RESTORED_HOME_TEAMID => self::HOME_FINAL_SCORE,
        ];

        foreach ($expected as $teamid => $points) {
            $actual = $scores[$teamid] ?? null;
            if ($actual !== $points) {
                throw new RuntimeException(
                    'Aborting: restored player points for teamid ' . $teamid . ' sum to '
                    . var_export($actual, true) . ', expected ' . $points
                    . '. The embedded payload does not reconcile with the final score.'
                );
            }
        }
    }

    // ------------------------------------------------------------- plumbing

    /** Runs a four-placeholder coordinate statement; returns affected rows. */
    private function execCoordinate(string $sql, int $visitorTeamid, int $homeTeamid): int
    {
        $stmt = $this->db->prepare($sql);
        if ($stmt === false) {
            throw new RuntimeException('Failed to prepare statement: ' . $this->db->error);
        }

        $date = self::GAME_DATE;
        $ordinal = self::GAME_OF_THAT_DAY;
        $stmt->bind_param('siii', $date, $visitorTeamid, $homeTeamid, $ordinal);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();

        return (int) $affected;
    }

    /**
     * `bind_param()` takes its values by reference, so a row read out of a class
     * constant cannot be splatted into it directly.
     *
     * Binding a PHP null under an 'i' type char sends SQL NULL, which is how the
     * NULL attendance and NULL quarter columns get written.
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
