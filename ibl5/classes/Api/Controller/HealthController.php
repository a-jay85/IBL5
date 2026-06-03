<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
use Api\Repository\HealthRepository;
use Api\Response\JsonResponder;

/**
 * Liveness/readiness probe for external uptime monitors.
 *
 * Returns a flat, monitor-friendly body (no success/error envelope):
 *   { "status": "ok"|"degraded", "db": bool, "checkedAt": ISO8601 }
 *
 * HTTP 200 when the database answers a lightweight SELECT 1; HTTP 503 when it does not.
 * This route is intentionally unauthenticated (see ApiKeyAuthBootstrap) so probes can
 * reach it without an API key; it exposes no schema data, only reachability.
 */
class HealthController implements ControllerInterface
{
    private HealthRepository $healthRepository;

    public function __construct(HealthRepository $healthRepository)
    {
        $this->healthRepository = $healthRepository;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $dbOk = $this->healthRepository->isReachable();

        $responder->raw(
            [
                'status' => $dbOk ? 'ok' : 'degraded',
                'db' => $dbOk,
                'checkedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            $dbOk ? 200 : 503
        );
    }
}
