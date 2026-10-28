<?php

declare(strict_types=1);

namespace Database;

/**
 * Caps how long one SQL statement may run on a web-request connection.
 *
 * MariaDB aborts a statement that runs past max_statement_time with error 1969
 * and frees the connection. CLI runs (cron, migrations, PHPUnit, scripts) keep
 * no cap. `php -S` reports SAPI "cli-server" and serves real web requests, so it
 * is capped like LiteSpeed.
 */
final class StatementTimeout
{
    public const DEFAULT_SECONDS = 10;
    public const MAX_SECONDS = 3600;
    /** MariaDB ER_STATEMENT_TIMEOUT. */
    public const ER_STATEMENT_TIMEOUT = 1969;

    /**
     * Parse the optional $dbStatementTimeoutSeconds config value.
     *
     * null (unset) -> DEFAULT_SECONDS. An int in [0, MAX_SECONDS] is used as-is;
     * 0 disables the cap. Anything else (negative, over MAX_SECONDS, string, float,
     * bool, '') falls back to DEFAULT_SECONDS.
     */
    public static function resolveSeconds(mixed $configured): int
    {
        if (is_int($configured) && $configured >= 0 && $configured <= self::MAX_SECONDS) {
            return $configured;
        }
        return self::DEFAULT_SECONDS;
    }

    /**
     * Apply the cap to a fresh web-request connection. No-op under the 'cli' SAPI
     * and when the resolved value is 0. Returns true only when a SET ran and succeeded.
     */
    public static function apply(\mysqli|\PDO $connection, mixed $configuredSeconds, string $sapi = \PHP_SAPI): bool
    {
        if ($sapi === 'cli') {
            return false;
        }
        $seconds = self::resolveSeconds($configuredSeconds);
        if ($seconds === 0) {
            return false;
        }
        return self::setSession($connection, $seconds);
    }

    /**
     * Lift the cap for a legitimately long web request (the sim updater).
     */
    public static function disable(\mysqli|\PDO $connection): bool
    {
        return self::setSession($connection, 0);
    }

    private static function setSession(\mysqli|\PDO $connection, int $seconds): bool
    {
        // $seconds is a validated int; SET takes no bound parameters.
        $sql = 'SET SESSION max_statement_time = ' . $seconds;
        try {
            if ($connection instanceof \mysqli) {
                return $connection->query($sql) !== false;
            }
            return $connection->exec($sql) !== false;
        } catch (\mysqli_sql_exception | \PDOException $e) {
            \Logging\LoggerFactory::getChannel('db')->warning('statement_timeout_set_failed', [
                'seconds' => $seconds,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
