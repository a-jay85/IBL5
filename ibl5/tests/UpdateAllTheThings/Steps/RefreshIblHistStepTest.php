<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\RefreshIblHistStep;

class RefreshIblHistStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertInstanceOf(PipelineStepInterface::class, new RefreshIblHistStep($stub));
    }

    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = $this->createStub(\mysqli::class);
        $this->assertSame('ibl_hist refreshed', (new RefreshIblHistStep($stub))->getLabel());
    }
}
