<?php

declare(strict_types=1);

namespace DataRetention\Contracts;

/**
 * Data access for retention purges of personal data.
 */
interface DataRetentionRepositoryInterface
{
    /**
     * Count `auth_users_audit_log` rows whose `event_at` is strictly before the cutoff.
     */
    public function countAuditLogRowsOlderThan(int $cutoffEpoch): int;

    /**
     * Delete at most `$limit` `auth_users_audit_log` rows whose `event_at` is strictly
     * before the cutoff.
     *
     * @return int Number of rows deleted
     */
    public function deleteAuditLogBatchOlderThan(int $cutoffEpoch, int $limit): int;
}
