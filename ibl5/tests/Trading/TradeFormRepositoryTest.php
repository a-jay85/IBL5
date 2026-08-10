<?php

declare(strict_types=1);

namespace Tests\Trading;

use Tests\WideUnit\WideUnitTestCase;
use Trading\TradeFormRepository;

class TradeFormRepositoryTest extends WideUnitTestCase
{
    private TradeFormRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new TradeFormRepository($this->mockDb);
    }

    public function testGetTeamPlayersForTradingReturnsPlayers(): void
    {
        $this->mockDb->setMockData([
            ['pid' => 1, 'name' => 'Player A', 'pos' => 'PG', 'ordinal' => 1],
            ['pid' => 2, 'name' => 'Player B', 'pos' => 'SG', 'ordinal' => 2],
        ]);

        $result = $this->repository->getTeamPlayersForTrading(3);

        $this->assertCount(2, $result);
    }

    public function testGetTeamPlayersForTradingReturnsEmptyWhenNoPlayers(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamPlayersForTrading(3);

        $this->assertSame([], $result);
    }

    public function testGetTeamDraftPicksForTradingReturnsPicks(): void
    {
        $this->mockDb->setMockData([
            ['pickid' => 1, 'owner_teamid' => 5, 'year' => 2027, 'round' => 1],
            ['pickid' => 2, 'owner_teamid' => 5, 'year' => 2027, 'round' => 2],
        ]);

        $result = $this->repository->getTeamDraftPicksForTrading(5);

        $this->assertCount(2, $result);
    }

    public function testGetTeamDraftPicksForTradingReturnsEmptyWhenNoPicks(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamDraftPicksForTrading(5);

        $this->assertSame([], $result);
    }

    public function testGetAllTeamsWithCityReturnsTeams(): void
    {
        $this->mockDb->setMockData([
            ['team_name' => 'Boston', 'city' => 'Boston'],
            ['team_name' => 'Miami', 'city' => 'Miami'],
        ]);

        $result = $this->repository->getAllTeamsWithCity();

        $this->assertCount(2, $result);
        $this->assertNotEmpty($result);
    }

    public function testGetTeamPlayerCountReturnsCount(): void
    {
        $this->mockDb->setMockData([['cnt' => 5]]);

        $result = $this->repository->getTeamPlayerCount(2);

        $this->assertSame(5, $result);
        $this->assertIsInt($result);
    }

    public function testGetTeamPlayerCountReturnsZeroWhenNoResult(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamPlayerCount(2);

        $this->assertSame(0, $result);
    }

    public function testGetTeamPlayerCountOffseasonExcludesExpiredContracts(): void
    {
        $this->mockDb->onQuery('salary', [['cnt' => 3]]);

        $result = $this->repository->getTeamPlayerCount(2, true);

        $this->assertSame(3, $result);
        $this->assertQueryExecuted('salary');
    }
}
