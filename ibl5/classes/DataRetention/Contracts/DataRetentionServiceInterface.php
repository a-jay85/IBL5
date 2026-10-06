<?php

declare(strict_types=1);

namespace DataRetention\Contracts;

use DataRetention\AuditPurgeResult;

/**
 * Retention purges of personal data.
 */
interface DataRetentionServiceInterface
{
    /**
     * Delete `auth_users_audit_log` rows older than the retention window, in batches.
     *
     * Each batch autocommits, so a partial purge resumes on the next run.
     *
     * @param int $retentionDays Rows with `event_at` before now minus this many days are purged
     * @param bool $dryRun Count eligible rows without deleting any
     * @param int $nowEpoch Current Unix time
     * @param int $batchSize Rows per DELETE; the default mirrors DataRetentionService::PURGE_BATCH_SIZE
     *
     * @throws \InvalidArgumentException When $retentionDays or $batchSize is below 1
     * @throws \RuntimeException When the table does not drain within the batch cap
     */
    public function purgeAuditLog(int $retentionDays, bool $dryRun, int $nowEpoch, int $batchSize = 1000): AuditPurgeResult;
}
