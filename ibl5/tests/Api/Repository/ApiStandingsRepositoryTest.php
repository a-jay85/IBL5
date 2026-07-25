<?php

declare(strict_types=1);

namespace Tests\Api\Repository;

use Api\Repository\ApiStandingsRepository;
use Tests\WideUnit\WideUnitTestCase;

class ApiStandingsRepositoryTest extends WideUnitTestCase
{
    private ApiStandingsRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new ApiStandingsRepository($this->mockDb);
    }

    public function testGetStandingsWithoutFilterReturnsAllRows(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_uuid' => 'uuid-1', 'conference' => 'East', 'win_percentage' => 0.75],
            ['teamid' => 2, 'team_uuid' => 'uuid-2', 'conference' => 'West', 'win_percentage' => 0.50],
        ]);

        $result = $this->repository->getStandings();

        self::assertCount(2, $result);
    }

    public function testGetStandingsWithoutFilterQueriesView(): void
    {
        $this->mockDb->setMockData([]);

        $this->repository->getStandings();

        $this->assertQueryExecuted('vw_team_standings');
    }

    public function testGetStandingsWithConferenceFilterQueriesWithConference(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_uuid' => 'uuid-1', 'conference' => 'East', 'win_percentage' => 0.75],
        ]);

        $result = $this->repository->getStandings('East');

        self::assertCount(1, $result);
        $this->assertQueryExecuted('conference');
    }

    public function testGetStandingsWithConferenceFilterDoesNotReturnOtherConferences(): void
    {
        // MockDatabase returns whatever is in mockData regardless, but the SQL with
        // conference filter is sent — verify the WHERE fragment is in the query
        $this->mockDb->setMockData([]);

        $this->repository->getStandings('West');

        $this->assertQueryExecuted('conference =');
    }

    public function testGetStandingsReturnsEmptyWhenNone(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getStandings();

        self::assertSame([], $result);
    }
}
