<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrImportMode;
use PlrParser\PlrParseResult;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\SnapshotPlrStep;

class SnapshotPlrStepTest extends TestCase
{
    private JsbImportRepositoryInterface $stubJsbRepo;
    private PlrParserServiceInterface $stubPlrService;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubJsbRepo = $this->createStub(JsbImportRepositoryInterface::class);
        $this->stubPlrService = $this->createStub(PlrParserServiceInterface::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    private function createStep(): SnapshotPlrStep
    {
        return new SnapshotPlrStep(
            $this->stubPlrService,
            $this->stubJsbRepo,
            2026,
            $this->stubResolver,
        );
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $this->assertInstanceOf(PipelineStepInterface::class, $this->createStep());
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $this->assertSame('Player snapshot', $this->createStep()->getLabel());
    }

    public function testSkipsWhenResolverReturnsNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('not found', $result->detail);
    }

    public function testUsesEndOfSeasonPhaseWhenChampionExists(): void
    {
        $this->stubResolver->method('getContents')->willReturn('plr-bytes');
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(true);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 10;

        $mockPlrService = $this->createMock(PlrParserServiceInterface::class);
        $mockPlrService->expects($this->once())
            ->method('processPlrDataForYear')
            ->with(
                'plr-bytes',
                2026,
                PlrImportMode::Snapshot,
                'end-of-season',
                'current-season',
            )
            ->willReturn($plrResult);

        $step = new SnapshotPlrStep($mockPlrService, $this->stubJsbRepo, 2026, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('end-of-season', $result->detail);
    }

    public function testUsesMidSeasonPhaseWhenNoChampion(): void
    {
        $this->stubResolver->method('getContents')->willReturn('plr-bytes');
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(false);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 5;

        $mockPlrService = $this->createMock(PlrParserServiceInterface::class);
        $mockPlrService->expects($this->once())
            ->method('processPlrDataForYear')
            ->with(
                'plr-bytes',
                2026,
                PlrImportMode::Snapshot,
                'mid-season',
                'current-season',
            )
            ->willReturn($plrResult);

        $step = new SnapshotPlrStep($mockPlrService, $this->stubJsbRepo, 2026, $this->stubResolver);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('mid-season', $result->detail);
    }

    public function testReturnsSuccessWithResultSummary(): void
    {
        $this->stubResolver->method('getContents')->willReturn('plr-bytes');
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(false);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 42;
        $this->stubPlrService->method('processPlrDataForYear')->willReturn($plrResult);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('42 players upserted', $result->detail);
    }
}
