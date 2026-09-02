<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\Season2008Finals4Restore;
use PHPUnit\Framework\Attributes\Group;

/**
 * Integration tests for Season2008Finals4Restore.
 *
 * Transaction isolation note: Season2008Finals4Restore has no manageTransaction seam.
 * runRestore() always calls $this->db->begin_transaction() internally, which in MariaDB
 * implicitly commits any already-open transaction. This means DatabaseTestCase's outer T1
 * (the one setUp() opens) is committed the moment runRestore() is called. Tests that
 * call runRestore() leave committed state in the test database and must clean the game
 * coordinate before re-seeding so repeated runs within a single suite execution work.
 *
 * tearDownAfterClass() cleans all committed rows so subsequent suite runs start clean.
 *
 * @see Season2008Finals4Restore The class under test
 */
#[Group('database')]
final class Season2008Finals4RestoreTest extends DatabaseTestCase
{
    private const EXPECTED_TEAM_ROWS   = 2;
    private const EXPECTED_PLAYER_ROWS = 24;

    /**
     * A season-2008 date that is NOT the game being restored.
     *
     * Season2008Finals4Restore's State 2 guard refuses to proceed when the box score
     * tables hold no `2008-%` rows at all — the production database has 1642/19177 of
     * them, the seeded test database has none. Sentinel rows at this date make the
     * season look present without colliding with the target coordinate, which
     * alreadyInsertedCount() matches on game_date + visitor/home + ordinal.
     */
    private const DECOY_DATE = '2008-01-02';

    /**
     * ibl_plr rows this class created to satisfy ibl_box_scores' pid foreign key.
     *
     * runRestore() commits, so these survive tearDown()'s rollback and would otherwise
     * leak into every later test class in the same suite run.
     *
     * @var list<int>
     */
    private static array $seededPids = [];

