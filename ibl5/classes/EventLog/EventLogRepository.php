<?php

declare(strict_types=1);

namespace EventLog;

/**
 * Writes product-analytics request rows to ibl_events.
 *
 * Fire-and-forget: the caller (RequestEventLoggingBootstrap) wraps this in
 * try/catch and never rethrows. All string fields are pre-truncated by the
 * caller to the column widths in migration 154 so an over-length client header
 * cannot error the prepared statement.
 */
class EventLogRepository extends \BaseMysqliRepository
{
    /**
     * Insert one request event. Nullable identity/header fields accept null,
     * which mysqli's bind_param sends as SQL NULL.
     *
     * @return int affected rows (1 on success)
     */
    public function insert(
        string $requestUri,
        ?string $routeName,
        string $httpMethod,
        ?string $username,
        ?int $teamId,
        ?string $referer,
        ?string $userAgent
    ): int {
        $sql = 'INSERT INTO `ibl_events` '
             . '(request_uri, route_name, http_method, username, team_id, referer, user_agent) '
             . 'VALUES (?, ?, ?, ?, ?, ?, ?)';

        // Type string, one char per placeholder in order:
        //   request_uri s | route_name s | http_method s | username s
        //   team_id i | referer s | user_agent s
        return $this->execute(
            $sql,
            'ssssiss',
            $requestUri,
            $routeName,
            $httpMethod,
            $username,
            $teamId,
            $referer,
            $userAgent
        );
    }
}
