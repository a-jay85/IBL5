<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ResetExtensionAttemptsStep;
use Tests\WideUnit\Mocks\MockDatabase;

class ResetExtensionAttemptsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $mockDb = new MockDatabase();
        $step = new ResetExtensionAttemptsStep($mockDb);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $mockDb = new MockDatabase();
        $step = new ResetExtensionAttemptsStep($mockDb);

        $this->assertSame('Extension attempts reset', $step->getLabel());
    }

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $step = new ResetExtensionAttemptsStep($mockDb);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Extension attempts reset', $result->label);
    }
}
