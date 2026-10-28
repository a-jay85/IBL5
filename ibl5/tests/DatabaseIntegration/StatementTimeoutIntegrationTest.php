<?php

declare(strict_types=1);

namespace Tests\DatabaseIntegration;

use Database\StatementTimeout;
use PHPUnit\Framework\Attributes\Group;

/**
 * Runs the statement cap against a real MariaDB.
 *
 * Every test opens its own connection: the base class handle is wrapped in a
 * transaction and must stay uncapped. The SAPI string is injected, so CLI PHPUnit
 * still drives both the web and CLI branches.
 */
#[Group('database')]
class StatementTimeoutIntegrationTest extends DatabaseTestCase
{
    /** @var list<\mysqli> */
    private array $openMysqli = [];

    protected function tearDown(): void
    {
        foreach ($this->openMysqli as $conn) {
            try {
                $conn->close();
            } catch (\Throwable) {
                // Already closed
            }
        }
        $this->openMysqli = [];
        \Logging\LoggerFactory::reset();

        parent::tearDown();
    }

    public function testWebSapiAbortsLongMysqliStatementWithError1969(): void
    {
        $conn = $this->openMysqli();
        StatementTimeout::apply($conn, 1, 'litespeed');

        $start = hrtime(true);
        try {
            $conn->query('SELECT SLEEP(3)');
            self::fail('Expected the statement to be aborted by max_statement_time');
        } catch (\mysqli_sql_exception $e) {
            $this->assertSame(StatementTimeout::ER_STATEMENT_TIMEOUT, $e->getCode());
        }
        $elapsedSeconds = (hrtime(true) - $start) / 1_000_000_000;

        $this->assertLessThan(2.5, $elapsedSeconds);
    }

    public function testWebSapiAbortsLongPdoStatementWithError1969(): void
    {
        $pdo = $this->openPdo();
        StatementTimeout::apply($pdo, 1, 'litespeed');

        $start = hrtime(true);
        try {
            $pdo->query('SELECT SLEEP(3)');
            self::fail('Expected the statement to be aborted by max_statement_time');
        } catch (\PDOException $e) {
            $this->assertSame(StatementTimeout::ER_STATEMENT_TIMEOUT, $e->errorInfo[1] ?? null);
        }
        $elapsedSeconds = (hrtime(true) - $start) / 1_000_000_000;

        $this->assertLessThan(2.5, $elapsedSeconds);
    }

    public function testWebSapiSetsDefaultSessionValueOfTen(): void
    {
        $conn = $this->openMysqli();

        $this->assertTrue(StatementTimeout::apply($conn, null, 'litespeed'));

        $this->assertEqualsWithDelta(10.0, $this->sessionCap($conn), 0.0001);
    }

    public function testCliSapiLeavesStatementUncapped(): void
    {
        $conn = $this->openMysqli();

        $this->assertFalse(StatementTimeout::apply($conn, 1, 'cli'));

        $result = $conn->query('SELECT SLEEP(1.5) AS s');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertEquals(0, $row['s']);
        $this->assertEqualsWithDelta(0.0, $this->sessionCap($conn), 0.0001);
    }

    public function testDisableLiftsCapOnCappedConnection(): void
    {
        $conn = $this->openMysqli();
        StatementTimeout::apply($conn, 1, 'litespeed');

        $this->assertTrue(StatementTimeout::disable($conn));

        $result = $conn->query('SELECT SLEEP(1.5) AS s');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $this->assertEquals(0, $row['s']);
    }

