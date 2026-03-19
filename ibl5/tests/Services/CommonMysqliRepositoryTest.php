<?php

declare(strict_types=1);

namespace Tests\Services;

use Services\CommonMysqliRepository;
use Tests\Integration\IntegrationTestCase;

class CommonMysqliRepositoryTest extends IntegrationTestCase
{
    private CommonMysqliRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new CommonMysqliRepository($this->mockDb);
    }

    public function testGetTeamnameFromUsernameQueriesTeamInfo(): void
    {
        $this->mockDb->setMockData([['team_name' => 'Metros']]);

        $result = $this->repository->getTeamnameFromUsername('testuser');

        $this->assertSame('Metros', $result);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testGetTeamnameFromUsernameReturnsFreeAgentsForEmptyUsername(): void
    {
        $result = $this->repository->getTeamnameFromUsername('');

        $this->assertSame('Free Agents', $result);
    }

    public function testGetTeamnameFromUsernameReturnsFreeAgentsForNull(): void
    {
        $result = $this->repository->getTeamnameFromUsername(null);

        $this->assertSame('Free Agents', $result);
    }

    public function testGetTeamnameFromUsernameReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamnameFromUsername('nonexistent');

        $this->assertNull($result);
    }

    public function testGetUsernameFromTeamnameQueriesTeamInfo(): void
    {
        $this->mockDb->setMockData([['gm_username' => 'testuser']]);

        $result = $this->repository->getUsernameFromTeamname('Metros');

        $this->assertSame('testuser', $result);
        $this->assertQueryExecuted('ibl_team_info');
    }

    public function testGetUsernameFromTeamnameReturnsNullWhenNotFound(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getUsernameFromTeamname('NonExistentTeam');

        $this->assertNull($result);
    }

    public function testGetUsernameFromTeamnameReturnsNullForVacantTeam(): void
    {
        $this->mockDb->setMockData([['gm_username' => null]]);

        $result = $this->repository->getUsernameFromTeamname('Metros');

        $this->assertNull($result);
    }

    // ============================================
    // getTeamSalarySummary() tests
    // ============================================

    public function testGetTeamSalarySummaryReturnsBothSalaryTotals(): void
    {
        $this->mockDb->setMockData([['current_salary' => 5000, 'next_year_salary' => 4500]]);

        $result = $this->repository->getTeamSalarySummary('Metros');

        $this->assertSame(5000, $result['current']);
        $this->assertSame(4500, $result['nextYear']);
    }

    public function testGetTeamSalarySummaryReturnsZerosForEmptyTeam(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamSalarySummary('Empty Team');

        $this->assertSame(0, $result['current']);
        $this->assertSame(0, $result['nextYear']);
    }
}
