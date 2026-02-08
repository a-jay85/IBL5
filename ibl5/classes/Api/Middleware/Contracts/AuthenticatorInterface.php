<?php

declare(strict_types=1);

namespace Api\Middleware\Contracts;

interface AuthenticatorInterface
{
    /**
     * Authenticate the current request using the X-API-Key header.
     *
     * @return array{key_hash: string, permission_level: string, rate_limit_tier: string}|null
     *         The API key record if valid, or null if authentication fails
     */
    public function authenticate(): ?array;
}
