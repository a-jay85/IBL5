<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\TestCase;
use PlrParser\Contracts\PlrParserServiceInterface;
use PlrParser\PlrParseResult;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\EndOfSeasonImportStep;

class EndOfSeasonImportStepTest extends TestCase
{
    private JsbImportRepositoryInterface $stubRepo;
    private JsbImportService $stubService;
    private PlrParserServiceInterface $stubPlrService;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(JsbImportRepositoryInterface::class);
        $this->stubService = $this->createStub(JsbImportService::class);
        $this->stubPlrService = $this->createStub(PlrParserServiceInterface::class);
    }

    private function createStep(string $basePath = '/tmp'): EndOfSeasonImportStep
    {
        return new EndOfSeasonImportStep(
            $this->stubRepo,
            $this->stubService,
            $this->stubPlrService,
            2026,
            $basePath,
            'IBL5',
        );
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $this->assertInstanceOf(PipelineStepInterface::class, $this->createStep());
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $this->assertSame('End-of-season imports', $this->createStep()->getLabel());
    }

    public function testSkipsWhenNoChampionDetermined(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(false);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertStringContainsString('No champion', $result->detail);
    }

    public function testRunsImportsWhenChampionExists(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        $jsbResult = new JsbImportResult();
        $jsbResult->addInserted();

        $this->stubService->method('processDraFile')->willReturn($jsbResult);
        $this->stubService->method('processRetFile')->willReturn($jsbResult);
        $this->stubService->method('processHofFile')->willReturn($jsbResult);
        $this->stubService->method('processAwaFile')->willReturn($jsbResult);

        $plrResult = new PlrParseResult();
        $plrResult->playersUpserted = 5;
        $this->stubPlrService->method('processPlrFileForYear')->willReturn($plrResult);

        // Use a temp dir with actual files
        $tmpDir = sys_get_temp_dir() . '/eos-test-' . uniqid();
        mkdir($tmpDir, 0777, true);
        foreach (['dra', 'ret', 'hof', 'awa', 'car', 'plr'] as $ext) {
            touch($tmpDir . '/IBL5.' . $ext);
        }

        try {
            $step = new EndOfSeasonImportStep(
                $this->stubRepo,
                $this->stubService,
                $this->stubPlrService,
                2026,
                $tmpDir,
                'IBL5',
            );
            $result = $step->execute();

            $this->assertTrue($result->success);
            $this->assertNotEmpty($result->messages);
        } finally {
            // Cleanup
            foreach (glob($tmpDir . '/*') as $file) {
                unlink($file);
            }
            rmdir($tmpDir);
        }
    }

    public function testSkipsIndividualImportWhenFileNotFound(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        // Base path has no files — all imports should be silently skipped
        $result = $this->createStep('/nonexistent/path')->execute();

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testErrorsFromIndividualImportAreCollected(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        $errorResult = new JsbImportResult();
        $errorResult->addError('Parse failed');
        $errorResult->addError('Another error');

        $this->stubService->method('processDraFile')->willReturn($errorResult);

        $tmpDir = sys_get_temp_dir() . '/eos-err-' . uniqid();
        mkdir($tmpDir, 0777, true);
        touch($tmpDir . '/IBL5.dra');

        try {
            $step = new EndOfSeasonImportStep(
                $this->stubRepo,
                $this->stubService,
                $this->stubPlrService,
                2026,
                $tmpDir,
                'IBL5',
            );
            $result = $step->execute();

            $this->assertTrue($result->success);
            $this->assertSame(2, $result->messageErrorCount);
        } finally {
            unlink($tmpDir . '/IBL5.dra');
            rmdir($tmpDir);
        }
    }
}
