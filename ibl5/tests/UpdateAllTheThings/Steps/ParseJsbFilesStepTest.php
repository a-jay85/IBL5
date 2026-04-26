<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\JsbSourceResolverInterface;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ParseJsbFilesStep;

class ParseJsbFilesStepTest extends TestCase
{
    private JsbImportService $stubService;
    private JsbSourceResolverInterface $stubResolver;

    protected function setUp(): void
    {
        $this->stubService = $this->createStub(JsbImportService::class);
        $this->stubResolver = $this->createStub(JsbSourceResolverInterface::class);
    }

    private function createStep(): ParseJsbFilesStep
    {
        return new ParseJsbFilesStep($this->stubService, $this->stubResolver, 2026);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $this->assertInstanceOf(PipelineStepInterface::class, $this->createStep());
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $this->assertSame('JSB files parsed', $this->createStep()->getLabel());
    }

    public function testExecuteReturnsSuccessWhenAllFilesNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame('JSB files parsed', $result->label);
    }

    public function testExecuteProcessesFilesReturnedByResolver(): void
    {
        $jsbResult = new JsbImportResult();
        $jsbResult->addMessage('TRN: 10 records');

        $this->stubResolver->method('getContents')->willReturnMap([
            ['trn', 'trn-data'],
            ['car', null],
            ['his', null],
            ['asw', null],
            ['rcb', null],
        ]);

        $this->stubService->method('processTrnData')->willReturn($jsbResult);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertContains('TRN: 10 records', $result->messages);
    }

    public function testExecutePassesErrorCountFromResult(): void
    {
        $jsbResult = new JsbImportResult();
        $jsbResult->addError('Failed to parse player');
        $jsbResult->addError('Unknown team');

        $this->stubResolver->method('getContents')->willReturnMap([
            ['trn', 'trn-data'],
            ['car', null],
            ['his', null],
            ['asw', null],
            ['rcb', null],
        ]);
        $this->stubService->method('processTrnData')->willReturn($jsbResult);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->messageErrorCount);
    }

    public function testExecuteSkipsFileWhenResolverReturnsNull(): void
    {
        $this->stubResolver->method('getContents')->willReturn(null);

        $result = $this->createStep()->execute();

        $this->assertTrue($result->success);
        $this->assertSame([], $result->messages);
    }
}
