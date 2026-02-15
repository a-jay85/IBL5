<?php

declare(strict_types=1);

namespace Database;

/**
 * PDO connection singleton for the delight-im/auth library
 *
 * The rest of the application continues to use mysqli ($mysqli_db).
 * This class provides the PDO instance required by \Delight\Auth\Auth,
 * reading the same config.php globals used by db/db.php.
 */
class PdoConnection
{
    private static ?\PDO $instance = null;

    /**
     * Get the singleton PDO connection
     *
     * Reads $dbhost, $dbuname, $dbpass, $dbname from config.php globals.
     * Must be called after config.php has been included.
     */
    public static function getInstance(): \PDO
    {
        if (self::$instance !== null) {
            return self::$instance;
        }

        /**
         * @var string $dbhost
         * @var string $dbuname
         * @var string $dbpass
         * @var string $dbname
         */
        global $dbhost, $dbuname, $dbpass, $dbname;

        $dsn = 'mysql:host=' . $dbhost . ';dbname=' . $dbname . ';charset=utf8mb4';

        self::$instance = new \PDO(
            $dsn,
            $dbuname,
            $dbpass,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );

        return self::$instance;
    }

    /**
     * Create a PDO connection with explicit credentials (for testing / standalone scripts)
     *
     * @param string $host Database host
     * @param string $username Database username
     * @param string $password Database password
     * @param string $database Database name
     */
    public static function createWithCredentials(
        string $host,
        string $username,
        string $password,
        string $database,
    ): \PDO {
        $dsn = 'mysql:host=' . $host . ';dbname=' . $database . ';charset=utf8mb4';

        return new \PDO(
            $dsn,
            $username,
            $password,
            [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ],
        );
    }

    /**
     * Reset the singleton (for testing)
     */
    public static function reset(): void
    {
        self::$instance = null;
    }
}
