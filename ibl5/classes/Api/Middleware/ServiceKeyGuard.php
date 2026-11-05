<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Repository\ApiKeyRepository;

/**
 * Answers one question for the bug-pipeline routes: is the caller's API key a service key?
 *
 * A GM can mint a key for themselves on the API Keys page, and those are always the
 * 'standard' rate-limit tier. The pipeline bot's key must be elevated or unlimited
 * (README.bugbot.md), so a self-issued key never passes this check.
 */
class ServiceKeyGuard
{
    private ApiKeyRepository $apiKeys;

    public function __construct(ApiKeyRepository $apiKeys)
    {
        $this->apiKeys = $apiKeys;
    }

    public function callerIsServiceKey(): bool
    {
        $raw = $_SERVER['HTTP_X_API_KEY'] ?? '';
        if (!is_string($raw) || $raw === '') {
            $raw = $_GET['key'] ?? '';
        }
        if (!is_string($raw) || $raw === '') {
            return false;
        }
        $key = $this->apiKeys->findByHash(hash('sha256', $raw));
        return $key !== null && $key['rate_limit_tier'] !== 'standard';
    }
}
