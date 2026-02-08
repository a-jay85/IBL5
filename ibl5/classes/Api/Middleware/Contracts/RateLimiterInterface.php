<?php

declare(strict_types=1);

namespace Api\Middleware\Contracts;

interface RateLimiterInterface
{
    /**
     * Check if the request is within rate limits.
     *
     * @param array{key_hash: string, permission_level: string, rate_limit_tier: string} $apiKey
     * @return array<string, string>|null Null if allowed, or headers array (Retry-After, X-RateLimit-*) if rate limited
     */
    public function check(array $apiKey): ?array;
}
