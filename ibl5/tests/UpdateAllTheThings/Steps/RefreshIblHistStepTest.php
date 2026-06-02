<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Updater\Steps\RefreshIblHistStep;

class RefreshIblHistStepTest extends TestCase
{
    public function testGetLabelReturnsExpectedLabel(): void
    {
        $stub = self::createStub(\mysqli::class);
        $this->assertSame('ibl_hist refreshed', (new RefreshIblHistStep($stub))->getLabel());
    }
}
