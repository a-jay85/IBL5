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
        $host = $_ENV['DB_HOST'] ?? '127.0.0.1';
        $user = $_ENV['DB_USER'] ?? 'ibl';
        $pass = $_ENV['DB_PASS'] ?? '';
        $name = $_ENV['DB_NAME'] ?? 'ibl5_test';

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
        $db->close();

        parent::tearDownAfterClass();
    }

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
        $this->cleanInsertedCoordinate();

        $result = $this->makeRestore()->runRestore(false);

        self::assertSame('inserted', $result['status']);
        self::assertSame(self::EXPECTED_TEAM_ROWS, $this->teamRowCount());
    }

    public function testRunRestore_insertsPlayerRows_whenProceed(): void
    {
        $this->cleanInsertedCoordinate();

        $result = $this->makeRestore()->runRestore(false);

        self::assertSame('inserted', $result['status']);
        self::assertSame(self::EXPECTED_PLAYER_ROWS, $this->playerRowCount());
    }

    public function testRunRestore_scoreSumsMatchExpected(): void
    {
        $this->cleanInsertedCoordinate();

        $this->makeRestore()->runRestore(false);

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
        $this->cleanInsertedCoordinate();
        $this->makeRestore()->runRestore(false);

        // Second call: rows already exist → noop
        $result = $this->makeRestore()->runRestore(false);

        self::assertSame('noop', $result['status']);
        // Counts must not have changed
        self::assertSame(self::EXPECTED_TEAM_ROWS, $this->teamRowCount());
        self::assertSame(self::EXPECTED_PLAYER_ROWS, $this->playerRowCount());
    }

    public function testRunRestore_isNoop_whenFingerprintMismatch(): void
    {
        $this->cleanInsertedCoordinate();

        // Fingerprint seam: override with wrong expected counts
        $override = ['team_rows_2008' => 99999, 'player_rows_2008' => 99999];
        $result = $this->makeRestore($override)->runRestore(false);

        self::assertSame('noop', $result['status']);
        self::assertSame(0, $this->teamRowCount(), 'fingerprint mismatch must not insert team rows');
        self::assertSame(0, $this->playerRowCount(), 'fingerprint mismatch must not insert player rows');
    }

    public function testDryRun_insertsNothing_exits0(): void
    {
        $this->cleanInsertedCoordinate();

        // Snapshot ibl_playoff_series_results before dry run
        $seriesBefore = $this->scalarQuery(
            'SELECT COUNT(*) FROM ibl_playoff_series_results WHERE year = 2008 AND round = 4'
        );

        $result = $this->makeRestore()->runRestore(true);

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
