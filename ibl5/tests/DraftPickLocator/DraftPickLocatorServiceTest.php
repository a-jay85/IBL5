<?php

declare(strict_types=1);

namespace Tests\DraftPickLocator;

use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use DraftPickLocator\DraftPickLocatorService;
use DraftPickLocator\Contracts\DraftPickLocatorRepositoryInterface;

/**
 * DraftPickLocatorServiceTest - Tests for DraftPickLocatorService
 */
#[AllowMockObjectsWithoutExpectations]
class DraftPickLocatorServiceTest extends TestCase
{
    private object $mockRepository;
    private DraftPickLocatorService $service;

    protected function setUp(): void
    {
        $this->mockRepository = $this->createMock(DraftPickLocatorRepositoryInterface::class);
        $this->service = new DraftPickLocatorService($this->mockRepository);
    }

    // ============================================
    // CONSTRUCTOR TESTS
    // ============================================

    public function testServiceCanBeInstantiated(): void
    {
        $this->assertInstanceOf(DraftPickLocatorService::class, $this->service);
    }

    // ============================================
    // GET ALL TEAMS WITH PICKS TESTS
    // ============================================

    public function testGetAllTeamsWithPicksReturnsEmptyArrayWhenNoTeams(): void
    {
        $this->mockRepository->method('getAllTeams')->willReturn([]);
        $this->mockRepository->method('getAllDraftPicksGroupedByTeam')->willReturn([]);

        $result = $this->service->getAllTeamsWithPicks();

        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }

    public function testGetAllTeamsWithPicksReturnsTeamsWithPickData(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '00FF00', 'color2' => 'FFFFFF'],
        ];
        $allPicks = [
            'Celtics' => [
                ['ownerofpick' => 'Celtics', 'year' => 2025, 'round' => 1],
            ],
        ];

        $this->mockRepository->method('getAllTeams')->willReturn($teams);
        $this->mockRepository->method('getAllDraftPicksGroupedByTeam')->willReturn($allPicks);

        $result = $this->service->getAllTeamsWithPicks();

        $this->assertCount(1, $result);
        $this->assertEquals('Celtics', $result[0]['teamName']);
        $this->assertEquals('Boston', $result[0]['teamCity']);
        $this->assertArrayHasKey('picks', $result[0]);
    }

    public function testGetAllTeamsWithPicksIncludesTeamColors(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'Boston', 'team_name' => 'Celtics', 'color1' => '00FF00', 'color2' => 'FFFFFF'],
        ];

        $this->mockRepository->method('getAllTeams')->willReturn($teams);
        $this->mockRepository->method('getAllDraftPicksGroupedByTeam')->willReturn([]);

        $result = $this->service->getAllTeamsWithPicks();

        $this->assertEquals('00FF00', $result[0]['color1']);
        $this->assertEquals('FFFFFF', $result[0]['color2']);
    }

    public function testGetAllTeamsWithPicksConvertsTeamIdToInt(): void
    {
        $teams = [
            ['teamid' => 5, 'team_city' => 'Test', 'team_name' => 'Test Team', 'color1' => '000', 'color2' => 'FFF'],
        ];

        $this->mockRepository->method('getAllTeams')->willReturn($teams);
        $this->mockRepository->method('getAllDraftPicksGroupedByTeam')->willReturn([]);

        $result = $this->service->getAllTeamsWithPicks();

        $this->assertIsInt($result[0]['teamId']);
        $this->assertSame(5, $result[0]['teamId']);
    }

    public function testGetAllTeamsWithPicksCallsRepositoryForEachTeam(): void
    {
        $teams = [
            ['teamid' => 1, 'team_city' => 'A', 'team_name' => 'Team A', 'color1' => '000', 'color2' => 'FFF'],
            ['teamid' => 2, 'team_city' => 'B', 'team_name' => 'Team B', 'color1' => '000', 'color2' => 'FFF'],
            ['teamid' => 3, 'team_city' => 'C', 'team_name' => 'Team C', 'color1' => '000', 'color2' => 'FFF'],
        ];

        $allPicks = [
            'Team A' => [['ownerofpick' => 'Team A', 'year' => 2025, 'round' => 1]],
            'Team B' => [['ownerofpick' => 'Team B', 'year' => 2025, 'round' => 1]],
            'Team C' => [['ownerofpick' => 'Team C', 'year' => 2025, 'round' => 1]],
        ];

        $this->mockRepository->method('getAllTeams')->willReturn($teams);
        $this->mockRepository->expects($this->once())
            ->method('getAllDraftPicksGroupedByTeam')
            ->willReturn($allPicks);

        $result = $this->service->getAllTeamsWithPicks();

        $this->assertCount(3, $result);
        $this->assertCount(1, $result[0]['picks']);
        $this->assertCount(1, $result[1]['picks']);
        $this->assertCount(1, $result[2]['picks']);
    }
}
