<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Shared\SharedRepository;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\ResetExtensionAttemptsStep;

class ResetExtensionAttemptsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stubRepo = $this->createStub(SharedRepository::class);
        $step = new ResetExtensionAttemptsStep($stubRepo);

        $this->assertInstanceOf(PipelineStepInterface::class, $step);
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stubRepo = $this->createStub(SharedRepository::class);
        $step = new ResetExtensionAttemptsStep($stubRepo);

        $this->assertSame('Extension attempts reset', $step->getLabel());
    }

    public function testExecuteCallsResetAndReturnsSuccess(): void
    {
        $mockRepo = $this->createMock(SharedRepository::class);
        $mockRepo->expects($this->once())->method('resetSimContractExtensionAttempts');

        $step = new ResetExtensionAttemptsStep($mockRepo);
        $result = $step->execute();

        $this->assertTrue($result->success);
        $this->assertSame('Extension attempts reset', $result->label);
    }
}
