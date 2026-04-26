<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\Contracts\JsbImportRepositoryInterface;
use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\EndOfSeasonImportStep;

class EndOfSeasonImportStepTest extends TestCase
{
    private JsbImportRepositoryInterface $stubRepo;
    private JsbImportService $stubService;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubRepo = $this->createStub(JsbImportRepositoryInterface::class);
        $this->stubService = $this->createStub(JsbImportService::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    private function createStep(): EndOfSeasonImportStep
    {
        return new EndOfSeasonImportStep(
            $this->stubRepo,
            $this->stubService,
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

        $this->stubResolver->method('getContents')->willReturnMap([
            ['dra', 'dra-data'],
            ['ret', 'ret-data'],
            ['hof', 'hof-data'],
            ['awa', 'awa-data'],
            ['car', 'car-data'],
        ]);

        $this->stubService->method('processDraData')->willReturn($jsbResult);
        $this->stubService->method('processRetData')->willReturn($jsbResult);
        $this->stubService->method('processHofData')->willReturn($jsbResult);
        $this->stubService->method('processAwaData')->willReturn($jsbResult);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertNotEmpty($result->messages);
    }

    public function testSkipsIndividualImportWhenResolverReturnsNull(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        $this->stubResolver->method('getContents')->willReturn(null);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame(0, $result->messageErrorCount);
        $this->assertSame([], $result->messages);
    }

    public function testErrorsFromIndividualImportAreCollected(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        $errorResult = new JsbImportResult();
        $errorResult->addError('Parse failed');
        $errorResult->addError('Another error');

        $this->stubResolver->method('getContents')->willReturnMap([
            ['dra', 'dra-data'],
            ['ret', null],
            ['hof', null],
            ['awa', null],
            ['car', null],
        ]);
        $this->stubService->method('processDraData')->willReturn($errorResult);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->messageErrorCount);
    }

    public function testAwaSkippedWhenCarDataNull(): void
    {
        $this->stubRepo->method('hasChampionForSeason')->willReturn(true);

        $jsbResult = new JsbImportResult();

        $this->stubResolver->method('getContents')->willReturnMap([
            ['dra', null],
            ['ret', null],
            ['hof', null],
            ['awa', 'awa-data'],
            ['car', null],
        ]);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->messages);
    }
}
