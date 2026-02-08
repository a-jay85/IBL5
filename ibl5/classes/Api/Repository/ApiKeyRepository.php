<?php

declare(strict_types=1);

namespace Api\Repository;

class ApiKeyRepository extends \BaseMysqliRepository
{
    /**
     * Look up an active API key by its SHA-256 hash.
     *
     * @return array{key_hash: string, key_prefix: string, owner_name: string, permission_level: string, rate_limit_tier: string}|null
     */
    public function findByHash(string $keyHash): ?array
    {
        /** @var array{key_hash: string, key_prefix: string, owner_name: string, permission_level: string, rate_limit_tier: string}|null $row */
        $row = $this->fetchOne(
            'SELECT key_hash, key_prefix, owner_name, permission_level, rate_limit_tier
             FROM ibl_api_keys
             WHERE key_hash = ? AND is_active = 1',
            's',
            $keyHash
        );

        return $row;
    }

    /**
     * Update the last_used_at timestamp for an API key.
     */
    public function touchLastUsed(string $keyHash): void
    {
        $this->execute(
            'UPDATE ibl_api_keys SET last_used_at = NOW() WHERE key_hash = ?',
            's',
            $keyHash
        );
    }
}
