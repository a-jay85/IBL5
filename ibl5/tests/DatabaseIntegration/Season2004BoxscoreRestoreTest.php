<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\Season2004BoxscoreRestore;
use GameBoxscore\GameBoxscoreRepository;
use GameBoxscore\GameBoxscoreService;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Season2004BoxscoreRestore.
 *
 * Transaction isolation note: Season2004BoxscoreRestore has no manageTransaction
 * seam. runRestore() always calls $this->db->begin_transaction() internally, which
 * in MariaDB implicitly commits any already-open transaction. This means
 * DatabaseTestCase's outer T1 (the one setUp() opens) is committed the moment
 * runRestore() is called. Tests that only call assertPreconditions() — which
 * never opens a transaction — do benefit from T1's rollback in tearDown().
 *
 * Consequently, tests that call runRestore() leave committed state in the test
 * database and must clean the phantom and restored coordinates before seeding so
 * repeated runs within a single suite execution work correctly. Rebuilding the test
 * database via bin/db-test-up stops rows bleeding between suite *runs*, but not
 * between test *classes* within one run -- so tearDownAfterClass() below deletes
 * everything this class commits.
 *
 * Plan defect noted: the packet assumes a manageTransaction constructor parameter
 * (on the model of PhantomBoxscoreRepair) but Season2004BoxscoreRestore exposes
 * only (mysqli $db, ?array $expectedOverride). There is no way to keep runRestore()
 * inside DatabaseTestCase's outer transaction without modifying the class under test,
 * which the packet forbids.
 *
 * Prerequisites: migration 170 must be applied to ibl5_test before this suite runs.
 * bin/db-test-up restore-2004-suns-heat-boxscore applies it automatically via the
 * worktree's migrations/ directory.
 *
 * @see Season2004BoxscoreRestore The class under test
 * @see PhantomBoxscoreRepairTest Sister test for the 2008 phantom repair
 */
#[Group('database')]
final class Season2004BoxscoreRestoreTest extends DatabaseTestCase
{
    /** Number of phantom player rows seeded by the fixture (scaled down from production 24). */
    private const PHANTOM_PLAYER_COUNT = 2;

    /** High PIDs for phantom player rows — far above the real-player range, never conflict. */
    private const PHANTOM_PID_1 = 200001001;
    private const PHANTOM_PID_2 = 200001002;

    /** Sim ID for ibl_sim_game_recaps; FK-constrained to ibl_sim_summaries. */
    private const TEST_SIM = 998;

    /**
     * The 23 real Suns (teamid=23, first 11) and Heat (teamid=2, last 12) player PIDs
     * embedded in Season2004BoxscoreRestore::PLAYER_ROWS (private constant, transcribed here).
     *
     * @var list<int>
     */
    private const RESTORED_PIDS = [
        3280, 4167, 2989, 4496, 4834, 3553, 4164, 4844, 1758, 1480, 2714,  // Suns
        4852, 3872, 4826, 4845, 3285, 4843, 2983, 1235, 3279, 3857, 1253, 1236, // Heat
    ];

