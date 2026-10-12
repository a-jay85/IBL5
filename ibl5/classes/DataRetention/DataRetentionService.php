<?php

declare(strict_types=1);

namespace DataRetention;

use DataRetention\Contracts\DataRetentionRepositoryInterface;
use DataRetention\Contracts\DataRetentionServiceInterface;

final class DataRetentionService implements DataRetentionServiceInterface
{
    public const AUDIT_LOG_RETENTION_DAYS = 180;
    public const PURGE_BATCH_SIZE = 1000;
    private const SECONDS_PER_DAY = 86400;

    public function __construct(
        private readonly DataRetentionRepositoryInterface $repository,
    ) {
    }

    /**
     * @see DataRetentionServiceInterface::purgeAuditLog()
     */
    public function purgeAuditLog(int $retentionDays, bool $dryRun, int $nowEpoch, int $batchSize = self::PURGE_BATCH_SIZE): AuditPurgeResult
    {
        if ($retentionDays < 1) {
            throw new \InvalidArgumentException('Retention days must be at least 1, got ' . $retentionDays);
        }
        if ($batchSize < 1) {
            throw new \InvalidArgumentException('Batch size must be at least 1, got ' . $batchSize);
        }

        $cutoff = $nowEpoch - $retentionDays * self::SECONDS_PER_DAY;
        $eligible = $this->repository->countAuditLogRowsOlderThan($cutoff);

        if ($dryRun || $eligible === 0) {
            return new AuditPurgeResult($cutoff, $eligible, 0, $dryRun);
        }

        // Each batch autocommits so the lock on a table every login writes stays short.
        // The extra round covers rows that aged past the cutoff between the count and the deletes.
        $maxBatches = intdiv($eligible + $batchSize - 1, $batchSize) + 1;
        $deleted = 0;
        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $batchDeleted = $this->repository->deleteAuditLogBatchOlderThan($cutoff, $batchSize);
            $deleted += $batchDeleted;
            if ($batchDeleted < $batchSize) {
                return new AuditPurgeResult($cutoff, $eligible, $deleted, false);
            }
        }

        throw new \RuntimeException(
            'audit-log purge exceeded ' . $maxBatches . ' batches; rows are still being written below the cutoff'
        );
    }
}
