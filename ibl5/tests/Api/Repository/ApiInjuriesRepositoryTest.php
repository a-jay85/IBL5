<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\ApiInjuriesRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiInjuriesRepositoryTest extends WideUnitTestCase
{
    private ApiInjuriesRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiInjuriesRepository($this->mockDb);
    }

    public function testGetInjuredPlayersReturnsRows(): void
    {
        $this->mockDb->setMockData([
            ['player_uuid' => 'uuid-1', 'pid' => 10, 'name' => 'John Doe', 'pos' => 'PG', 'injured' => 5,
             'teamid' => 1, 'team_uuid' => 'team-uuid-1', 'team_city' => 'Chicago', 'team_name' => 'Bulls'],
        ]);

        $result = $this->repository->getInjuredPlayers();

        self::assertCount(1, $result);
        self::assertSame('John Doe', $result[0]['name']);
    }

    public function testGetInjuredPlayersReturnsEmptyArrayWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getInjuredPlayers();

        self::assertSame([], $result);
    }

    public function testQueryFiltersOnInjuredColumn(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getInjuredPlayers();

        $this->assertQueryExecuted('injured');
    }

    public function testQueryJoinsTeamInfo(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getInjuredPlayers();

        $this->assertQueryExecuted('ibl_team_info');
    }
}
