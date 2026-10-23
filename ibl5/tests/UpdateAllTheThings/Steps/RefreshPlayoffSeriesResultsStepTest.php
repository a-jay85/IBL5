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

    public function testExecuteRollsBackAndReportsFailureWhenInsertFails(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->failOnNthInsert(1);

        $result = (new RefreshPlayoffSeriesResultsStep($mockDb))->execute();

        $log = $mockDb->getOperationLog();
        $this->assertFalse($result->success);
        $this->assertContains('ROLLBACK', $log);
        $this->assertNotContains('COMMIT', $log);
    }

    public function testRebuildWithinCurrentTransactionIssuesNoTransactionControl(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshPlayoffSeriesResultsStep($mockDb))->rebuildWithinCurrentTransaction();

        $log = $mockDb->getOperationLog();
        $this->assertNotContains('BEGIN', $log);
        $this->assertNotContains('COMMIT', $log);
        $this->assertNotContains('ROLLBACK', $log);
    }

    public function testRebuildWithinCurrentTransactionIssuesExactlyDeleteThenInsertWithoutPlaceholders(): void
    {
        $mockDb = new MockDatabase();
        (new RefreshPlayoffSeriesResultsStep($mockDb))->rebuildWithinCurrentTransaction();

        $log = $mockDb->getOperationLog();
        $this->assertCount(2, $log);
        $this->assertTrue(str_starts_with($log[0], 'DELETE FROM ibl_playoff_series_results'));
        $this->assertTrue(str_starts_with($log[1], 'INSERT INTO ibl_playoff_series_results'));
        // A zero-parameter dbExec() statement must carry no placeholder: an
        // unbound `?` means input is being threaded into this constant SQL.
        $this->assertStringNotContainsString('?', $log[0]);
        $this->assertStringNotContainsString('?', $log[1]);
    }

    public function testRebuildWithinCurrentTransactionReturnsInsertAffectedRows(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->setAffectedRows(7);

        $result = (new RefreshPlayoffSeriesResultsStep($mockDb))->rebuildWithinCurrentTransaction();

        $this->assertSame(7, $result);
    }

    public function testRebuildWithinCurrentTransactionPropagatesFailureWithoutRollback(): void
    {
        $mockDb = new MockDatabase();
        $mockDb->failOnNthInsert(1);

        try {
            (new RefreshPlayoffSeriesResultsStep($mockDb))->rebuildWithinCurrentTransaction();
            self::fail('expected RuntimeException');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('Execute failed', $e->getMessage());
            $this->assertNotContains('ROLLBACK', $mockDb->getOperationLog());
        }
    }
}
