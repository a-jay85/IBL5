<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Pagination\Paginator;
use Api\Repository\ApiLeadersRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiLeadersRepositoryTest extends WideUnitTestCase
{
    private ApiLeadersRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiLeadersRepository($this->mockDb);
    }

    // --- getLeaders ---

    public function testGetLeadersReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-1', 'pid' => 10, 'name' => 'Alice', 'games' => 82, 'pts' => 2000],
        ]);

        $result = $this->repository->getLeaders($this->buildPaginator());

        self::assertCount(1, $result);
        self::assertSame('Alice', $result[0]['name']);
    }

    public function testGetLeadersQueriesHistTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator());

        $this->assertQueryExecuted('ibl_hist');
    }

    public function testGetLeadersWithPpgCategoryUsesPpgSortExpression(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['category' => 'ppg']);

        // The ppg sort expression divides points by games
        $this->assertQueryExecuted('fgm');
    }

    public function testGetLeadersWithRpgCategoryUsesRpgSortExpression(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['category' => 'rpg']);

        $this->assertQueryExecuted('reb');
    }

    public function testGetLeadersWithInvalidCategoryFallsBackToPpg(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['category' => 'invalid_category']);

        // Falls back to ppg — the ppg expression includes fgm
        $this->assertQueryExecuted('fgm');
        // Should NOT use an invalid sort expression
        $this->assertQueryNotExecuted('invalid_category');
    }

    public function testGetLeadersWithSeasonFilterAppliesWhere(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['season' => '2025']);

        $this->assertQueryExecuted('h.year');
    }

    public function testGetLeadersWithMinGamesFilterAboveZeroAppliesWhere(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['min_games' => '10']);

        $this->assertQueryExecuted('h.games >=');
    }

    public function testGetLeadersWithMinGamesFilterOfZeroDoesNotApplyWhere(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getLeaders($this->buildPaginator(), ['min_games' => '0']);

        $this->assertQueryNotExecuted('h.games >=');
    }

    public function testGetLeadersReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getLeaders($this->buildPaginator());

        self::assertSame([], $result);
    }

    // --- countLeaders ---

    public function testCountLeadersReturnsTotal(): void
    {
        $this->mockDb->setMockData([['total' => 150]]);

        $count = $this->repository->countLeaders();

        self::assertSame(150, $count);
    }

    public function testCountLeadersReturnsZeroWhenNoResult(): void
    {
        $this->mockDb->setMockData([]);

        $count = $this->repository->countLeaders();

        self::assertSame(0, $count);
    }

    public function testCountLeadersWithSeasonFilterAppliesWhere(): void
    {
        $this->mockDb->setMockData([['total' => 80]]);

        $this->repository->countLeaders(['season' => '2025']);

        $this->assertQueryExecuted('h.year');
    }

    // --- getAvailableSeasons ---

    public function testGetAvailableSeasonsReturnsSeasonYears(): void
    {
        $this->mockDb->setMockData([
            ['year' => 2025],
            ['year' => 2024],
        ]);

        $result = $this->repository->getAvailableSeasons();

        self::assertIsArray($result);
    }

    private function buildPaginator(): Paginator
    {
        return new Paginator([], 'pts', ['pts', 'games', 'ast']);
    }
}
