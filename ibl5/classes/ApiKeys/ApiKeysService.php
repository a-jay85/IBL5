<?php

declare(strict_types=1);

namespace ApiKeys;

use ApiKeys\Contracts\ApiKeysRepositoryInterface;
use ApiKeys\Contracts\ApiKeysServiceInterface;

/**
 * ApiKeysService - Business logic for self-service API key management
 *
 * Key generation follows the same pattern as scripts/generate_api_key.php:
 * ibl_ prefix + 32 hex chars from 16 random bytes, stored as SHA-256 hash.
 *
 * @see ApiKeysServiceInterface For method contracts
 */
class ApiKeysService implements ApiKeysServiceInterface
{
    private ApiKeysRepositoryInterface $repository;

    /**
     * Optional PSR-3 logger. When null, falls back to LoggerFactory::getChannel('audit').
     */
    private \Psr\Log\LoggerInterface $logger;

    public function __construct(ApiKeysRepositoryInterface $repository, ?\Psr\Log\LoggerInterface $logger = null)
    {
        $this->repository = $repository;
        $this->logger = $logger ?? \Logging\LoggerFactory::getChannel('audit');
    }

    /**
     * @see ApiKeysServiceInterface::generateKeyForUser()
     */
    public function generateKeyForUser(int $userId, string $username): array
    {
        $existing = $this->repository->findByUserId($userId);
        if ($existing !== null && $existing['is_active'] === 1) {
            throw new \RuntimeException('User already has an active API key. Revoke it before generating a new one.');
        }

        $rawKey = 'ibl_' . bin2hex(random_bytes(16));
        $keyHash = hash('sha256', $rawKey);
        $keyPrefix = substr($rawKey, 0, 8);

        $this->repository->createKey($userId, $keyHash, $keyPrefix, $username);

        $this->logger->info('api_key_generated', [
            'action' => 'api_key_generated',
            'user_id' => $userId,
            'username' => $username,
            'key_prefix' => $keyPrefix,
        ]);

        return [
            'raw_key' => $rawKey,
            'prefix' => $keyPrefix,
        ];
    }

    /**
     * @see ApiKeysServiceInterface::revokeKeyForUser()
     */
    public function revokeKeyForUser(int $userId): void
    {
        $this->repository->revokeByUserId($userId);

        $this->logger->info('api_key_revoked', [
            'action' => 'api_key_revoked',
            'user_id' => $userId,
        ]);
    }

    /**
     * @see ApiKeysServiceInterface::getUserKeyStatus()
     */
    public function getUserKeyStatus(int $userId): ?array
    {
        $key = $this->repository->findByUserId($userId);
        if ($key === null) {
            return null;
        }

        // Only return active keys for status display
        if ($key['is_active'] !== 1) {
            return null;
        }

        return $key;
    }
}
