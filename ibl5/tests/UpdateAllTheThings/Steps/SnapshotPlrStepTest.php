<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrImportMode;
use PlrParser\PlrParseResult;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\SnapshotPlrStep;

class SnapshotPlrStepTest extends TestCase
{
    private JsbImportRepositoryInterface $stubJsbRepo;
    private PlrParserServiceInterface $stubPlrService;

    protected function setUp(): void
    {
        $this->stubJsbRepo = $this->createStub(JsbImportRepositoryInterface::class);
        $this->stubPlrService = $this->createStub(PlrParserServiceInterface::class);
    }

    private function createStep(string $plrFilePath = '/tmp/nonexistent.plr'): SnapshotPlrStep
    {
        return new SnapshotPlrStep(
            $this->stubPlrService,
            $this->stubJsbRepo,
            2026,
            $plrFilePath,
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

    public function testSkipsWhenPlrFileDoesNotExist(): void
    {
        $result = $this->createStep('/nonexistent/path/IBL5.plr')->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('not found', $result->detail);
    }

    public function testUsesEndOfSeasonPhaseWhenChampionExists(): void
    {
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(true);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 10;

        $mockPlrService = $this->createMock(PlrParserServiceInterface::class);
        $mockPlrService->expects($this->once())
            ->method('processPlrFileForYear')
            ->with(
                $this->anything(),
                2026,
                PlrImportMode::Snapshot,
                'end-of-season',
                'current-season',
            )
            ->willReturn($plrResult);

        $tmpFile = tempnam(sys_get_temp_dir(), 'plr-test-');
        self::assertIsString($tmpFile);

        try {
            $step = new SnapshotPlrStep($mockPlrService, $this->stubJsbRepo, 2026, $tmpFile);
            $result = $step->execute();

            $this->assertTrue($result->success);
            $this->assertStringContainsString('end-of-season', $result->detail);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testUsesMidSeasonPhaseWhenNoChampion(): void
    {
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(false);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 5;

        $mockPlrService = $this->createMock(PlrParserServiceInterface::class);
        $mockPlrService->expects($this->once())
            ->method('processPlrFileForYear')
            ->with(
                $this->anything(),
                2026,
                PlrImportMode::Snapshot,
                'mid-season',
                'current-season',
            )
            ->willReturn($plrResult);

        $tmpFile = tempnam(sys_get_temp_dir(), 'plr-test-');
        self::assertIsString($tmpFile);

        try {
            $step = new SnapshotPlrStep($mockPlrService, $this->stubJsbRepo, 2026, $tmpFile);
            $result = $step->execute();

            $this->assertTrue($result->success);
            $this->assertStringContainsString('mid-season', $result->detail);
        } finally {
            unlink($tmpFile);
        }
    }

    public function testReturnsSuccessWithResultSummary(): void
    {
        $this->stubJsbRepo->method('hasChampionForSeason')->willReturn(false);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 42;
        $this->stubPlrService->method('processPlrFileForYear')->willReturn($plrResult);

        $tmpFile = tempnam(sys_get_temp_dir(), 'plr-test-');
        self::assertIsString($tmpFile);

        try {
            $result = $this->createStep($tmpFile)->execute();

            $this->assertTrue($result->success);
            $this->assertStringContainsString('42 players upserted', $result->detail);
        } finally {
            unlink($tmpFile);
        }
    }
}
