<?php

declare(strict_types=1);

namespace Tests\LeagueConfig;

use LeagueConfig\Contracts\LeagueConfigRepositoryInterface;
use LeagueConfig\LeagueConfigService;
use PHPUnit\Framework\TestCase;

class LeagueConfigServiceTest extends TestCase
{
    public function testProcessLgeFileReturnsSuccess(): void
    {
        $lgeFile = dirname(__DIR__, 2) . '/IBL5.lge';
        if (!file_exists($lgeFile)) {
            $this->fail("Test .lge file not found at: {$lgeFile}");
        }

        $mockRepository = $this->createMock(LeagueConfigRepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('upsertSeasonConfig')
            ->with($this->isInt(), $this->isArray())
            ->willReturn(28);

        $service = new LeagueConfigService($mockRepository);
        $result = $service->processLgeFile($lgeFile);

        $this->assertTrue($result['success']);
        $this->assertSame(28, $result['teams_stored']);
        $this->assertNotEmpty($result['messages']);
    }

    public function testProcessLgeFileReturnsErrorForMissingFile(): void
    {
        $stubRepository = $this->createStub(LeagueConfigRepositoryInterface::class);
        $service = new LeagueConfigService($stubRepository);

        $result = $service->processLgeFile('/nonexistent/IBL5.lge');

        $this->assertFalse($result['success']);
        $this->assertSame(0, $result['season_ending_year']);
        $this->assertSame(0, $result['teams_stored']);
        $this->assertArrayHasKey('error', $result);
    }

    // ── crossCheckWithFranchiseSeasons ─────────────────────────────

    public function testCrossCheckReturnsEmptyWhenAllMatch(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([
            ['team_slot' => 1, 'team_name' => 'Miami'],
            ['team_slot' => 2, 'team_name' => 'New York'],
        ]);
        $stubRepo->method('getFranchiseTeamsBySeason')->willReturn([
            1 => 'Miami',
            2 => 'New York',
        ]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertSame([], $result);
    }

    public function testCrossCheckReturnsErrorWhenNoConfigFound(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('No league config found', $result[0]);
    }

    public function testCrossCheckReturnsErrorWhenNoFranchiseDataFound(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([
            ['team_slot' => 1, 'team_name' => 'Miami'],
        ]);
        $stubRepo->method('getFranchiseTeamsBySeason')->willReturn([]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('No franchise_seasons data', $result[0]);
    }

    public function testCrossCheckDetectsMissingSlotInFranchiseMap(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([
            ['team_slot' => 1, 'team_name' => 'Miami'],
            ['team_slot' => 99, 'team_name' => 'Phantom Team'],
        ]);
        $stubRepo->method('getFranchiseTeamsBySeason')->willReturn([
            1 => 'Miami',
        ]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Slot 99', $result[0]);
        $this->assertStringContainsString('no matching franchise_id', $result[0]);
    }

    public function testCrossCheckDetectsNameMismatch(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([
            ['team_slot' => 1, 'team_name' => 'Miami Heat'],
        ]);
        $stubRepo->method('getFranchiseTeamsBySeason')->willReturn([
            1 => 'Miami Dolphins',
        ]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Miami Heat', $result[0]);
        $this->assertStringContainsString('Miami Dolphins', $result[0]);
    }

    public function testCrossCheckDetectsFranchiseNotInLgeFile(): void
    {
        $stubRepo = $this->createStub(LeagueConfigRepositoryInterface::class);
        $stubRepo->method('getConfigForSeason')->willReturn([
            ['team_slot' => 1, 'team_name' => 'Miami'],
        ]);
        $stubRepo->method('getFranchiseTeamsBySeason')->willReturn([
            1 => 'Miami',
            2 => 'New York',
        ]);

        $service = new LeagueConfigService($stubRepo);
        $result = $service->crossCheckWithFranchiseSeasons(2026);

        $this->assertCount(1, $result);
        $this->assertStringContainsString('Franchise 2', $result[0]);
        $this->assertStringContainsString('not present in .lge file', $result[0]);
    }
}
