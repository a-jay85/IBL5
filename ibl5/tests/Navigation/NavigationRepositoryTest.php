<?php

declare(strict_types=1);

namespace Tests\Navigation;

use Navigation\NavigationRepository;
use Tests\Integration\IntegrationTestCase;

class NavigationRepositoryTest extends IntegrationTestCase
{
    private NavigationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NavigationRepository($this->mockDb);
    }

    public function testResolveTeamIdReturnsIdForValidUser(): void
    {
        $this->mockDb->setMockData([['teamid' => 5]]);

        $result = $this->repository->resolveTeamId('TestUser');

        $this->assertSame(5, $result);
        $this->assertQueryExecuted('nuke_users');
    }

    public function testResolveTeamIdReturnsNullForInvalidUser(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->resolveTeamId('NonExistentUser');

        $this->assertNull($result);
    }

    public function testGetTeamsDataReturnsStructuredArray(): void
    {
        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'conference' => 'Eastern', 'division' => 'Atlantic'],
            ['teamid' => 2, 'team_name' => 'Lakers', 'team_city' => 'Los Angeles', 'conference' => 'Western', 'division' => 'Pacific'],
        ]);

        $result = $this->repository->getTeamsData();

        $this->assertNotNull($result);
        $this->assertArrayHasKey('Eastern', $result);
        $this->assertArrayHasKey('Western', $result);
        $this->assertArrayHasKey('Atlantic', $result['Eastern']);
        $this->assertArrayHasKey('Pacific', $result['Western']);
        $this->assertSame(1, $result['Eastern']['Atlantic'][0]['teamid']);
        $this->assertSame('Celtics', $result['Eastern']['Atlantic'][0]['team_name']);
    }

    public function testGetTeamsDataReturnsNullWhenEmpty(): void
    {
        $this->mockDb->setMockData([]);

        $result = $this->repository->getTeamsData();

        $this->assertNull($result);
    }
}
