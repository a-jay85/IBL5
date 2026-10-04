<?php

declare(strict_types=1);

namespace DataRetention;

use DataRetention\Contracts\DataRetentionRepositoryInterface;

final class DataRetentionRepository extends \BaseMysqliRepository implements DataRetentionRepositoryInterface
{
    /**
     * @see DataRetentionRepositoryInterface::countAuditLogRowsOlderThan()
     */
    public function countAuditLogRowsOlderThan(int $cutoffEpoch): int
    {
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `event_at` < ?',
            'i',
            $cutoffEpoch
        );

        return (int) ($row['n'] ?? 0);
    }

    /**
     * @see DataRetentionRepositoryInterface::deleteAuditLogBatchOlderThan()
     */
    public function deleteAuditLogBatchOlderThan(int $cutoffEpoch, int $limit): int
    {
        return $this->execute(
            'DELETE FROM `auth_users_audit_log` WHERE `event_at` < ? LIMIT ?',
            'ii',
            $cutoffEpoch,
            $limit
        );
    }
}
