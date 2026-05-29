<?php

declare(strict_types=1);

namespace Tests\Navigation;

use League\LeagueContext;
use Navigation\NavigationRepository;
use Tests\WideUnit\WideUnitTestCase;

class NavigationRepositoryTest extends WideUnitTestCase
{
    private NavigationRepository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->repository = new NavigationRepository($this->mockDb);
    }

    protected function tearDown(): void
    {
        // setSharedLeagueContext() writes a static slot that leaks into every
        // later test in the process; always clear it.
        \BaseMysqliRepository::clearSharedLeagueContext();
        parent::tearDown();
    }

    public function testResolveTeamIdReturnsIdForValidUser(): void
    {
        $this->mockDb->setMockData([['teamid' => 5]]);

        $result = $this->repository->resolveTeamId('TestUser');

        $this->assertSame(5, $result);
        $this->assertQueryExecuted('ibl_team_info');
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

    // ── Olympics static-path identity lock (the #878 regression) ──────────
    // NavigationRepository receives NO instance LeagueContext in production
    // (themes/IBL/theme.php constructs `new NavigationRepository($mysqli_db)`),
    // so the only Olympics signal it could see is the STATIC shared context.
    // resolveTeamId() reads the IBL-only `gm_username` column; it MUST stay on
    // ibl_team_info even under Olympics, or it fatals on the missing column.

    public function testResolveTeamIdStaysOnIblTeamInfoUnderOlympicsStaticContext(): void
    {
        $context = new LeagueContext();
        $context->setLeague(LeagueContext::LEAGUE_OLYMPICS);
        \BaseMysqliRepository::setSharedLeagueContext($context);

        $this->mockDb->setMockData([['teamid' => 5]]);

        $result = $this->repository->resolveTeamId('TestUser');

        $this->assertSame(5, $result);
        $this->assertQueryExecuted('ibl_team_info');
        $this->assertQueryNotExecuted('ibl_olympics_team_info');
    }

    public function testGetTeamsDataReturnsIblTeamsUnderOlympicsStaticContext(): void
    {
        $context = new LeagueContext();
        $context->setLeague(LeagueContext::LEAGUE_OLYMPICS);
        \BaseMysqliRepository::setSharedLeagueContext($context);

        $this->mockDb->setMockData([
            ['teamid' => 1, 'team_name' => 'Celtics', 'team_city' => 'Boston', 'conference' => 'Eastern', 'division' => 'Atlantic'],
        ]);

        $result = $this->repository->getTeamsData();

        $this->assertNotNull($result);
        $this->assertSame('Celtics', $result['Eastern']['Atlantic'][0]['team_name']);
        $this->assertQueryExecuted('ibl_team_info');
        $this->assertQueryExecuted('ibl_standings');
        $this->assertQueryNotExecuted('ibl_olympics_team_info');
        $this->assertQueryNotExecuted('ibl_olympics_standings');
    }
}
