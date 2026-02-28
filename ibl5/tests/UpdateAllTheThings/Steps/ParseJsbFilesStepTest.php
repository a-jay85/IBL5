<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use JsbParser\JsbImportResult;
use JsbParser\JsbImportService;
use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ParseJsbFilesStep;

class ParseJsbFilesStepTest extends TestCase
{
    private JsbImportService $stubService;
    private \Season $stubSeason;

    protected function setUp(): void
    {
        $this->stubService = $this->createStub(JsbImportService::class);
        $this->stubSeason = $this->createStub(\Season::class);
    }

    public function testImplementsPipelineStepInterface(): void
    {
        $step = new ParseJsbFilesStep($this->stubService, '/tmp', $this->stubSeason);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $step = new ParseJsbFilesStep($this->stubService, '/tmp', $this->stubSeason);

        $this->assertSame('JSB files parsed', $step->getLabel());
    }

    public function testExecuteReturnsSuccessWithSummary(): void
    {
        $jsbResult = new JsbImportResult();
        $jsbResult->addMessage('CAR: 150 records');
        $jsbResult->addMessage('TRN: 10 trades');

        $this->stubService->method('processCurrentSeason')->willReturn($jsbResult);

        $step = new ParseJsbFilesStep($this->stubService, '/tmp', $this->stubSeason);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('JSB files parsed', $result->label);
        $this->assertNotSame('', $result->detail);
        $this->assertSame(['CAR: 150 records', 'TRN: 10 trades'], $result->messages);
        $this->assertSame(0, $result->messageErrorCount);
    }

    public function testExecutePassesErrorCountFromResult(): void
    {
        $jsbResult = new JsbImportResult();
        $jsbResult->addError('Failed to parse player');
        $jsbResult->addError('Unknown team');

        $this->stubService->method('processCurrentSeason')->willReturn($jsbResult);

        $step = new ParseJsbFilesStep($this->stubService, '/tmp', $this->stubSeason);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame(2, $result->messageErrorCount);
    }

    public function testExecuteCapturesOutputBufferLog(): void
    {
        $jsbResult = new JsbImportResult();

        $this->stubService->method('processCurrentSeason')->willReturnCallback(
            static function () use ($jsbResult): JsbImportResult {
                echo '<p>Processing JSB...</p>';
                return $jsbResult;
            }
        );

        $step = new ParseJsbFilesStep($this->stubService, '/tmp', $this->stubSeason);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('<p>Processing JSB...</p>', $result->capturedLog);
    }
}
