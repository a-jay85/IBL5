<?php

declare(strict_types=1);

namespace Api\Middleware;

use Api\Middleware\Contracts\RateLimiterInterface;
use Api\Repository\RateLimitRepository;

class RateLimiter implements RateLimiterInterface
{
    /** @var array<string, int> Requests per minute by tier */
    private const TIER_LIMITS = [
        'standard' => 60,
        'elevated' => 300,
        'unlimited' => 0, // 0 = no limit
    ];

    private RateLimitRepository $repository;

    public function __construct(RateLimitRepository $repository)
    {
        $this->repository = $repository;
    }

    /**
     * @see RateLimiterInterface::check()
     */
    public function check(array $apiKey): ?array
    {
        $tier = $apiKey['rate_limit_tier'];
        $limit = self::TIER_LIMITS[$tier] ?? self::TIER_LIMITS['standard'];

        // Unlimited tier skips rate limiting
        if ($limit === 0) {
            $this->repository->increment($apiKey['key_hash']);
            $this->maybePrune();
            return null;
        }

        $keyHash = $apiKey['key_hash'];

        // Increment first (atomic upsert)
        $this->repository->increment($keyHash);

        // Check current count
        $count = $this->repository->getRequestCount($keyHash);

        // Probabilistic cleanup
        $this->maybePrune();

        if ($count > $limit) {
            return [
                'Retry-After' => '60',
                'X-RateLimit-Limit' => (string) $limit,
                'X-RateLimit-Remaining' => '0',
                'X-RateLimit-Reset' => (string) (time() + 60),
            ];
        }

        return null;
    }

    /**
     * ~1% probability cleanup of old rate limit entries.
     */
    private function maybePrune(): void
    {
        if (random_int(1, 100) === 1) {
            $this->repository->pruneOldEntries();
        }
    }
}
