<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Pagination\Paginator;
use Api\Repository\ApiGameRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\WideUnit\WideUnitTestCase;

class ApiGameRepositoryTest extends WideUnitTestCase
{
    private ApiGameRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiGameRepository($this->mockDb);
    }

    // --- getGames ---

    public function testGetGamesReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['game_uuid' => 'g-uuid-1', 'season_year' => 2025, 'game_status' => 'Final'],
        ]);

        $result = $this->repository->getGames($this->buildPaginator());

        self::assertCount(1, $result);
        self::assertSame('g-uuid-1', $result[0]['game_uuid']);
    }

    public function testGetGamesQueriesScheduleView(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator());

        $this->assertQueryExecuted('vw_schedule_upcoming');
    }

    public function testGetGamesWithSeasonFilterBindsSeason(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator(), ['season' => '2025']);

        $this->assertQueryExecuted('season_year');
    }

    public function testGetGamesWithStatusFilterBindsStatus(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator(), ['status' => 'Final']);

        $this->assertQueryExecuted('game_status');
    }

    public function testGetGamesWithTeamFilterBindsTeamUuid(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator(), ['team' => 'team-uuid-1']);

        $this->assertQueryExecuted('visitor_uuid');
    }

    public function testGetGamesWithDateFilterBindsDate(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator(), ['date' => '2025-01-15']);

        $this->assertQueryExecuted('game_date =');
    }

    public function testGetGamesWithDateRangeFilter(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildPaginator(), [
            'date_start' => '2025-01-01',
            'date_end' => '2025-01-31',
        ]);

        $this->assertQueryExecuted('game_date >=');
    }

    public function testGetGamesReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getGames($this->buildPaginator());

        self::assertSame([], $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedSortColumnProvider(): array
    {
        return [
            'game_date' => ['game_date'],
            'visitor_score' => ['visitor_score'],
            'home_score' => ['home_score'],
        ];
    }

    /**
     * Characterization pin: every allowed sort column reaches the executed SQL
     * as the ORDER BY identifier. Literal oracle — independent of production consts.
     */
    #[DataProvider('allowedSortColumnProvider')]
    public function testGetGamesOrdersByEachAllowedSortColumn(string $col): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildAllowlistPaginator(['sort' => $col]));

        $this->assertPreparedSqlContains(' ORDER BY ' . $col . ' ASC LIMIT ? OFFSET ?');
    }

    public function testGetGamesFallsBackToDefaultSortForRejectedColumn(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildAllowlistPaginator(['sort' => 'uuid']));

        $this->assertPreparedSqlContains(' ORDER BY game_date ASC ');
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            self::assertStringNotContainsString('DROP', $query);
            self::assertStringNotContainsString('SELECT 1', $query);
        }
    }

    public function testGetGamesOrdersDescendingWhenRequested(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGames($this->buildAllowlistPaginator(['sort' => 'visitor_score', 'order' => 'DESC']));
        $this->assertPreparedSqlContains(' ORDER BY visitor_score DESC ');

        $this->mockDb->clearQueries();

        $this->repository->getGames($this->buildAllowlistPaginator(['sort' => 'visitor_score', 'order' => 'sideways']));
        $this->assertPreparedSqlContains(' ORDER BY visitor_score ASC ');
        $this->assertPreparedSqlNotContains(' DESC ');
    }

    // --- countGames ---

    public function testCountGamesReturnsTotal(): void
    {
        $this->mockDb->setMockData([['total' => 42]]);

        $count = $this->repository->countGames();

        self::assertSame(42, $count);
    }

    public function testCountGamesReturnsZeroWhenNoneFound(): void
    {
        $this->mockDb->setMockData([]);

        $count = $this->repository->countGames();

        self::assertSame(0, $count);
    }

    public function testCountGamesWithSeasonFilterAppliesWhereClause(): void
    {
        $this->mockDb->setMockData([['total' => 10]]);

        $this->repository->countGames(['season' => '2025']);

        $this->assertQueryExecuted('season_year');
    }

    // --- getGameByUuid ---

    public function testGetGameByUuidReturnsGame(): void
    {
        $this->mockDb->setMockData([
            ['game_uuid' => 'g-uuid-abc', 'season_year' => 2025, 'game_status' => 'Final'],
        ]);

        $result = $this->repository->getGameByUuid('g-uuid-abc');

        self::assertIsArray($result);
        self::assertSame('g-uuid-abc', $result['game_uuid']);
    }

    public function testGetGameByUuidReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getGameByUuid('no-such-uuid');

        self::assertNull($result);
    }

    public function testGetGameByUuidQueriesGameUuid(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getGameByUuid('test-uuid');

        $this->assertQueryExecuted('game_uuid');
    }

    // --- getBoxscoreTeams ---

    public function testGetBoxscoreTeamsReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['visitor_q1_points' => 30, 'home_q1_points' => 28],
        ]);

        $result = $this->repository->getBoxscoreTeams(1, 2, '2025-01-15');

        self::assertCount(1, $result);
    }

    public function testGetBoxscoreTeamsQueriesBoxScoresTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getBoxscoreTeams(1, 2, '2025-01-15');

        $this->assertQueryExecuted('ibl_box_scores_teams');
    }

    // --- getBoxscorePlayers ---

    public function testGetBoxscorePlayersReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['name' => 'Player A', 'pos' => 'PG', 'game_min' => 38],
        ]);

        $result = $this->repository->getBoxscorePlayers(1, 2, '2025-01-15');

        self::assertCount(1, $result);
        self::assertSame('Player A', $result[0]['name']);
    }

    public function testGetBoxscorePlayersQueriesBoxScoresTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getBoxscorePlayers(1, 2, '2025-01-15');

        $this->assertQueryExecuted('ibl_box_scores');
    }

    public function testGamesThrowsForSortOutsideRepositoryMap(): void
    {
        $paginator = new Paginator(['sort' => 'secret_col'], 'secret_col', ['secret_col']);

        try {
            $this->repository->getGames($paginator);
            self::fail('expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('Invalid sort column: secret_col', $e->getMessage());
        } finally {
            self::assertSame([], $this->mockDb->getExecutedQueries());
        }
    }

    /**
     * Contract pin: SORT_COLUMNS publishes exactly the master API sort vocabulary,
     * each key mapping to the identically named SQL column.
     */
    public function testGameSortColumnsMatchPublishedApiSortList(): void
    {
        $expected = ['game_date', 'visitor_score', 'home_score'];

        self::assertSame($expected, array_keys(ApiGameRepository::SORT_COLUMNS));
        self::assertSame($expected, array_values(ApiGameRepository::SORT_COLUMNS));
    }

    private function buildPaginator(): Paginator
    {
        return new Paginator([], 'game_date', ['game_date', 'season_year', 'game_status']);
    }

    /**
     * Paginator built directly with literal allowlist/default (independent oracle).
     *
     * @param array<string, string> $query
     */
    private function buildAllowlistPaginator(array $query): Paginator
    {
        return new Paginator($query, 'game_date', ['game_date', 'visitor_score', 'home_score']);
    }

    private function assertPreparedSqlContains(string $needle): void
    {
        $found = false;
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            if (str_contains($query, $needle)) {
                $found = true;
                break;
            }
        }
        self::assertTrue(
            $found,
            "No prepared query contained '" . $needle . "'. Prepared: " . implode(' | ', $this->mockDb->getPreparedQueries())
        );
    }

    private function assertPreparedSqlNotContains(string $needle): void
    {
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            self::assertStringNotContainsString($needle, $query);
        }
    }
}
