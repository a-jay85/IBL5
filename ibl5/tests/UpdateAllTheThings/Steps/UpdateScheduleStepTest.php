<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\ScheduleUpdater;
use Updater\Steps\UpdateScheduleStep;

class UpdateScheduleStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stubUpdater = $this->createStub(ScheduleUpdater::class);
        $step = new UpdateScheduleStep($stubUpdater);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stubUpdater = $this->createStub(ScheduleUpdater::class);
        $step = new UpdateScheduleStep($stubUpdater);

        $this->assertSame('Schedule updated', $step->getLabel());
    }

    public function testExecuteCallsUpdateAndReturnsSuccess(): void
    {
        $mockUpdater = $this->createMock(ScheduleUpdater::class);
        $mockUpdater->expects($this->once())->method('update');

        $step = new UpdateScheduleStep($mockUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Schedule updated', $result->label);
    }

    public function testExecuteCapturesOutputBufferLog(): void
    {
        $stubUpdater = $this->createStub(ScheduleUpdater::class);
        $stubUpdater->method('update')->willReturnCallback(static function (): void {
            echo '<p>Updating schedule...</p>';
        });

        $step = new UpdateScheduleStep($stubUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('<p>Updating schedule...</p>', $result->capturedLog);
    }
}
