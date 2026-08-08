<?php

declare(strict_types=1);

namespace EventLog;

/**
 * Static holder for the pending analytics row id and domain-event action.
 *
 * Lifecycle per request:
 *   1. RequestEventLoggingBootstrap::boot() inserts the row and calls arm($id, $db).
 *   2. POST handlers call setAction('trade_submitted') on success paths.
 *   3. At shutdown, PHP invokes flush() which writes updateOutcome() and resets.
 *
 * flush() is public so it can be injected in unit tests without needing a real
 * HTTP response. The catch inside flush() is intentionally empty — see D10.
 */
final class EventLogger
{
    private static ?int $pendingId = null;
    private static ?string $pendingAction = null;
    private static ?\mysqli $pendingDb = null;
    private static bool $registered = false;

    /**
     * Arm the shutdown flush for a newly inserted row.
     * The $db is stored so flush() can build a repository without accessing $GLOBALS.
     * Registers the shutdown function only on the first call.
     */
    public static function arm(int $id, \mysqli $db): void
    {
        self::$pendingId = $id;
        self::$pendingDb = $db;
        if (!self::$registered) {
            register_shutdown_function([self::class, 'flush']);
            self::$registered = true;
        }
    }

    /**
     * Record the domain event for the current request.
     * Last write wins (D11). Truncated to 64 chars (column width).
     */
    public static function setAction(string $action): void
    {
        self::$pendingAction = mb_strcut($action, 0, 64, 'UTF-8');
    }

    /**
     * Validate and clamp an http_response_code() return value.
     * Returns null for false (CLI/no response), 0, out-of-range, or non-int.
     */
    public static function normalizeStatus(int|bool $code): ?int
    {
        return is_int($code) && $code >= 100 && $code <= 599 ? $code : null;
    }

    /**
     * Write the outcome UPDATE. Called by the shutdown handler, and injectable
     * in tests via the optional $repo parameter.
     *
     * Silent: any error is swallowed (D10). State is reset in finally so a
     * second invocation is always a no-op.
     */
    public static function flush(?EventLogRepository $repo = null): void
    {
        if (self::$pendingId === null) {
            return;
        }

        try {
            if ($repo === null) {
                if (self::$pendingDb === null) {
                    return;
                }
                $repo = new EventLogRepository(self::$pendingDb);
            }

            $repo->updateOutcome(
                self::$pendingId,
                self::normalizeStatus(http_response_code()),
                self::$pendingAction
            );
        } catch (\Throwable) {
            // Intentionally empty — see D10. At shutdown, logging is unsafe and
            // a throw from inside catch is unhandleable (fatal in response tail).
        } finally {
            self::reset();
        }
    }

    /**
     * Reset state. Call in test setUp()/tearDown() for isolation.
     * Does NOT clear $registered (shutdown functions cannot be unregistered).
     */
    public static function reset(): void
    {
        self::$pendingId = null;
        self::$pendingAction = null;
        self::$pendingDb = null;
    }
}
