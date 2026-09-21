<?php

declare(strict_types=1);

namespace Tests\Updater;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshHeadToHeadRecordsStep;

/**
 * @covers \Updater\Steps\RefreshHeadToHeadRecordsStep
 */
class RefreshHeadToHeadRecordsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshHeadToHeadRecordsStep::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);

        $this->assertSame(
            'head-to-head records cache refreshed',
            (new RefreshHeadToHeadRecordsStep($stub))->getLabel(),
        );
    }

    public function testExecuteReturnsFailureWhenDatabaseThrows(): void
    {
        $stub = self::createStub(\mysqli::class);
        $stub->method('prepare')->willThrowException(new \RuntimeException('DB connection lost'));

        $result = (new RefreshHeadToHeadRecordsStep($stub))->execute();

        $this->assertFalse($result->success);
        $this->assertSame('head-to-head records cache refreshed', $result->label);
    }
}
