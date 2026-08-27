<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Boxscore\BoxscoreRepository;
use Boxscore\RejectedGame;
use PHPUnit\Framework\Attributes\Group;

#[Group('database')]
class ScheduleGuardRejectsTest extends DatabaseTestCase
{
    private BoxscoreRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new BoxscoreRepository($this->db);
    }

    // ── Schema ────────────────────────────────────────────────────────────────

    /**
     * The migration must create the table with all expected columns and indexes.
     *
     * Asserts presence of each named column and both named indexes rather than
     * asserting a count — resilient to future additive columns.
     */
    public function testMigrationCreatesTableWithExpectedColumns(): void
    {
        $result = $this->db->query('SHOW COLUMNS FROM schedule_guard_rejects');
        self::assertNotFalse($result, 'SHOW COLUMNS failed: ' . $this->db->error);

        $columns = [];
        while ($row = $result->fetch_assoc()) {
            $columns[] = $row['Field'];
        }
        $result->free();

        foreach (['id', 'rejected_at', 'season_year', 'game_date', 'visitor_teamid', 'home_teamid', 'game_of_that_day', 'reason', 'stored_game_of_that_day', 'source_archive'] as $col) {
            self::assertContains($col, $columns, "Column '{$col}' must exist in schedule_guard_rejects");
        }

        $idxResult = $this->db->query('SHOW INDEX FROM schedule_guard_rejects');
        self::assertNotFalse($idxResult, 'SHOW INDEX failed: ' . $this->db->error);

        $indexNames = [];
        while ($row = $idxResult->fetch_assoc()) {
            $indexNames[] = $row['Key_name'];
        }
        $idxResult->free();

        self::assertContains('idx_season_rejected', $indexNames, 'Index idx_season_rejected must exist');
        self::assertContains('idx_triple', $indexNames, 'Index idx_triple must exist');
    }

    // ── Write path ────────────────────────────────────────────────────────────

    /**
     * Three rejects in, three rows out — round-trip reason, gotd, and source_archive.
     */
    public function testRecordRejectedGamesInsertsOneRowPerReject(): void
    {
        $rejects = [
            new RejectedGame('2008-04-05', 21, 17, 1, RejectedGame::REASON_NOT_IN_SCHEDULE),
            new RejectedGame('2008-04-06', 3, 8, 2, RejectedGame::REASON_NOT_IN_SCHEDULE),
            new RejectedGame('2008-04-07', 5, 12, 1, RejectedGame::REASON_NOT_IN_SCHEDULE),
        ];

        $result = $this->repo->recordRejectedGames(2008, $rejects, '07-08_36_playoffs.zip');

        self::assertSame(3, $result);

        $stmt = $this->db->prepare('SELECT reason, game_of_that_day, source_archive FROM schedule_guard_rejects WHERE season_year = 2008 ORDER BY game_date');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        self::assertCount(3, $rows);
        self::assertSame(RejectedGame::REASON_NOT_IN_SCHEDULE, $rows[0]['reason']);
        self::assertSame(2, $rows[1]['game_of_that_day']);
        self::assertSame('07-08_36_playoffs.zip', $rows[2]['source_archive']);
    }

    /**
     * Empty reject list must return 0 and write nothing — the overwhelmingly common path.
     */
    public function testEmptyRejectListWritesNothingAndReturnsZero(): void
    {
        $result = $this->repo->recordRejectedGames(2008, [], null);

        self::assertSame(0, $result);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM schedule_guard_rejects WHERE season_year = 2008');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame(0, (int) $row['cnt']);
    }

    /**
     * A null source archive must be stored as '' — not NULL — since the column is NOT NULL.
     */
    public function testNullSourceArchiveIsStoredAsEmptyString(): void
    {
        $reject = new RejectedGame('2008-01-15', 21, 17, 1, RejectedGame::REASON_NOT_IN_SCHEDULE);

        $result = $this->repo->recordRejectedGames(2008, [$reject], null);

        self::assertSame(1, $result);

        $stmt = $this->db->prepare('SELECT source_archive FROM schedule_guard_rejects WHERE season_year = 2008 LIMIT 1');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame('', $row['source_archive']);
    }

    /**
     * A duplicate-triple reject with stored ordinals [1, 6] must persist '1,6' in
     * stored_game_of_that_day — comma-joined, not JSON.
     */
    public function testDuplicateTripleRejectStoresStoredOrdinals(): void
    {
        $reject = new RejectedGame('2008-04-05', 21, 17, 3, RejectedGame::REASON_DUPLICATE_TRIPLE, [1, 6]);

        $this->repo->recordRejectedGames(2008, [$reject], null);

        $stmt = $this->db->prepare('SELECT stored_game_of_that_day FROM schedule_guard_rejects WHERE season_year = 2008 LIMIT 1');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame('1,6', $row['stored_game_of_that_day']);
    }

    /**
     * When the reject count exceeds MAX_RECORDED_REJECTS, only MAX_RECORDED_REJECTS rows
     * are written and the return value equals MAX_RECORDED_REJECTS.
     */
    public function testExceedingMaxRecordedRejectsTruncatesAndStillReturns(): void
    {
        $rejects = [];
        $max     = BoxscoreRepository::MAX_RECORDED_REJECTS;
        for ($i = 0; $i <= $max; $i++) {
            $rejects[] = new RejectedGame('2008-01-15', 21, 17, $i + 1, RejectedGame::REASON_NOT_IN_SCHEDULE);
        }

        $result = $this->repo->recordRejectedGames(2008, $rejects, null);

        self::assertSame($max, $result);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM schedule_guard_rejects WHERE season_year = 2008');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        self::assertSame($max, (int) $row['cnt']);
    }

    /**
     * A DB failure (closed connection) must not propagate — the method returns 0 and
     * the surrounding import continues. Reaching the assertion is the no-throw proof.
     */
    public function testWriteFailureIsSwallowedAndReturnsZero(): void
    {
        $reject = new RejectedGame('2008-01-15', 21, 17, 1, RejectedGame::REASON_NOT_IN_SCHEDULE);

        // Close the connection so any DB operation inside recordRejectedGames() throws.
        // DatabaseTestCase::tearDown() wraps rollback/close in try/catch \Throwable,
        // so the already-closed connection does not cause a secondary test failure.
        $this->db->close();

        $result = $this->repo->recordRejectedGames(2008, [$reject], null);

        // Reaching this assertion confirms no exception escaped the method.
        self::assertSame(0, $result);
    }

    /**
     * Two runs for different season years must not bleed into each other's listing.
     */
    public function testRecordsAreScopedToTheOperatingSeason(): void
    {
        $reject2008 = new RejectedGame('2008-04-05', 21, 17, 1, RejectedGame::REASON_NOT_IN_SCHEDULE);
        $reject2009 = new RejectedGame('2009-04-05', 3, 8, 1, RejectedGame::REASON_NOT_IN_SCHEDULE);

        $this->repo->recordRejectedGames(2008, [$reject2008], null);
        $this->repo->recordRejectedGames(2009, [$reject2009], null);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM schedule_guard_rejects WHERE season_year = 2008');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertSame(1, (int) $row['cnt']);

        $stmt = $this->db->prepare('SELECT COUNT(*) AS cnt FROM schedule_guard_rejects WHERE season_year = 2009');
        self::assertNotFalse($stmt);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
        self::assertSame(1, (int) $row['cnt']);
    }
}
