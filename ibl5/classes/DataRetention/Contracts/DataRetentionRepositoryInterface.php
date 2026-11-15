<?php

declare(strict_types=1);

namespace DataRetention\Contracts;

use DataRetention\AccountFootprintTarget;

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

    /**
     * Look up an account by id. Never selects `email`.
     *
     * @return array{id: int, username: ?string}|null
     */
    public function findAuthUserById(int $userId): ?array;

    /**
     * Every team whose GM slot names this username, ordered by teamid.
     *
     * @return list<array{teamid: int, team_name: string}>
     */
    public function findTeamsByGmUsername(string $username): array;

    /**
     * Count the rows one footprint target would write for this account.
     *
     * Returns 0 without a query when a username target gets a null or empty username.
     */
    public function countAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int;

    /**
     * Run one footprint target's write for this account.
     *
     * Returns 0 without a query when a username target gets a null or empty username.
     *
     * @return int Rows affected
     */
    public function applyAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int;

    /**
     * Run `$work` inside one transaction; any throw rolls every write back.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function runInTransaction(callable $work): mixed;
}
