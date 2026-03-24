<?php

declare(strict_types=1);

namespace ApiKeys\Contracts;

interface ApiKeysRepositoryInterface
{
    /**
     * Find an API key record by user ID.
     *
     * Returns the key metadata (prefix, permission level, active status, timestamps)
     * or null if no key exists for this user.
     *
     * @return array{key_prefix: string, permission_level: string, rate_limit_tier: string, is_active: int, created_at: string, last_used_at: ?string}|null
     */
    public function findByUserId(int $userId): ?array;

    /**
     * Insert a new API key linked to a user.
     *
     * @param int $userId User ID from nuke_users
     * @param string $keyHash SHA-256 hash of the raw API key
     * @param string $keyPrefix First 8 characters of the raw key (for display)
     * @param string $ownerName Human-readable owner name (username)
     */
    public function createKey(int $userId, string $keyHash, string $keyPrefix, string $ownerName): void;

    /**
     * Revoke (deactivate) a user's API key.
     *
     * Sets is_active = 0 rather than deleting the row, preserving audit history.
     */
    public function revokeByUserId(int $userId): void;
}