    /**
     * Removes the rows this class knowingly commits to the shared test database.
     *
     * runRestore() calls begin_transaction() internally (MariaDB: implicitly commits T1),
     * so any committed INSERT survives tearDown()'s rollback and leaks into later test
     * classes in the same suite run.
     *
     * A dedicated connection is required: tearDown() rolls its own connection back, so
     * deleting via $this->db would be rolled back and not actually clean anything.
     */
    public static function tearDownAfterClass(): void
    {
        $host = self::envOr('DB_HOST', '127.0.0.1');
        $user = self::envOr('DB_USER', 'ibl');
        $pass = self::envOr('DB_PASS', '');
        $name = self::envOr('DB_NAME', 'ibl5_test');

        $db = new \mysqli();
        $db->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $db->real_connect($host, $user, $pass, $name);
        if ($db->connect_errno !== 0) {
            parent::tearDownAfterClass();
            return;
        }

        $db->query(
            "DELETE FROM `" . Season2008Finals4Restore::TEAM_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
        $db->query(
            "DELETE FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
        $db->query(
            "DELETE FROM `" . Season2008Finals4Restore::TEAM_TABLE . "`
             WHERE game_date = '" . self::DECOY_DATE . "'"
        );
        $db->query(
            "DELETE FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . self::DECOY_DATE . "'"
        );
        if (self::$seededPids !== []) {
            $db->query('DELETE FROM ibl_plr WHERE pid IN (' . implode(',', self::$seededPids) . ')');
            self::$seededPids = [];
        }
        $db->close();

        parent::tearDownAfterClass();
    }

    /**
     * Read an environment variable, falling back when it is unset or empty.
     *
     * getenv(), not $_ENV: docker-compose injects these as real environment variables
     * and the container's variables_order leaves $_ENV unpopulated, so $_ENV would
     * silently fall back to 127.0.0.1 and refuse the connection.
     * DatabaseTestCase::requireEnv() reads them the same way; this is a static mirror
     * of it because requireEnv() is a private instance method and this class needs the
     * values from the static tearDownAfterClass().
     */
    private static function envOr(string $name, string $fallback): string
    {
        $value = getenv($name);

        return ($value === false || $value === '') ? $fallback : $value;
    }

    /**
     * @param array{team_rows_2008: int, player_rows_2008: int}|null $expectedOverride
     */
    private function makeRestore(?array $expectedOverride = null): Season2008Finals4Restore
    {
        return new Season2008Finals4Restore($this->db, $expectedOverride);
    }

    /** Clean the 2008 Finals G4 coordinate so the next runRestore() can proceed. */
    private function cleanInsertedCoordinate(): void
    {
        $this->db->query(
            "DELETE FROM `" . Season2008Finals4Restore::TEAM_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
        $this->db->query(
            "DELETE FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
    }

    /**
     * Put sentinel `2008-%` rows in both box score tables.
     *
     * Without them count2008TeamRows() is 0 and runRestore() stops at State 2
     * ("season absent") before it ever reaches the insert path.
     */
    private function seed2008Fingerprint(): void
    {
        foreach ([Season2008Finals4Restore::TEAM_TABLE, Season2008Finals4Restore::PLAYER_TABLE] as $table) {
            // Delete first so repeated calls within one suite run stay at exactly one
            // sentinel row per table — runRestore() commits, so these survive tearDown().
            $this->db->query("DELETE FROM `" . $table . "` WHERE game_date = '" . self::DECOY_DATE . "'");
            $this->db->query("INSERT INTO `" . $table . "` (game_date) VALUES ('" . self::DECOY_DATE . "')");
        }
    }

    /**
     * Create the ibl_plr rows the payload's FK needs, remembering which ones we made.
     *
     * Only genuinely missing IDs are inserted, so tearDownAfterClass() never deletes a
     * player the seed fixture owns.
     */
    private function seedPayloadPlayers(): void
    {
        foreach (Season2008Finals4Restore::payloadPlayerIds() as $pid) {
            if (in_array($pid, self::$seededPids, true)) {
                continue;
            }
            if ($this->scalarQuery('SELECT COUNT(*) FROM ibl_plr WHERE pid = ' . $pid) > 0) {
                continue;
            }
            $this->db->query('INSERT INTO ibl_plr (pid) VALUES (' . $pid . ')');
            self::$seededPids[] = $pid;
        }
    }

    private function count2008Rows(string $table): int
    {
        return $this->scalarQuery("SELECT COUNT(*) FROM `" . $table . "` WHERE game_date LIKE '2008-%'");
    }

    /**
     * Clean the target coordinate, seed the season, and return a restore whose
     * fingerprint matches what the test database actually holds right now.
     *
     * The production EXPECTED counts (1642/19177) describe the live database, so a
     * seeded test database can never match them; the constructor's override seam
     * exists for exactly this. Counts are measured after seeding, so the fingerprint
     * guard passes and State 3 is what the test exercises.
     */
    private function arrangeProceedState(): Season2008Finals4Restore
    {
        $this->cleanInsertedCoordinate();
        $this->seedPayloadPlayers();
        $this->seed2008Fingerprint();

        return $this->makeRestore([
            'team_rows_2008'   => $this->count2008Rows(Season2008Finals4Restore::TEAM_TABLE),
            'player_rows_2008' => $this->count2008Rows(Season2008Finals4Restore::PLAYER_TABLE),
        ]);
    }

    private function scalarQuery(string $sql): int
    {
        $result = $this->db->query($sql);
        self::assertNotFalse($result, 'Query failed: ' . $this->db->error);
        $row = $result->fetch_row();
        $result->free();
        return (int) ($row[0] ?? 0);
    }

    private function teamRowCount(): int
    {
        return $this->scalarQuery(
            "SELECT COUNT(*) FROM `" . Season2008Finals4Restore::TEAM_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
    }

    private function playerRowCount(): int
    {
        return $this->scalarQuery(
            "SELECT COUNT(*) FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );
    }

    // ---------------------------------------------------------------- tests

    public function testRunRestore_insertsTeamRows_whenProceed(): void
    {
        $result = $this->arrangeProceedState()->runRestore(false);

        self::assertSame('inserted', $result['status']);
        self::assertSame(self::EXPECTED_TEAM_ROWS, $this->teamRowCount());
    }

    public function testRunRestore_insertsPlayerRows_whenProceed(): void
    {
        $result = $this->arrangeProceedState()->runRestore(false);

        self::assertSame('inserted', $result['status']);
        self::assertSame(self::EXPECTED_PLAYER_ROWS, $this->playerRowCount());
    }

    public function testRunRestore_scoreSumsMatchExpected(): void
    {
        $this->arrangeProceedState()->runRestore(false);

        $visitorScore = $this->scalarQuery(
            "SELECT SUM(calc_points) FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID . "
               AND teamid = " . Season2008Finals4Restore::VISITOR_TEAMID
        );
        $homeScore = $this->scalarQuery(
            "SELECT SUM(calc_points) FROM `" . Season2008Finals4Restore::PLAYER_TABLE . "`
             WHERE game_date = '" . Season2008Finals4Restore::GAME_DATE . "'
               AND visitor_teamid = " . Season2008Finals4Restore::VISITOR_TEAMID . "
               AND home_teamid = " . Season2008Finals4Restore::HOME_TEAMID . "
               AND teamid = " . Season2008Finals4Restore::HOME_TEAMID
        );

        self::assertSame(Season2008Finals4Restore::VISITOR_FINAL, $visitorScore);
        self::assertSame(Season2008Finals4Restore::HOME_FINAL, $homeScore);
    }

    public function testRunRestore_isNoop_whenAlreadyInserted(): void
    {
        $this->arrangeProceedState()->runRestore(false);

        // Second call: rows already exist → State 1 noop, reached before the
        // fingerprint guard, so a plain instance is enough here.
        $result = $this->makeRestore()->runRestore(false);

        self::assertSame('noop', $result['status']);
        // Counts must not have changed
        self::assertSame(self::EXPECTED_TEAM_ROWS, $this->teamRowCount());
        self::assertSame(self::EXPECTED_PLAYER_ROWS, $this->playerRowCount());
    }

    public function testRunRestore_isNoop_whenFingerprintMismatch(): void
    {
        $this->cleanInsertedCoordinate();
        // Seed the season too, otherwise State 2 ("season absent") noops first and this
        // test would pass without the fingerprint guard ever being consulted.
        $this->seed2008Fingerprint();

        // Fingerprint seam: override with wrong expected counts
        $override = ['team_rows_2008' => 99999, 'player_rows_2008' => 99999];
        $result = $this->makeRestore($override)->runRestore(false);

        self::assertSame('noop', $result['status']);
        self::assertSame(0, $this->teamRowCount(), 'fingerprint mismatch must not insert team rows');
        self::assertSame(0, $this->playerRowCount(), 'fingerprint mismatch must not insert player rows');
    }

    public function testDryRun_insertsNothing_exits0(): void
    {
        $restore = $this->arrangeProceedState();

        // Snapshot ibl_playoff_series_results before dry run
        $seriesBefore = $this->scalarQuery(
            'SELECT COUNT(*) FROM ibl_playoff_series_results WHERE year = 2008 AND round = 4'
        );

        $result = $restore->runRestore(true);

        self::assertSame('inserted', $result['status'], 'dry run should still report inserted');
        self::assertTrue($result['dryRun']);
        self::assertSame(0, $this->teamRowCount(), 'dry run must not insert team rows');
        self::assertSame(0, $this->playerRowCount(), 'dry run must not insert player rows');
        self::assertSame(
            $seriesBefore,
            $this->scalarQuery(
                'SELECT COUNT(*) FROM ibl_playoff_series_results WHERE year = 2008 AND round = 4'
            ),
            'dry run must not modify ibl_playoff_series_results'
        );
    }
}
