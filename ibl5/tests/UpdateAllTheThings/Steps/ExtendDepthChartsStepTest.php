<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use SavedDepthChart\SavedDepthChartRepository;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ExtendDepthChartsStep;

class ExtendDepthChartsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stubRepo = $this->createStub(SavedDepthChartRepository::class);
        $step = new ExtendDepthChartsStep($stubRepo, '2026-02-27', 15);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stubRepo = $this->createStub(SavedDepthChartRepository::class);
        $step = new ExtendDepthChartsStep($stubRepo, '2026-02-27', 15);

        $this->assertSame('Saved depth charts updated', $step->getLabel());
    }

    public function testExecuteReturnsSuccessWithCount(): void
    {
        $mockRepo = $this->createMock(SavedDepthChartRepository::class);
        $mockRepo->expects($this->once())
            ->method('extendActiveDepthCharts')
            ->with('2026-02-27', 15)
            ->willReturn(5);

        $step = new ExtendDepthChartsStep($mockRepo, '2026-02-27', 15);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('5 active DCs extended', $result->detail);
    }

    public function testExecuteCapturesOutputBufferLog(): void
    {
        $stubRepo = $this->createStub(SavedDepthChartRepository::class);
        $stubRepo->method('extendActiveDepthCharts')->willReturnCallback(static function (): int {
            echo '<p>Extending DCs...</p>';
            return 3;
        });

        $step = new ExtendDepthChartsStep($stubRepo, '2026-02-27', 15);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('<p>Extending DCs...</p>', $result->capturedLog);
    }
}