    /**
     * Removes the rows this class knowingly commits to the shared test database.
     *
     * runRestore() calls begin_transaction() internally, which in MariaDB implicitly
     * commits DatabaseTestCase's outer transaction -- so everything seeded before that
     * call survives tearDown()'s rollback and leaks into every later test class in the
     * same run. seedRestoredPlayerPids() already parks its 23 players on teamid 0 so no
     * franchise cap is inflated, but the committed boxscore, backup and recap rows still
     * sit at the restored coordinate where later classes can see them. Cleaning up here
     * removes the leak rather than muting its symptoms.
     *
     * A dedicated connection is required: tearDown() rolls its own connection back, so
     * deletes issued on it would be discarded along with the per-test transaction.
     *
     * The ibl_plr delete is scoped to the `test-` uuid prefix that insertPidIgnore()
     * writes, so a real seeded player sharing one of these PIDs is never removed.
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

        $d = Season2004BoxscoreRestore::GAME_DATE;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;
        $coordinates = [
            [Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID, Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID],
            [Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID, Season2004BoxscoreRestore::RESTORED_HOME_TEAMID],
        ];

        foreach ($coordinates as [$v, $h]) {
            $where = "WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g";
            $db->query("DELETE FROM ibl_box_scores_season2004_backup $where");
            $db->query("DELETE FROM ibl_box_scores_teams_season2004_backup $where");
            $db->query("DELETE FROM ibl_box_scores $where");
            $db->query("DELETE FROM ibl_box_scores_teams $where");
        }

        // ibl_box_scores.pid is FK-constrained to ibl_plr, so players go after the
        // boxscore deletes above.
        $pids = implode(',', array_merge(self::RESTORED_PIDS, [self::PHANTOM_PID_1, self::PHANTOM_PID_2]));
        $db->query("DELETE FROM ibl_plr WHERE pid IN ($pids) AND uuid LIKE 'test-%'");

        // Recaps are FK-constrained to ibl_sim_summaries, so they go first.
        $db->query('DELETE FROM ibl_sim_game_recaps WHERE sim = ' . self::TEST_SIM);
        $db->query('DELETE FROM ibl_sim_summaries WHERE sim = ' . self::TEST_SIM);

        $db->close();

        parent::tearDownAfterClass();
    }

    /** @param array{phantom_ids: list<int>, phantom_player_rows: int}|null $expectedOverride */
    private function makeRestore(?array $expectedOverride): Season2004BoxscoreRestore
    {
        return new Season2004BoxscoreRestore($this->db, $expectedOverride);
    }

