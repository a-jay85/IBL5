<?php

declare(strict_types=1);

namespace Api\Controller;

use Api\Contracts\ControllerInterface;
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
    private \mysqli $db;

    public function __construct(\mysqli $db)
    {
        $this->db = $db;
    }

    /**
     * @see ControllerInterface::handle()
     */
    public function handle(array $params, array $query, JsonResponder $responder, ?array $body = null): void
    {
        $dbOk = $this->isDatabaseReachable();

        $responder->raw(
            [
                'status' => $dbOk ? 'ok' : 'degraded',
                'db' => $dbOk,
                'checkedAt' => gmdate('Y-m-d\TH:i:s\Z'),
            ],
            $dbOk ? 200 : 503
        );
    }

    /**
     * Run a lightweight no-schema probe against the DB handle.
     *
     * A thrown exception (mysqli reports failures as exceptions under
     * MYSQLI_REPORT_STRICT) means the connection is unusable.
     */
    private function isDatabaseReachable(): bool
    {
        try {
            $this->db->query('SELECT 1');
            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
