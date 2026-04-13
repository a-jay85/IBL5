<?php

declare(strict_types=1);

namespace Tests\Unit\BulkImport;

use BulkImport\BulkImportSummary;
use JsbParser\JsbImportResult;
use PHPUnit\Framework\TestCase;

/**
 * @covers \BulkImport\BulkImportSummary
 */
class BulkImportSummaryTest extends TestCase
{
    public function testStartsEmpty(): void
    {
        $summary = new BulkImportSummary();

        $this->assertSame(0, $summary->filesProcessed);
        $this->assertSame(0, $summary->totalInserted);
        $this->assertSame(0, $summary->totalUpdated);
        $this->assertSame(0, $summary->totalSkipped);
        $this->assertSame(0, $summary->totalErrors);
        $this->assertSame([], $summary->errorMessages);
        $this->assertFalse($summary->hasErrors());
    }

    public function testAddResultAccumulatesCounts(): void
    {
        $summary = new BulkImportSummary();

        $result1 = new JsbImportResult();
        $result1->inserted = 10;
        $result1->updated = 5;
        $result1->skipped = 3;

        $result2 = new JsbImportResult();
        $result2->inserted = 7;
        $result2->updated = 2;

        $summary->addResult($result1);
        $summary->addResult($result2);

        $this->assertSame(2, $summary->filesProcessed);
        $this->assertSame(17, $summary->totalInserted);
        $this->assertSame(7, $summary->totalUpdated);
        $this->assertSame(3, $summary->totalSkipped);
    }

    public function testAddResultCollectsErrorMessages(): void
    {
        $summary = new BulkImportSummary();

        $result = new JsbImportResult();
        $result->addError('File corrupt');
        $result->addMessage('Processed 10 records');

        $summary->addResult($result);

        $this->assertSame(1, $summary->totalErrors);
        $this->assertCount(1, $summary->errorMessages);
        $this->assertSame('ERROR: File corrupt', $summary->errorMessages[0]);
        $this->assertTrue($summary->hasErrors());
    }

    public function testAddErrorDirectly(): void
    {
        $summary = new BulkImportSummary();

        $summary->addError('Archive not found');

        $this->assertSame(1, $summary->totalErrors);
        $this->assertTrue($summary->hasErrors());
        $this->assertSame('Archive not found', $summary->errorMessages[0]);
    }

    public function testHasErrorsFalseWhenNoErrors(): void
    {
        $summary = new BulkImportSummary();

        $result = new JsbImportResult();
        $result->inserted = 5;
        $summary->addResult($result);

        $this->assertFalse($summary->hasErrors());
    }

    public function testPrintSummaryOutputsFormattedText(): void
    {
        $summary = new BulkImportSummary();

        $result = new JsbImportResult();
        $result->inserted = 100;
        $result->updated = 50;
        $result->skipped = 25;
        $summary->addResult($result);

        ob_start();
        $summary->printSummary();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('BULK IMPORT COMPLETE', $output);
        $this->assertStringContainsString('Files processed:   1', $output);
        $this->assertStringContainsString('Records inserted:  100', $output);
        $this->assertStringContainsString('Records updated:   50', $output);
        $this->assertStringContainsString('Records skipped:   25', $output);
    }

    public function testPrintSummaryWithCustomTitle(): void
    {
        $summary = new BulkImportSummary();

        ob_start();
        $summary->printSummary('VERIFICATION COMPLETE');
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('VERIFICATION COMPLETE', $output);
    }

    public function testPrintSummaryShowsErrorCountWhenPresent(): void
    {
        $summary = new BulkImportSummary();
        $summary->addError('test error');

        ob_start();
        $summary->printSummary();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringContainsString('Errors:            1', $output);
    }

    public function testPrintSummaryOmitsErrorLineWhenZero(): void
    {
        $summary = new BulkImportSummary();

        ob_start();
        $summary->printSummary();
        $output = ob_get_clean();

        $this->assertIsString($output);
        $this->assertStringNotContainsString('Errors:', $output);
    }
}
