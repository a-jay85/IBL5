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
                self::assertStringContainsString((string) $days, $e->getMessage());
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
        $service->purgeAuditLog(180, false, self::NOW, 0);
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
        $this->expectExceptionMessageIsOrContains('exceeded 3 batches');
        $service->purgeAuditLog(180, false, self::NOW, 2);
    }
}
