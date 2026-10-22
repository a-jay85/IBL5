<?php

declare(strict_types=1);

namespace DataRetention;

use DataRetention\Contracts\DataRetentionRepositoryInterface;

final class DataRetentionRepository extends \Database\BaseMysqliRepository implements DataRetentionRepositoryInterface
{
    /**
     * @see DataRetentionRepositoryInterface::countAuditLogRowsOlderThan()
     */
    public function countAuditLogRowsOlderThan(int $cutoffEpoch): int
    {
        // COUNT(*) always returns exactly one row.
        /** @var array{n: int} $row */
        $row = $this->fetchOne(
            'SELECT COUNT(*) AS n FROM `auth_users_audit_log` WHERE `event_at` < ?',
            'i',
            $cutoffEpoch
        );

        return $row['n'];
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
