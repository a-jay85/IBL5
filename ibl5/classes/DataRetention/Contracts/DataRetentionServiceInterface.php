<?php

declare(strict_types=1);

namespace DataRetention\Contracts;

use DataRetention\AccountDeletionReport;
use DataRetention\AuditPurgeResult;
use DataRetention\UnknownAccountException;

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

    /**
     * Delete an account and every row keyed to it, or count those rows on a dry run.
     *
     * With `$confirm`, every write runs in one transaction in AccountFootprintTarget case
     * order. Audit rows about other users keep their row with `admin_id` set to NULL;
     * `ibl_events` and `ibl_team_info` keep their rows with the username set to NULL.
     *
     * @param int $userId `auth_users.id`
     * @param bool $confirm False counts the rows and writes nothing
     *
     * @throws \InvalidArgumentException When $userId is below 1
     * @throws UnknownAccountException When no account has the id; nothing is written
     * @throws \RuntimeException When the `auth_users` row did not go; the transaction rolls back
     */
    public function deleteAccount(int $userId, bool $confirm): AccountDeletionReport;
}
