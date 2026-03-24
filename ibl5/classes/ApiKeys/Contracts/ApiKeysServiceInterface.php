<?php

declare(strict_types=1);

namespace ApiKeys\Contracts;

interface ApiKeysServiceInterface
{
    /**
     * Generate a new API key for a user.
     *
     * The raw key is returned exactly once — it is never stored or retrievable again.
     * Throws if the user already has an active key (must revoke first).
     *
     * @return array{raw_key: string, prefix: string}
     * @throws \RuntimeException If user already has an active key
     */
    public function generateKeyForUser(int $userId, string $username): array;

    /**
     * Revoke (deactivate) a user's API key.
     */
    public function revokeKeyForUser(int $userId): void;

    /**
     * Get the current key status for a user.
     *
     * @return array{key_prefix: string, permission_level: string, rate_limit_tier: string, is_active: int, created_at: string, last_used_at: ?string}|null
     */
    public function getUserKeyStatus(int $userId): ?array;
}
