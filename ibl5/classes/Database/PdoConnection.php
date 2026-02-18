<?php

declare(strict_types=1);

namespace Database;

/**
 * Singleton PDO connection for delight-im/auth.
 *
 * Reads database credentials from config.php globals ($dbhost, $dbuname, $dbpass, $dbname).
 * Provides a reset() method for testability.
 */
class PdoConnection
{
    private static ?\PDO $instance = null;

    /**
     * Get the singleton PDO instance.
     *
     * Creates a new connection on first call using config.php globals.
     */
    public static function getInstance(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        /** @var string $dbhost */
        global $dbhost;
        /** @var string $dbuname */
        global $dbuname;
        /** @var string $dbpass */
        global $dbpass;
        /** @var string $dbname */
        global $dbname;

        $dsn = 'mysql:host=' . $dbhost . ';dbname=' . $dbname . ';charset=utf8mb4';

        self::$instance = new \PDO(
            $dsn,
            $dbuname,
            $dbpass,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        return self::$instance;
    }

    /**
     * Inject a specific PDO instance (for testing).
     */
    public static function setInstance(\PDO $pdo): void
    {
        self::$instance = $pdo;
    }

    /**
     * Reset the singleton (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
