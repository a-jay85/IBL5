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
        $stub = $this->createStub(\mysqli::class);
        $this->assertInstanceOf(PipelineStepInterface::class, new RefreshPlayoffSeriesResultsStep($stub));
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertSame(
            'playoff series results refreshed',
            (new RefreshPlayoffSeriesResultsStep($stub))->getLabel(),
        );
    }
}
