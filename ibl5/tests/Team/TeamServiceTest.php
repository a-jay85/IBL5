<?php

declare(strict_types=1);

namespace Tests\Team;

use PHPUnit\Framework\TestCase;
use League\League;
use Team\TeamService;
use Team\Contracts\TeamServiceInterface;
use Team\Contracts\TeamRepositoryInterface;
use Team\Contracts\TeamQueryRepositoryInterface;
use Tests\WideUnit\Mocks\MockDatabase;
use Tests\WideUnit\Mocks\TestDataFactory;

/**
 * Tests for TeamService
 *
 * Validates data orchestration logic
 */
class TeamServiceTest extends TestCase
{
    public function testImplementsInterface(): void
    {
        $interfaces = class_implements(TeamService::class);
        self::assertContains(
            TeamServiceInterface::class,
            $interfaces ? $interfaces : [],
        );
    }

    public function testGetTeamPageDataConsumesInjectedTeamQueryRepository(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->onQuery('ibl_team_info', [
            TestDataFactory::createTeam([
                'teamid' => 1,
                'team_name' => 'Bulls',
                'team_city' => 'Chicago',
            ]),
        ]);

        $repository = self::createStub(TeamRepositoryInterface::class);
        // No current-season power data → skip the current-season card branch.
        $repository->method('getTeamPowerData')->willReturn(null);

        $leagueContext = self::createStub(\League\LeagueContext::class);
        $leagueContext->method('getConfig')->willReturn(['images_path' => '/images']);

        $teamQueryRepository = $this->createMock(TeamQueryRepositoryInterface::class);
        $teamQueryRepository->expects($this->once())
            ->method('getDraftPicks')
            ->with(1)
            ->willReturn([]);

        $league = self::createStub(League::class);
        $league->method('getAllTeamsResult')->willReturn([]);

        $service = new TeamService($mockDb, $repository, $leagueContext, $teamQueryRepository, $league);

        $result = $service->getTeamPageData(1, null, 'ratings');

        // getDraftPicks() expectation above proves the injected repository was
        // consumed; the empty pick list renders the bare draft-picks container.
        $this->assertTrue($result['isActualTeam']);
        $this->assertStringContainsString('draft-picks-list', $result['draftPicksTable']);
    }
}
