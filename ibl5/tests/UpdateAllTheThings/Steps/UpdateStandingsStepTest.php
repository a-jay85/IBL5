<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\StandingsUpdater;
use Updater\Steps\UpdateStandingsStep;

class UpdateStandingsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stubUpdater = $this->createStub(StandingsUpdater::class);
        $step = new UpdateStandingsStep($stubUpdater);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stubUpdater = $this->createStub(StandingsUpdater::class);
        $step = new UpdateStandingsStep($stubUpdater);

        $this->assertSame('Standings updated', $step->getLabel());
    }

    public function testExecuteCallsUpdateAndReturnsSuccess(): void
    {
        $mockUpdater = $this->createMock(StandingsUpdater::class);
        $mockUpdater->expects($this->once())->method('update');

        $step = new UpdateStandingsStep($mockUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Standings updated', $result->label);
    }

    public function testExecuteCapturesOutputBufferLog(): void
    {
        $stubUpdater = $this->createStub(StandingsUpdater::class);
        $stubUpdater->method('update')->willReturnCallback(static function (): void {
            echo '<p>Computing standings...</p>';
        });

        $step = new UpdateStandingsStep($stubUpdater);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('<p>Computing standings...</p>', $result->capturedLog);
    }
}
