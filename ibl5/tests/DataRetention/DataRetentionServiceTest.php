<?php

declare(strict_types=1);

namespace Tests\DataRetention;

use DataRetention\Contracts\DataRetentionRepositoryInterface;
use DataRetention\DataRetentionService;
use PHPUnit\Framework\TestCase;

class DataRetentionServiceTest extends TestCase
{
    private const NOW = 1_000_000_000;

    public function testPurgeAuditLogRejectsNonPositiveRetentionDays(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::never())->method('countAuditLogRowsOlderThan');
        $repository->expects(self::never())->method('deleteAuditLogBatchOlderThan');
        $service = new DataRetentionService($repository);

        foreach ([0, -5] as $days) {
            try {
                $service->purgeAuditLog($days, false, self::NOW);
                self::fail('Expected InvalidArgumentException for retention days ' . $days);
            } catch (\InvalidArgumentException $e) {
                self::assertSame('Retention days must be at least 1, got ' . $days, $e->getMessage());
            }
        }
    }

    public function testPurgeAuditLogRejectsNonPositiveBatchSize(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::never())->method('countAuditLogRowsOlderThan');
        $repository->expects(self::never())->method('deleteAuditLogBatchOlderThan');
        $service = new DataRetentionService($repository);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessageIs('Batch size must be at least 1, got 0');
        $service->purgeAuditLog(180, false, self::NOW, 0);
    }

    public function testPurgeAuditLogAcceptsOneDayAndBatchSizeOne(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('countAuditLogRowsOlderThan')
            ->with(self::NOW - 86400)
            ->willReturn(1);
        $repository->expects(self::exactly(2))
            ->method('deleteAuditLogBatchOlderThan')
            ->with(self::NOW - 86400, 1)
            ->willReturnOnConsecutiveCalls(1, 0);
        $service = new DataRetentionService($repository);

        $result = $service->purgeAuditLog(1, false, self::NOW, 1);

        self::assertSame(1, $result->deleted);
    }

    public function testPurgeAuditLogDrainsAcrossBatches(): void
    {
        $cutoff = self::NOW - 180 * 86400;
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(5);
        $repository->expects(self::exactly(3))
            ->method('deleteAuditLogBatchOlderThan')
            ->with($cutoff, 2)
            ->willReturnOnConsecutiveCalls(2, 2, 1);
        $service = new DataRetentionService($repository);

        $result = $service->purgeAuditLog(180, false, self::NOW, 2);

        self::assertSame($cutoff, $result->cutoffEpoch);
        self::assertSame(5, $result->eligible);
        self::assertSame(5, $result->deleted);
        self::assertFalse($result->dryRun);
    }

    public function testPurgeAuditLogDefaultBatchSizeIsOneThousand(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(1);
        $repository->expects(self::once())
            ->method('deleteAuditLogBatchOlderThan')
            ->with(self::NOW - 180 * 86400, 1000)
            ->willReturn(1);
        $service = new DataRetentionService($repository);

        self::assertSame(1, $service->purgeAuditLog(180, false, self::NOW)->deleted);
        // @phpstan-ignore staticMethod.alreadyNarrowedType (const value statically known; assertion guards future edits)
        self::assertSame(1000, DataRetentionService::PURGE_BATCH_SIZE);
        // @phpstan-ignore staticMethod.alreadyNarrowedType (const value statically known; assertion guards future edits)
        self::assertSame(180, DataRetentionService::AUDIT_LOG_RETENTION_DAYS);
    }

    public function testPurgeAuditLogCutoffIsNowMinusRetentionDays(): void
    {
        $expectedCutoff = self::NOW - 180 * 86400;
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())
            ->method('countAuditLogRowsOlderThan')
            ->with($expectedCutoff)
            ->willReturn(0);
        $service = new DataRetentionService($repository);

        $result = $service->purgeAuditLog(180, false, self::NOW);

        self::assertSame($expectedCutoff, $result->cutoffEpoch);
    }

    public function testPurgeAuditLogStopsWhenNoEligibleRows(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(0);
        $repository->expects(self::never())->method('deleteAuditLogBatchOlderThan');
        $service = new DataRetentionService($repository);

        $result = $service->purgeAuditLog(180, false, self::NOW);

        self::assertSame(0, $result->eligible);
        self::assertSame(0, $result->deleted);
        self::assertFalse($result->dryRun);
    }

    public function testPurgeAuditLogDryRunNeverCallsDelete(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(7);
        $repository->expects(self::never())->method('deleteAuditLogBatchOlderThan');
        $service = new DataRetentionService($repository);

        $result = $service->purgeAuditLog(180, true, self::NOW);

        self::assertSame(7, $result->eligible);
        self::assertSame(0, $result->deleted);
        self::assertTrue($result->dryRun);
    }

    public function testPurgeAuditLogLoopIsBoundedWhenDeleteKeepsReturningFullBatches(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(3);
        // Cap is intdiv(3 + 2 - 1, 2) + 1 = 3 batches.
        $repository->expects(self::exactly(3))
            ->method('deleteAuditLogBatchOlderThan')
            ->with(self::NOW - 180 * 86400, 2)
            ->willReturn(2);
        $service = new DataRetentionService($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessageIs(
            'audit-log purge exceeded 3 batches; rows are still being written below the cutoff'
        );
        $service->purgeAuditLog(180, false, self::NOW, 2);
    }

    public function testPurgeAuditLogLoopCapWhenEligibleDividesEvenlyIntoBatches(): void
    {
        $repository = self::createMock(DataRetentionRepositoryInterface::class);
        $repository->expects(self::once())->method('countAuditLogRowsOlderThan')->willReturn(4);
        // Cap is intdiv(4 + 2 - 1, 2) + 1 = 3 batches: two to drain, one for late arrivals.
        $repository->expects(self::exactly(3))
            ->method('deleteAuditLogBatchOlderThan')
            ->willReturn(2);
        $service = new DataRetentionService($repository);

        $this->expectException(\RuntimeException::class);
        $service->purgeAuditLog(180, false, self::NOW, 2);
    }
}
