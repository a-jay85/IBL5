<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\Season1993PhantomRepair;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Season1993PhantomRepair.
 *
 * Transaction isolation note: Season1993PhantomRepair::runRepair() always calls
 * $this->db->begin_transaction() internally on the 'proceed' path, which in
 * MariaDB implicitly commits any already-open transaction. DatabaseTestCase's
 * outer T1 (opened by setUp()) is committed the moment runRepair() reaches that
 * call.
 *
 * Tests that only call assertPreconditions() — or that trigger a noop/throw path
 * before begin_transaction() fires — still benefit from T1's rollback in tearDown().
 *
 * Tests that call runRepair() on the 'proceed' path leave committed state in the
 * test database and must call cleanAll() before seeding so repeated runs within a
 * suite execution work correctly. tearDownAfterClass() removes everything this
 * class commits.
 *
 * Prerequisites: migration 180 (backup tables) must be applied before this suite
 * runs. bin/db-test-up fix-1993-phantom-warriors-sonics-boxscore applies it
 * automatically via the worktree's migrations/ directory.
 *
 * @see Season1993PhantomRepair The class under test
 */
#[Group('database')]
final class Season1993PhantomRepairTest extends DatabaseTestCase
{
    /** Number of phantom player rows seeded by the fixture (12 per team). */
    private const PHANTOM_PLAYER_COUNT = 24;

    /**
     * First PID for phantom player rows (24 total: 200001993–200002016).
     * Uses 9-digit values (ibl_plr.pid is INT, max ~2.1 billion).
     */
    private const PHANTOM_PID_START = 200001993;

    /** First PID for real-game player rows (3 total: 200001930–200001932). */
    private const REAL_PID_START = 200001930;

    /** Sim ID for ibl_sim_game_recaps; FK-constrained to ibl_sim_summaries. */
    private const TEST_SIM = 997;

    /**
     * Removes all rows this class commits to the shared test database.
     *
     * A dedicated connection is required because tearDown() rolls back its own
     * connection, so deletes issued on it would be discarded along with the
     * per-test transaction.
     *
     * Cleanup order (mirrors the runRepair delete order):
     *  1. Backup tables at the phantom coordinate.
     *  2. Live boxscore tables at all three seeded coordinates.
     *  3. Sim recap + sim summary rows.
     *  4. ibl_plr rows in the test PID band.
     */
    public static function tearDownAfterClass(): void
    {
        $db = new \mysqli();
        $db->real_connect(
            (string) getenv('DB_HOST'),
            (string) getenv('DB_USER'),
            (string) getenv('DB_PASS'),
            (string) getenv('DB_NAME')
        );

        $d  = Season1993PhantomRepair::GAME_DATE;
        $v  = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h  = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g  = Season1993PhantomRepair::GAME_OF_THAT_DAY;
        $gr = Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY;

        // 1. Backup tables at the phantom coordinate.
        $db->query("DELETE FROM ibl_box_scores_teams_season1993_phantom_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $db->query("DELETE FROM ibl_box_scores_season1993_phantom_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");

        // 2. Live boxscore tables at all three seeded coordinates.
        // Phantom coordinate (visitor 24 @ home 22, ordinal 1).
        $db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        // Real game coordinate (same teams, ordinal 5).
        $db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr");
        $db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr");
        // Bystander coordinate (visitor 3 @ home 4, ordinal 1).
        $db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=3 AND home_teamid=4 AND game_of_that_day=1");

        // 3. Sim recap rows must be deleted before sim summaries (FK).
        $db->query('DELETE FROM ibl_sim_game_recaps WHERE sim = ' . self::TEST_SIM);
        $db->query('DELETE FROM ibl_sim_summaries WHERE sim = ' . self::TEST_SIM);

        // 4. ibl_plr rows — boxscore FK cleared above, so these are safe now.
        // Range covers both phantom PIDs (200001993–200002016) and real PIDs (200001930–200001932).
        $db->query("DELETE FROM ibl_plr WHERE pid BETWEEN " . self::REAL_PID_START . " AND 200002020 AND uuid LIKE 'test-1993-%'");

        $db->close();

        parent::tearDownAfterClass();
    }

    // ─────────────────────────────────────────── helpers ──────────────────────

    /** @param array{phantom_ids: list<int>, phantom_player_rows: int, phantom_player_id_range: array{int, int}}|null $expectedOverride */
    private function makeRepair(?array $expectedOverride): Season1993PhantomRepair
    {
        return new Season1993PhantomRepair($this->db, $expectedOverride);
    }

    /**
     * Seeds 2 phantom team rows and 24 phantom player rows at the phantom
     * coordinate (Warriors @ Sonics, game_of_that_day=1).
     *
     * Reads the auto-increment ids of the team rows and the MIN/MAX id of the
     * player rows from the DB so the caller can build $expectedOverride.
     *
     * Requires that the 24 phantom PIDs already exist in ibl_plr (FK constraint).
     * Call seedPhantomPidsInPlr() before this method in runRepair tests, or
     * insertPidIgnore() for each PID in T1 rollback tests.
     *
     * @return array{phantom_ids: list<int>, phantom_player_rows: int, phantom_player_id_range: array{int, int}}
     */
    private function seedPhantomRows(): array
    {
        $id1 = $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Warriors',
            Season1993PhantomRepair::GAME_OF_THAT_DAY,
            Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
            Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
        );
        $id2 = $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Sonics',
            Season1993PhantomRepair::GAME_OF_THAT_DAY,
            Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
            Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
        );