    /**
     * Seeds 2 phantom team rows (Aces @ Jazz, gotd=5) and 2 phantom player rows.
     * Returns the sorted auto-increment IDs so the caller can build $expectedOverride.
     *
     * Safe for T1-rollback tests (uses plain insertTestPlayer, rolled back normally)
     * and for runRestore tests (called AFTER cleanPhantomCoordinate() + insertPidIgnore()).
     *
     * @return array{phantom_ids: list<int>}
     */
    private function seedPhantomRows(): array
    {
        $id1 = $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'Aces',
            Season2004BoxscoreRestore::GAME_OF_THAT_DAY,
            Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
        );
        $id2 = $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'Jazz',
            Season2004BoxscoreRestore::GAME_OF_THAT_DAY,
            Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
        );

        // Player rows: both high-PID players on the phantom visitor (Aces, teamid 16).
        $this->insertPlayerBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            self::PHANTOM_PID_1,
            'Phantom1',
            'PG',
            Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            overrides: ['game_of_that_day' => Season2004BoxscoreRestore::GAME_OF_THAT_DAY],
        );
        $this->insertPlayerBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            self::PHANTOM_PID_2,
            'Phantom2',
            'SG',
            Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
            Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
            overrides: ['game_of_that_day' => Season2004BoxscoreRestore::GAME_OF_THAT_DAY],
        );

        $ids = [$id1, $id2];
        sort($ids);

        return ['phantom_ids' => $ids];
    }

    /**
     * INSERT IGNORE a player into ibl_plr. Used for high-PID phantom players in
     * runRestore tests where T1 is committed and the row persists across test methods.
     */
    private function insertPidIgnore(int $pid, string $name, int $teamid = 1): void
    {
        $uuid = sprintf('test-%09d-0000-000000000001', $pid);
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
     * Seeds the two high-PID phantom players into ibl_plr using INSERT IGNORE.
     * Called in runRestore tests before cleanPhantomCoordinate() re-seeds player rows.
     */
    private function seedPhantomPidsInPlr(): void
    {
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
    }

    /**
     * INSERT IGNORE all 23 Suns/Heat player PIDs into ibl_plr. runRestore() inserts
     * 23 player rows into ibl_box_scores via FK; those players must exist first.
     */
    private function seedRestoredPlayerPids(): void
    {
        foreach (self::RESTORED_PIDS as $pid) {
            $this->insertPidIgnore($pid, 'Player' . $pid, 0);
        }
    }

    /**
     * Deletes all rows at the phantom coordinate (Aces @ Jazz, gotd=5) from both
     * the team and player boxscore tables. Call this inside T1 before re-seeding
     * phantom rows in runRestore tests; the delete is committed with the rest of T1
     * when runRestore() calls begin_transaction().
     */
    private function cleanPhantomCoordinate(): void
    {
        $d = Season2004BoxscoreRestore::GAME_DATE;
        $v = Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID;
        $h = Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;

        $this->db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
    }

    /**
     * Deletes all rows at the restored coordinate (Suns @ Heat, gotd=5) from both
     * the team and player boxscore tables. Call this in T1 before direct insertion
     * tests so previous runRestore() calls do not cause UNIQUE KEY conflicts.
     */
    private function cleanRestoredCoordinate(): void
    {
        $d = Season2004BoxscoreRestore::GAME_DATE;
        $v = Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID;
        $h = Season2004BoxscoreRestore::RESTORED_HOME_TEAMID;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;

        $this->db->query("DELETE FROM ibl_box_scores WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
    }

    /**
     * Deletes rows at the phantom coordinate from the season-2004 backup tables.
     * fillBackups() in runRestore() uses INSERT (not INSERT IGNORE), so the backup
     * tables must be empty before each runRestore call, or assertBackupCounts() throws.
     */
    private function cleanBackupTables(): void
    {
        $d = Season2004BoxscoreRestore::GAME_DATE;
        $v = Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID;
        $h = Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;

        $this->db->query("DELETE FROM ibl_box_scores_teams_season2004_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
        $this->db->query("DELETE FROM ibl_box_scores_season2004_backup WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g");
    }

    /**
     * Inserts the 2 restored team rows (Suns and Heat) at the restored coordinate with
     * NULL attendance and all 10 quarter columns NULL — matching the real restored payload.
     * Uses an explicit prepared statement because insertRow() cannot send SQL NULL.
     */
    private function insertRestoredTeamRowsWithNullQuarters(): void
    {
        $teamRows = [
            ['Suns', 42, 92, 9, 11, 5, 12, 16, 41, 11, 6, 17, 3, 20],
            ['Heat', 45, 87, 13, 22, 11, 26, 21, 43, 21, 11, 7, 11, 13],
        ];

        $stmt = $this->db->prepare(
            'INSERT INTO ibl_box_scores_teams
             (game_date, name, game_of_that_day, visitor_teamid, home_teamid,
              attendance, capacity, visitor_wins, visitor_losses, home_wins, home_losses,
              visitor_q1_points, visitor_q2_points, visitor_q3_points, visitor_q4_points, visitor_ot_points,
              home_q1_points, home_q2_points, home_q3_points, home_q4_points, home_ot_points,
              game_2gm, game_2ga, game_ftm, game_fta, game_3gm, game_3ga,
              game_orb, game_drb, game_ast, game_stl, game_tov, game_blk, game_pf)
             VALUES (?, ?, ?, ?, ?,
                     NULL, ?, ?, ?, ?, ?,
                     NULL, NULL, NULL, NULL, NULL,
                     NULL, NULL, NULL, NULL, NULL,
                     ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        self::assertNotFalse($stmt, 'Prepare team insert failed: ' . $this->db->error);

        foreach ($teamRows as [$name, $twoGm, $twoGa, $ftm, $fta, $threeGm, $threeGa, $orb, $drb, $ast, $stl, $tov, $blk, $pf]) {
            $date    = Season2004BoxscoreRestore::GAME_DATE;
            $gotd    = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;
            $vtid    = Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID;
            $htid    = Season2004BoxscoreRestore::RESTORED_HOME_TEAMID;
            $cap     = Season2004BoxscoreRestore::CAPACITY;
            $vw      = Season2004BoxscoreRestore::VISITOR_WINS;
            $vl      = Season2004BoxscoreRestore::VISITOR_LOSSES;
            $hw      = Season2004BoxscoreRestore::HOME_WINS;
            $hl      = Season2004BoxscoreRestore::HOME_LOSSES;

            $refs = [&$date, &$name, &$gotd, &$vtid, &$htid, &$cap, &$vw, &$vl, &$hw, &$hl,
                     &$twoGm, &$twoGa, &$ftm, &$fta, &$threeGm, &$threeGa,
                     &$orb, &$drb, &$ast, &$stl, &$tov, &$blk, &$pf];
            $stmt->bind_param('ssiiii' . 'iiii' . 'iiiiiiiiiiiii', ...$refs);
            $stmt->execute();
        }

        $stmt->close();
    }

    private function scalar(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result, 'Query failed: ' . $sql . ' — ' . $this->db->error);
        /** @var array<string, float|int|string|null> $row */
        $row = $result->fetch_assoc();

        return (int) $row['c'];
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Tests that only call assertPreconditions() — outer T1 rollback is intact
    // ═══════════════════════════════════════════════════════════════════════

    public function testProceedsWhenPhantomPresentAndRestoredAbsent(): void
    {
        // Clean committed rows that may exist from previous runRestore tests (in T1 → rolled back in tearDown).
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        // INSERT IGNORE: phantom PIDs may be committed from a prior runRestore test run
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
        $seed = $this->seedPhantomRows();

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];
        $verdict = $this->makeRestore($override)->assertPreconditions();

        self::assertSame('proceed', $verdict);
    }

    public function testNoOpWhenAlreadyRestored(): void
    {
        // Ensure phantom coord is empty (if restored rows exist from a prior run, this still returns noop).
        // Clean restored and re-insert exactly one row to control the scenario.
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        // Restored team rows present, phantom absent → already done.
        $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'Suns',
            Season2004BoxscoreRestore::GAME_OF_THAT_DAY,
            Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID,
            Season2004BoxscoreRestore::RESTORED_HOME_TEAMID,
        );

        $verdict = $this->makeRestore(null)->assertPreconditions();

        self::assertSame('noop', $verdict);
    }

    public function testNoOpWhenSeasonAbsent(): void
    {
        // Clean both coordinates so that neither phantom nor restored rows are visible.
        // This tests the "season simply not present" branch of assertPreconditions.
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        // No rows at either the phantom or restored coordinate → 2004 season not here.
        $verdict = $this->makeRestore(null)->assertPreconditions();

        self::assertSame('noop', $verdict);
    }

    public function testAbortsWhenBothPhantomAndRestoredPresent(): void
    {
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
        $seed = $this->seedPhantomRows();

        // Restored row alongside phantom → ambiguous state the repair cannot handle.
        $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'Suns',
            Season2004BoxscoreRestore::GAME_OF_THAT_DAY,
            Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID,
            Season2004BoxscoreRestore::RESTORED_HOME_TEAMID,
        );

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];

        try {
            $this->makeRestore($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('BOTH', $e->getMessage());
        }
    }

    public function testAbortsWhenPhantomIdsDoNotMatch(): void
    {
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
        $seed = $this->seedPhantomRows();

        // Pass IDs that are one higher than what was seeded — mismatch forces abort.
        $wrongIds = array_map(static fn (int $id): int => $id + 999999, $seed['phantom_ids']);
        $override = ['phantom_ids' => $wrongIds, 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];

        try {
            $this->makeRestore($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            // This assertion is the mutation-check target for testAbortsWhenPhantomIdsDoNotMatch.
            // Commenting it out and re-running should produce a red test.
            self::assertStringContainsString('do not match the ids', $e->getMessage());
        }
    }

    public function testAbortsOnPlayerRowCountMismatch(): void
    {
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
        $seed = $this->seedPhantomRows();

        // Override claims 99 player rows but only 2 exist → count check fails.
        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => 99];

        try {
            $this->makeRestore($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('phantom player rows', $e->getMessage());
        }
    }

    public function testAbortsWhenSimGameRecapExists(): void
    {
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->insertPidIgnore(self::PHANTOM_PID_1, 'Phantom1', Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID);
        $this->insertPidIgnore(self::PHANTOM_PID_2, 'Phantom2', Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID);
        $seed = $this->seedPhantomRows();

        // A recap referencing the phantom coordinate blocks deletion.
        $this->db->query('INSERT IGNORE INTO ibl_sim_summaries (sim, status) VALUES (' . self::TEST_SIM . ", 'done')");
        $this->insertRow('ibl_sim_game_recaps', [
            'sim' => self::TEST_SIM,
            'season_year' => 2004,
            'game_date' => Season2004BoxscoreRestore::GAME_DATE,
            'visitor_teamid' => Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID,
            'home_teamid' => Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID,
            'game_of_that_day' => Season2004BoxscoreRestore::GAME_OF_THAT_DAY,
            'sort_order' => 1,
            'recap_text' => 'phantom recap',
        ]);

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];

        try {
            $this->makeRestore($override)->assertPreconditions();
            self::fail('Expected RuntimeException was not thrown');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('sim game recap', $e->getMessage());
        }
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Tests that call runRestore() — T1 is committed; state persists in ibl5_test
    // ═══════════════════════════════════════════════════════════════════════

    public function testDryRunChangesNothing(): void
    {
        // Clean any state left by a previous suite run, then seed a fresh fixture.
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->cleanBackupTables();
        $this->seedPhantomPidsInPlr();
        $this->seedRestoredPlayerPids();
        $seed = $this->seedPhantomRows();

        $phantomTeamsBefore = $this->scalar(
            "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
             WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
               AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
               AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
               AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
        );

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];
        $result = $this->makeRestore($override)->runRestore(true);

        self::assertSame('proceed', $result['status'], 'dryRun should still report proceed');
        self::assertSame(
            $phantomTeamsBefore,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'dryRun must not delete phantom team rows (T2 rolled back)'
        );
        self::assertSame(
            0,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::RESTORED_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'dryRun must not insert restored team rows (T2 rolled back)'
        );
        self::assertSame(
            0,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams_season2004_backup
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'dryRun must not populate backup tables (T2 rolled back)'
        );
    }

    public function testBackupTablesPopulatedBeforeDelete(): void
    {
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->cleanBackupTables();
        $this->seedPhantomPidsInPlr();
        $this->seedRestoredPlayerPids();
        $seed = $this->seedPhantomRows();

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];
        $result = $this->makeRestore($override)->runRestore(false);

        self::assertSame('proceed', $result['status']);
        self::assertSame(
            2,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams_season2004_backup
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'backup team table must hold the 2 phantom team rows before deletion'
        );
        self::assertSame(
            self::PHANTOM_PLAYER_COUNT,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_season2004_backup
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'backup player table must hold the seeded phantom player rows before deletion'
        );
        self::assertSame(
            0,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::PHANTOM_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::PHANTOM_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'phantom team rows must be deleted after restore'
        );
        self::assertSame(
            2,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=" . Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID . "
                   AND home_teamid=" . Season2004BoxscoreRestore::RESTORED_HOME_TEAMID . "
                   AND game_of_that_day=" . Season2004BoxscoreRestore::GAME_OF_THAT_DAY
            ),
            'restored (Suns/Heat) team rows must be present after restore'
        );
    }

    // ═══════════════════════════════════════════════════════════════════════
    // Tests that verify restored-row properties via direct insertion (T1 rollback)
    // ═══════════════════════════════════════════════════════════════════════

    public function testRestoredRowsHaveNullAttendanceAndQuarters(): void
    {
        // Clean any previously restored rows so UNIQUE KEY is free, then insert
        // fresh rows with NULL attendance + quarters. T1 rollback undoes both the
        // delete and the insert, leaving the committed state from testBackupTablesPopulatedBeforeDelete
        // intact.
        $this->cleanRestoredCoordinate();
        $this->insertRestoredTeamRowsWithNullQuarters();

        $d = Season2004BoxscoreRestore::GAME_DATE;
        $v = Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID;
        $h = Season2004BoxscoreRestore::RESTORED_HOME_TEAMID;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;

        $result = $this->db->query(
            "SELECT attendance, visitor_q1_points, home_q1_points
             FROM ibl_box_scores_teams
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g
             LIMIT 1"
        );
        self::assertNotFalse($result);
        $row = $result->fetch_assoc();
        $result->free();
        self::assertNotNull($row, 'Expected a team row at the restored coordinate');

        self::assertNull($row['attendance'], 'attendance must be SQL NULL (not carried in the source)');
        self::assertNull($row['visitor_q1_points'], 'visitor_q1_points must be SQL NULL (not carried in the source)');
        self::assertNull($row['home_q1_points'], 'home_q1_points must be SQL NULL (not carried in the source)');
    }

    public function testRestoredPlayerPointsSumToFinalScore(): void
    {
        // Drive the restore and verify the embedded PLAYER_ROWS reconcile.
        // Cleans first so this test is idempotent across suite runs.
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->cleanBackupTables();
        $this->seedPhantomPidsInPlr();
        $this->seedRestoredPlayerPids();
        $seed = $this->seedPhantomRows();

        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];
        $this->makeRestore($override)->runRestore(false);

        $d = Season2004BoxscoreRestore::GAME_DATE;
        $v = Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID;
        $h = Season2004BoxscoreRestore::RESTORED_HOME_TEAMID;
        $g = Season2004BoxscoreRestore::GAME_OF_THAT_DAY;

        $result = $this->db->query(
            "SELECT teamid, SUM(calc_points) AS pts
             FROM ibl_box_scores
             WHERE game_date='$d' AND visitor_teamid=$v AND home_teamid=$h AND game_of_that_day=$g
             GROUP BY teamid"
        );
        self::assertNotFalse($result);

        $scores = [];
        while ($row = $result->fetch_assoc()) {
            $scores[(int) $row['teamid']] = (int) $row['pts'];
        }
        $result->free();

        self::assertSame(
            Season2004BoxscoreRestore::VISITOR_FINAL_SCORE,
            $scores[Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID] ?? null,
            'Suns (visitor) player calc_points must sum to VISITOR_FINAL_SCORE=108'
        );
        self::assertSame(
            Season2004BoxscoreRestore::HOME_FINAL_SCORE,
            $scores[Season2004BoxscoreRestore::RESTORED_HOME_TEAMID] ?? null,
            'Heat (home) player calc_points must sum to HOME_FINAL_SCORE=136'
        );
    }

    public function testOtherGamesOnSameDateUntouched(): void
    {
        // Teams 3 (Nuggets) and 4 (Pacers) at gotd=1 — different from the phantom/restored gotd=5.
        $this->cleanPhantomCoordinate();
        $this->cleanRestoredCoordinate();
        $this->cleanBackupTables();
        $this->seedPhantomPidsInPlr();
        $this->seedRestoredPlayerPids();

        // Seed a control game on the same date with different teams.
        // Clean the control coordinate first — these rows are committed by runRestore and survive tearDown.
        $d = Season2004BoxscoreRestore::GAME_DATE;
        $this->db->query("DELETE FROM ibl_box_scores_teams WHERE game_date='$d' AND visitor_teamid=3 AND home_teamid=4 AND game_of_that_day=1");
        $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'V3',
            1,
            3,
            4,
        );
        $this->insertTeamBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            'H4',
            1,
            3,
            4,
        );

        $seed = $this->seedPhantomRows();
        $override = ['phantom_ids' => $seed['phantom_ids'], 'phantom_player_rows' => self::PHANTOM_PLAYER_COUNT];
        $this->makeRestore($override)->runRestore(false);

        self::assertSame(
            2,
            $this->scalar(
                "SELECT COUNT(*) AS c FROM ibl_box_scores_teams
                 WHERE game_date='" . Season2004BoxscoreRestore::GAME_DATE . "'
                   AND visitor_teamid=3 AND home_teamid=4 AND game_of_that_day=1"
            ),
            'Control game (3@4, gotd=1) must survive the restore unmodified'
        );
    }

    public function testBackupMigrationRelaxesGeneratedColumns(): void
    {
        // Migration 170 uses CREATE TABLE IF NOT EXISTS + MODIFY (idempotent).
        // Applying it to a database that already has the backup tables is a no-op at
        // the data level and merely re-asserts the column type, which is safe to repeat.
        $sql = file_get_contents(dirname(__DIR__, 2) . '/migrations/170_create_season2004_restore_backup_tables.sql');
        self::assertIsString($sql, 'Migration 170 SQL file must be readable');
        self::assertTrue($this->db->multi_query($sql), 'Migration 170 must execute without error: ' . $this->db->error);
        while ($this->db->more_results()) {
            $this->db->next_result();
        }

        // Both backup tables must exist.
        $teamBackup = $this->db->query("SHOW TABLES LIKE 'ibl_box_scores_teams_season2004_backup'");
        self::assertNotFalse($teamBackup);
        self::assertSame(1, $teamBackup->num_rows, 'ibl_box_scores_teams_season2004_backup must exist after migration 170');
        $teamBackup->free();

        $playerBackup = $this->db->query("SHOW TABLES LIKE 'ibl_box_scores_season2004_backup'");
        self::assertNotFalse($playerBackup);
        self::assertSame(1, $playerBackup->num_rows, 'ibl_box_scores_season2004_backup must exist after migration 170');
        $playerBackup->free();

        // No column in either backup table may still be STORED GENERATED.
        // (The MODIFY statements in migration 170 convert them to ordinary nullable columns.)
        $generatedCount = $this->scalar(
            "SELECT COUNT(*) AS c FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME IN ('ibl_box_scores_teams_season2004_backup', 'ibl_box_scores_season2004_backup')
               AND EXTRA LIKE '%GENERATED%'"
        );
        self::assertSame(0, $generatedCount, 'After migration 170, no backup column should remain STORED GENERATED');
    }

    /**
     * Known accepted defect — do NOT fix.
     *
     * GameBoxscoreRepository::getGameInfo() computes awayScore and homeScore as
     * `visitor_q1_points + visitor_q2_points + ... + COALESCE(visitor_ot_points, 0)`.
     * When any quarter column is SQL NULL, the arithmetic chain evaluates to NULL, and
     * GameBoxscoreService::getBoxscore() casts that as `(int)(NULL ?? 0) = 0`. The
     * restored 2004-02-09 rows land NULL quarters by design (the source did not carry
     * them), so the boxscore header will render 0–0 instead of 108–136.
     *
     * The correct final scores ARE accessible via calc_points (player totals), which
     * is a separate generated column independent of the quarter columns; the totals row
     * reflects those correctly. The header discrepancy is an intentional, out-of-scope
     * gap documented in Season2004BoxscoreRestore's class docblock.
     */
    public function testKnownDefectNullQuarterHeaderRendersZero(): void
    {
        // Use two high-PID scratch players so T1 rollback cleans them.
        $scratchPid1 = 200001003;
        $scratchPid2 = 200001004;
        $this->insertTestPlayer($scratchPid1, 'Scratch1', ['teamid' => Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID]);
        $this->insertTestPlayer($scratchPid2, 'Scratch2', ['teamid' => Season2004BoxscoreRestore::RESTORED_HOME_TEAMID]);

        $this->cleanRestoredCoordinate();
        $this->insertRestoredTeamRowsWithNullQuarters();

        // Insert two player rows so computeTotals() yields non-zero pts, making the
        // contrast with the 0-header visible.
        $this->insertPlayerBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            $scratchPid1,
            'Scratch1',
            'PG',
            Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID,
            Season2004BoxscoreRestore::RESTORED_HOME_TEAMID,
            Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID,
            minutes: 30,
            points2m: 10,
            points2a: 20,
            ftm: 0,
            fta: 0,
            points3m: 0,
            points3a: 0,
            overrides: ['game_of_that_day' => Season2004BoxscoreRestore::GAME_OF_THAT_DAY],
        );
        $this->insertPlayerBoxscoreRow(
            Season2004BoxscoreRestore::GAME_DATE,
            $scratchPid2,
            'Scratch2',
            'PG',
            Season2004BoxscoreRestore::RESTORED_VISITOR_TEAMID,
            Season2004BoxscoreRestore::RESTORED_HOME_TEAMID,
            Season2004BoxscoreRestore::RESTORED_HOME_TEAMID,
            minutes: 30,
            points2m: 10,
            points2a: 20,
            ftm: 0,
            fta: 0,
            points3m: 0,
            points3a: 0,
            overrides: ['game_of_that_day' => Season2004BoxscoreRestore::GAME_OF_THAT_DAY],
        );

        $repo    = new GameBoxscoreRepository($this->db);
        $service = new GameBoxscoreService($repo);
        $boxscore = $service->getBoxscore(Season2004BoxscoreRestore::GAME_DATE, Season2004BoxscoreRestore::GAME_OF_THAT_DAY);

        self::assertTrue($boxscore['found'], 'Boxscore must be found after seeding the restored rows');

        // Defect: NULL quarter columns → awayScore = NULL → (int)(NULL ?? 0) = 0.
        self::assertSame(0, $boxscore['awayTeam']['score'], 'Known defect: NULL quarters cause awayScore to render as 0 instead of the real Suns score');
        self::assertSame(0, $boxscore['homeTeam']['score'], 'Known defect: NULL quarters cause homeScore to render as 0 instead of the real Heat score');

        // Contrast: player-row totals (from calc_points) ARE non-zero, proving the
        // data is present and the defect is isolated to the quarter-sum path.
        self::assertGreaterThan(0, $boxscore['awayTotals']['pts'], 'Away totals from player rows must be non-zero despite the header defect');
        self::assertGreaterThan(0, $boxscore['homeTotals']['pts'], 'Home totals from player rows must be non-zero despite the header defect');
    }
}