    public function testPdoConnectionSingletonCapsWebSapiOnly(): void
    {
        $keys = ['dbhost', 'dbuname', 'dbpass', 'dbname', 'dbStatementTimeoutSeconds'];
        $saved = [];
        foreach ($keys as $key) {
            $saved[$key] = ['set' => array_key_exists($key, $GLOBALS), 'value' => $GLOBALS[$key] ?? null];
        }

        try {
            $GLOBALS['dbhost'] = self::env('DB_HOST');
            $GLOBALS['dbuname'] = self::env('DB_USER');
            $GLOBALS['dbpass'] = self::env('DB_PASS');
            $GLOBALS['dbname'] = self::env('DB_NAME');
            unset($GLOBALS['dbStatementTimeoutSeconds']);

            \Database\PdoConnection::reset();
            $web = \Database\PdoConnection::getInstance('litespeed');
            $this->assertEqualsWithDelta(10.0, $this->pdoSessionCap($web), 0.0001);

            \Database\PdoConnection::reset();
            $cli = \Database\PdoConnection::getInstance();
            $this->assertEqualsWithDelta(0.0, $this->pdoSessionCap($cli), 0.0001);
        } finally {
            \Database\PdoConnection::reset();
            foreach ($saved as $key => $state) {
                if ($state['set']) {
                    $GLOBALS[$key] = $state['value'];
                } else {
                    unset($GLOBALS[$key]);
                }
            }
        }
    }

    public function testRepositoryTimeoutIsLoggedWithQueryAndRethrown(): void
    {
        $handler = new \Monolog\Handler\TestHandler();
        \Logging\LoggerFactory::forTesting($handler);

        $conn = $this->openMysqli();
        StatementTimeout::apply($conn, 1, 'litespeed');

        $repository = new class ($conn) extends \Database\BaseMysqliRepository {
            /** @return array<string, mixed>|null */
            public function run(): ?array
            {
                return $this->fetchOne('SELECT SLEEP(3) AS s');
            }
        };

        $caught = null;
        try {
            $repository->run();
        } catch (\mysqli_sql_exception $e) {
            $caught = $e;
        }

        $this->assertInstanceOf(\mysqli_sql_exception::class, $caught);
        $this->assertSame(StatementTimeout::ER_STATEMENT_TIMEOUT, $caught->getCode());
        $this->assertTrue($handler->hasErrorThatContains('max_statement_time'));

        $matching = array_values(array_filter(
            $handler->getRecords(),
            static fn ($record): bool => str_contains($record->message, 'max_statement_time')
        ));
        $this->assertCount(1, $matching);
        $this->assertSame('SELECT SLEEP(3) AS s', $matching[0]->context['query'] ?? null);
    }

    private function pdoSessionCap(\PDO $pdo): float
    {
        $statement = $pdo->query('SELECT @@session.max_statement_time AS t');
        $this->assertInstanceOf(\PDOStatement::class, $statement);
        $row = $statement->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row);
        $value = $row['t'];
        $this->assertTrue(is_int($value) || is_float($value) || is_string($value));

        return (float) $value;
    }

    private function openMysqli(): \mysqli
    {
        $conn = new \mysqli();
        $conn->options(MYSQLI_OPT_INT_AND_FLOAT_NATIVE, 1);
        $conn->real_connect(
            self::env('DB_HOST'),
            self::env('DB_USER'),
            self::env('DB_PASS'),
            self::env('DB_NAME')
        );
        if ($conn->connect_errno !== 0) {
            self::fail('Database connection failed: ' . $conn->connect_error);
        }
        $this->openMysqli[] = $conn;

        return $conn;
    }

    private function openPdo(): \PDO
    {
        $dsn = 'mysql:host=' . self::env('DB_HOST')
            . ';dbname=' . self::env('DB_NAME') . ';charset=utf8mb4';

        return new \PDO($dsn, self::env('DB_USER'), self::env('DB_PASS'), [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
        ]);
    }

    private static function env(string $name): string
    {
        $value = getenv($name);
        if ($value === false || $value === '') {
            self::fail("Environment variable $name is not set; database integration tests need a real MariaDB connection.");
        }

        return $value;
    }

    private function sessionCap(\mysqli $conn): float
    {
        $result = $conn->query('SELECT @@session.max_statement_time AS t');
        $this->assertInstanceOf(\mysqli_result::class, $result);
        $row = $result->fetch_assoc();
        $this->assertIsArray($row);
        $value = $row['t'];
        $this->assertTrue(is_int($value) || is_float($value) || is_string($value));

        return (float) $value;
    }
}
