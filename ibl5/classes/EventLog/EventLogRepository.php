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
     * @return int New row id, or 0 if the insert did not affect exactly one row.
     */
    public function insert(
        string $requestUri,
        ?string $routeName,
        string $httpMethod,
        ?string $username,
        ?int $teamId,
        ?string $referer,
        ?string $userAgent,
        ?string $sessionId = null,
        ?string $trafficClass = null
    ): int {
        $sql = 'INSERT INTO `ibl_events` '
             . '(request_uri, route_name, http_method, username, team_id, referer, user_agent, session_id, traffic_class) '
             . 'VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)';

        // Type string, one char per placeholder in order (9 chars, 9 params):
        //   request_uri s | route_name s | http_method s | username s
        //   team_id i | referer s | user_agent s | session_id s | traffic_class s
        $affected = $this->execute(
            $sql,
            'ssssissss',
            $requestUri,
            $routeName,
            $httpMethod,
            $username,
            $teamId,
            $referer,
            $userAgent,
            $sessionId,
            $trafficClass
        );

        return $affected === 1 ? $this->getLastInsertId() : 0;
    }

    /**
     * Record the response outcome for a previously inserted event row.
     *
     * Called from the shutdown handler, after output. Both values are
     * optional: a request that ends before shutdown leaves them NULL.
     *
     * @param int $id Row id returned by insert().
     * @param int|null $httpStatus Response code, already range-validated by the caller.
     * @param string|null $action Domain event literal, already truncated by the caller.
     * @return int Affected rows (1 on success, 0 if the row is gone).
     */
    public function updateOutcome(int $id, ?int $httpStatus, ?string $action): int
    {
        $sql = 'UPDATE `ibl_events` SET http_status = ?, action = ? WHERE id = ?';

        // Type string, one char per placeholder in order (3 chars, 3 params):
        //   http_status i | action s | id i
        return $this->execute($sql, 'isi', $httpStatus, $action, $id);
    }
}
