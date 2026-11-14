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
     * Look up an account by id. Never selects the email.
     *
     * @return array{id: int, username: string|null}|null Null when no `auth_users` row has the id
     */
    public function findAuthUserById(int $userId): ?array;

    /**
     * Every team whose GM slot names the username, ordered by teamid.
     *
     * @return list<array{teamid: int, team_name: string}>
     */
    public function findTeamsByGmUsername(string $username): array;

    /**
     * Count the rows one footprint target would change for the account.
     *
     * Returns 0 without a query when a username target gets a null or empty username.
     */
    public function countAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int;

    /**
     * Run one footprint target's write for the account.
     *
     * Returns 0 without a query when a username target gets a null or empty username.
     *
     * @return int Number of rows changed
     */
    public function applyAccountTarget(AccountFootprintTarget $target, int $userId, ?string $username): int;

    /**
     * Run the callback in one transaction; any throw rolls every write back.
     *
     * @template T
     * @param callable(): T $work
     * @return T
     */
    public function runInTransaction(callable $work): mixed;
}