        // 12 players on the visitor (Warriors, teamid 24), 12 on the home (Sonics, teamid 22).
        for ($i = 0; $i < 12; $i++) {
            $pid = self::PHANTOM_PID_START + $i;
            $this->insertPlayerBoxscoreRow(
                Season1993PhantomRepair::GAME_DATE,
                $pid,
                'PhantomV' . $i,
                'PG',
                Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
                Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
                Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
                overrides: [
                    'game_of_that_day' => Season1993PhantomRepair::GAME_OF_THAT_DAY,
                    'uuid' => sprintf('test-1993-%d', $pid),
                ],
            );
        }
        for ($i = 12; $i < 24; $i++) {
            $pid = self::PHANTOM_PID_START + $i;
            $this->insertPlayerBoxscoreRow(
                Season1993PhantomRepair::GAME_DATE,
                $pid,
                'PhantomH' . ($i - 12),
                'PG',
                Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
                Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
                Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
                overrides: [
                    'game_of_that_day' => Season1993PhantomRepair::GAME_OF_THAT_DAY,
                    'uuid' => sprintf('test-1993-%d', $pid),
                ],
            );
        }

        $ids = [$id1, $id2];
        sort($ids);

        $d = Season1993PhantomRepair::GAME_DATE;
        $v = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g = Season1993PhantomRepair::GAME_OF_THAT_DAY;

        $rangeResult = $this->db->query(
            "SELECT MIN(id) AS min_id, MAX(id) AS max_id FROM ibl_box_scores
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g"
        );
        self::assertNotFalse($rangeResult, 'Player id range query failed: ' . $this->db->error);
        /** @var array{min_id: int|string, max_id: int|string} $rangeRow */
        $rangeRow = $rangeResult->fetch_assoc();
        $rangeResult->free();

