<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Pagination\Paginator;
use Api\Repository\ApiTeamRepository;
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

    private function buildPaginator(): Paginator
    {
        return new Paginator([], 'team_name', ['team_name', 'team_city', 'conference']);
    }
}
