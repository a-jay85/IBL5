<?php

declare(strict_types=1);

namespace Bootstrap;

use Api\Middleware\RateLimiter;
use Api\Repository\RateLimitRepository;
use Api\Response\JsonResponder;
use Bootstrap\Contracts\BootstrapStepInterface;
use Bootstrap\Contracts\ContainerInterface;

/**
 * Checks rate limits for the authenticated API key.
 *
 * Runs after auth; on rate limit hit, sends 429 and terminates.
 */
class RateLimitingBootstrap implements BootstrapStepInterface
{
    /**
     * @see BootstrapStepInterface::boot()
     */
    public function boot(ContainerInterface $container): void
    {
        /** @var \mysqli $db */
        $db = $container->get(\mysqli::class);
        /** @var array{key_hash: string, permission_level: string, rate_limit_tier: string} $apiKey */
        $apiKey = $container->get('api.key');

        $rateLimiter = new RateLimiter(new RateLimitRepository($db));
        $rateLimitResult = $rateLimiter->check($apiKey);

        if ($rateLimitResult !== null) {
            /** @var JsonResponder $responder */
            $responder = $container->get('api.responder');
            $responder->error(429, 'rate_limit_exceeded', 'Rate limit exceeded. Try again later.', $rateLimitResult);
            $container->set('app.terminated', true);
        }
    }
}
