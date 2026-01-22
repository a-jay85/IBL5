<?php

declare(strict_types=1);

namespace Auth\Contracts;

/**
 * Interface for User Migration Service
 *
 * Handles migration of users from PHP-Nuke tables to Laravel users table.
 */
interface UserMigrationServiceInterface
{
    /**
     * Sync all users from nuke_users to users table
     *
     * @param bool $dryRun If true, don't actually modify the database
     * @return array{migrated: int, skipped: int, errors: array<string>}
     */
    public function syncFromNuke(bool $dryRun = false): array;

    /**
     * Sync a single user from nuke_users to users table
     *
     * @param int $nukeUserId The nuke_users.user_id
     * @return bool True if sync successful
     */
    public function syncUser(int $nukeUserId): bool;

    /**
     * Get admin role for a user based on nuke_authors table
     *
     * @param string $username The username to check
     * @return string The role (commissioner, owner, or spectator)
     */
    public function determineRole(string $username): string;

    /**
     * Get migration statistics
     *
     * @return array{total_nuke: int, total_users: int, migrated: int, pending: int}
     */
    public function getStats(): array;
}
