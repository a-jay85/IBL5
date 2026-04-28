<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshTeamSeasonRecordsStep;

class RefreshTeamSeasonRecordsStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertInstanceOf(PipelineStepInterface::class, new RefreshTeamSeasonRecordsStep($stub));
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertSame(
            'team season records refreshed',
            (new RefreshTeamSeasonRecordsStep($stub))->getLabel(),
        );
    }
}
