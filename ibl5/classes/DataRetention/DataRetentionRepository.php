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

    /**
     * @see DataRetentionRepositoryInterface::findAuthUserById()
     */
    public function findAuthUserById(int $userId): ?array
    {
        /** @var array{id: int, username: string|null}|null $row */
        $row = $this->fetchOne(
            'SELECT `id`, `username` FROM `auth_users` WHERE `id` = ?',
            'i',
            $userId
        );

        return $row;
    }

    /**
     * @see DataRetentionRepositoryInterface::findTeamsByGmUsername()
     */
    public function findTeamsByGmUsername(string $username): array
    {
        /** @var list<array{teamid: int, team_name: string}> $rows */
        $rows = $this->fetchAll(
            'SELECT `teamid`, `team_name` FROM `ibl_team_info` WHERE `gm_username` = ? ORDER BY `teamid`',
            's',
            $username
        );

        return $rows;
    }

    /**
     * @see DataRetentionRepositoryInterface::countAccountTarget()
     */
    public function countAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int
    {
        if ($target->bindsUsername()) {
            if ($username === null || $username === '') {
                return 0;
            }
            /** @var array{n: int} $row */
            $row = $this->fetchOne($target->countSql(), 's', $username);
        } else {
            /** @var array{n: int} $row */
            $row = $this->fetchOne($target->countSql(), 'i', $userId);
        }

        return $row['n'];
    }

    /**
     * @see DataRetentionRepositoryInterface::applyAccountTarget()
     */
    public function applyAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int
    {
        if ($target->bindsUsername()) {
            if ($username === null || $username === '') {
                return 0;
            }
            return $this->execute($target->writeSql(), 's', $username);
        }

        return $this->execute($target->writeSql(), 'i', $userId);
    }

    /**
     * @see DataRetentionRepositoryInterface::runInTransaction()
     */
    public function runInTransaction(callable $work): mixed
    {
        return $this->transactional($work);
    }
}
