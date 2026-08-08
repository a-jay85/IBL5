<?php

declare(strict_types=1);

namespace Tests\UpdateAllTheThings\Steps;

use PHPUnit\Framework\TestCase;
use Tests\WideUnit\Mocks\MockDatabase;
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

    public function testExecuteReturnsSuccess(): void
    {
        $mockDb = new MockDatabase();
        $result = (new RefreshPlayoffSeriesResultsStep($mockDb))->execute();

        $this->assertTrue($result->success);
        $this->assertSame('playoff series results refreshed', $result->label);
    }

    public function testExecuteWrapsInTransaction(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshPlayoffSeriesResultsStep($mockDb))->execute();

        $log = $mockDb->getOperationLog();
        $beginIdx = array_search('BEGIN', $log, true);
        $commitIdx = array_search('COMMIT', $log, true);

        $this->assertNotFalse($beginIdx, 'Expected BEGIN in operation log');
        $this->assertNotFalse($commitIdx, 'Expected COMMIT in operation log');
        $this->assertLessThan($commitIdx, $beginIdx, 'BEGIN must precede COMMIT');
    }
}