        return [
            'phantom_ids'              => $ids,
            'phantom_player_rows'      => self::PHANTOM_PLAYER_COUNT,
            'phantom_player_id_range'  => [(int) $rangeRow['min_id'], (int) $rangeRow['max_id']],
        ];
    }

    /**
     * Seeds 2 team rows and 3 player rows at the real-game coordinate
     * (same teams, game_of_that_day=5).
     *
     * Requires the 3 real PIDs to be in ibl_plr before this call.
     */
    private function seedRealGameRows(): void
    {
        $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Warriors',
            Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY,
            Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
            Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
        );
        $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Sonics',
            Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY,
            Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
            Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
        );

        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPlayerBoxscoreRow(
                Season1993PhantomRepair::GAME_DATE,
                $pid,
                'Real' . $i,
                'PG',
                Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
                Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
                $teamid,
                overrides: [
                    'game_of_that_day' => Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY,
                    'uuid' => sprintf('test-1993-real-%d', $pid),
                ],
            );
        }
    }

    /**
     * Seeds 2 team rows at the bystander coordinate (visitor=3, home=4, ordinal=1).
     * No player rows; bystander teams have no FK dependency on ibl_plr.
     */
    private function seedBystanderRows(): void
    {
        $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Bystander1',
            1,
            3,
            4,
        );
        $this->insertTeamBoxscoreRow(
            Season1993PhantomRepair::GAME_DATE,
            'Bystander2',
            1,
            3,
            4,
        );
    }

    /**
     * INSERT IGNORE a single player into ibl_plr with a test-1993-prefixed uuid.
     *
     * INSERT IGNORE is safe to call repeatedly: if the pid already exists (from a
     * previous committed runRepair test), it is a no-op. Inside T1 the insert is
     * rolled back; as a committed INSERT IGNORE it persists for tearDownAfterClass.
     */
    private function insertPidIgnore(int $pid, string $name, int $teamid = 1): void
    {
        $uuid = sprintf('test-1993-%d', $pid);
        $stmt = $this->db->prepare(
            'INSERT IGNORE INTO ibl_plr
             (pid, name, age, teamid, pos, stamina, exp, bird, cy, cyt, salary_yr1, salary_yr2, retired, ordinal, droptime, uuid)
             VALUES (?, ?, 27, ?, \'PG\', 80, 5, 3, 1, 3, 1500, 1600, 0, 1, 0, ?)'
        );
        self::assertNotFalse($stmt, 'Prepare insertPidIgnore failed: ' . $this->db->error);
        $stmt->bind_param('isis', $pid, $name, $teamid, $uuid);
        $stmt->execute();
        $stmt->close();
    }

    /**
     * INSERT IGNORE all 24 phantom player PIDs into ibl_plr.
     * Called before cleanAll()+seedPhantomRows() in runRepair tests.
     */
    private function seedPhantomPidsInPlr(): void
    {
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
    }

    /**
     * INSERT IGNORE the 3 real-game player PIDs into ibl_plr.
     * Called before seedRealGameRows() in runRepair tests.
     */
    private function seedRealPidsInPlr(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }
    }

    /**
     * Deletes all rows seeded by this class from both live tables and backup tables.
     *
     * In runRepair tests, call this before seeding; the deletes are inside T1, which
     * begin_transaction() in runRepair commits automatically before T2 runs.
     *
     * In T1 rollback tests, call this to guarantee a clean slate for assertions about
     * backup table contents; tearDown() rolls back all T1 mutations (including these
     * deletes), restoring the prior committed state for the next test.
     */
    private function cleanAll(): void
    {
        $d  = Season1993PhantomRepair::GAME_DATE;
        $v  = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h  = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g  = Season1993PhantomRepair::GAME_OF_THAT_DAY;
        $gr = Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY;

        // Backup tables first (no FK; safe to delete in any order).
        $this->db->query("DELETE FROM ibl_box_scores_teams_season1993_phantom_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $this->db->query("DELETE FROM ibl_box_scores_season1993_phantom_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");

        // Live tables at phantom coordinate.
        $this->db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");

        // Live tables at real-game coordinate.
        $this->db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr");
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr");

        // Bystander coordinate.
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=3 AND home_teamid=4 AND game_of_that_day=1");

        // Sim game recaps (FK: recaps reference summaries; delete recaps first).
        $this->db->query('DELETE FROM ibl_sim_game_recaps WHERE sim = ' . self::TEST_SIM);
    }

    /**
     * Executes a SQL query that must return a single row with a column aliased `c`
     * and returns its integer value.
     */
    private function scalar(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result, 'Query failed: ' . $sql . ' — ' . $this->db->error);
        /** @var array<string, float|int|string|null> $row */
        $row = $result->fetch_assoc();

        return (int) $row['c'];
    }

    /**
     * Returns the row count at the phantom coordinate in the given table.
     */
    private function phantomCount(string $table): int
    {
        $d = Season1993PhantomRepair::GAME_DATE;
        $v = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g = Season1993PhantomRepair::GAME_OF_THAT_DAY;

        return $this->scalar(
            "SELECT COUNT(*) AS c FROM $table
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g"
        );
    }

    // ════════════════════════════════════════════════════════════════════════
    // assertPreconditions-only tests — outer T1 rollback is intact
    // ════════════════════════════════════════════════════════════════════════

    public function testProceedsWhenPhantomPresent(): void
    {
        // INSERT IGNORE so the FK is satisfied whether or not a previous committed
        // run already placed these pids in ibl_plr.
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();
        $this->seedBystanderRows();

        $verdict = $this->makeRepair($seed)->assertPreconditions();

        self::assertSame('proceed', $verdict);
    }

    public function testNoOpWhenSeasonAbsent(): void
    {
        // cleanAll() inside T1 ensures the backup table counts read 0 even if a
        // prior committed runRepair test filled them. tearDown() rolls the cleanup
        // back after this test.
        $this->cleanAll();

        $result = $this->makeRepair(null)->runRepair(false);

        self::assertSame('noop', $result['status']);
        self::assertSame(['teams' => 0, 'players' => 0], $result['deleted']);
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE),
            'Backup team table must have 0 rows at phantom coordinate'
        );
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE),
            'Backup player table must have 0 rows at phantom coordinate'
        );
    }

    public function testAbortsWhenPhantomIdsDoNotMatch(): void
    {
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        // Override with IDs that can never match what was just seeded.
        $override = [
            'phantom_ids'             => [1, 2],
            'phantom_player_rows'     => self::PHANTOM_PLAYER_COUNT,
            'phantom_player_id_range' => $seed['phantom_player_id_range'],
        ];

        try {
            $this->makeRepair($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('do not match the ids', $e->getMessage());
            // assertPreconditions is read-only: live phantom rows untouched.
            self::assertSame(2, $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE));
            self::assertSame(self::PHANTOM_PLAYER_COUNT, $this->phantomCount(Season1993PhantomRepair::PLAYER_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsOnPlayerRowCountMismatch(): void
    {
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        // Delete one phantom player row so the count is 23 instead of 24.
        $d = Season1993PhantomRepair::GAME_DATE;
        $v = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g = Season1993PhantomRepair::GAME_OF_THAT_DAY;
        $this->db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g LIMIT 1");

        try {
            $this->makeRepair($seed)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('phantom player rows', $e->getMessage());
            self::assertSame(2, $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsOnPlayerIdRangeMismatch(): void
    {
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        // Shift min by 1 so the range no longer matches what is actually in the DB.
        $wrongRange = [$seed['phantom_player_id_range'][0] + 1, $seed['phantom_player_id_range'][1]];
        $override   = [
            'phantom_ids'             => $seed['phantom_ids'],
            'phantom_player_rows'     => self::PHANTOM_PLAYER_COUNT,
            'phantom_player_id_range' => $wrongRange,
        ];

        try {
            $this->makeRepair($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('player ids span', $e->getMessage());
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsWhenRealGameAbsent(): void
    {
        // Seed phantom and bystander only; no slot-5 rows so realGameTeamRowCount() returns 0.
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedBystanderRows();

        try {
            $this->makeRepair($seed)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('ordinal 5', $e->getMessage());
            // Phantom rows untouched.
            self::assertSame(2, $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE));
            self::assertSame(self::PHANTOM_PLAYER_COUNT, $this->phantomCount(Season1993PhantomRepair::PLAYER_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE));
        }
    }

    public function testAbortsWhenSimGameRecapExists(): void
    {
        for ($i = 0; $i < 24; $i++) {
            $pid    = self::PHANTOM_PID_START + $i;
            $teamid = $i < 12
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Phantom' . $i, $teamid);
        }
        for ($i = 0; $i < 3; $i++) {
            $pid    = self::REAL_PID_START + $i;
            $teamid = $i < 2
                ? Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID
                : Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
            $this->insertPidIgnore($pid, 'Real' . $i, $teamid);
        }

        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        $this->db->query('INSERT IGNORE INTO ibl_sim_summaries (sim, status) VALUES (' . self::TEST_SIM . ", 'done')");
        $this->insertRow('ibl_sim_game_recaps', [
            'sim'              => self::TEST_SIM,
            'season_year'      => 1993,
            'game_date'        => Season1993PhantomRepair::GAME_DATE,
            'visitor_teamid'   => Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID,
            'home_teamid'      => Season1993PhantomRepair::PHANTOM_HOME_TEAMID,
            'game_of_that_day' => Season1993PhantomRepair::GAME_OF_THAT_DAY,
            'sort_order'       => 1,
            'recap_text'       => 'phantom recap',
        ]);

        $override = [
            'phantom_ids'             => $seed['phantom_ids'],
            'phantom_player_rows'     => self::PHANTOM_PLAYER_COUNT,
            'phantom_player_id_range' => $seed['phantom_player_id_range'],
        ];

        try {
            $this->makeRepair($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('sim game recap', $e->getMessage());
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE));
            self::assertSame(0, $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE));
        }
    }

    // ════════════════════════════════════════════════════════════════════════
    // runRepair tests — T1 is committed; state persists in ibl5_test
    // ════════════════════════════════════════════════════════════════════════

    public function testSecondRunIsNoOp(): void
    {
        $this->seedPhantomPidsInPlr();
        $this->seedRealPidsInPlr();
        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        // First run: proceeds and backs up + deletes phantom rows (T2 commits).
        $first = $this->makeRepair($seed)->runRepair(false);
        self::assertSame('proceed', $first['status']);

        // Second run: phantom rows are gone → assertPreconditions returns 'noop'
        // → runRepair returns before begin_transaction.
        $second = $this->makeRepair($seed)->runRepair(false);
        self::assertSame('noop', $second['status']);
        self::assertSame(['teams' => 0, 'players' => 0], $second['deleted']);

        // Backup tables must still hold the rows from the first run.
        self::assertSame(
            2,
            $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE),
            'Backup team table must still hold 2 rows after second (noop) run'
        );
        self::assertSame(
            self::PHANTOM_PLAYER_COUNT,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE),
            'Backup player table must still hold 24 rows after second (noop) run'
        );

        // Real-game slot-5 team rows must be untouched.
        $d  = Season1993PhantomRepair::GAME_DATE;
        $v  = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h  = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $gr = Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY;
        self::assertSame(
            2,
            $this->scalar("SELECT COUNT(*) AS c FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr"),
            'Slot-5 team rows must be unchanged after second (noop) run'
        );
    }

    public function testDryRunChangesNothing(): void
    {
        $this->seedPhantomPidsInPlr();
        $this->seedRealPidsInPlr();
        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        $result = $this->makeRepair($seed)->runRepair(true);

        self::assertSame('proceed', $result['status'], 'dry run must still report proceed');
        self::assertSame(
            ['teams' => 2, 'players' => self::PHANTOM_PLAYER_COUNT],
            $result['deleted'],
            'dry run must report the counts as if the delete ran'
        );

        // Live phantom rows must be unchanged (T2 was rolled back).
        self::assertSame(
            2,
            $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE),
            'Dry run must not delete phantom team rows (T2 rolled back)'
        );
        self::assertSame(
            self::PHANTOM_PLAYER_COUNT,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_TABLE),
            'Dry run must not delete phantom player rows (T2 rolled back)'
        );

        // Backup tables must be empty (T2 was rolled back).
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE),
            'Dry run must not populate backup team table (T2 rolled back)'
        );
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE),
            'Dry run must not populate backup player table (T2 rolled back)'
        );
    }

    public function testBackupTablesPopulatedBeforeDelete(): void
    {
        $this->seedPhantomPidsInPlr();
        $this->seedRealPidsInPlr();
        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        $result = $this->makeRepair($seed)->runRepair(false);

        self::assertSame('proceed', $result['status']);

        // Backup tables must hold the phantom rows.
        self::assertSame(
            2,
            $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE),
            'Backup team table must hold 2 phantom team rows'
        );
        self::assertSame(
            self::PHANTOM_PLAYER_COUNT,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE),
            'Backup player table must hold 24 phantom player rows'
        );

        // Live phantom rows must have been deleted.
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE),
            'Phantom team rows must be deleted after repair'
        );
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_TABLE),
            'Phantom player rows must be deleted after repair'
        );

        // Verify the backup team row with the smaller id carries the right coordinate.
        $d = Season1993PhantomRepair::GAME_DATE;
        $v = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g = Season1993PhantomRepair::GAME_OF_THAT_DAY;

        $backupResult = $this->db->query(
            "SELECT visitor_teamid, home_teamid, game_of_that_day, game_type
             FROM " . Season1993PhantomRepair::TEAM_BACKUP_TABLE . "
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g
             ORDER BY id ASC LIMIT 1"
        );
        self::assertNotFalse($backupResult, 'Backup team query failed: ' . $this->db->error);
        /** @var array<string, int|string|null> $backupRow */
        $backupRow = $backupResult->fetch_assoc();
        $backupResult->free();

        self::assertNotNull($backupRow, 'Expected at least one backup team row');
        self::assertSame($v, (int) $backupRow['visitor_teamid']);
        self::assertSame($h, (int) $backupRow['home_teamid']);
        self::assertSame($g, (int) $backupRow['game_of_that_day']);
        // game_type is a generated column in the live table copied as a regular value;
        // asserting not-null confirms the backup captured it.
        self::assertNotNull($backupRow['game_type'], 'game_type must be captured in the backup row');
    }

    public function testBackupFillSkipsRowsAlreadyBackedUp(): void
    {
        $this->seedPhantomPidsInPlr();
        $this->seedRealPidsInPlr();
        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();

        // Manually pre-fill backup tables so the NOT EXISTS guard in fillBackups() is tested.
        $d = Season1993PhantomRepair::GAME_DATE;
        $v = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $g = Season1993PhantomRepair::GAME_OF_THAT_DAY;
        $this->db->query(
            "INSERT INTO " . Season1993PhantomRepair::TEAM_BACKUP_TABLE . "
             SELECT * FROM " . Season1993PhantomRepair::TEAM_TABLE . "
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g"
        );
        $this->db->query(
            "INSERT INTO " . Season1993PhantomRepair::PLAYER_BACKUP_TABLE . "
             SELECT * FROM " . Season1993PhantomRepair::PLAYER_TABLE . "
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g"
        );

        $result = $this->makeRepair($seed)->runRepair(false);

        self::assertSame('proceed', $result['status']);

        // Backup tables must hold exactly 2 and 24 rows — NOT EXISTS skipped duplicates.
        self::assertSame(
            2,
            $this->phantomCount(Season1993PhantomRepair::TEAM_BACKUP_TABLE),
            'Backup team table must hold exactly 2 rows (no duplicates from NOT EXISTS guard)'
        );
        self::assertSame(
            self::PHANTOM_PLAYER_COUNT,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_BACKUP_TABLE),
            'Backup player table must hold exactly 24 rows (no duplicates from NOT EXISTS guard)'
        );

        // Live phantom rows must have been deleted.
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::TEAM_TABLE),
            'Phantom team rows must be deleted even when backup was pre-filled'
        );
        self::assertSame(
            0,
            $this->phantomCount(Season1993PhantomRepair::PLAYER_TABLE),
            'Phantom player rows must be deleted even when backup was pre-filled'
        );
    }

    public function testOtherRowsOnSameDateUntouched(): void
    {
        $this->seedPhantomPidsInPlr();
        $this->seedRealPidsInPlr();
        $this->cleanAll();
        $seed = $this->seedPhantomRows();
        $this->seedRealGameRows();
        $this->seedBystanderRows();

        $this->makeRepair($seed)->runRepair(false);

        $d  = Season1993PhantomRepair::GAME_DATE;
        $v  = Season1993PhantomRepair::PHANTOM_VISITOR_TEAMID;
        $h  = Season1993PhantomRepair::PHANTOM_HOME_TEAMID;
        $gr = Season1993PhantomRepair::REAL_GAME_OF_THAT_DAY;

        // Slot-5 (real game) team and player rows must be intact.
        self::assertSame(
            2,
            $this->scalar("SELECT COUNT(*) AS c FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr"),
            'Real-game (slot-5) team rows must survive the repair'
        );
        self::assertSame(
            3,
            $this->scalar("SELECT COUNT(*) AS c FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$gr"),
            'Real-game (slot-5) player rows must survive the repair'
        );

        // Bystander game (visitor 3 @ home 4, ordinal 1) rows must be intact.
        self::assertSame(
            2,
            $this->scalar("SELECT COUNT(*) AS c FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=3 AND home_teamid=4 AND game_of_that_day=1"),
            'Bystander-game rows must survive the repair'
        );
    }

    public function testBackupMigrationRelaxesGeneratedColumns(): void
    {
        // Migration 180 uses CREATE TABLE IF NOT EXISTS + ALTER TABLE MODIFY (idempotent).
        // Applying it to a DB that already has the backup tables is a no-op at the data
        // level and merely re-asserts the column type, which is safe to repeat.
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/180_create_season1993_phantom_backup_tables.sql');
        self::assertIsString($sql, 'Migration 180 SQL file must be readable');
        self::assertTrue($this->db->multi_query($sql), 'Migration 180 must execute without error: ' . $this->db->error);
        while ($this->db->more_results()) {
            $this->db->next_result();
        }

        // Both backup tables must exist.
        $teamBackup = $this->db->query("SHOW TABLES LIKE 'ibl_box_scores_teams_season1993_phantom_backup'");
        self::assertNotFalse($teamBackup);
        self::assertSame(1, $teamBackup->num_rows, 'ibl_box_scores_teams_season1993_phantom_backup must exist after migration 180');
        $teamBackup->free();

        $playerBackup = $this->db->query("SHOW TABLES LIKE 'ibl_box_scores_season1993_phantom_backup'");
        self::assertNotFalse($playerBackup);
        self::assertSame(1, $playerBackup->num_rows, 'ibl_box_scores_season1993_phantom_backup must exist after migration 180');
        $playerBackup->free();

        // No column in either backup table may still be STORED GENERATED.
        $generatedCount = $this->scalar(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('ibl_box_scores_teams_season1993_phantom_backup', 'ibl_box_scores_season1993_phantom_backup')
               AND EXTRA LIKE '%GENERATED%'"
        );
        self::assertSame(0, $generatedCount, 'After migration 180, no backup column should remain STORED GENERATED');
    }
}
