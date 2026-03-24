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
     * Extract the API key from the request.
     *
     * Checks the X-API-Key header first, then falls back to the ?key= query parameter.
     * The query param fallback is needed for Google Sheets IMPORTDATA() which cannot
     * send custom HTTP headers.
     *
     * Security note: Keys in query params appear in server access logs. This is an
     * acceptable trade-off for read-only export endpoints.
     */
    private function getApiKeyFromRequest(): ?string
    {
        /** @var string $headerKey */
        $headerKey = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if ($headerKey !== '') {
            return $headerKey;
        }

        // Fallback for clients that cannot send custom headers (e.g., Google Sheets IMPORTDATA)
        $queryKey = $_GET['key'] ?? '';
        if (!is_string($queryKey) || $queryKey === '') {
            return null;
        }

        return $queryKey;
    }
}
