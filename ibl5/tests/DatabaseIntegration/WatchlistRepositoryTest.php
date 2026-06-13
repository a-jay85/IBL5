<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use PHPUnit\Framework\Attributes\Group;
use Watchlist\WatchlistRepository;

/**
 * Integration coverage for WatchlistRepository against real MariaDB.
 *
 * Teams 1 (Metros) and 2 (Stars) are pre-seeded by the DB fixture, so they
 * satisfy fk_watchlist_team. Each watched pid is inserted via insertTestPlayer()
 * to satisfy fk_watchlist_player. All work rolls back per test (DatabaseTestCase).
 */
#[Group('database')]
class WatchlistRepositoryTest extends DatabaseTestCase
{
    private const TEAM_A = 1;
    private const TEAM_B = 2;
    private const PID_A = 200000901;
    private const PID_B = 200000902;

    private WatchlistRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repo = new WatchlistRepository($this->db);
    }

    public function testAddWatchInsertsScopedRowAndIsWatchedReturnsTrue(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Watch Target A');

        self::assertFalse($this->repo->isWatched(self::TEAM_A, self::PID_A));
        self::assertTrue($this->repo->addWatch(self::TEAM_A, self::PID_A));
        self::assertTrue($this->repo->isWatched(self::TEAM_A, self::PID_A));
        // Scoped: a different team does not see the row.
        self::assertFalse($this->repo->isWatched(self::TEAM_B, self::PID_A));
    }

    public function testAddWatchIsIdempotent(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Watch Target A');

        self::assertTrue($this->repo->addWatch(self::TEAM_A, self::PID_A));
        self::assertTrue($this->repo->addWatch(self::TEAM_A, self::PID_A));

        self::assertSame(1, $this->countRows(self::TEAM_A, self::PID_A));
    }

    public function testSaveNotePersistsAndIsReturnedByGetWatchlist(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Watch Target A');
        $this->repo->addWatch(self::TEAM_A, self::PID_A);

        self::assertTrue($this->repo->saveNote(self::TEAM_A, self::PID_A, 'sleeper pick'));

        $rows = $this->repo->getWatchlistForTeam(self::TEAM_A);
        self::assertCount(1, $rows);
        self::assertSame(self::PID_A, $rows[0]['pid']);
        self::assertSame('sleeper pick', $rows[0]['note']);
        self::assertSame('Watch Target A', $rows[0]['name']);
    }

    public function testGetWatchlistReturnsOnlyWatchedPlayersWithJoinedData(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Watch Target A', ['pos' => 'SG']);
        $this->insertTestPlayer(self::PID_B, 'Unwatched B');
        $this->repo->addWatch(self::TEAM_A, self::PID_A);

        $rows = $this->repo->getWatchlistForTeam(self::TEAM_A);

        self::assertCount(1, $rows);
        self::assertSame(self::PID_A, $rows[0]['pid']);
        self::assertSame('SG', $rows[0]['pos']);
    }

    public function testGetWatchlistDoesNotLeakOtherTeamsRows(): void
    {
        $this->insertTestPlayer(self::PID_A, 'Team A Target');
        $this->insertTestPlayer(self::PID_B, 'Team B Target');
        $this->repo->addWatch(self::TEAM_A, self::PID_A);
        $this->repo->addWatch(self::TEAM_B, self::PID_B);

        $teamARows = $this->repo->getWatchlistForTeam(self::TEAM_A);

        self::assertCount(1, $teamARows);
        self::assertSame(self::PID_A, $teamARows[0]['pid']);
    }

    public function testSaveNoteDoesNotMutateAnotherTeamsRow(): void
    {
        $this->insertTestPlayer(self::PID_B, 'Team B Only');
        $this->repo->addWatch(self::TEAM_B, self::PID_B);
        $this->repo->saveNote(self::TEAM_B, self::PID_B, 'B private note');

        // Team A attempts to write a note against a pid only team B watches.
        $this->repo->saveNote(self::TEAM_A, self::PID_B, 'A injected note');

        $bRows = $this->repo->getWatchlistForTeam(self::TEAM_B);
        self::assertCount(1, $bRows);
        self::assertSame('B private note', $bRows[0]['note']);
        // No row was created for team A.
        self::assertSame(0, $this->countRows(self::TEAM_A, self::PID_B));
    }

    public function testRemoveWatchDoesNotAffectAnotherTeamsRow(): void
    {
        $this->insertTestPlayer(self::PID_B, 'Team B Only');
        $this->repo->addWatch(self::TEAM_B, self::PID_B);

        // Team A attempts to remove a pid only team B watches.
        $this->repo->removeWatch(self::TEAM_A, self::PID_B);

        self::assertSame(1, $this->countRows(self::TEAM_B, self::PID_B));
        self::assertTrue($this->repo->isWatched(self::TEAM_B, self::PID_B));
    }

    private function countRows(int $teamid, int $pid): int
    {
        $stmt = $this->db->prepare(
            "SELECT COUNT(*) AS c FROM `gm_player_watchlist` WHERE teamid = ? AND pid = ?"
        );
        self::assertNotFalse($stmt);
        $stmt->bind_param('ii', $teamid, $pid);
        $stmt->execute();
        $result = $stmt->get_result();
        self::assertNotFalse($result);
        /** @var array{c: int} $row */
        $row = $result->fetch_assoc();
        $stmt->close();

        return (int) $row['c'];
    }
}
