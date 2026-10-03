<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Pagination\Paginator;
use Api\Repository\ApiTeamRepository;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\WideUnit\WideUnitTestCase;

class ApiTeamRepositoryTest extends WideUnitTestCase
{
    private ApiTeamRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiTeamRepository($this->mockDb);
    }

    // --- getTeams ---

    public function testGetTeamsReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'uuid' => 'team-uuid-1', 'team_city' => 'Chicago', 'team_name' => 'Bulls'],
            ['teamid' => 2, 'uuid' => 'team-uuid-2', 'team_city' => 'New York', 'team_name' => 'Knicks'],
        ]);

        $result = $this->repository->getTeams($this->buildPaginator());

        self::assertCount(2, $result);
        self::assertSame('Bulls', $result[0]['team_name']);
    }

    public function testGetTeamsQueriesTeamInfoTable(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeams($this->buildPaginator());

        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testGetTeamsFiltersToRealTeamIds(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeams($this->buildPaginator());

        $this->assertQueryExecuted('BETWEEN 1 AND');
    }

    public function testGetTeamsReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeams($this->buildPaginator());

        self::assertSame([], $result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function allowedSortColumnProvider(): array
    {
        return [
            'team_name' => ['team_name'],
            'team_city' => ['team_city'],
            'owner_name' => ['owner_name'],
            'conference' => ['conference'],
            'division' => ['division'],
        ];
    }

    /**
     * Characterization pin: every allowed sort column reaches the executed SQL
     * as the ORDER BY identifier. Literal oracle — independent of production consts.
     */
    #[DataProvider('allowedSortColumnProvider')]
    public function testGetTeamsOrdersByEachAllowedSortColumn(string $col): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeams($this->buildAllowlistPaginator(['sort' => $col]));

        $this->assertPreparedSqlContains(' ORDER BY ' . $col . ' ASC LIMIT ? OFFSET ?');
    }

    public function testGetTeamsFallsBackToDefaultSortForRejectedColumn(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeams($this->buildAllowlistPaginator(['sort' => 'owner_name DESC, (SELECT 1)']));

        $this->assertPreparedSqlContains(' ORDER BY team_name ASC ');
        foreach ($this->mockDb->getPreparedQueries() as $query) {
            self::assertStringNotContainsString('DROP', $query);
            self::assertStringNotContainsString('SELECT 1', $query);
        }
    }

    public function testGetTeamsOrdersDescendingWhenRequested(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeams($this->buildAllowlistPaginator(['sort' => 'owner_name', 'order' => 'DESC']));
        $this->assertPreparedSqlContains(' ORDER BY owner_name DESC ');

        $this->mockDb->clearQueries();

        $this->repository->getTeams($this->buildAllowlistPaginator(['sort' => 'owner_name', 'order' => 'sideways']));
        $this->assertPreparedSqlContains(' ORDER BY owner_name ASC ');
        $this->assertPreparedSqlNotContains(' DESC ');
    }

    // --- countTeams ---

    public function testCountTeamsReturnsTotal(): void
    {
        $this->mockDb->setMockData([['total' => 32]]);

        $count = $this->repository->countTeams();

        self::assertSame(32, $count);
    }

    public function testCountTeamsReturnsZeroWhenFetchReturnsNull(): void
    {
        $this->mockDb->setMockData([]);

        $count = $this->repository->countTeams();

        self::assertSame(0, $count);
    }

    public function testCountTeamsQueriesTeamInfoTable(): void
    {
        $this->mockDb->setMockData([['total' => 32]]);

        $this->repository->countTeams();

        $this->assertQueryExecuted('ibl_team_info');
    }

    // --- getTeamByUuid ---

    public function testGetTeamByUuidReturnsTeam(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'uuid' => 'team-uuid-abc', 'team_city' => 'Boston', 'team_name' => 'Celtics'],
        ]);

        $result = $this->repository->getTeamByUuid('team-uuid-abc');

        self::assertIsArray($result);
        self::assertSame('Celtics', $result['team_name']);
    }

    public function testGetTeamByUuidReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamByUuid('no-such-uuid');

        self::assertNull($result);
    }

    public function testGetTeamByUuidQueriesOnUuid(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getTeamByUuid('test-uuid');

        $this->assertQueryExecuted('t.uuid =');
    }

    public function testGetTeamsThrowsForSortOutsideRepositoryMap(): void
    {
        $paginator = new Paginator(['sort' => 'secret_col'], 'secret_col', ['secret_col']);

        try {
            $this->repository->getTeams($paginator);
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
    public function testTeamSortColumnsMatchPublishedApiSortList(): void
    {
        $expected = ['team_name', 'team_city', 'owner_name', 'conference', 'division'];

        self::assertSame($expected, array_keys(ApiTeamRepository::SORT_COLUMNS));
        self::assertSame($expected, array_values(ApiTeamRepository::SORT_COLUMNS));
    }

    private function buildPaginator(): Paginator
    {
        return new Paginator([], 'team_name', ['team_name', 'team_city', 'conference']);
    }

    /**
     * Paginator built directly with literal allowlist/default (independent oracle).
     *
     * @param array<string, string> $query
     */
    private function buildAllowlistPaginator(array $query): Paginator
    {
        return new Paginator($query, 'team_name', ['team_name', 'team_city', 'owner_name', 'conference', 'division']);
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
