<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Middleware\Contracts\AuthenticatorInterface;
use Api\Repository\ApiKeyRepository;

class ApiKeyAuthenticator implements AuthenticatorInterface
{
    private ApiKeyRepository $repository;

    public function __construct(ApiKeyRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see AuthenticatorInterface::authenticate()
     */
    public function authenticate(): ?array
    {
        $rawKey = $this->getApiKeyFromRequest();
        if ($rawKey === null) {
            return null;
        }

        $keyHash = hash('sha256', $rawKey);
        $apiKey = $this->repository->findByHash($keyHash);

        if ($apiKey === null) {
            return null;
        }

        // Update last_used_at asynchronously (fire-and-forget)
        $this->repository->touchLastUsed($keyHash);

        return $apiKey;
    }

    /**
     * Extract the API key from the X-API-Key header.
     */
    private function getApiKeyFromRequest(): ?string
    {
        /** @var string $key */
        $key = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($key === '') {
            return null;
        }

        return $key;
    }
}
