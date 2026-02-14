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
        $lgeFile = dirname(__DIR__, 2) . '/scoNonFiles/IBL0607Sim13/IBL5.lge';
        if (!file_exists($lgeFile)) {
            $this->fail("Test .lge file not found at: {$lgeFile}");
        }

        $mockRepository = $this->createMock(LeagueConfigRepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('upsertSeasonConfig')
            ->with(2007, $this->isArray())
            ->willReturn(28);

        $service = new LeagueConfigService($mockRepository);
        $result = $service->processLgeFile($lgeFile);

        $this->assertTrue($result['success']);
        $this->assertSame(2007, $result['season_ending_year']);
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

    public function testHasConfigDelegatesToRepository(): void
    {
        $mockRepository = $this->createMock(LeagueConfigRepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('hasConfigForSeason')
            ->with(2007)
            ->willReturn(true);

        $service = new LeagueConfigService($mockRepository);

        $this->assertTrue($service->hasConfigForCurrentSeason(2007));
    }

    public function testHasConfigReturnsFalseWhenNoData(): void
    {
        $mockRepository = $this->createMock(LeagueConfigRepositoryInterface::class);
        $mockRepository->expects($this->once())
            ->method('hasConfigForSeason')
            ->with(2027)
            ->willReturn(false);

        $service = new LeagueConfigService($mockRepository);

        $this->assertFalse($service->hasConfigForCurrentSeason(2027));
    }
}
