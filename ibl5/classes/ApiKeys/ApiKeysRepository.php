<?php

declare(strict_types=1);

namespace ApiKeys;

use ApiKeys\Contracts\ApiKeysRepositoryInterface;
use BaseMysqliRepository;

/**
 * ApiKeysRepository - Database operations for self-service API key management
 *
 * @see ApiKeysRepositoryInterface For method contracts
 */
class ApiKeysRepository extends BaseMysqliRepository implements ApiKeysRepositoryInterface
{
    /**
     * @see ApiKeysRepositoryInterface::findByUserId()
     */
    public function findByUserId(int $userId): ?array
    {
        /** @var array{key_prefix: string, permission_level: string, rate_limit_tier: string, is_active: int, created_at: string, last_used_at: ?string}|null $row */
        $row = $this->fetchOne(
            'SELECT key_prefix, permission_level, rate_limit_tier, is_active, created_at, last_used_at
             FROM ibl_api_keys
             WHERE user_id = ?
             ORDER BY created_at DESC
             LIMIT 1',
            'i',
            $userId
        );

        return $row;
    }

    /**
     * @see ApiKeysRepositoryInterface::createKey()
     */
    public function createKey(int $userId, string $keyHash, string $keyPrefix, string $ownerName): void
    {
        $this->execute(
            'INSERT INTO ibl_api_keys (user_id, key_hash, key_prefix, owner_name, permission_level, rate_limit_tier)
             VALUES (?, ?, ?, ?, ?, ?)',
            'isssss',
            $userId,
            $keyHash,
            $keyPrefix,
            $ownerName,
            'public',
            'standard'
        );
    }

    /**
     * @see ApiKeysRepositoryInterface::revokeByUserId()
     */
    public function revokeByUserId(int $userId): void
    {
        $this->execute(
            'UPDATE ibl_api_keys SET is_active = 0 WHERE user_id = ? AND is_active = 1',
            'i',
            $userId
        );
    }
}
