<?php

declare(strict_types=1);

namespace Tests\Updater\Steps;

use GoogleSheets\Contracts\GoogleSheetConnectionRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\GoogleSheets\Fakes\InMemoryConnectionRepository;
use Updater\Contracts\PipelineStepInterface;
use Updater\Steps\QueueGoogleSheetRefreshStep;

class QueueGoogleSheetRefreshStepTest extends TestCase
{
    public function testImplementsPipelineStepInterface(): void
    {
        $step = new QueueGoogleSheetRefreshStep(new InMemoryConnectionRepository());

        // Reflection keeps this assertion from folding to a constant true under PHPStan.
        self::assertTrue(
            (new \ReflectionClass($step))->implementsInterface(PipelineStepInterface::class)
        );
        self::assertNotSame('', $step->getLabel());
    }

    public function testExecuteMarksActiveConnectionsPendingAndReportsCount(): void
    {
        $repo = new InMemoryConnectionRepository();
        $repo->upsert(1, 'enc-1', 'sheet-1', 'https://docs.google.com/spreadsheets/d/sheet-1');
        $repo->upsert(2, 'enc-2', 'sheet-2', 'https://docs.google.com/spreadsheets/d/sheet-2');
        $repo->upsert(3, 'enc-3', 'sheet-3', 'https://docs.google.com/spreadsheets/d/sheet-3');
        $repo->markBroken(3, GoogleSheetConnectionRepositoryInterface::REASON_INVALID_GRANT);

        $result = (new QueueGoogleSheetRefreshStep($repo))->execute();

        self::assertTrue($result->success);
        self::assertStringContainsString('Queued 2 Google Sheet refresh(es)', $result->detail);
        self::assertSame(1, $repo->rows[1]['refresh_pending']);
        self::assertSame(1, $repo->rows[2]['refresh_pending']);
        self::assertSame(0, $repo->rows[3]['refresh_pending']);
    }

    public function testExecuteReturnsSkippedWhenNothingConnected(): void
    {
        $result = (new QueueGoogleSheetRefreshStep(new InMemoryConnectionRepository()))->execute();

        self::assertTrue($result->success);
        self::assertSame('No connected Google Sheets.', $result->detail);
    }

    public function testExecuteReturnsSkippedNotFailureWhenRepositoryThrows(): void
    {
        $repo = self::createStub(GoogleSheetConnectionRepositoryInterface::class);
        $repo->method('markAllActivePending')->willThrowException(new \RuntimeException('Table missing'));

        $result = (new QueueGoogleSheetRefreshStep($repo))->execute();

        self::assertTrue($result->success);
        self::assertSame('', $result->errorMessage);
        self::assertSame('Google Sheets queue unavailable: RuntimeException', $result->detail);
    }
}
