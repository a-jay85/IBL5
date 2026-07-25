<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Pagination\Paginator;
use Api\Repository\ApiPlayerRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiPlayerRepositoryTest extends WideUnitTestCase
{
    private ApiPlayerRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiPlayerRepository($this->mockDb);
    }

    // --- getPlayers ---

    public function testGetPlayersReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-1', 'pid' => 10, 'name' => 'Alice', 'position' => 'PG'],
        ]);

        $result = $this->repository->getPlayers($this->buildPaginator());

        self::assertCount(1, $result);
        self::assertSame('Alice', $result[0]['name']);
    }

    public function testGetPlayersQueriesPlayerCurrentView(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getPlayers($this->buildPaginator());

        $this->assertQueryExecuted('vw_player_current');
    }

    public function testGetPlayersWithPositionFilterAppliesWhereClause(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getPlayers($this->buildPaginator(), ['position' => 'PG']);

        $this->assertQueryExecuted('position =');
    }

    public function testGetPlayersWithTeamFilterAppliesWhereClause(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getPlayers($this->buildPaginator(), ['team' => 'team-uuid-1']);

        $this->assertQueryExecuted('team_uuid =');
    }

    public function testGetPlayersWithSearchFilterAppliesLike(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getPlayers($this->buildPaginator(), ['search' => 'james']);

        $this->assertQueryExecuted('name LIKE');
    }

    public function testGetPlayersReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getPlayers($this->buildPaginator());

        self::assertSame([], $result);
    }

    // --- countPlayers ---

    public function testCountPlayersReturnsTotal(): void
    {
        $this->mockDb->setMockData([['total' => 320]]);

        $count = $this->repository->countPlayers();

        self::assertSame(320, $count);
    }

    public function testCountPlayersReturnsZeroWhenNoResult(): void
    {
        $this->mockDb->setMockData([]);

        $count = $this->repository->countPlayers();

        self::assertSame(0, $count);
    }

    public function testCountPlayersWithPositionFilterAppliesWhere(): void
    {
        $this->mockDb->setMockData([['total' => 80]]);

        $this->repository->countPlayers(['position' => 'SG']);

        $this->assertQueryExecuted('position =');
    }

    // --- getAllPlayersForExport ---

    public function testGetAllPlayersForExportReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-1', 'name' => 'Alice'],
            ['player_uuid' => 'uuid-2', 'name' => 'Bob'],
        ]);

        $result = $this->repository->getAllPlayersForExport();

        self::assertCount(2, $result);
    }

    public function testGetAllPlayersForExportQueriesViewOrderedByName(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getAllPlayersForExport();

        $this->assertQueryExecuted('name ASC');
    }

    // --- getPlayerByUuid ---

    public function testGetPlayerByUuidReturnsPlayer(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-abc', 'name' => 'Charlie', 'position' => 'SF'],
        ]);

        $result = $this->repository->getPlayerByUuid('uuid-abc');

        self::assertIsArray($result);
        self::assertSame('Charlie', $result['name']);
    }

    public function testGetPlayerByUuidReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getPlayerByUuid('no-such-uuid');

        self::assertNull($result);
    }

    public function testGetPlayerByUuidQueriesPlayerUuidColumn(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getPlayerByUuid('test-uuid');

        $this->assertQueryExecuted('player_uuid');
    }

    private function buildPaginator(): Paginator
    {
        return new Paginator([], 'name', ['name', 'position', 'points_per_game']);
    }
}
