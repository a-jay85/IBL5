<?php

declare(strict_types=1);

namespace Tests\Watchlist;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
use Watchlist\WatchlistRepository;

/**
 * WideUnit coverage: verifies each method issues a prepared statement against
 * gm_player_watchlist with the values bound (no string interpolation) and the
 * teamid scoping present on every read/write (the IDOR defense).
 */
class WatchlistRepositoryTest extends TestCase
{
    private MockDatabase $mockDb;
    private WatchlistRepository $repository;

    protected function setUp(): void
    {
        $this->mockDb = new MockDatabase();
        $this->repository = new WatchlistRepository($this->mockDb);
    }

    public function testAddWatchExecutesIdempotentInsert(): void
    {
        $this->mockDb->setReturnTrue(true);

        self::assertTrue($this->repository->addWatch(1, 42));

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('INSERT IGNORE INTO gm_player_watchlist', $queries[0]);
        self::assertStringContainsString('VALUES (1, 42)', $queries[0]);
    }

    public function testRemoveWatchExecutesScopedDelete(): void
    {
        $this->mockDb->setReturnTrue(true);

        self::assertTrue($this->repository->removeWatch(1, 42));

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('DELETE FROM gm_player_watchlist', $queries[0]);
        self::assertStringContainsString('WHERE teamid = 1 AND pid = 42', $queries[0]);
    }

    public function testSaveNoteExecutesScopedUpdateWithBoundNote(): void
    {
        $this->mockDb->setReturnTrue(true);

        self::assertTrue($this->repository->saveNote(1, 42, 'scout this guy'));

        $queries = $this->mockDb->getExecutedQueries();
        self::assertCount(1, $queries);
        self::assertStringContainsString('UPDATE gm_player_watchlist SET note =', $queries[0]);
        self::assertStringContainsString("'scout this guy'", $queries[0]);
        self::assertStringContainsString('WHERE teamid = 1 AND pid = 42', $queries[0]);
    }

    public function testIsWatchedTrueWhenRowExists(): void
    {
        $this->mockDb->onQuery('gm_player_watchlist', [['exists' => 1]]);

        self::assertTrue($this->repository->isWatched(1, 42));

        $queries = $this->mockDb->getExecutedQueries();
        self::assertStringContainsString('SELECT 1 FROM gm_player_watchlist', $queries[0]);
        self::assertStringContainsString('WHERE teamid = 1 AND pid = 42', $queries[0]);
    }

    public function testIsWatchedFalseWhenNoRow(): void
    {
        $this->mockDb->onQuery('gm_player_watchlist', []);

        self::assertFalse($this->repository->isWatched(1, 42));
    }

    public function testGetWatchlistForTeamIsScopedByTeamid(): void
    {
        $this->mockDb->onQuery('gm_player_watchlist', [
            ['pid' => 2, 'note' => 'n', 'name' => 'Test Player Two'],
        ]);

        $rows = $this->repository->getWatchlistForTeam(7);

        self::assertCount(1, $rows);
        $queries = $this->mockDb->getExecutedQueries();
        self::assertStringContainsString('FROM gm_player_watchlist w', $queries[0]);
        self::assertStringContainsString('JOIN ibl_plr p', $queries[0]);
        self::assertStringContainsString('WHERE w.teamid = 7', $queries[0]);
    }
}
