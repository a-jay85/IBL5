<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshPlayoffSeriesResultsStep;

class RefreshPlayoffSeriesResultsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshPlayoffSeriesResultsStep::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame(
            'playoff series results refreshed',
            (new RefreshPlayoffSeriesResultsStep($stub))->getLabel(),
        );
    }
}
