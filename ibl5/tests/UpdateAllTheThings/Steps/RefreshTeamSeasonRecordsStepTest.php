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
        self::assertContains(
            PipelineStepInterface::class,
            (array) class_implements(RefreshTeamSeasonRecordsStep::class)
        );
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame(
            'team season records refreshed',
            (new RefreshTeamSeasonRecordsStep($stub))->getLabel(),
        );
    }
}
