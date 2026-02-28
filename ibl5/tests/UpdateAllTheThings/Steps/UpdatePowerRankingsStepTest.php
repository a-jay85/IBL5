<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\PowerRankingsUpdater;
use Updater\Steps\UpdatePowerRankingsStep;

class UpdatePowerRankingsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stubUpdater = $this->createStub(PowerRankingsUpdater::class);
        $step = new UpdatePowerRankingsStep($stubUpdater);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stubUpdater = $this->createStub(PowerRankingsUpdater::class);
        $step = new UpdatePowerRankingsStep($stubUpdater);

        $this->assertSame('Power rankings updated', $step->getLabel());
    }

    public function testExecuteCallsUpdateAndReturnsSuccess(): void
    {
        $mockUpdater = $this->createMock(PowerRankingsUpdater::class);
        $mockUpdater->expects($this->once())->method('update');

        $step = new UpdatePowerRankingsStep($mockUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Power rankings updated', $result->label);
    }

    public function testExecuteCapturesOutputBufferLog(): void
    {
        $stubUpdater = $this->createStub(PowerRankingsUpdater::class);
        $stubUpdater->method('update')->willReturnCallback(static function (): void {
            echo '<p>Calculating power rankings...</p>';
        });

        $step = new UpdatePowerRankingsStep($stubUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('<p>Calculating power rankings...</p>', $result->capturedLog);
    }
}
